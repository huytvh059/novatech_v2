<?php
declare(strict_types=1);

// Cấp mật khẩu tạm cho tài khoản DB cục bộ (auth_source = 'db'), đọc mật khẩu từ stdin.
if (PHP_SAPI !== 'cli') {
    exit(1);
}

require __DIR__ . '/../vendor/autoload.php';

use NovaTech\Db;

$username = strtolower(trim((string) ($argv[1] ?? '')));
if (!preg_match('/\A[a-zA-Z0-9._-]{1,64}\z/', $username)) {
    fwrite(STDERR, "Cách dùng: php tools/set_password.php <username>\n");
    exit(1);
}

$u = Db::one('SELECT id, username, auth_source FROM users WHERE username = ?', [$username]);
if ($u === null) {
    fwrite(STDERR, "Không tìm thấy tài khoản: {$username}\n");
    exit(1);
}
if ($u['auth_source'] !== 'db') {
    fwrite(STDERR, "Tài khoản {$username} thuộc Active Directory (auth_source=ldap). Hãy đổi mật khẩu trên DC01.\n");
    exit(1);
}

fwrite(STDERR, "Nhập mật khẩu mới cho {$username} (12-72 byte): ");
$password = rtrim((string) fgets(STDIN), "\r\n");
if (strlen($password) < 12 || strlen($password) > 72) {
    fwrite(STDERR, "Mật khẩu phải dài từ 12 đến 72 byte.\n");
    exit(1);
}

$pdo = Db::pdo();
$pdo->beginTransaction();
Db::query(
    'UPDATE users SET password_hash = ?, must_change_password = 1, so_lan_sai = 0, khoa_den = NULL, session_version = session_version + 1 WHERE id = ?',
    [password_hash($password, PASSWORD_BCRYPT), $u['id']]
);
Db::audit('cli_password_set', 'users:' . $u['id'], 'success', ['id' => (int) $u['id'], 'username' => 'cli']);
$pdo->commit();

echo "Đã cập nhật mật khẩu tạm cho {$username} (yêu cầu đổi khi đăng nhập).\n";
