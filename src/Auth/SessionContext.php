<?php
declare(strict_types=1);

namespace NovaTech\Auth;

use NovaTech\Config;
use NovaTech\Db;

/**
 * Secure PHP session lifecycle with AD periodic role refresh & session_version invalidation.
 */
final class SessionContext
{
    private const IDLE_TIMEOUT_SECONDS = 1800;

    /** @var array<string, mixed>|null */
    private static ?array $currentUser = null;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $cfg = Config::get();
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_name('novatech_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => (bool) ($cfg['secure_cookie'] ?? false),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        self::hydrateCurrentUser();
    }

    private static function hydrateCurrentUser(): void
    {
        self::$currentUser = null;
        if (!isset($_SESSION['uid'])) {
            return;
        }

        $cfg = Config::get();
        $authProvider = (string) ($cfg['auth']['provider'] ?? 'db');
        $u = Db::one(
            'SELECT u.*, d.ten_phong_ban, d.ma_phong_ban FROM users u JOIN departments d ON d.id = u.phong_ban_id WHERE u.id = ?',
            [(int) $_SESSION['uid']]
        );

        if ($u !== null && $u['auth_source'] === 'ldap') {
            $refreshSeconds = max(10, min(300, (int) ($cfg['auth']['ldap']['refresh_seconds'] ?? 60)));
            if (time() - (int) ($_SESSION['ad_checked_at'] ?? 0) >= $refreshSeconds) {
                $synced = (new LdapAuthProvider((array) ($cfg['auth']['ldap'] ?? [])))->refresh($u);
                $_SESSION['ad_checked_at'] = time();
                $u = $synced !== null
                    ? Db::one(
                        'SELECT u.*, d.ten_phong_ban, d.ma_phong_ban FROM users u JOIN departments d ON d.id = u.phong_ban_id WHERE u.id = ?',
                        [(int) $synced['id']]
                    )
                    : null;
            }
        }

        if ($u !== null && $u['auth_source'] === 'ldap' && $authProvider !== 'ldap') {
            $u = null;
        }

        if ($u !== null && $u['auth_source'] === 'db' && $authProvider === 'ldap') {
            $allowed = array_map('strtolower', (array) ($cfg['auth']['local_admins'] ?? []));
            if ($u['role'] !== 'it' || !in_array(strtolower((string) $u['username']), $allowed, true)) {
                $u = null;
            }
        }

        if (
            $u === null
            || $u['trang_thai'] !== 'active'
            || (int) $u['session_version'] !== (int) ($_SESSION['version'] ?? 0)
            || (time() - (int) ($_SESSION['last_seen'] ?? 0)) > self::IDLE_TIMEOUT_SECONDS
        ) {
            self::clearSessionData();

            return;
        }

        self::$currentUser = $u;
        $_SESSION['_auth_actor'] = ['id' => (int) $u['id'], 'username' => $u['username']];
        $_SESSION['last_seen'] = time();
    }

    /**
     * @param array<string, mixed> $user
     */
    public static function establishLogin(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION = [
            'uid' => (int) $user['id'],
            'version' => (int) $user['session_version'],
            'last_seen' => time(),
            'ad_checked_at' => time(),
            '_auth_actor' => ['id' => (int) $user['id'], 'username' => $user['username']],
        ];
        self::$currentUser = null;
        self::hydrateCurrentUser();
    }

    public static function destroy(): void
    {
        self::$currentUser = null;
        self::clearSessionData();
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $p['path'],
                'secure' => $p['secure'],
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        session_destroy();
    }

    private static function clearSessionData(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function user(): ?array
    {
        return self::$currentUser;
    }

    public static function isIt(): bool
    {
        if (self::$currentUser === null) {
            return false;
        }

        // Hỗ trợ cả role 'it' và thành viên thuộc phòng IT (tránh lỗi phòng IT không thấy ticket từ HR/Kế toán)
        return self::$currentUser['role'] === 'it'
            || strcasecmp((string) (self::$currentUser['ma_phong_ban'] ?? ''), 'IT') === 0;
    }

    public static function isHr(): bool
    {
        return (self::$currentUser['role'] ?? '') === 'hr';
    }

    public static function isAccounting(): bool
    {
        return (self::$currentUser['role'] ?? '') === 'accounting';
    }

    public static function canSubmitTickets(): bool
    {
        // Theo ma trận phân quyền Bai_2_NovaTech_Helpdesk.md: Kế toán & HR gửi yêu cầu hỗ trợ; IT tiếp nhận & xử lý.
        return self::$currentUser !== null && !self::isIt();
    }

    /**
     * @param array<string, mixed> $ticketRow
     */
    public static function canViewTicket(array $ticketRow): bool
    {
        if (self::$currentUser === null) {
            return false;
        }
        if (self::isIt()) {
            return true;
        }

        // Theo ma trận Bai_2_NovaTech_Helpdesk.md: Kế toán / HR xem được yêu cầu của phòng ban mình,
        // không xem được yêu cầu của phòng ban khác (Test 15, Test 16).
        return (int) $ticketRow['phong_ban_id'] === (int) self::$currentUser['phong_ban_id'];
    }
}
