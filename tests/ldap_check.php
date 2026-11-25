<?php
declare(strict_types=1);

// Kiểm thử logic xác thực & đồng bộ Active Directory LDAP (không cần kết nối DC01 trực tiếp).
require __DIR__ . '/../vendor/autoload.php';

use NovaTech\Auth\LdapAuthProvider;

$n = 0;
function verify(bool $cond, string $name): void
{
    global $n;
    if (!$cond) {
        throw new RuntimeException('FAIL: ' . $name);
    }
    $n++;
}

function invalid(callable $fn, string $name): void
{
    try {
        $fn();
    } catch (Throwable $e) {
        verify(true, $name);

        return;
    }
    throw new RuntimeException('FAIL (expected exception): ' . $name);
}

$config = [
    'uri' => 'ldap://10.10.100.11:389',
    'start_tls' => false,
    'allow_insecure' => true,
    'domain' => 'ad.novatech.test',
    'netbios' => 'NOVATECH',
    'base_dn' => 'DC=ad,DC=novatech,DC=test',
    'bind_dn' => 'CN=svc_portal,OU=Service,DC=ad,DC=novatech,DC=test',
    'bind_password' => 'lab-only-service-secret',
    'groups' => [
        'it' => 'CN=GG_IT,OU=Groups,DC=ad,DC=novatech,DC=test',
        'hr' => 'CN=GG_HR,OU=Groups,DC=ad,DC=novatech,DC=test',
        'accounting' => 'CN=GG_KeToan,OU=Groups,DC=ad,DC=novatech,DC=test',
    ],
    'department_map' => [
        'HR' => 'Nhân sự',
        'Nhân sự' => 'Nhân sự',
        'IT' => 'IT',
        'Kế toán' => 'Kế toán',
        'Accounting' => 'Kế toán',
    ],
];

// Transport validation
LdapAuthProvider::validateTransport($config);
verify(true, 'plain LDAP explicit opt-in');
invalid(fn () => LdapAuthProvider::validateTransport(array_replace($config, ['allow_insecure' => false])), 'plain LDAP denied by default');
LdapAuthProvider::validateTransport(array_replace($config, ['uri' => 'ldaps://dc01.ad.novatech.test:636', 'allow_insecure' => false]));
verify(true, 'LDAPS permitted');
LdapAuthProvider::validateTransport(array_replace($config, ['start_tls' => true, 'allow_insecure' => false]));
verify(true, 'StartTLS permitted');

foreach (['http://host', 'ldap://user:secret@host', 'ldap://host/path', 'ldap://host?query'] as $badUri) {
    invalid(fn () => LdapAuthProvider::validateTransport(array_replace($config, ['uri' => $badUri])), 'bad URI: ' . $badUri);
}
invalid(fn () => LdapAuthProvider::validateTransport(array_replace($config, ['bind_password' => ''])), 'empty bind_password');
invalid(fn () => LdapAuthProvider::validateTransport(array_replace($config, ['bind_password' => "secret\0x"])), 'NUL in bind_password');

// Username normalization (short, DOMAIN\user, UPN)
verify(LdapAuthProvider::normalizeUsername('KT01', $config) === 'kt01', 'short username');
verify(LdapAuthProvider::normalizeUsername('NOVATECH\\kt01', $config) === 'kt01', 'NetBIOS username');
verify(LdapAuthProvider::normalizeUsername('kt01@ad.novatech.test', $config) === 'kt01', 'UPN username');
foreach (['OTHER\\kt01', 'kt01@other.test', '*)(|(objectClass=*)', 'a\\b\\c', ''] as $badUser) {
    invalid(fn () => LdapAuthProvider::normalizeUsername($badUser, $config), 'reject bad username: ' . $badUser);
}

// AD Account state checks (UAC, lock, password expired, must change password, accountExpires)
$entry = [
    'useraccountcontrol' => ['512'],
    'pwdlastset' => ['123'],
    'accountexpires' => ['0'],
    'department' => ['IT'],
];
verify(LdapAuthProvider::enabled($entry), 'enabled normal AD user');
verify(!LdapAuthProvider::enabled(array_replace($entry, ['useraccountcontrol' => ['514']])), 'disabled AD user (UAC bit 2)');
verify(!LdapAuthProvider::enabled(array_replace($entry, ['msds-user-account-control-computed' => ['16']])), 'locked AD user');
verify(!LdapAuthProvider::enabled(array_replace($entry, ['msds-user-account-control-computed' => ['8388608']])), 'expired AD password');
verify(!LdapAuthProvider::enabled(array_replace($entry, ['pwdlastset' => ['0']])), 'must change AD password');
verify(!LdapAuthProvider::enabled(array_replace($entry, ['accountexpires' => ['1']])), 'expired AD account');

// Department mapping
verify(LdapAuthProvider::departmentName($entry, 'employee', $config) === 'IT', 'employee department_map');
verify(LdapAuthProvider::departmentName([], 'it', $config) === 'IT', 'it maps to IT');
verify(LdapAuthProvider::departmentName([], 'hr', $config) === 'Nhân sự', 'hr maps to Nhân sự');
verify(LdapAuthProvider::departmentName([], 'accounting', $config) === 'Kế toán', 'accounting maps to Kế toán');
invalid(fn () => LdapAuthProvider::departmentName([], 'employee', $config), 'missing department fails closed');
verify(LdapAuthProvider::departmentName([], 'employee', $config + ['default_department' => 'IT']) === 'IT', 'explicit default_department fallback');

// PHP LDAP extension filter escape & GUID roundtrip
verify(extension_loaded('ldap'), 'ext-ldap loaded');
$filter = ldap_escape("*)(objectClass=*)\0", '', LDAP_ESCAPE_FILTER);
verify(!str_contains($filter, '*') && !str_contains($filter, '(') && !str_contains($filter, "\0"), 'LDAP filter injection escaped');
$guid = random_bytes(16);
verify(strlen(bin2hex($guid)) === 32 && hex2bin(bin2hex($guid)) === $guid, 'objectGUID 16-byte <-> 32-hex roundtrip');

echo "PASS: {$n} LDAP checks\n";
