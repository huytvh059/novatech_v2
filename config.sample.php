<?php
declare(strict_types=1);

// Sao chép file này ra ngoài webroot (ví dụ /etc/novatech/config.php hoặc đặt biến môi trường NOVATECH_CONFIG).
// Không lưu mật khẩu thật trong repository hoặc báo cáo.
return [
    'db' => [
        'host' => '10.10.100.13',
        'port' => 3306,
        'name' => 'novatech_db',
        'user' => 'webapp',
        'password' => '<DB_PASSWORD>',
    ],
    'auth' => [
        // 'db' cho giai đoạn 1 (MariaDB), chuyển 'ldap' khi kết nối DC01 (10.10.100.11).
        'provider' => 'db',
        // Tài khoản DB cứu hộ phòng IT (username ngắn) vẫn đăng nhập được khi bật chế độ LDAP.
        'local_admins' => ['it01'],
        'ldap' => [
            'uri' => 'ldap://10.10.100.11:389',
            'start_tls' => false,
            // Chỉ bật true trong mạng lab cô lập khi DC01 chưa cấu hình LDAPS/StartTLS.
            'allow_insecure' => false,
            'domain' => 'ad.novatech.test',
            'netbios' => 'NOVATECH',
            'base_dn' => 'DC=ad,DC=novatech,DC=test',
            'bind_dn' => '<LDAP_READONLY_ACCOUNT_DN>',
            'bind_password' => '<LDAP_BIND_PASSWORD>',
            'timeout' => 3,
            'refresh_seconds' => 60,
            'groups' => [
                'it' => 'CN=GG_IT,OU=Groups,DC=ad,DC=novatech,DC=test',
                'hr' => 'CN=GG_HR,OU=Groups,DC=ad,DC=novatech,DC=test',
                'accounting' => 'CN=GG_KeToan,OU=Groups,DC=ad,DC=novatech,DC=test',
            ],
            'department_map' => [
                'IT' => 'IT',
                'HR' => 'Nhân sự',
                'Nhân sự' => 'Nhân sự',
                'Kế toán' => 'Kế toán',
                'Accounting' => 'Kế toán',
            ],
            'default_department' => '',
        ],
    ],
    'secure_cookie' => false,
    'error_log' => __DIR__ . '/storage/php-error.log',
];
