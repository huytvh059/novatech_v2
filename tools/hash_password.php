<?php
declare(strict_types=1);

// Sinh bcrypt hash qua stdin (không truyền mật khẩu qua tham số dòng lệnh).
if (PHP_SAPI !== 'cli') {
    exit(1);
}

fwrite(STDERR, 'Nhập mật khẩu cần băm: ');
$password = rtrim((string) fgets(STDIN), "\r\n");
if (strlen($password) < 12 || strlen($password) > 72) {
    fwrite(STDERR, "Mật khẩu phải dài từ 12 đến 72 byte.\n");
    exit(1);
}

echo password_hash($password, PASSWORD_BCRYPT) . PHP_EOL;
