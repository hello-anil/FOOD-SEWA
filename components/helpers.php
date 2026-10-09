<?php

declare(strict_types=1);

if (!function_exists('base_url')) {
    function base_url(string $path = ''): string
    {
        $normalized = '/' . ltrim($path, '/');
        if ($normalized === '/') {
            return APP_BASE_URL;
        }

        return APP_BASE_URL . $normalized;
    }
}

if (!function_exists('normalize_email')) {
    function normalize_email(string $email): string
    {
        return strtolower(trim($email));
    }
}

if (!function_exists('str_length')) {
    function str_length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    }
}

if (!function_exists('validate_email')) {
    function validate_email(string $email): string
    {
        $email = normalize_email($email);
        if ($email === '' || strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Please provide a valid email address.');
        }

        return $email;
    }
}

if (!function_exists('validate_password_strength')) {
    function validate_password_strength(string $password, int $minLength = 10): void
    {
        if (strlen($password) < $minLength) {
            throw new RuntimeException('Password must be at least ' . $minLength . ' characters long.');
        }
        if (!preg_match('/[A-Z]/', $password)) {
            throw new RuntimeException('Password must include at least one uppercase letter.');
        }
        if (!preg_match('/[a-z]/', $password)) {
            throw new RuntimeException('Password must include at least one lowercase letter.');
        }
        if (!preg_match('/[0-9]/', $password)) {
            throw new RuntimeException('Password must include at least one number.');
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            throw new RuntimeException('Password must include at least one special character.');
        }
    }
}

if (!function_exists('validate_person_name')) {
    function validate_person_name(string $name, int $minLength = 2, int $maxLength = 120): string
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? $name);
        $len = str_length($name);

        if ($len < $minLength || $len > $maxLength) {
            throw new RuntimeException('Name must be between ' . $minLength . ' and ' . $maxLength . ' characters.');
        }

        if (!preg_match('/^[\p{L}\p{N} .\'-]+$/u', $name)) {
            throw new RuntimeException('Name contains invalid characters.');
        }

        return $name;
    }
}

if (!function_exists('validate_full_name')) {
    function validate_full_name(string $name, int $minLength = 2, int $maxLength = 120): string
    {
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? $name);
        $len = str_length($name);

        if ($len < $minLength || $len > $maxLength) {
            throw new RuntimeException('Full name must be between ' . $minLength . ' and ' . $maxLength . ' characters.');
        }

        // Full name must not contain numeric characters.
        if (preg_match('/\p{N}/u', $name)) {
            throw new RuntimeException('Full name cannot contain numbers.');
        }

        if (!preg_match('/^[\p{L} .\'-]+$/u', $name)) {
            throw new RuntimeException('Full name contains invalid characters.');
        }

        return $name;
    }
}

if (!function_exists('validate_phone')) {
    function validate_phone(string $phone): string
    {
        $phone = trim($phone);
        $normalized = preg_replace('/[^\d+]/', '', $phone) ?? '';
        $normalized = preg_replace('/(?!^)\+/', '', $normalized) ?? '';

        if (!preg_match('/^\+?\d{7,15}$/', $normalized)) {
            throw new RuntimeException('Please provide a valid phone number.');
        }

        return $normalized;
    }
}

if (!function_exists('validate_enum')) {
    function validate_enum(string $value, array $allowed, string $errorMessage = 'Invalid value.'): string
    {
        if (!in_array($value, $allowed, true)) {
            throw new RuntimeException($errorMessage);
        }

        return $value;
    }
}

if (!function_exists('validate_tracking_number')) {
    function validate_tracking_number(string $tracking): string
    {
        $tracking = strtoupper(trim($tracking));
        if ($tracking === '' || strlen($tracking) > 60 || !preg_match('/^[A-Z0-9-]{6,60}$/', $tracking)) {
            throw new RuntimeException('Invalid tracking number format.');
        }

        return $tracking;
    }
}

if (!function_exists('validate_order_code')) {
    function validate_order_code(string $orderCode): string
    {
        $orderCode = strtoupper(trim($orderCode));
        if ($orderCode === '' || strlen($orderCode) > 50 || !preg_match('/^[A-Z0-9-]{5,50}$/', $orderCode)) {
            throw new RuntimeException('Invalid order code format.');
        }

        return $orderCode;
    }
}

if (!function_exists('validate_coordinates')) {
    function validate_coordinates(float $lat, float $lng): void
    {
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            throw new RuntimeException('Invalid latitude/longitude.');
        }
    }
}

if (!function_exists('client_ip')) {
    function client_ip(): string
    {
        $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));
        return $ip !== '' ? $ip : '0.0.0.0';
    }
}

if (!function_exists('rate_limit_allow')) {
    function rate_limit_allow(string $bucket, string $identifier, int $maxAttempts, int $windowSeconds): bool
    {
        $maxAttempts = max(1, $maxAttempts);
        $windowSeconds = max(1, $windowSeconds);

        $dir = APP_ROOT . '/uploads/.ratelimit';
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            return true;
        }

        $hash = sha1($bucket . '|' . $identifier);
        $file = $dir . DIRECTORY_SEPARATOR . $hash . '.json';
        $fp = fopen($file, 'c+');
        if ($fp === false) {
            return true;
        }

        $allowed = true;
        $now = time();

        try {
            if (!flock($fp, LOCK_EX)) {
                fclose($fp);
                return true;
            }

            rewind($fp);
            $raw = stream_get_contents($fp);
            $data = is_string($raw) ? json_decode($raw, true) : null;
            if (!is_array($data)) {
                $data = ['window_start' => $now, 'count' => 0];
            }

            $windowStart = (int) ($data['window_start'] ?? $now);
            $count = (int) ($data['count'] ?? 0);
            if (($now - $windowStart) >= $windowSeconds) {
                $windowStart = $now;
                $count = 0;
            }

            if ($count >= $maxAttempts) {
                $allowed = false;
            } else {
                $count++;
            }

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode([
                'window_start' => $windowStart,
                'count' => $count,
            ], JSON_UNESCAPED_SLASHES));
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
        } catch (Throwable $e) {
            fclose($fp);
            return true;
        }

        return $allowed;
    }
}

if (!function_exists('rate_limit_reset')) {
    function rate_limit_reset(string $bucket, string $identifier): void
    {
        $dir = APP_ROOT . '/uploads/.ratelimit';
        if (!is_dir($dir)) {
            return;
        }

        $hash = sha1($bucket . '|' . $identifier);
        $file = $dir . DIRECTORY_SEPARATOR . $hash . '.json';
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path): never
    {
        header('Location: ' . (str_starts_with($path, 'http') ? $path : base_url($path)));
        exit;
    }
}

if (!function_exists('e')) {
    function e(?string $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('is_post')) {
    function is_post(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('verify_csrf')) {
    function verify_csrf(): void
    {
        $token = $_POST['_csrf'] ?? '';
        $sessionToken = $_SESSION['_csrf'] ?? '';

        if (!$token || !$sessionToken || !hash_equals($sessionToken, $token)) {
            http_response_code(419);
            exit('Invalid CSRF token.');
        }
    }
}

if (!function_exists('paginate')) {
    function paginate(int $total, int $perPage = 10): array
    {
        $page = (int) ($_GET['page'] ?? 1);
        $page = max(1, $page);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);

        return [
            'page' => $page,
            'per_page' => $perPage,
            'offset' => ($page - 1) * $perPage,
            'pages' => $pages,
        ];
    }
}

if (!function_exists('guest_cookie_options')) {
    function guest_cookie_options(int $days = 30): array
    {
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

        return [
            'expires' => time() + ($days * 86400),
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }
}

if (!function_exists('money')) {
    function money(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}

if (!function_exists('now_utc')) {
    function now_utc(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}

if (!function_exists('upload_image')) {
    function upload_image(array $file, string $dir, array $allowedExt = ['jpg', 'jpeg', 'png', 'webp'], int $maxSize = 2097152): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload failed.');
        }

        if (($file['size'] ?? 0) > $maxSize) {
            throw new RuntimeException('File too large.');
        }

        $tmpPath = $file['tmp_name'] ?? '';
        $originalName = $file['name'] ?? '';

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpPath);
        $mimeAllowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mime, $mimeAllowed, true)) {
            throw new RuntimeException('Invalid file type.');
        }

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            throw new RuntimeException('Invalid extension.');
        }

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Could not create upload directory.');
        }

        $newName = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $newName;

        if (!move_uploaded_file($tmpPath, $dest)) {
            throw new RuntimeException('Failed to save uploaded file.');
        }

        return $newName;
    }
}

if (!function_exists('http_json')) {
    function http_json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES);
        exit;
    }
}
