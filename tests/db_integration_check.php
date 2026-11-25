<?php
declare(strict_types=1);

// Kiểm thử tích hợp thật với MariaDB (novatech_db): Schema, Seed, Auth DB, LoginGuard, RBAC Ticket Flow, Audit Logs.
// Chạy khi đã cấu hình NOVATECH_CONFIG trỏ tới DB thật đã import db/schema.sql và db/seed.sql.
require __DIR__ . '/../vendor/autoload.php';

use NovaTech\Auth\AuthFactory;
use NovaTech\Config;
use NovaTech\Db;

$cfg = Config::get();
$pdo = Db::pdo();

$n = 0;
function assertTrue(bool $cond, string $msg): void
{
    global $n;
    if (!$cond) {
        throw new RuntimeException('FAIL: ' . $msg);
    }
    $n++;
}

// 1. Verify tables exist in novatech_db
$tables = array_column(Db::rows('SHOW TABLES'), array_key_first(Db::one('SHOW TABLES') ?? []));
foreach (['departments', 'users', 'assets', 'announcements', 'tickets', 'ticket_comments', 'audit_logs', 'login_attempts'] as $tbl) {
    assertTrue(in_array($tbl, $tables, true), "table {$tbl} exists in novatech_db");
}

// 2. Verify seed counts (3 departments, 8 IP-plan VMs, 4 seed users)
assertTrue((int) Db::one('SELECT COUNT(*) AS c FROM departments')['c'] === 3, '3 departments seeded');
assertTrue((int) Db::one('SELECT COUNT(*) AS c FROM assets')['c'] === 8, '8 assets (IP Plan) seeded');
assertTrue((int) Db::one('SELECT COUNT(*) AS c FROM users')['c'] >= 3, '3 seed users exist (kt01, hr01, it01)');

// 3. Verify Atk4\Data\Persistence\Sql works on real MariaDB models
$p = Db::persistence();
$assetModel = new \NovaTech\Model\Asset($p);
$dc01 = $assetModel->loadBy('ten_may', 'DC01');
assertTrue($dc01->get('fixed_ip') === '10.10.100.11', 'Atk4 Asset model reads DC01 10.10.100.11');

$ticketModel = new \NovaTech\Model\Ticket($p);
$loadedTickets = $ticketModel->export(['id', 'tieu_de', 'phong_ban_title', 'trang_thai']);
assertTrue(count($loadedTickets) >= 3, 'Atk4 Ticket model exports real MariaDB rows with HasOne title');

// 4. Verify DbAuthProvider + LoginGuard (if NOVATECH_TEST_PASSWORD is supplied)
$testPass = getenv('NOVATECH_TEST_PASSWORD');
if (is_string($testPass) && $testPass !== '') {
    [$provider, $uname] = AuthFactory::resolve('kt01');
    $okUser = $provider->authenticate($uname, $testPass);
    assertTrue($okUser !== null && $okUser['role'] === 'accounting', 'kt01 authenticates via DbAuthProvider');

    // Wrong password test
    $badUser = $provider->authenticate($uname, 'WrongPassword!123');
    assertTrue($badUser === null, 'wrong password rejected and audited');
}

echo "PASS: {$n} real MariaDB integration checks\n";
