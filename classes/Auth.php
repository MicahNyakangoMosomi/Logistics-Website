<?php

require_once __DIR__ . '/Database.php';

class Auth
{
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }

    public static function login(string $nationalId, string $password): bool
    {
        self::startSession();

        $nationalId = trim($nationalId);
        $password = trim($password);

        if ($nationalId === '' || $password === '') {
            return false;
        }

        $stmt = Database::connection()->prepare(
            "SELECT * FROM members WHERE NationalID = :national_id AND Status = 'Active' LIMIT 1"
        );
        $stmt->execute([
            ':national_id' => $nationalId,
        ]);

        $member = $stmt->fetch();
        if (!$member || !self::passwordMatches($password, $member['Password'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['member_id'] = (string) $member['MemberID'];
        $_SESSION['national_id'] = $member['NationalID'];
        $_SESSION['member_name'] = trim($member['FirstName'] . ' ' . $member['LastName']);

        return true;
    }

    public static function member(): ?array
    {
        self::startSession();

        if (empty($_SESSION['member_id'])) {
            return null;
        }

        $stmt = Database::connection()->prepare('SELECT * FROM members WHERE MemberID = :id LIMIT 1');
        $stmt->execute([':id' => (string) $_SESSION['member_id']]);
        $member = $stmt->fetch();

        return $member ?: null;
    }

    public static function requireMember(): array
    {
        $member = self::member();
        if (!$member) {
            header('Location: ../auth/login.php');
            exit;
        }

        return $member;
    }

    public static function logout(): void
    {
        self::startSession();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'], $params['httponly']);
        }

        session_destroy();
    }

    public static function generatePassword(int $length = 8): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $password = '';
        $max = strlen($alphabet) - 1;

        for ($i = 0; $i < $length; $i++) {
            $password .= $alphabet[random_int(0, $max)];
        }

        return $password;
    }

    public static function adminAllowed(): bool
    {
        $config = require __DIR__ . '/../config/config.php';
        $token = (string)($config['app']['admin_token'] ?? '');

        self::startSession();
        if (!empty($_SESSION['admin_user_id']) && in_array($_SESSION['admin_role'] ?? '', ['admin', 'staff'], true)) {
            return true;
        }

        if ($token === '') {
            return false;
        }

        if (!empty($_SESSION['admin_allowed'])) {
            return true;
        }

        $provided = $_GET['token'] ?? $_POST['token'] ?? '';
        if (hash_equals($token, (string) $provided)) {
            $_SESSION['admin_allowed'] = true;
            $_SESSION['admin_role'] = 'admin';
            return true;
        }

        return false;
    }

    public static function requireAdmin(): void
    {
        if (!self::adminAllowed()) {
            header('Location: ../auth/admin_login.php');
            exit;
        }
    }

    public static function loginAdmin(string $email, string $password): bool
    {
        self::startSession();

        $stmt = Database::connection()->prepare(
            "SELECT * FROM admin_users WHERE Email = :email AND Status = 'Active' LIMIT 1"
        );
        $stmt->execute([':email' => trim($email)]);
        $admin = $stmt->fetch();

        if (!$admin || !hash_equals((string)$admin['Password'], $password)) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['admin_user_id'] = (int)$admin['AdminUserID'];
        $_SESSION['admin_name'] = $admin['FullName'];
        $_SESSION['admin_role'] = $admin['Role'];

        return true;
    }

    public static function adminRole(): string
    {
        self::startSession();

        return (string)($_SESSION['admin_role'] ?? '');
    }

    public static function logoutAdmin(): void
    {
        self::startSession();
        unset($_SESSION['admin_user_id'], $_SESSION['admin_name'], $_SESSION['admin_role'], $_SESSION['admin_allowed']);
    }

    private static function passwordMatches(string $plain, string $stored): bool
    {
        $info = password_get_info($stored);
        if (($info['algo'] ?? 0) !== 0) {
            return password_verify($plain, $stored);
        }

        return hash_equals($stored, $plain);
    }
}
