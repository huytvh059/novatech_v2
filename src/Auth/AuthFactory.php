<?php
declare(strict_types=1);

namespace NovaTech\Auth;

use InvalidArgumentException;
use NovaTech\Config;
use NovaTech\Db;
use RuntimeException;

/**
 * Selects DB or Active Directory LDAP provider based on config, keeping rescue DB admin support.
 */
final class AuthFactory
{
    /**
     * @return array{0: AuthProvider, 1: string}
     */
    public static function resolve(string $input): array
    {
        $auth = Config::get()['auth'] ?? ['provider' => 'db'];
        $provider = (string) ($auth['provider'] ?? 'db');

        if ($provider === 'db') {
            if (!preg_match('/\A[a-zA-Z0-9._-]{1,64}\z/', trim($input))) {
                throw new InvalidArgumentException('Tên đăng nhập không hợp lệ.');
            }

            return [new DbAuthProvider(), strtolower(trim($input))];
        }

        if ($provider !== 'ldap') {
            throw new RuntimeException('Auth provider không hợp lệ: ' . $provider);
        }

        $ldap = (array) ($auth['ldap'] ?? []);
        $username = LdapAuthProvider::normalizeUsername($input, $ldap);
        $localAdmins = array_map('strtolower', (array) ($auth['local_admins'] ?? []));

        // Rescue DB login only via short username (not DOMAIN\user or UPN).
        if (trim($input) === $username && in_array($username, $localAdmins, true)) {
            $rescue = Db::one(
                "SELECT id FROM users WHERE username = ? AND auth_source = 'db' AND role = 'it' AND trang_thai = 'active'",
                [$username]
            );
            if ($rescue === null) {
                throw new RuntimeException('Tài khoản cứu hộ phải là tài khoản phòng IT cục bộ đang hoạt động.');
            }

            return [new DbAuthProvider(), $username];
        }

        return [new LdapAuthProvider($ldap), $username];
    }
}
