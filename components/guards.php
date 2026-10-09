<?php

declare(strict_types=1);

if (!function_exists('mark_auth_session_started')) {
    function mark_auth_session_started(): void
    {
        $now = time();
        $_SESSION['_auth_started_at'] = $now;
        $_SESSION['_auth_last_seen'] = $now;
    }
}

if (!function_exists('auth_session_expired')) {
    function auth_session_expired(): bool
    {
        $now = time();
        $startedAt = (int) ($_SESSION['_auth_started_at'] ?? 0);
        $lastSeen = (int) ($_SESSION['_auth_last_seen'] ?? 0);

        if ($startedAt <= 0 || $lastSeen <= 0) {
            $_SESSION['_auth_started_at'] = $now;
            $_SESSION['_auth_last_seen'] = $now;
            return false;
        }

        $idleLimit = max(60, SESSION_IDLE_TIMEOUT_MINUTES * 60);
        $absoluteLimit = max(300, SESSION_ABSOLUTE_TIMEOUT_HOURS * 3600);

        if (($now - $lastSeen) > $idleLimit || ($now - $startedAt) > $absoluteLimit) {
            return true;
        }

        $_SESSION['_auth_last_seen'] = $now;
        return false;
    }
}

if (!function_exists('require_admin')) {
    function require_admin(): void
    {
        if (empty($_SESSION['admin_id'])) {
            flash_error('Please login as admin.');
            redirect('/admin/auth.php');
        }

        if (auth_session_expired()) {
            unset($_SESSION['admin_id'], $_SESSION['_auth_started_at'], $_SESSION['_auth_last_seen']);
            session_regenerate_id(true);
            flash_error('Session expired. Please login again.');
            redirect('/admin/auth.php');
        }
    }
}

if (!function_exists('require_user')) {
    function require_user(): void
    {
        if (empty($_SESSION['user_id'])) {
            flash_error('Please login to continue.');
            redirect('/user/auth.php');
        }

        if (auth_session_expired()) {
            unset($_SESSION['user_id'], $_SESSION['_auth_started_at'], $_SESSION['_auth_last_seen']);
            session_regenerate_id(true);
            flash_error('Session expired. Please login again.');
            redirect('/user/auth.php');
        }
    }
}

if (!function_exists('current_admin')) {
    function current_admin(): ?array
    {
        $adminId = (int) ($_SESSION['admin_id'] ?? 0);
        if ($adminId <= 0) {
            return null;
        }

        if (auth_session_expired()) {
            unset($_SESSION['admin_id'], $_SESSION['_auth_started_at'], $_SESSION['_auth_last_seen']);
            session_regenerate_id(true);
            return null;
        }

        $stmt = db()->prepare('SELECT id, name, email, status FROM admins WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $adminId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        return $result ?: null;
    }
}

if (!function_exists('current_user')) {
    function current_user(): ?array
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            return null;
        }

        if (auth_session_expired()) {
            unset($_SESSION['user_id'], $_SESSION['_auth_started_at'], $_SESSION['_auth_last_seen']);
            session_regenerate_id(true);
            return null;
        }

        $stmt = db()->prepare('SELECT id, full_name, email, phone, status, created_at FROM users WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        return $result ?: null;
    }
}

if (!function_exists('assert_address_owner')) {
    function assert_address_owner(int $addressId, int $userId): void
    {
        $stmt = db()->prepare('SELECT id FROM addresses WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->bind_param('ii', $addressId, $userId);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            http_response_code(403);
            exit('Unauthorized address access.');
        }
    }
}

if (!function_exists('assert_order_owner')) {
    function assert_order_owner(int $orderId, int $userId): void
    {
        $stmt = db()->prepare('SELECT id FROM orders WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->bind_param('ii', $orderId, $userId);
        $stmt->execute();
        if (!$stmt->get_result()->fetch_assoc()) {
            http_response_code(403);
            exit('Unauthorized order access.');
        }
    }
}
