<?php

declare(strict_types=1);

if (!function_exists('random_code')) {
    function random_code(string $prefix, int $bytes = 6): string
    {
        return strtoupper($prefix . bin2hex(random_bytes($bytes)));
    }
}

if (!function_exists('ensure_guest_session_id')) {
    function ensure_guest_session_id(): string
    {
        $current = $_COOKIE['guest_session_id'] ?? '';
        if ($current !== '') {
            return $current;
        }

        $guestId = bin2hex(random_bytes(16));
        setcookie('guest_session_id', $guestId, guest_cookie_options(30));
        $_COOKIE['guest_session_id'] = $guestId;

        return $guestId;
    }
}

if (!function_exists('actor_context')) {
    function actor_context(): array
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId > 0) {
            return ['user_id' => $userId, 'session_id' => null];
        }

        return ['user_id' => null, 'session_id' => ensure_guest_session_id()];
    }
}

if (!function_exists('get_or_create_cart_id')) {
    function get_or_create_cart_id(?int $userId, ?string $sessionId): int
    {
        if ($userId) {
            $stmt = db()->prepare('SELECT id FROM carts WHERE user_id = ? LIMIT 1');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if ($row) {
                return (int) $row['id'];
            }

            $stmt = db()->prepare('INSERT INTO carts (user_id, session_id, created_at) VALUES (?, NULL, NOW())');
            $stmt->bind_param('i', $userId);
            $stmt->execute();

            return (int) db()->insert_id;
        }

        $sessionId = $sessionId ?: ensure_guest_session_id();
        $stmt = db()->prepare('SELECT id FROM carts WHERE session_id = ? LIMIT 1');
        $stmt->bind_param('s', $sessionId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) {
            return (int) $row['id'];
        }

        $stmt = db()->prepare('INSERT INTO carts (user_id, session_id, created_at) VALUES (NULL, ?, NOW())');
        $stmt->bind_param('s', $sessionId);
        $stmt->execute();

        return (int) db()->insert_id;
    }
}

if (!function_exists('get_or_create_wishlist_id')) {
    function get_or_create_wishlist_id(?int $userId, ?string $sessionId): int
    {
        if ($userId) {
            $stmt = db()->prepare('SELECT id FROM wishlists WHERE user_id = ? LIMIT 1');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            if ($row) {
                return (int) $row['id'];
            }

            $stmt = db()->prepare('INSERT INTO wishlists (user_id, session_id, created_at) VALUES (?, NULL, NOW())');
            $stmt->bind_param('i', $userId);
            $stmt->execute();

            return (int) db()->insert_id;
        }

        $sessionId = $sessionId ?: ensure_guest_session_id();
        $stmt = db()->prepare('SELECT id FROM wishlists WHERE session_id = ? LIMIT 1');
        $stmt->bind_param('s', $sessionId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) {
            return (int) $row['id'];
        }

        $stmt = db()->prepare('INSERT INTO wishlists (user_id, session_id, created_at) VALUES (NULL, ?, NOW())');
        $stmt->bind_param('s', $sessionId);
        $stmt->execute();

        return (int) db()->insert_id;
    }
}

if (!function_exists('get_cart_items')) {
    function get_cart_items(?int $userId, ?string $sessionId): array
    {
        if ($userId) {
            $stmt = db()->prepare('SELECT ci.id, ci.cart_id, ci.food_id, ci.qty, ci.price_snapshot, f.name, f.image_path, f.status, f.stock_qty
                FROM carts c
                INNER JOIN cart_items ci ON ci.cart_id = c.id
                INNER JOIN foods f ON f.id = ci.food_id
                WHERE c.user_id = ?
                ORDER BY ci.id DESC');
            $stmt->bind_param('i', $userId);
        } else {
            $stmt = db()->prepare('SELECT ci.id, ci.cart_id, ci.food_id, ci.qty, ci.price_snapshot, f.name, f.image_path, f.status, f.stock_qty
                FROM carts c
                INNER JOIN cart_items ci ON ci.cart_id = c.id
                INNER JOIN foods f ON f.id = ci.food_id
                WHERE c.session_id = ?
                ORDER BY ci.id DESC');
            $stmt->bind_param('s', $sessionId);
        }

        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

if (!function_exists('cart_summary')) {
    function cart_summary(?int $userId, ?string $sessionId): array
    {
        $items = get_cart_items($userId, $sessionId);
        $subtotal = 0.0;
        $count = 0;
        foreach ($items as $item) {
            $line = (float) $item['price_snapshot'] * (int) $item['qty'];
            $subtotal += $line;
            $count += (int) $item['qty'];
        }

        return [
            'items' => $items,
            'subtotal' => $subtotal,
            'count' => $count,
        ];
    }
}

if (!function_exists('add_to_cart')) {
    function add_to_cart(int $foodId, int $qty, ?int $userId, ?string $sessionId): void
    {
        $qty = max(1, $qty);
        if ($qty > MAX_CART_ITEM_QTY) {
            throw new RuntimeException('Maximum quantity per item is ' . MAX_CART_ITEM_QTY . '.');
        }

        $stmt = db()->prepare('SELECT id, price, stock_qty, status FROM foods WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $foodId);
        $stmt->execute();
        $food = $stmt->get_result()->fetch_assoc();

        if (!$food || $food['status'] !== 'ACTIVE') {
            throw new RuntimeException('Food item is unavailable.');
        }

        $cartId = get_or_create_cart_id($userId, $sessionId);

        $stmt = db()->prepare('SELECT id, qty FROM cart_items WHERE cart_id = ? AND food_id = ? LIMIT 1');
        $stmt->bind_param('ii', $cartId, $foodId);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $newQty = $qty;

        if ($existing) {
            $newQty += (int) $existing['qty'];
        }

        if ($newQty > MAX_CART_ITEM_QTY) {
            throw new RuntimeException('Maximum quantity per item is ' . MAX_CART_ITEM_QTY . '.');
        }

        if ($newQty > (int) $food['stock_qty']) {
            throw new RuntimeException('Requested quantity exceeds stock.');
        }

        $priceSnapshot = (float) $food['price'];

        if ($existing) {
            $id = (int) $existing['id'];
            $stmt = db()->prepare('UPDATE cart_items SET qty = ?, price_snapshot = ? WHERE id = ?');
            $stmt->bind_param('idi', $newQty, $priceSnapshot, $id);
            $stmt->execute();
            return;
        }

        $stmt = db()->prepare('INSERT INTO cart_items (cart_id, food_id, qty, price_snapshot) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('iiid', $cartId, $foodId, $qty, $priceSnapshot);
        $stmt->execute();
    }
}

if (!function_exists('update_cart_item_qty')) {
    function update_cart_item_qty(int $cartItemId, int $qty, ?int $userId, ?string $sessionId): void
    {
        $qty = max(0, $qty);
        if ($qty > MAX_CART_ITEM_QTY) {
            throw new RuntimeException('Maximum quantity per item is ' . MAX_CART_ITEM_QTY . '.');
        }

        if ($userId) {
            $stmt = db()->prepare('SELECT ci.id, ci.food_id, f.stock_qty
                FROM carts c
                INNER JOIN cart_items ci ON ci.cart_id = c.id
                INNER JOIN foods f ON f.id = ci.food_id
                WHERE ci.id = ? AND c.user_id = ? LIMIT 1');
            $stmt->bind_param('ii', $cartItemId, $userId);
        } else {
            $stmt = db()->prepare('SELECT ci.id, ci.food_id, f.stock_qty
                FROM carts c
                INNER JOIN cart_items ci ON ci.cart_id = c.id
                INNER JOIN foods f ON f.id = ci.food_id
                WHERE ci.id = ? AND c.session_id = ? LIMIT 1');
            $stmt->bind_param('is', $cartItemId, $sessionId);
        }

        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();
        if (!$item) {
            throw new RuntimeException('Cart item not found.');
        }

        if ($qty === 0) {
            $stmt = db()->prepare('DELETE FROM cart_items WHERE id = ?');
            $stmt->bind_param('i', $cartItemId);
            $stmt->execute();
            return;
        }

        if ($qty > (int) $item['stock_qty']) {
            throw new RuntimeException('Requested quantity exceeds stock.');
        }

        $stmt = db()->prepare('UPDATE cart_items SET qty = ? WHERE id = ?');
        $stmt->bind_param('ii', $qty, $cartItemId);
        $stmt->execute();
    }
}

if (!function_exists('remove_cart_item')) {
    function remove_cart_item(int $cartItemId, ?int $userId, ?string $sessionId): void
    {
        if ($userId) {
            $stmt = db()->prepare('DELETE ci FROM cart_items ci
                INNER JOIN carts c ON c.id = ci.cart_id
                WHERE ci.id = ? AND c.user_id = ?');
            $stmt->bind_param('ii', $cartItemId, $userId);
        } else {
            $stmt = db()->prepare('DELETE ci FROM cart_items ci
                INNER JOIN carts c ON c.id = ci.cart_id
                WHERE ci.id = ? AND c.session_id = ?');
            $stmt->bind_param('is', $cartItemId, $sessionId);
        }

        $stmt->execute();
    }
}

if (!function_exists('wishlist_items')) {
    function wishlist_items(?int $userId, ?string $sessionId): array
    {
        if ($userId) {
            $stmt = db()->prepare('SELECT wi.id, wi.food_id, wi.created_at, f.name, f.price, f.image_path, f.status
                FROM wishlists w
                INNER JOIN wishlist_items wi ON wi.wishlist_id = w.id
                INNER JOIN foods f ON f.id = wi.food_id
                WHERE w.user_id = ?
                ORDER BY wi.id DESC');
            $stmt->bind_param('i', $userId);
        } else {
            $stmt = db()->prepare('SELECT wi.id, wi.food_id, wi.created_at, f.name, f.price, f.image_path, f.status
                FROM wishlists w
                INNER JOIN wishlist_items wi ON wi.wishlist_id = w.id
                INNER JOIN foods f ON f.id = wi.food_id
                WHERE w.session_id = ?
                ORDER BY wi.id DESC');
            $stmt->bind_param('s', $sessionId);
        }

        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

if (!function_exists('toggle_wishlist_item')) {
    function toggle_wishlist_item(int $foodId, ?int $userId, ?string $sessionId): bool
    {
        $wishlistId = get_or_create_wishlist_id($userId, $sessionId);
        $stmt = db()->prepare('SELECT id FROM wishlist_items WHERE wishlist_id = ? AND food_id = ? LIMIT 1');
        $stmt->bind_param('ii', $wishlistId, $foodId);
        $stmt->execute();
        $item = $stmt->get_result()->fetch_assoc();

        if ($item) {
            $id = (int) $item['id'];
            $stmt = db()->prepare('DELETE FROM wishlist_items WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            return false;
        }

        $stmt = db()->prepare('INSERT INTO wishlist_items (wishlist_id, food_id, created_at) VALUES (?, ?, NOW())');
        $stmt->bind_param('ii', $wishlistId, $foodId);
        $stmt->execute();
        return true;
    }
}

if (!function_exists('merge_guest_state_into_user')) {
    function merge_guest_state_into_user(int $userId): void
    {
        $guestSession = $_COOKIE['guest_session_id'] ?? '';
        if ($guestSession === '') {
            return;
        }

        $guestCartStmt = db()->prepare('SELECT id FROM carts WHERE session_id = ? LIMIT 1');
        $guestCartStmt->bind_param('s', $guestSession);
        $guestCartStmt->execute();
        $guestCart = $guestCartStmt->get_result()->fetch_assoc();

        if ($guestCart) {
            $guestCartId = (int) $guestCart['id'];
            $userCartId = get_or_create_cart_id($userId, null);

            $stmt = db()->prepare('SELECT food_id, qty, price_snapshot FROM cart_items WHERE cart_id = ?');
            $stmt->bind_param('i', $guestCartId);
            $stmt->execute();
            $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            foreach ($items as $item) {
                $foodId = (int) $item['food_id'];
                $qty = (int) $item['qty'];
                $priceSnapshot = (float) $item['price_snapshot'];

                $check = db()->prepare('SELECT id, qty FROM cart_items WHERE cart_id = ? AND food_id = ? LIMIT 1');
                $check->bind_param('ii', $userCartId, $foodId);
                $check->execute();
                $existing = $check->get_result()->fetch_assoc();

                if ($existing) {
                    $newQty = (int) $existing['qty'] + $qty;
                    if ($newQty > MAX_CART_ITEM_QTY) {
                        $newQty = MAX_CART_ITEM_QTY;
                    }
                    $id = (int) $existing['id'];
                    $up = db()->prepare('UPDATE cart_items SET qty = ?, price_snapshot = ? WHERE id = ?');
                    $up->bind_param('idi', $newQty, $priceSnapshot, $id);
                    $up->execute();
                } else {
                    $qty = min(MAX_CART_ITEM_QTY, $qty);
                    $ins = db()->prepare('INSERT INTO cart_items (cart_id, food_id, qty, price_snapshot) VALUES (?, ?, ?, ?)');
                    $ins->bind_param('iiid', $userCartId, $foodId, $qty, $priceSnapshot);
                    $ins->execute();
                }
            }

            $del = db()->prepare('DELETE FROM carts WHERE id = ?');
            $del->bind_param('i', $guestCartId);
            $del->execute();
        }

        $guestWishStmt = db()->prepare('SELECT id FROM wishlists WHERE session_id = ? LIMIT 1');
        $guestWishStmt->bind_param('s', $guestSession);
        $guestWishStmt->execute();
        $guestWish = $guestWishStmt->get_result()->fetch_assoc();

        if ($guestWish) {
            $guestWishId = (int) $guestWish['id'];
            $userWishId = get_or_create_wishlist_id($userId, null);

            $stmt = db()->prepare('SELECT food_id FROM wishlist_items WHERE wishlist_id = ?');
            $stmt->bind_param('i', $guestWishId);
            $stmt->execute();
            $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            foreach ($items as $item) {
                $foodId = (int) $item['food_id'];
                $check = db()->prepare('SELECT id FROM wishlist_items WHERE wishlist_id = ? AND food_id = ? LIMIT 1');
                $check->bind_param('ii', $userWishId, $foodId);
                $check->execute();
                if (!$check->get_result()->fetch_assoc()) {
                    $ins = db()->prepare('INSERT INTO wishlist_items (wishlist_id, food_id, created_at) VALUES (?, ?, NOW())');
                    $ins->bind_param('ii', $userWishId, $foodId);
                    $ins->execute();
                }
            }

            $del = db()->prepare('DELETE FROM wishlists WHERE id = ?');
            $del->bind_param('i', $guestWishId);
            $del->execute();
        }
    }
}
if (!function_exists('active_categories')) {
    function active_categories(): array
    {
        $stmt = db()->prepare('SELECT id, name FROM categories WHERE status = ? ORDER BY name ASC');
        $status = 'ACTIVE';
        $stmt->bind_param('s', $status);
        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

if (!function_exists('active_foods')) {
    function active_foods(?int $categoryId = null): array
    {
        if ($categoryId) {
            $stmt = db()->prepare('SELECT f.id, f.name, f.description, f.price, f.stock_qty, f.image_path, c.name AS category_name
                FROM foods f
                INNER JOIN categories c ON c.id = f.category_id
                WHERE f.status = ? AND c.status = ? AND f.category_id = ?
                ORDER BY f.id DESC');
            $activeA = 'ACTIVE';
            $activeB = 'ACTIVE';
            $stmt->bind_param('ssi', $activeA, $activeB, $categoryId);
        } else {
            $stmt = db()->prepare('SELECT f.id, f.name, f.description, f.price, f.stock_qty, f.image_path, c.name AS category_name
                FROM foods f
                INNER JOIN categories c ON c.id = f.category_id
                WHERE f.status = ? AND c.status = ?
                ORDER BY f.id DESC');
            $activeA = 'ACTIVE';
            $activeB = 'ACTIVE';
            $stmt->bind_param('ss', $activeA, $activeB);
        }

        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

if (!function_exists('get_user_addresses')) {
    function get_user_addresses(int $userId): array
    {
        ensure_addresses_extended_schema();

        $stmt = db()->prepare('SELECT id, full_name, phone,
            COALESCE(NULLIF(address_name, \'\'), full_name) AS address_name,
            COALESCE(NULLIF(address_line_1, \'\'), address_line) AS address_line_1,
            COALESCE(address_line_2, landmark) AS address_line_2,
            city,
            COALESCE(NULLIF(zone, \'\'), area) AS zone,
            address_line,
            area,
            landmark,
            lat, lng, is_default, created_at
            FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC');
        $stmt->bind_param('i', $userId);
        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

if (!function_exists('ensure_addresses_extended_schema')) {
    function ensure_addresses_extended_schema(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;

        $existingColumns = [];
        $result = db()->query('SHOW COLUMNS FROM addresses');
        while ($row = $result->fetch_assoc()) {
            $existingColumns[] = (string) ($row['Field'] ?? '');
        }

        $required = ['address_name', 'address_line_1', 'address_line_2', 'zone'];
        $missing = [];
        foreach ($required as $column) {
            if (!in_array($column, $existingColumns, true)) {
                $missing[] = $column;
            }
        }

        if ($missing) {
            throw new RuntimeException('Address schema is outdated. Run database migration for columns: ' . implode(', ', $missing) . '.');
        }
    }
}

if (!function_exists('save_user_address')) {
    function save_user_address(int $userId, array $data): int
    {
        ensure_addresses_extended_schema();

        $stmt = db()->prepare('SELECT full_name, phone FROM users WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if (!$user) {
            throw new RuntimeException('User account not found.');
        }

        $fullName = trim((string) ($user['full_name'] ?? ''));
        $phone = trim((string) ($user['phone'] ?? ''));

        $rawAddressName = trim((string) ($data['address_name'] ?? 'HOME'));
        $addressNameOther = trim((string) ($data['address_name_other'] ?? ''));
        $normalizedAddressName = strtoupper($rawAddressName);
        if ($normalizedAddressName === 'HOME') {
            $addressName = 'Home';
        } elseif ($normalizedAddressName === 'OFFICE') {
            $addressName = 'Office';
        } elseif ($normalizedAddressName === 'OTHER') {
            $addressName = $addressNameOther !== '' ? $addressNameOther : 'Other';
        } else {
            // Backward-compatible fallback if a direct text value is posted.
            $addressName = $rawAddressName;
        }
        if (str_length($addressName) < 2 || str_length($addressName) > 60 || !preg_match('/^[\p{L}\p{N} .\'-]+$/u', $addressName)) {
            throw new RuntimeException('Address name must be 2-60 characters with valid characters.');
        }

        $addressLine1 = trim((string) ($data['address_line_1'] ?? $data['address_line'] ?? ''));
        $addressLine2 = trim((string) ($data['address_line_2'] ?? $data['landmark'] ?? ''));
        $city = trim((string) ($data['city'] ?? ''));
        $zone = trim((string) ($data['zone'] ?? $data['area'] ?? ''));
        $hasLat = isset($data['lat']) && trim((string) $data['lat']) !== '';
        $hasLng = isset($data['lng']) && trim((string) $data['lng']) !== '';
        if ($hasLat xor $hasLng) {
            throw new RuntimeException('Please select a complete map location.');
        }

        $lat = $hasLat ? (float) $data['lat'] : null;
        $lng = $hasLng ? (float) $data['lng'] : null;
        if ($lat !== null && $lng !== null) {
            validate_coordinates($lat, $lng);
        }
        $isDefault = !empty($data['is_default']) ? 1 : 0;

        $hasStructuredAddress = ($addressLine1 !== '' && $city !== '' && $zone !== '');
        $hasMapAddress = ($lat !== null && $lng !== null);
        if (!$hasStructuredAddress && !$hasMapAddress) {
            throw new RuntimeException('Provide either Address Line 1 + City + Zone, or select location on map.');
        }

        if (str_length($addressLine1) > 255 || str_length($addressLine2) > 255 || str_length($city) > 120 || str_length($zone) > 120) {
            throw new RuntimeException('Address fields exceed allowed length.');
        }
        if ($addressLine1 !== '' && !preg_match('/^[\p{L}\p{N} .,#\/\'-]+$/u', $addressLine1)) {
            throw new RuntimeException('Address Line 1 contains invalid characters.');
        }
        if ($addressLine2 !== '' && !preg_match('/^[\p{L}\p{N} .,#\/\'-]+$/u', $addressLine2)) {
            throw new RuntimeException('Address Line 2 contains invalid characters.');
        }
        if ($city !== '' && !preg_match('/^[\p{L}\p{N} .\'-]+$/u', $city)) {
            throw new RuntimeException('City contains invalid characters.');
        }
        if ($zone !== '' && !preg_match('/^[\p{L}\p{N} .\'-]+$/u', $zone)) {
            throw new RuntimeException('Zone contains invalid characters.');
        }

        db()->begin_transaction();
        try {
            if ($isDefault === 1) {
                $stmt = db()->prepare('UPDATE addresses SET is_default = 0 WHERE user_id = ?');
                $stmt->bind_param('i', $userId);
                $stmt->execute();
            }

            $stmt = db()->prepare('INSERT INTO addresses (
                user_id, full_name, phone, address_name, address_line, address_line_1, address_line_2,
                city, zone, area, landmark, lat, lng, is_default, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
            $area = $zone;
            $landmark = $addressLine2 !== '' ? $addressLine2 : null;
            $stmt->bind_param(
                'issssssssssddi',
                $userId,
                $fullName,
                $phone,
                $addressName,
                $addressLine1,
                $addressLine1,
                $landmark,
                $city,
                $zone,
                $area,
                $landmark,
                $lat,
                $lng,
                $isDefault
            );
            $stmt->execute();
            $id = (int) db()->insert_id;

            db()->commit();
            return $id;
        } catch (Throwable $e) {
            db()->rollback();
            throw $e;
        }
    }
}

if (!function_exists('find_coupon')) {
    function find_coupon(string $code, int $userId): ?array
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return null;
        }
        if (!preg_match('/^[A-Z0-9_-]{4,30}$/', $code)) {
            throw new RuntimeException('Invalid coupon code format.');
        }

        $stmt = db()->prepare('SELECT id, code, discount_percent, max_uses, used_count, user_id, expires_at, status, type
            FROM coupons
            WHERE code = ? LIMIT 1');
        $stmt->bind_param('s', $code);
        $stmt->execute();
        $coupon = $stmt->get_result()->fetch_assoc();

        if (!$coupon) {
            throw new RuntimeException('Coupon does not exist.');
        }

        if ($coupon['status'] !== 'ACTIVE') {
            throw new RuntimeException('Coupon is not active.');
        }

        if ($coupon['expires_at'] !== null && strtotime((string) $coupon['expires_at']) < time()) {
            throw new RuntimeException('Coupon has expired.');
        }

        if ((int) $coupon['used_count'] >= (int) $coupon['max_uses']) {
            throw new RuntimeException('Coupon usage limit reached.');
        }

        $lockedUserId = (int) ($coupon['user_id'] ?? 0);
        if ($lockedUserId > 0 && $lockedUserId !== $userId) {
            throw new RuntimeException('Coupon is not valid for this account.');
        }

        return $coupon;
    }
}

if (!function_exists('calculate_discount')) {
    function calculate_discount(float $subtotal, ?array $coupon): array
    {
        if (!$coupon) {
            return ['discount_percent' => 0.0, 'discount_amount' => 0.0];
        }

        $percent = (float) $coupon['discount_percent'];
        $amount = round(($subtotal * $percent) / 100, 2);

        return [
            'discount_percent' => $percent,
            'discount_amount' => min($amount, $subtotal),
        ];
    }
}

if (!function_exists('generate_unique_order_code')) {
    function generate_unique_order_code(): string
    {
        for ($i = 0; $i < 10; $i++) {
            $code = random_code('ORD', 5);
            $stmt = db()->prepare('SELECT id FROM orders WHERE order_code = ? LIMIT 1');
            $stmt->bind_param('s', $code);
            $stmt->execute();
            if (!$stmt->get_result()->fetch_assoc()) {
                return $code;
            }
        }

        throw new RuntimeException('Could not generate unique order code.');
    }
}

if (!function_exists('generate_unique_tracking_number')) {
    function generate_unique_tracking_number(): string
    {
        for ($i = 0; $i < 10; $i++) {
            $code = random_code('TRK', 6);
            $stmt = db()->prepare('SELECT id FROM orders WHERE tracking_number = ? LIMIT 1');
            $stmt->bind_param('s', $code);
            $stmt->execute();
            if (!$stmt->get_result()->fetch_assoc()) {
                return $code;
            }
        }

        throw new RuntimeException('Could not generate tracking number.');
    }
}

if (!function_exists('create_order_from_cart')) {
    function create_order_from_cart(int $userId, int $addressId, string $couponCode = ''): array
    {
        assert_address_owner($addressId, $userId);

        $cartId = get_or_create_cart_id($userId, null);
        $stmt = db()->prepare('SELECT ci.food_id, ci.qty, ci.price_snapshot, f.name, f.stock_qty, f.status
            FROM cart_items ci
            INNER JOIN foods f ON f.id = ci.food_id
            WHERE ci.cart_id = ?');
        $stmt->bind_param('i', $cartId);
        $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        if (!$items) {
            throw new RuntimeException('Cart is empty.');
        }

        $subtotal = 0.0;
        foreach ($items as $item) {
            if ($item['status'] !== 'ACTIVE') {
                throw new RuntimeException('One or more food items are unavailable.');
            }
            if ((int) $item['qty'] > (int) $item['stock_qty']) {
                throw new RuntimeException('Some items exceed available stock.');
            }
            $subtotal += ((float) $item['price_snapshot'] * (int) $item['qty']);
        }

        $coupon = null;
        if (trim($couponCode) !== '') {
            $coupon = find_coupon($couponCode, $userId);
            if (($coupon['type'] ?? '') === 'PRIVATE_LATE' && $subtotal < LATE_COUPON_MIN_ORDER_AMOUNT) {
                throw new RuntimeException('This coupon is valid only for orders above NPR ' . money(LATE_COUPON_MIN_ORDER_AMOUNT) . '.');
            }
        }

        $discount = calculate_discount($subtotal, $coupon);
        $total = max(0.0, round($subtotal - $discount['discount_amount'], 2));

        db()->begin_transaction();
        try {
            $orderCode = generate_unique_order_code();
            $paymentStatus = 'PENDING';
            $deliveryStatus = 'PENDING';
            $couponId = $coupon ? (int) $coupon['id'] : null;

            if ($couponId) {
                $stmt = db()->prepare('INSERT INTO orders (
                        user_id, address_id, order_code, total_amount, payment_status, delivery_status,
                        tracking_number, delivery_start_at, delivered_at, late_coupon_generated,
                        refund_required, refund_note, coupon_id, discount_percent, discount_amount, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, NULL, NULL, NULL, 0, 0, NULL, ?, ?, ?, NOW())');
                $stmt->bind_param(
                    'iisdssidd',
                    $userId,
                    $addressId,
                    $orderCode,
                    $total,
                    $paymentStatus,
                    $deliveryStatus,
                    $couponId,
                    $discount['discount_percent'],
                    $discount['discount_amount']
                );
            } else {
                $stmt = db()->prepare('INSERT INTO orders (
                        user_id, address_id, order_code, total_amount, payment_status, delivery_status,
                        tracking_number, delivery_start_at, delivered_at, late_coupon_generated,
                        refund_required, refund_note, coupon_id, discount_percent, discount_amount, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, NULL, NULL, NULL, 0, 0, NULL, NULL, ?, ?, NOW())');
                $stmt->bind_param(
                    'iisdssdd',
                    $userId,
                    $addressId,
                    $orderCode,
                    $total,
                    $paymentStatus,
                    $deliveryStatus,
                    $discount['discount_percent'],
                    $discount['discount_amount']
                );
            }
            $stmt->execute();
            $orderId = (int) db()->insert_id;

            $itemStmt = db()->prepare('INSERT INTO order_items (order_id, food_id, name_snapshot, price_snapshot, qty)
                VALUES (?, ?, ?, ?, ?)');
            foreach ($items as $item) {
                $foodId = (int) $item['food_id'];
                $name = (string) $item['name'];
                $price = (float) $item['price_snapshot'];
                $qty = (int) $item['qty'];
                $itemStmt->bind_param('iisdi', $orderId, $foodId, $name, $price, $qty);
                $itemStmt->execute();
            }

            db()->commit();

            return [
                'id' => $orderId,
                'order_code' => $orderCode,
                'total_amount' => $total,
                'discount_amount' => $discount['discount_amount'],
                'discount_percent' => $discount['discount_percent'],
                'coupon_id' => $couponId,
                'tracking_number' => null,
            ];
        } catch (Throwable $e) {
            db()->rollback();
            throw $e;
        }
    }
}

if (!function_exists('get_order_by_id')) {
    function get_order_by_id(int $orderId): ?array
    {
        $stmt = db()->prepare('SELECT o.*, a.full_name AS addr_full_name, a.phone AS addr_phone, a.address_line, a.city, a.area, a.landmark, a.lat, a.lng
            FROM orders o
            INNER JOIN addresses a ON a.id = o.address_id
            WHERE o.id = ? LIMIT 1');
        $stmt->bind_param('i', $orderId);
        $stmt->execute();

        $order = $stmt->get_result()->fetch_assoc();
        return $order ?: null;
    }
}

if (!function_exists('user_orders')) {
    function user_orders(int $userId): array
    {
        $stmt = db()->prepare('SELECT id, order_code, total_amount, payment_status, delivery_status, tracking_number, created_at, refund_required
            FROM orders WHERE user_id = ? ORDER BY id DESC');
        $stmt->bind_param('i', $userId);
        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

if (!function_exists('order_items')) {
    function order_items(int $orderId): array
    {
        $stmt = db()->prepare('SELECT id, food_id, name_snapshot, price_snapshot, qty FROM order_items WHERE order_id = ? ORDER BY id ASC');
        $stmt->bind_param('i', $orderId);
        $stmt->execute();

        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

if (!function_exists('clear_user_cart')) {
    function clear_user_cart(int $userId): void
    {
        $stmt = db()->prepare('SELECT id FROM carts WHERE user_id = ? LIMIT 1');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $cart = $stmt->get_result()->fetch_assoc();
        if (!$cart) {
            return;
        }

        $cartId = (int) $cart['id'];
        $stmt = db()->prepare('DELETE FROM cart_items WHERE cart_id = ?');
        $stmt->bind_param('i', $cartId);
        $stmt->execute();
    }
}
if (!function_exists('register_coupon_usage')) {
    function register_coupon_usage(int $couponId, int $userId, int $orderId): void
    {
        $stmt = db()->prepare('SELECT id FROM coupon_usage WHERE coupon_id = ? AND order_id = ? LIMIT 1');
        $stmt->bind_param('ii', $couponId, $orderId);
        $stmt->execute();
        if ($stmt->get_result()->fetch_assoc()) {
            return;
        }

        $stmt = db()->prepare('INSERT INTO coupon_usage (coupon_id, user_id, order_id, used_at) VALUES (?, ?, ?, NOW())');
        $stmt->bind_param('iii', $couponId, $userId, $orderId);
        $stmt->execute();

        $stmt = db()->prepare('UPDATE coupons SET used_count = used_count + 1 WHERE id = ? AND used_count < max_uses');
        $stmt->bind_param('i', $couponId);
        $stmt->execute();
    }
}

if (!function_exists('record_payment')) {
    function record_payment(int $orderId, string $gateway, float $amount, string $status, string $txnRef, array $rawResponse): void
    {
        $raw = json_encode($rawResponse, JSON_UNESCAPED_SLASHES);
        $stmt = db()->prepare('INSERT INTO payments (order_id, gateway, amount, status, txn_ref, raw_response, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())');
        $stmt->bind_param('isdsss', $orderId, $gateway, $amount, $status, $txnRef, $raw);
        $stmt->execute();
    }
}

if (!function_exists('process_payment_success')) {
    function process_payment_success(int $orderId, string $gateway, string $txnRef, float $amount, array $rawResponse): array
    {
        $txnRef = trim($txnRef);
        if ($txnRef === '' || strlen($txnRef) > 120) {
            throw new RuntimeException('Invalid payment transaction reference.');
        }

        db()->begin_transaction();
        try {
            $orderStmt = db()->prepare('SELECT id, user_id, total_amount, payment_status, delivery_status, coupon_id, tracking_number, refund_required
                FROM orders WHERE id = ? FOR UPDATE');
            $orderStmt->bind_param('i', $orderId);
            $orderStmt->execute();
            $order = $orderStmt->get_result()->fetch_assoc();
            if (!$order) {
                throw new RuntimeException('Order not found.');
            }

            $orderTotal = (float) $order['total_amount'];
            if (abs($amount - $orderTotal) > 0.01) {
                throw new RuntimeException('Paid amount does not match order total.');
            }

            $success = 'SUCCESS';
            $paymentStmt = db()->prepare('SELECT id, order_id FROM payments WHERE gateway = ? AND txn_ref = ? AND status = ? LIMIT 1 FOR UPDATE');
            $paymentStmt->bind_param('sss', $gateway, $txnRef, $success);
            $paymentStmt->execute();
            $existingSuccess = $paymentStmt->get_result()->fetch_assoc();
            if ($existingSuccess && (int) $existingSuccess['order_id'] !== $orderId) {
                throw new RuntimeException('Payment transaction already used for another order.');
            }
            if (!$existingSuccess) {
                record_payment($orderId, $gateway, $amount, 'SUCCESS', $txnRef, $rawResponse);
            }

            if ($order['payment_status'] === 'PAID') {
                db()->commit();
                return [
                    'status' => ((int) ($order['refund_required'] ?? 0) === 1) ? 'refund_required' : 'paid',
                    'tracking_number' => $order['tracking_number'],
                    'already_processed' => true,
                ];
            }

            $itemsStmt = db()->prepare('SELECT oi.food_id, oi.qty, f.stock_qty
                FROM order_items oi
                INNER JOIN foods f ON f.id = oi.food_id
                WHERE oi.order_id = ? FOR UPDATE');
            $itemsStmt->bind_param('i', $orderId);
            $itemsStmt->execute();
            $items = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);

            $insufficient = false;
            foreach ($items as $item) {
                if ((int) $item['stock_qty'] < (int) $item['qty']) {
                    $insufficient = true;
                    break;
                }
            }

            if ($insufficient) {
                $paid = 'PAID';
                $cancelled = 'CANCELLED';
                $note = 'Insufficient stock after payment';
                $stmt = db()->prepare('UPDATE orders SET payment_status = ?, delivery_status = ?, refund_required = 1, refund_note = ? WHERE id = ?');
                $stmt->bind_param('sssi', $paid, $cancelled, $note, $orderId);
                $stmt->execute();
                db()->commit();
                return ['status' => 'refund_required'];
            }

            $stockStmt = db()->prepare('UPDATE foods SET stock_qty = stock_qty - ? WHERE id = ? AND stock_qty >= ?');
            foreach ($items as $item) {
                $qty = (int) $item['qty'];
                $foodId = (int) $item['food_id'];
                $stockStmt->bind_param('iii', $qty, $foodId, $qty);
                $stockStmt->execute();
                if ($stockStmt->affected_rows < 1) {
                    $paid = 'PAID';
                    $cancelled = 'CANCELLED';
                    $note = 'Insufficient stock after payment';
                    $stmt = db()->prepare('UPDATE orders SET payment_status = ?, delivery_status = ?, refund_required = 1, refund_note = ? WHERE id = ?');
                    $stmt->bind_param('sssi', $paid, $cancelled, $note, $orderId);
                    $stmt->execute();
                    db()->commit();
                    return ['status' => 'refund_required'];
                }
            }

            $tracking = $order['tracking_number'] ?: generate_unique_tracking_number();
            $paid = 'PAID';
            $stmt = db()->prepare('UPDATE orders SET payment_status = ?, tracking_number = ? WHERE id = ?');
            $stmt->bind_param('ssi', $paid, $tracking, $orderId);
            $stmt->execute();

            $couponId = (int) ($order['coupon_id'] ?? 0);
            if ($couponId > 0) {
                register_coupon_usage($couponId, (int) $order['user_id'], $orderId);
            }

            clear_user_cart((int) $order['user_id']);

            db()->commit();

            return ['status' => 'paid', 'tracking_number' => $tracking];
        } catch (Throwable $e) {
            db()->rollback();
            throw $e;
        }
    }
}

if (!function_exists('process_payment_failed')) {
    function process_payment_failed(int $orderId, string $gateway, string $txnRef, float $amount, array $rawResponse): void
    {
        db()->begin_transaction();
        try {
            $stmt = db()->prepare('SELECT id, payment_status FROM orders WHERE id = ? FOR UPDATE');
            $stmt->bind_param('i', $orderId);
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc();
            if (!$order) {
                throw new RuntimeException('Order not found.');
            }

            record_payment($orderId, $gateway, $amount, 'FAILED', $txnRef, $rawResponse);

            if ($order['payment_status'] !== 'PAID') {
                $failed = 'FAILED';
                $stmt = db()->prepare('UPDATE orders SET payment_status = ?, tracking_number = NULL WHERE id = ?');
                $stmt->bind_param('si', $failed, $orderId);
                $stmt->execute();
            }

            db()->commit();
        } catch (Throwable $e) {
            db()->rollback();
            throw $e;
        }
    }
}

if (!function_exists('admin_dashboard_stats')) {
    function admin_dashboard_stats(): array
    {
        $stats = [
            'total_orders' => 0,
            'paid_orders' => 0,
            'failed_orders' => 0,
            'revenue' => 0.0,
            'out_for_delivery' => 0,
            'delivered' => 0,
        ];

        $queries = [
            'total_orders' => 'SELECT COUNT(*) AS c FROM orders',
            'paid_orders' => "SELECT COUNT(*) AS c FROM orders WHERE payment_status = 'PAID'",
            'failed_orders' => "SELECT COUNT(*) AS c FROM orders WHERE payment_status = 'FAILED'",
            'out_for_delivery' => "SELECT COUNT(*) AS c FROM orders WHERE delivery_status = 'OUT_FOR_DELIVERY'",
            'delivered' => "SELECT COUNT(*) AS c FROM orders WHERE delivery_status = 'DELIVERED'",
        ];

        foreach ($queries as $key => $sql) {
            $stmt = db()->prepare($sql);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stats[$key] = (int) ($row['c'] ?? 0);
        }

        $stmt = db()->prepare("SELECT COALESCE(SUM(total_amount), 0) AS revenue FROM orders WHERE payment_status = 'PAID' AND refund_required = 0");
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stats['revenue'] = (float) ($row['revenue'] ?? 0.0);

        return $stats;
    }
}

if (!function_exists('update_delivery_status')) {
    function update_delivery_status(int $orderId, string $status): void
    {
        $allowed = ['PENDING', 'CONFIRMED', 'OUT_FOR_DELIVERY', 'DELIVERED', 'CANCELLED'];
        if (!in_array($status, $allowed, true)) {
            throw new RuntimeException('Invalid delivery status.');
        }

        $stmt = db()->prepare('SELECT id, payment_status, delivery_status FROM orders WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        if (!$order) {
            throw new RuntimeException('Order not found.');
        }

        $current = (string) $order['delivery_status'];
        $paymentStatus = (string) $order['payment_status'];
        if ($current === $status) {
            return;
        }

        $transitions = [
            'PENDING' => ['CONFIRMED', 'CANCELLED'],
            'CONFIRMED' => ['OUT_FOR_DELIVERY', 'CANCELLED'],
            'OUT_FOR_DELIVERY' => ['DELIVERED', 'CANCELLED'],
            'DELIVERED' => [],
            'CANCELLED' => [],
        ];

        if (!in_array($status, $transitions[$current] ?? [], true)) {
            throw new RuntimeException('Invalid delivery status transition.');
        }

        if (in_array($status, ['OUT_FOR_DELIVERY', 'DELIVERED'], true) && $paymentStatus !== 'PAID') {
            throw new RuntimeException('Only paid orders can be marked for delivery.');
        }

        if ($status === 'OUT_FOR_DELIVERY') {
            $stmt = db()->prepare('UPDATE orders SET delivery_status = ?, delivery_start_at = IFNULL(delivery_start_at, NOW()) WHERE id = ?');
            $stmt->bind_param('si', $status, $orderId);
            $stmt->execute();
            return;
        }

        if ($status === 'DELIVERED') {
            $stmt = db()->prepare('UPDATE orders SET delivery_status = ?, delivered_at = NOW() WHERE id = ?');
            $stmt->bind_param('si', $status, $orderId);
            $stmt->execute();
            return;
        }

        $stmt = db()->prepare('UPDATE orders SET delivery_status = ? WHERE id = ?');
        $stmt->bind_param('si', $status, $orderId);
        $stmt->execute();
    }
}
if (!function_exists('generate_private_late_coupon')) {
    function generate_private_late_coupon(int $userId, float $discountPercent): array
    {
        for ($i = 0; $i < 10; $i++) {
            $code = random_code('LATE', 4);
            $stmt = db()->prepare('SELECT id FROM coupons WHERE code = ? LIMIT 1');
            $stmt->bind_param('s', $code);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc()) {
                continue;
            }

            $status = 'ACTIVE';
            $type = 'PRIVATE_LATE';
            $percent = max(1, min(100, (int) round($discountPercent)));
            $maxUses = 1;
            $expiresAt = date('Y-m-d H:i:s', time() + (7 * 86400));
            $stmt = db()->prepare('INSERT INTO coupons (code, discount_percent, max_uses, used_count, user_id, expires_at, status, type, created_at)
                VALUES (?, ?, ?, 0, ?, ?, ?, ?, NOW())');
            $stmt->bind_param('siiisss', $code, $percent, $maxUses, $userId, $expiresAt, $status, $type);
            $stmt->execute();

            return [
                'id' => (int) db()->insert_id,
                'code' => $code,
                'discount_percent' => (float) $percent,
                'expires_at' => $expiresAt,
            ];
        }

        throw new RuntimeException('Unable to generate late coupon.');
    }
}

if (!function_exists('late_coupon_discount_percent')) {
    function late_coupon_discount_percent(): float
    {
        $raw = get_system_setting('late_coupon_discount_percent');
        if ($raw !== null && is_numeric($raw)) {
            return (float) max(1, min(100, (int) round((float) $raw)));
        }

        return (float) LATE_COUPON_PERCENT;
    }
}

if (!function_exists('late_coupon_delay_threshold_minutes')) {
    function late_coupon_delay_threshold_minutes(): int
    {
        $raw = get_system_setting('late_coupon_delay_threshold_minutes');
        if ($raw !== null && is_numeric($raw)) {
            return (int) max(1, min(240, (int) round((float) $raw)));
        }

        return (int) LATE_COUPON_CLAIM_MINUTES;
    }
}

if (!function_exists('evaluate_late_coupon_threshold_rule')) {
    function evaluate_late_coupon_threshold_rule(array $order, ?int $expectedLimitSeconds = null): array
    {
        $thresholdSeconds = max(60, late_coupon_delay_threshold_minutes() * 60);

        if (($order['delivery_status'] ?? '') !== 'OUT_FOR_DELIVERY' || !empty($order['delivered_at']) || empty($order['delivery_start_at'])) {
            return [
                'claimable' => false,
                'elapsed_seconds' => 0,
                'expected_limit_seconds' => max(60, (int) ($expectedLimitSeconds ?? 0)),
                'threshold_seconds' => $thresholdSeconds,
                'trigger_seconds' => max(60, (int) ($expectedLimitSeconds ?? 0)) + $thresholdSeconds,
                'delay_seconds' => 0,
                'discount_percent' => 0.0,
                'threshold_minutes' => (int) ceil($thresholdSeconds / 60),
            ];
        }

        $startedAtTs = strtotime((string) $order['delivery_start_at']);
        if ($startedAtTs === false) {
            return [
                'claimable' => false,
                'elapsed_seconds' => 0,
                'expected_limit_seconds' => max(60, (int) ($expectedLimitSeconds ?? 0)),
                'threshold_seconds' => $thresholdSeconds,
                'trigger_seconds' => max(60, (int) ($expectedLimitSeconds ?? 0)) + $thresholdSeconds,
                'delay_seconds' => 0,
                'discount_percent' => 0.0,
                'threshold_minutes' => (int) ceil($thresholdSeconds / 60),
            ];
        }

        $elapsed = max(0, time() - $startedAtTs);
        $expected = max(60, (int) ($expectedLimitSeconds ?? expected_delivery_limit_seconds($order)));
        $trigger = $expected + $thresholdSeconds;
        $delay = max(0, $elapsed - $expected);
        $claimable = $elapsed > $trigger;

        return [
            'claimable' => $claimable,
            'elapsed_seconds' => $elapsed,
            'expected_limit_seconds' => $expected,
            'threshold_seconds' => $thresholdSeconds,
            'trigger_seconds' => $trigger,
            'delay_seconds' => $delay,
            'discount_percent' => $claimable ? late_coupon_discount_percent() : 0.0,
            'threshold_minutes' => (int) ceil($thresholdSeconds / 60),
        ];
    }
}

if (!function_exists('late_coupon_claimable_for_order')) {
    function late_coupon_claimable_for_order(array $order): bool
    {
        if ((int) ($order['late_coupon_generated'] ?? 0) === 1) {
            return false;
        }

        $evaluation = evaluate_late_coupon_threshold_rule($order);

        return (bool) ($evaluation['claimable'] ?? false);
    }
}

if (!function_exists('maybe_generate_late_coupon_for_order')) {
    function maybe_generate_late_coupon_for_order(int $orderId, ?int $expectedLimitSeconds = null): ?array
    {
        db()->begin_transaction();
        try {
            $stmt = db()->prepare('SELECT o.id, o.user_id, o.delivery_status, o.delivery_start_at, o.delivered_at, o.late_coupon_generated,
                    a.lat, a.lng
                FROM orders o
                INNER JOIN addresses a ON a.id = o.address_id
                WHERE o.id = ? FOR UPDATE');
            $stmt->bind_param('i', $orderId);
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc();

            if (!$order) {
                db()->rollback();
                return null;
            }

            if ((int) ($order['late_coupon_generated'] ?? 0) === 1) {
                $latest = fetch_latest_coupon_for_user((int) $order['user_id']);
                db()->commit();
                return $latest;
            }

            if (($order['delivery_status'] ?? '') !== 'OUT_FOR_DELIVERY' || !empty($order['delivered_at']) || empty($order['delivery_start_at'])) {
                db()->commit();
                return null;
            }

            $evaluation = evaluate_late_coupon_threshold_rule($order, $expectedLimitSeconds);
            if (!(bool) ($evaluation['claimable'] ?? false)) {
                db()->commit();
                return null;
            }

            $coupon = generate_private_late_coupon((int) $order['user_id'], (float) ($evaluation['discount_percent'] ?? 0.0));
            $flag = 1;
            $update = db()->prepare('UPDATE orders SET late_coupon_generated = ? WHERE id = ?');
            $update->bind_param('ii', $flag, $orderId);
            $update->execute();

            db()->commit();
            return $coupon;
        } catch (Throwable $e) {
            db()->rollback();
            throw $e;
        }
    }
}

if (!function_exists('claim_late_coupon_for_order')) {
    function claim_late_coupon_for_order(int $orderId, ?int $userId): ?array
    {
        db()->begin_transaction();
        try {
            $stmt = db()->prepare('SELECT id, user_id, delivery_status, delivery_start_at, delivered_at, late_coupon_generated
                FROM orders WHERE id = ? FOR UPDATE');
            $stmt->bind_param('i', $orderId);
            $stmt->execute();
            $order = $stmt->get_result()->fetch_assoc();

            if (!$order) {
                db()->rollback();
                return null;
            }

            if ($userId !== null && (int) $order['user_id'] !== $userId) {
                db()->rollback();
                throw new RuntimeException('Unauthorized coupon claim request.');
            }

            if ((int) $order['late_coupon_generated'] === 1) {
                $latest = fetch_latest_coupon_for_user((int) $order['user_id']);
                db()->commit();
                return $latest;
            }

            if ($order['delivery_status'] !== 'OUT_FOR_DELIVERY') {
                db()->commit();
                return null;
            }

            if (!empty($order['delivered_at']) || empty($order['delivery_start_at'])) {
                db()->commit();
                return null;
            }

            $evaluation = evaluate_late_coupon_threshold_rule($order);
            if (!(bool) ($evaluation['claimable'] ?? false)) {
                db()->commit();
                return null;
            }

            $coupon = generate_private_late_coupon((int) $order['user_id'], (float) ($evaluation['discount_percent'] ?? 0.0));
            $flag = 1;
            $stmt = db()->prepare('UPDATE orders SET late_coupon_generated = ? WHERE id = ?');
            $stmt->bind_param('ii', $flag, $orderId);
            $stmt->execute();

            db()->commit();
            return $coupon;
        } catch (Throwable $e) {
            db()->rollback();
            throw $e;
        }
    }
}

if (!function_exists('run_late_coupon_scan')) {
    function run_late_coupon_scan(): void
    {
        $stmt = db()->prepare('SELECT id FROM orders
            WHERE delivery_status = ?
              AND delivered_at IS NULL
              AND delivery_start_at IS NOT NULL
              AND late_coupon_generated = 0
            ORDER BY id ASC');
        $status = 'OUT_FOR_DELIVERY';
        $stmt->bind_param('s', $status);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($rows as $row) {
            $orderId = (int) ($row['id'] ?? 0);
            if ($orderId <= 0) {
                continue;
            }

            try {
                maybe_generate_late_coupon_for_order($orderId, null);
            } catch (Throwable $e) {
                // Continue scan even if one order fails.
            }
        }
    }
}

if (!function_exists('get_order_by_tracking')) {
    function get_order_by_tracking(string $trackingNumber): ?array
    {
        $stmt = db()->prepare('SELECT o.*, a.full_name AS addr_full_name, a.phone AS addr_phone, a.address_line, a.city, a.area, a.landmark, a.lat, a.lng, u.full_name AS user_name
            FROM orders o
            INNER JOIN addresses a ON a.id = o.address_id
            INNER JOIN users u ON u.id = o.user_id
            WHERE o.tracking_number = ? LIMIT 1');
        $stmt->bind_param('s', $trackingNumber);
        $stmt->execute();

        $order = $stmt->get_result()->fetch_assoc();
        return $order ?: null;
    }
}

if (!function_exists('ensure_support_tickets_table')) {
    function ensure_support_tickets_table(): bool
    {
        static $checked = false;
        static $available = false;

        if ($checked) {
            return $available;
        }

        $checked = true;
        try {
            db()->query('CREATE TABLE IF NOT EXISTS support_tickets (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                subject VARCHAR(160) NOT NULL,
                message TEXT NOT NULL,
                status ENUM(\'OPEN\',\'CLOSED\') NOT NULL DEFAULT \'OPEN\',
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_support_tickets_user (user_id),
                INDEX idx_support_tickets_status (status),
                CONSTRAINT fk_support_tickets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            $available = true;
        } catch (Throwable $e) {
            $available = false;
        }

        return $available;
    }
}

if (!function_exists('create_support_ticket')) {
    function create_support_ticket(int $userId, string $subject, string $message): int
    {
        if (!ensure_support_tickets_table()) {
            throw new RuntimeException('Support system is currently unavailable.');
        }

        $subject = trim($subject);
        $message = trim($message);
        if (str_length($subject) < 4 || str_length($subject) > 160) {
            throw new RuntimeException('Subject must be between 4 and 160 characters.');
        }
        if (str_length($message) < 10 || str_length($message) > 5000) {
            throw new RuntimeException('Message must be between 10 and 5000 characters.');
        }

        $stmt = db()->prepare('INSERT INTO support_tickets (user_id, subject, message, status, created_at, updated_at)
            VALUES (?, ?, ?, \'OPEN\', NOW(), NOW())');
        $stmt->bind_param('iss', $userId, $subject, $message);
        $stmt->execute();

        return (int) db()->insert_id;
    }
}

if (!function_exists('ensure_system_settings_table')) {
    function ensure_system_settings_table(): bool
    {
        static $checked = false;
        static $available = false;

        if ($checked) {
            return $available;
        }

        $checked = true;
        try {
            db()->query('CREATE TABLE IF NOT EXISTS system_settings (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(120) NOT NULL UNIQUE,
                setting_value TEXT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
            $available = true;
        } catch (Throwable $e) {
            $available = false;
        }

        return $available;
    }
}

if (!function_exists('get_system_setting')) {
    function get_system_setting(string $key, ?string $default = null): ?string
    {
        if (!ensure_system_settings_table()) {
            return $default;
        }

        $stmt = db()->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1');
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if (!$row) {
            return $default;
        }

        return isset($row['setting_value']) ? (string) $row['setting_value'] : $default;
    }
}

if (!function_exists('set_system_setting')) {
    function set_system_setting(string $key, string $value): void
    {
        if (!ensure_system_settings_table()) {
            throw new RuntimeException('Settings table is unavailable.');
        }

        $stmt = db()->prepare('INSERT INTO system_settings (setting_key, setting_value, created_at, updated_at)
            VALUES (?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()');
        $stmt->bind_param('ss', $key, $value);
        $stmt->execute();
    }
}

if (!function_exists('restaurant_coordinates')) {
    function restaurant_coordinates(): array
    {
        $lat = get_system_setting('restaurant_lat');
        $lng = get_system_setting('restaurant_lng');

        $resolvedLat = is_numeric($lat) ? (float) $lat : RESTAURANT_LAT;
        $resolvedLng = is_numeric($lng) ? (float) $lng : RESTAURANT_LNG;

        return ['lat' => $resolvedLat, 'lng' => $resolvedLng];
    }
}

if (!function_exists('haversine_distance_m')) {
    function haversine_distance_m(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) * sin($dLng / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}

if (!function_exists('route_path_distance_m')) {
    function route_path_distance_m(array $path): float
    {
        $total = 0.0;
        for ($i = 1, $n = count($path); $i < $n; $i++) {
            $a = $path[$i - 1];
            $b = $path[$i];
            if (!is_array($a) || !is_array($b)) {
                continue;
            }

            $total += haversine_distance_m((float) ($a['lat'] ?? 0), (float) ($a['lng'] ?? 0), (float) ($b['lat'] ?? 0), (float) ($b['lng'] ?? 0));
        }

        return $total;
    }
}

if (!function_exists('astar_route_eta_seconds')) {
    function astar_route_eta_seconds(array $path, ?float $expectedDurationSeconds = null): int
    {
        $count = count($path);
        if ($count < 2) {
            return 60;
        }

        $totalDistance = route_path_distance_m($path);
        $speedMps = 8.33;
        if ($expectedDurationSeconds !== null && $expectedDurationSeconds > 0.0 && $totalDistance > 0.0) {
            $speedMps = max(1.0, $totalDistance / $expectedDurationSeconds);
        }

        $goalIndex = $count - 1;
        $openSet = [0 => true];
        $cameFrom = [];
        $gScore = array_fill(0, $count, INF);
        $fScore = array_fill(0, $count, INF);

        $gScore[0] = 0.0;
        $fScore[0] = haversine_distance_m(
            (float) ($path[0]['lat'] ?? 0.0),
            (float) ($path[0]['lng'] ?? 0.0),
            (float) ($path[$goalIndex]['lat'] ?? 0.0),
            (float) ($path[$goalIndex]['lng'] ?? 0.0)
        ) / $speedMps;

        while ($openSet) {
            $current = null;
            $currentScore = INF;
            foreach ($openSet as $index => $_) {
                $score = $fScore[$index] ?? INF;
                if ($score < $currentScore) {
                    $currentScore = $score;
                    $current = $index;
                }
            }

            if ($current === null) {
                break;
            }

            if ($current === $goalIndex) {
                return (int) max(60, ceil($gScore[$goalIndex]));
            }

            unset($openSet[$current]);

            foreach ([$current - 1, $current + 1] as $neighbor) {
                if ($neighbor < 0 || $neighbor >= $count) {
                    continue;
                }

                $segmentDistance = haversine_distance_m(
                    (float) ($path[$current]['lat'] ?? 0.0),
                    (float) ($path[$current]['lng'] ?? 0.0),
                    (float) ($path[$neighbor]['lat'] ?? 0.0),
                    (float) ($path[$neighbor]['lng'] ?? 0.0)
                );
                $tentativeG = ($gScore[$current] ?? INF) + ($segmentDistance / $speedMps);
                if ($tentativeG >= ($gScore[$neighbor] ?? INF)) {
                    continue;
                }

                $cameFrom[$neighbor] = $current;
                $gScore[$neighbor] = $tentativeG;
                $heuristic = haversine_distance_m(
                    (float) ($path[$neighbor]['lat'] ?? 0.0),
                    (float) ($path[$neighbor]['lng'] ?? 0.0),
                    (float) ($path[$goalIndex]['lat'] ?? 0.0),
                    (float) ($path[$goalIndex]['lng'] ?? 0.0)
                ) / $speedMps;
                $fScore[$neighbor] = $tentativeG + $heuristic;
                $openSet[$neighbor] = true;
            }
        }

        return (int) max(60, ceil($totalDistance / $speedMps));
    }
}

if (!function_exists('fallback_route_data')) {
    function fallback_route_data(float $startLat, float $startLng, float $endLat, float $endLng): array
    {
        $path = [
            ['lat' => $startLat, 'lng' => $startLng],
            ['lat' => $endLat, 'lng' => $endLng],
        ];
        $distanceMeters = route_path_distance_m($path);
        $durationSeconds = astar_route_eta_seconds($path, null);

        return [
            'duration_seconds' => $durationSeconds,
            'distance_meters' => (int) round($distanceMeters),
            'path' => $path,
            'source' => 'astar-fallback',
        ];
    }
}

if (!function_exists('route_data')) {
    function route_data(float $startLat, float $startLng, float $endLat, float $endLng): array
    {
        static $cache = [];
        $cacheVersion = 'astar-v1';

        $cacheKey = implode(':', [
            $cacheVersion,
            round($startLat, 5),
            round($startLng, 5),
            round($endLat, 5),
            round($endLng, 5),
        ]);

        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $cacheFile = APP_ROOT . '/uploads/.route_cache_' . sha1($cacheKey) . '.json';
        if (is_file($cacheFile)) {
            $ageSeconds = time() - (int) filemtime($cacheFile);
            if ($ageSeconds >= 0 && $ageSeconds <= 21600) {
                $rawCache = file_get_contents($cacheFile);
                $decoded = is_string($rawCache) ? json_decode($rawCache, true) : null;
                if (is_array($decoded) && isset($decoded['duration_seconds'], $decoded['distance_meters'], $decoded['path'])) {
                    $cache[$cacheKey] = $decoded;
                    return $cache[$cacheKey];
                }
            }
        }

        if (!function_exists('curl_init')) {
            $cache[$cacheKey] = fallback_route_data($startLat, $startLng, $endLat, $endLng);
            @file_put_contents($cacheFile, json_encode($cache[$cacheKey], JSON_UNESCAPED_SLASHES));
            return $cache[$cacheKey];
        }

        $url = 'https://router.project-osrm.org/route/v1/driving/'
            . rawurlencode((string) $startLng) . ',' . rawurlencode((string) $startLat)
            . ';' . rawurlencode((string) $endLng) . ',' . rawurlencode((string) $endLat)
            . '?overview=full&geometries=geojson';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $raw = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!is_string($raw) || $statusCode >= 400) {
            $cache[$cacheKey] = fallback_route_data($startLat, $startLng, $endLat, $endLng);
            @file_put_contents($cacheFile, json_encode($cache[$cacheKey], JSON_UNESCAPED_SLASHES));
            return $cache[$cacheKey];
        }

        $json = json_decode($raw, true);
        $route = is_array($json) && isset($json['routes'][0]) && is_array($json['routes'][0]) ? $json['routes'][0] : null;
        $coordinates = $route['geometry']['coordinates'] ?? null;
        if (!is_array($route) || !is_array($coordinates) || count($coordinates) < 2) {
            $cache[$cacheKey] = fallback_route_data($startLat, $startLng, $endLat, $endLng);
            @file_put_contents($cacheFile, json_encode($cache[$cacheKey], JSON_UNESCAPED_SLASHES));
            return $cache[$cacheKey];
        }

        $path = [];
        foreach ($coordinates as $point) {
            if (!is_array($point) || count($point) < 2) {
                continue;
            }
            $path[] = ['lat' => (float) $point[1], 'lng' => (float) $point[0]];
        }

        if (count($path) < 2) {
            $cache[$cacheKey] = fallback_route_data($startLat, $startLng, $endLat, $endLng);
            @file_put_contents($cacheFile, json_encode($cache[$cacheKey], JSON_UNESCAPED_SLASHES));
            return $cache[$cacheKey];
        }

        $routeDuration = (float) ($route['duration'] ?? 0);
        $distanceMeters = (int) max(1, round((float) ($route['distance'] ?? route_path_distance_m($path))));
        $durationSeconds = astar_route_eta_seconds($path, $routeDuration > 0 ? $routeDuration : null);

        $cache[$cacheKey] = [
            'duration_seconds' => $durationSeconds,
            'distance_meters' => $distanceMeters,
            'path' => $path,
            'source' => 'astar-osrm',
        ];
        @file_put_contents($cacheFile, json_encode($cache[$cacheKey], JSON_UNESCAPED_SLASHES));

        return $cache[$cacheKey];
    }
}

if (!function_exists('expected_delivery_limit_seconds')) {
    function expected_delivery_limit_seconds(array $order): int
    {
        $restaurant = restaurant_coordinates();
        $startLat = (float) $restaurant['lat'];
        $startLng = (float) $restaurant['lng'];
        $endLat = isset($order['lat']) && $order['lat'] !== null ? (float) $order['lat'] : ($startLat + 0.015);
        $endLng = isset($order['lng']) && $order['lng'] !== null ? (float) $order['lng'] : ($startLng + 0.02);

        $route = route_data($startLat, $startLng, $endLat, $endLng);
        $base = DELIVERY_TIME_LIMIT_MINUTES * 60;
        $routeDuration = (int) ($route['duration_seconds'] ?? 0);

        if ($routeDuration <= 0) {
            return max(60, $base);
        }

        return max(60, $routeDuration);
    }
}

if (!function_exists('point_on_path_by_progress')) {
    function point_on_path_by_progress(array $path, float $progress): array
    {
        if (!$path) {
            return ['lat' => 0.0, 'lng' => 0.0];
        }

        if ($progress <= 0.0) {
            return $path[0];
        }
        if ($progress >= 1.0) {
            return $path[count($path) - 1];
        }

        $segments = [];
        $total = 0.0;
        for ($i = 1, $n = count($path); $i < $n; $i++) {
            $a = $path[$i - 1];
            $b = $path[$i];
            $d = haversine_distance_m((float) $a['lat'], (float) $a['lng'], (float) $b['lat'], (float) $b['lng']);
            $segments[] = $d;
            $total += $d;
        }

        if ($total <= 0.0) {
            return $path[count($path) - 1];
        }

        $target = $total * $progress;
        $walked = 0.0;
        foreach ($segments as $idx => $segmentDist) {
            $next = $walked + $segmentDist;
            if ($target <= $next) {
                $a = $path[$idx];
                $b = $path[$idx + 1];
                $ratio = $segmentDist > 0 ? (($target - $walked) / $segmentDist) : 0.0;
                return [
                    'lat' => (float) $a['lat'] + (((float) $b['lat'] - (float) $a['lat']) * $ratio),
                    'lng' => (float) $a['lng'] + (((float) $b['lng'] - (float) $a['lng']) * $ratio),
                ];
            }
            $walked = $next;
        }

        return $path[count($path) - 1];
    }
}

if (!function_exists('tracking_payload')) {
    function tracking_payload(array $order): array
    {
        $status = (string) $order['delivery_status'];
        $progress = 0.0;
        $elapsedSeconds = 0;

        $restaurant = restaurant_coordinates();
        $startLat = (float) $restaurant['lat'];
        $startLng = (float) $restaurant['lng'];
        $endLat = isset($order['lat']) && $order['lat'] !== null ? (float) $order['lat'] : ($startLat + 0.015);
        $endLng = isset($order['lng']) && $order['lng'] !== null ? (float) $order['lng'] : ($startLng + 0.02);

        $route = route_data($startLat, $startLng, $endLat, $endLng);
        $travelSeconds = max(60, (int) ($route['duration_seconds'] ?? (DELIVERY_TIME_LIMIT_MINUTES * 60)));

        $orderId = (int) ($order['id'] ?? 0);
        if ($orderId > 0) {
            try {
                $autoCoupon = maybe_generate_late_coupon_for_order($orderId, $travelSeconds);
                if ($autoCoupon && (int) ($order['late_coupon_generated'] ?? 0) === 0) {
                    $order['late_coupon_generated'] = 1;
                }
            } catch (Throwable $e) {
                // Keep tracking payload available even if coupon generation fails.
            }
        }

        $couponRule = evaluate_late_coupon_threshold_rule($order, $travelSeconds);
        $couponLimitSeconds = max(60, (int) ($couponRule['trigger_seconds'] ?? 60));
        $routePath = is_array($route['path'] ?? null) ? $route['path'] : [
            ['lat' => $startLat, 'lng' => $startLng],
            ['lat' => $endLat, 'lng' => $endLng],
        ];

        if ($status === 'DELIVERED') {
            $progress = 1.0;
        } elseif ($status === 'OUT_FOR_DELIVERY' && !empty($order['delivery_start_at'])) {
            $elapsedSeconds = max(0, time() - strtotime((string) $order['delivery_start_at']));
            $progress = min(1.0, $elapsedSeconds / $travelSeconds);
        }

        $rider = point_on_path_by_progress($routePath, $progress);
        $currentLat = (float) $rider['lat'];
        $currentLng = (float) $rider['lng'];

        $etaMinutes = null;
        $etaAt = null;
        $couponDueAt = null;
        if ($status === 'OUT_FOR_DELIVERY') {
            $etaMinutes = max(0, (int) ceil(($travelSeconds - $elapsedSeconds) / 60));
            if (!empty($order['delivery_start_at'])) {
                $startTs = strtotime((string) $order['delivery_start_at']);
                if ($startTs !== false) {
                    $etaAt = date('Y-m-d H:i:s', $startTs + $travelSeconds);
                    $couponDueAt = date('Y-m-d H:i:s', $startTs + $couponLimitSeconds);
                }
            }
        }

        return [
            'status' => $status,
            'progress' => round($progress, 4),
            'eta_minutes' => $etaMinutes,
            'eta_at' => $etaAt,
            'coupon_due_at' => $couponDueAt,
            'late_coupon_claimed' => ((int) ($order['late_coupon_generated'] ?? 0) === 1),
            'late_coupon_claimable' => late_coupon_claimable_for_order($order),
            'elapsed_seconds' => $elapsedSeconds,
            'delivery_limit_minutes' => (int) ceil($travelSeconds / 60),
            'coupon_limit_minutes' => (int) ceil($couponLimitSeconds / 60),
            'late_coupon_claim_minutes' => (int) ($couponRule['threshold_minutes'] ?? late_coupon_delay_threshold_minutes()),
            'late_coupon_discount_percent' => late_coupon_discount_percent(),
            'late_coupon_min_order_amount' => LATE_COUPON_MIN_ORDER_AMOUNT,
            'route_duration_seconds' => $travelSeconds,
            'route_distance_meters' => (int) ($route['distance_meters'] ?? 0),
            'route_source' => (string) ($route['source'] ?? 'fallback'),
            'route_path' => $routePath,
            'restaurant' => ['lat' => $startLat, 'lng' => $startLng],
            'customer' => ['lat' => $endLat, 'lng' => $endLng],
            'rider' => ['lat' => round($currentLat, 7), 'lng' => round($currentLng, 7)],
            'delivered_at' => $order['delivered_at'] ?? null,
        ];
    }
}

if (!function_exists('fetch_latest_coupon_for_user')) {
    function fetch_latest_coupon_for_user(int $userId): ?array
    {
        $type = 'PRIVATE_LATE';
        $active = 'ACTIVE';
        $stmt = db()->prepare('SELECT id, code, discount_percent, expires_at, created_at
            FROM coupons WHERE user_id = ? AND type = ? AND status = ? ORDER BY id DESC LIMIT 1');
        $stmt->bind_param('iss', $userId, $type, $active);
        $stmt->execute();

        $coupon = $stmt->get_result()->fetch_assoc();
        return $coupon ?: null;
    }
}

if (!function_exists('user_order_by_code')) {
    function user_order_by_code(int $userId, string $orderCode): ?array
    {
        $stmt = db()->prepare('SELECT o.*, a.full_name AS addr_full_name, a.phone AS addr_phone, a.address_line, a.city, a.area, a.landmark, a.lat, a.lng
            FROM orders o
            INNER JOIN addresses a ON a.id = o.address_id
            WHERE o.user_id = ? AND o.order_code = ? LIMIT 1');
        $stmt->bind_param('is', $userId, $orderCode);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();

        return $order ?: null;
    }
}
