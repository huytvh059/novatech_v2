<?php
declare(strict_types=1);

namespace NovaTech\Auth;

use NovaTech\Db;

/**
 * Local MariaDB password authentication (`auth_source = 'db'`).
 */
final class DbAuthProvider implements AuthProvider
{
    private const DUMMY_BCRYPT = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi';

    public function authenticate(string $username, string $password): ?array
    {
        return LoginGuard::attempt($username, static function () use ($username, $password): ?array {
            $u = Db::one("SELECT * FROM users WHERE username = ? AND auth_source = 'db' FOR UPDATE", [$username]);
            if ($password === '' || strlen($password) > 72 || str_contains($password, "\0")) {
                return null;
            }

            // Constant-work dummy verify to mitigate username enumeration timing leak.
            $valid = password_verify($password, (string) ($u['password_hash'] ?? self::DUMMY_BCRYPT));
            if ($u === null || !$valid || ($u['trang_thai'] ?? '') !== 'active') {
                return null;
            }

            return $u;
        });
    }
}
