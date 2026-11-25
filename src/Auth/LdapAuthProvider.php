<?php
declare(strict_types=1);

namespace NovaTech\Auth;

use InvalidArgumentException;
use LDAP\Connection;
use NovaTech\Db;
use RuntimeException;
use Throwable;

/**
 * Active Directory LDAP authentication & role/department synchronization (aligned with huytvh059/NOVATECH).
 *
 * - Normalizes `user`, `NOVATECH\user`, `user@ad.novatech.test`.
 * - Enforces StartTLS / LDAPS unless `allow_insecure => true` is explicitly configured for isolated lab.
 * - Maps nested AD groups (`GG_IT`, `GG_HR`, `GG_KeToan`) via `LDAP_MATCHING_RULE_IN_CHAIN` (1.2.840.113556.1.4.1941).
 * - Links AD users permanently via binary `objectGUID` (`ad_guid` 32-hex) without colliding/hijacking local DB accounts.
 */
final class LdapAuthProvider implements AuthProvider
{
    private const MATCHING_RULE_IN_CHAIN = '1.2.840.113556.1.4.1941';

    /** @var array<string, mixed> */
    private array $config;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function normalizeUsername(string $input, array $config): string
    {
        $name = trim($input);
        if (str_contains($name, '\\')) {
            $parts = explode('\\', $name);
            if (count($parts) !== 2 || strcasecmp($parts[0], (string) ($config['netbios'] ?? 'NOVATECH')) !== 0) {
                throw new InvalidArgumentException('Domain đăng nhập không hợp lệ.');
            }
            $name = $parts[1];
        } elseif (str_contains($name, '@')) {
            $parts = explode('@', $name);
            if (count($parts) !== 2 || strcasecmp($parts[1], (string) ($config['domain'] ?? '')) !== 0) {
                throw new InvalidArgumentException('Domain đăng nhập không hợp lệ.');
            }
            $name = $parts[0];
        }

        if (!preg_match('/\A[a-zA-Z0-9._-]{1,64}\z/', $name)) {
            throw new InvalidArgumentException('Tên đăng nhập AD không hợp lệ.');
        }

        return strtolower($name);
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function validateTransport(array $config): void
    {
        $uri = (string) ($config['uri'] ?? '');
        $parts = parse_url($uri);
        if (
            !$parts
            || !in_array($parts['scheme'] ?? '', ['ldap', 'ldaps'], true)
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || !in_array($parts['path'] ?? '', ['', '/'], true)
        ) {
            throw new RuntimeException('Cấu hình LDAP URI không hợp lệ.');
        }

        if ($parts['scheme'] === 'ldap' && empty($config['start_tls']) && ($config['allow_insecure'] ?? false) !== true) {
            throw new RuntimeException('LDAP không mã hóa chưa được cho phép rõ ràng.');
        }

        foreach (['base_dn', 'bind_dn', 'bind_password', 'domain'] as $key) {
            $val = $config[$key] ?? null;
            if (!is_string($val) || $val === '' || str_contains($val, "\0") || str_starts_with($val, '<')) {
                throw new RuntimeException('Thiếu cấu hình LDAP (' . $key . ').');
            }
        }

        foreach (['it', 'hr', 'accounting'] as $role) {
            if (empty($config['groups'][$role]) || !is_string($config['groups'][$role])) {
                throw new RuntimeException('Thiếu DN nhóm AD cho vai trò ' . $role . '.');
            }
        }
    }

    private function connect(): Connection
    {
        self::validateTransport($this->config);
        if (!extension_loaded('ldap')) {
            throw new RuntimeException('Thiếu PHP LDAP extension.');
        }

        $connection = ldap_connect((string) $this->config['uri']);
        if (!$connection) {
            throw new RuntimeException('Không khởi tạo được kết nối LDAP.');
        }

        $timeout = max(1, min(10, (int) ($this->config['timeout'] ?? 3)));
        ldap_set_option($connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($connection, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($connection, LDAP_OPT_NETWORK_TIMEOUT, $timeout);
        ldap_set_option($connection, LDAP_OPT_TIMELIMIT, $timeout);
        ldap_set_option($connection, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_DEMAND);

        if (!empty($this->config['start_tls']) && !@ldap_start_tls($connection)) {
            ldap_unbind($connection);

            throw new RuntimeException('Không thiết lập được LDAP StartTLS.');
        }

        return $connection;
    }

    private function serviceConnection(): Connection
    {
        $connection = $this->connect();
        if (!@ldap_bind($connection, (string) $this->config['bind_dn'], (string) $this->config['bind_password'])) {
            ldap_unbind($connection);

            throw new RuntimeException('LDAP service bind thất bại. Kiểm tra DC01 và cấu hình bind_dn.');
        }

        return $connection;
    }

    /**
     * @return list<string>
     */
    private function attributes(): array
    {
        return [
            'objectGUID',
            'sAMAccountName',
            'displayName',
            'mail',
            'telephoneNumber',
            'department',
            'userAccountControl',
            'msDS-User-Account-Control-Computed',
            'accountExpires',
            'pwdLastSet',
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function search(Connection $connection, string $filter): ?array
    {
        $timeout = max(1, min(10, (int) ($this->config['timeout'] ?? 3)));
        $result = @ldap_search($connection, (string) $this->config['base_dn'], $filter, $this->attributes(), 0, 2, $timeout);
        if ($result === false) {
            throw new RuntimeException('LDAP search thất bại.');
        }

        $entries = ldap_get_entries($connection, $result);
        if ($entries === false) {
            throw new RuntimeException('Không đọc được dữ liệu LDAP.');
        }

        if ((int) ($entries['count'] ?? 0) !== 1) {
            return null;
        }

        return $entries[0];
    }

    /**
     * @param array<string, mixed> $entry
     */
    public static function value(array $entry, string $key): string
    {
        return (string) ($entry[strtolower($key)][0] ?? '');
    }

    /**
     * @param array<string, mixed> $entry
     */
    public static function enabled(array $entry): bool
    {
        $uac = self::value($entry, 'userAccountControl');
        if ($uac === '' || ((int) $uac & 2) !== 0) {
            return false;
        }

        $computed = (int) self::value($entry, 'msDS-User-Account-Control-Computed');
        if (($computed & (16 | 8388608)) !== 0 || self::value($entry, 'pwdLastSet') === '0') {
            return false;
        }

        $expires = self::value($entry, 'accountExpires');

        return $expires === ''
            || $expires === '0'
            || $expires === '9223372036854775807'
            || ((float) $expires / 10000000 - 11644473600) > time();
    }

    /**
     * @param array<string, mixed> $entry
     */
    private function resolveRole(Connection $connection, array $entry): string
    {
        $sam = ldap_escape(self::value($entry, 'sAMAccountName'), '', LDAP_ESCAPE_FILTER);
        foreach (['it', 'hr', 'accounting'] as $role) {
            $groupDn = ldap_escape((string) $this->config['groups'][$role], '', LDAP_ESCAPE_FILTER);
            $filter = '(&(objectCategory=person)(objectClass=user)(sAMAccountName=' . $sam . ')(memberOf:' . self::MATCHING_RULE_IN_CHAIN . ':=' . $groupDn . '))';
            $result = @ldap_read($connection, (string) $entry['dn'], $filter, ['objectGUID']);
            if ($result === false) {
                throw new RuntimeException('Không kiểm tra được nhóm AD.');
            }
            if (ldap_count_entries($connection, $result) === 1) {
                return $role;
            }
        }

        return 'employee';
    }

    /**
     * @param array<string, mixed> $entry
     * @param array<string, mixed> $config
     */
    public static function departmentName(array $entry, string $role, array $config): string
    {
        $mapping = [
            'it' => 'IT',
            'hr' => 'Nhân sự',
            'accounting' => 'Kế toán',
        ];
        if (isset($mapping[$role])) {
            return $mapping[$role];
        }

        $adDepartment = self::value($entry, 'department');
        $department = (string) ($config['department_map'][$adDepartment] ?? ($config['default_department'] ?? ''));
        if (!in_array($department, ['IT', 'Nhân sự', 'Kế toán'], true)) {
            throw new RuntimeException('Nhân viên AD chưa có phòng ban hợp lệ.');
        }

        return $department;
    }

    /**
     * @param array<string, mixed> $entry
     *
     * @return array<string, mixed>|null
     */
    private function sync(Connection $connection, array $entry, ?int $expectedId = null): ?array
    {
        $binary = self::value($entry, 'objectGUID');
        if (strlen($binary) !== 16) {
            throw new RuntimeException('AD objectGUID không hợp lệ.');
        }

        $guid = bin2hex($binary);
        $existing = Db::one('SELECT * FROM users WHERE ad_guid = ? FOR UPDATE', [$guid]);
        if ($expectedId !== null && ($existing === null || (int) $existing['id'] !== $expectedId)) {
            return null;
        }

        if (!self::enabled($entry)) {
            if ($existing !== null) {
                Db::query(
                    "UPDATE users SET trang_thai = 'locked', session_version = session_version + 1, ad_synced_at = NOW() WHERE id = ?",
                    [$existing['id']]
                );
            }

            return null;
        }

        $username = self::normalizeUsername(self::value($entry, 'sAMAccountName'), $this->config);
        $collision = Db::one(
            'SELECT id FROM users WHERE username = ? AND (ad_guid IS NULL OR ad_guid <> ?)',
            [$username, $guid]
        );
        if ($collision !== null) {
            throw new RuntimeException('Username AD trùng tài khoản cục bộ khác. Không tự liên kết để tránh chiếm tài khoản.');
        }

        $role = $this->resolveRole($connection, $entry);
        $name = mb_substr(self::value($entry, 'displayName') ?: $username, 0, 150);
        $mail = self::value($entry, 'mail');
        if ($mail !== '' && (!filter_var($mail, FILTER_VALIDATE_EMAIL) || strlen($mail) > 190)) {
            $mail = '';
        }
        $extension = mb_substr(self::value($entry, 'telephoneNumber'), 0, 20);
        $department = self::departmentName($entry, $role, $this->config);
        $dept = Db::one('SELECT id FROM departments WHERE ten_phong_ban = ?', [$department]);
        if ($dept === null) {
            throw new RuntimeException('Phòng ban chưa được khởi tạo trong DB.');
        }

        if ($existing !== null) {
            $changed = $existing['role'] !== $role
                || (int) $existing['phong_ban_id'] !== (int) $dept['id']
                || $existing['trang_thai'] !== 'active';

            Db::query(
                "UPDATE users SET username = ?, ho_ten = ?, email = ?, so_may_le = ?, phong_ban_id = ?, role = ?, trang_thai = 'active', must_change_password = 0, session_version = session_version + ?, ad_synced_at = NOW() WHERE id = ?",
                [$username, $name, $mail, $extension, $dept['id'], $role, $changed ? 1 : 0, $existing['id']]
            );
            $id = (int) $existing['id'];
            if ($changed) {
                Db::audit('ad_permissions_sync', 'users:' . $id . ' -> ' . $role, 'success', ['id' => $id, 'username' => $username]);
            }
        } else {
            Db::query(
                "INSERT INTO users (username, password_hash, ho_ten, email, so_may_le, phong_ban_id, role, auth_source, ad_guid, ad_synced_at, must_change_password) VALUES (?, ?, ?, ?, ?, ?, ?, 'ldap', ?, NOW(), 0)",
                [$username, password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT), $name, $mail, $extension, $dept['id'], $role, $guid]
            );
            $id = (int) Db::pdo()->lastInsertId();
            Db::audit('ad_user_create', 'users:' . $id . ' (' . $role . ')', 'success', ['id' => $id, 'username' => $username]);
        }

        return Db::one('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public function authenticate(string $username, string $password): ?array
    {
        return LoginGuard::attempt($username, function () use ($username, $password): ?array {
            if ($password === '' || str_contains($password, "\0")) {
                return null;
            }

            $service = $this->serviceConnection();

            try {
                $entry = $this->search(
                    $service,
                    '(&(objectCategory=person)(objectClass=user)(sAMAccountName=' . ldap_escape($username, '', LDAP_ESCAPE_FILTER) . '))'
                );
                if ($entry === null || !self::enabled($entry)) {
                    return null;
                }

                $connection = $this->connect();

                try {
                    if (!@ldap_bind($connection, (string) $entry['dn'], $password)) {
                        $code = ldap_errno($connection);
                        if (!in_array($code, [48, 49], true)) {
                            throw new RuntimeException('AD từ chối bind hoặc dịch vụ không khả dụng.');
                        }

                        return null;
                    }
                } finally {
                    ldap_unbind($connection);
                }

                return $this->sync($service, $entry);
            } finally {
                ldap_unbind($service);
            }
        });
    }

    /**
     * Re-checks AD user status & group membership periodically during an active session.
     *
     * @param array<string, mixed> $user
     *
     * @return array<string, mixed>|null
     */
    public function refresh(array $user): ?array
    {
        $service = $this->serviceConnection();
        $pdo = Db::pdo();
        $pdo->beginTransaction();

        try {
            $binary = hex2bin((string) ($user['ad_guid'] ?? ''));
            if ($binary === false || strlen($binary) !== 16) {
                throw new RuntimeException('ad_guid trong DB không hợp lệ.');
            }

            $entry = $this->search(
                $service,
                '(&(objectCategory=person)(objectClass=user)(objectGUID=' . ldap_escape($binary, '', LDAP_ESCAPE_FILTER) . '))'
            );
            if ($entry === null) {
                Db::query(
                    "UPDATE users SET trang_thai = 'locked', session_version = session_version + 1, ad_synced_at = NOW() WHERE id = ?",
                    [$user['id']]
                );
                Db::audit('ad_access_revoked', 'users:' . $user['id'], 'denied', $user);
                $pdo->commit();

                return null;
            }

            $synced = $this->sync($service, $entry, (int) $user['id']);
            if ($synced === null) {
                Db::audit('ad_access_revoked', 'users:' . $user['id'], 'denied', $user);
            }
            $pdo->commit();

            return $synced;
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $error;
        } finally {
            ldap_unbind($service);
        }
    }
}
