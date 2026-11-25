<?php
declare(strict_types=1);

// Kiểm thử đơn vị không cần kết nối DB/DC: kiểm tra model definitions, RBAC logic, state machine, và cấu hình.
require __DIR__ . '/../vendor/autoload.php';

use NovaTech\Auth\SessionContext;
use NovaTech\Model\Ticket;
use NovaTech\Model\User;
use NovaTech\Ui\PortalAtkApp;

$count = 0;
function check(bool $cond, string $name): void
{
    global $count;
    if (!$cond) {
        throw new RuntimeException('FAIL: ' . $name);
    }
    $count++;
}

// 1. Ticket 5-state workflow (Bai_2_NovaTech_Helpdesk.md)
check(array_keys(Ticket::STATUSES) === ['new', 'received', 'in_progress', 'completed', 'closed'], 'ticket 5 states');
check(in_array('received', Ticket::ALLOWED_TRANSITIONS['new'], true), 'new -> received allowed');
check(in_array('in_progress', Ticket::ALLOWED_TRANSITIONS['new'], true), 'new -> in_progress allowed');
check(!in_array('completed', Ticket::ALLOWED_TRANSITIONS['new'], true), 'new cannot jump directly to completed');
check(!in_array('closed', Ticket::ALLOWED_TRANSITIONS['in_progress'], true), 'in_progress cannot jump to closed');
check(in_array('completed', Ticket::ALLOWED_TRANSITIONS['in_progress'], true), 'in_progress -> completed allowed');

// 2. Roles match AD + Bai_2 matrix (3 main roles + employee fallback, no itadmin)
check(array_keys(User::ROLES) === ['accounting', 'hr', 'it', 'employee'], 'user roles');

// 3. PortalAtkApp clean URL builder (no .php suffix)
$app = new PortalAtkApp([
    'catchExceptions' => false,
    'alwaysRun' => false,
    'request' => new \Nyholm\Psr7\ServerRequest('GET', 'http://localhost/'),
]);
$app->configureLocalAssets(__DIR__ . '/../vendor/atk4/ui/public');
check($app->url(['/tickets']) === '/tickets', 'clean url /tickets');
check($app->url(['/ticket', 'id' => 5]) === '/ticket?id=5', 'clean url /ticket?id=5');
check(str_starts_with($app->cdn['atk'], '/atk-assets'), 'local atk4/ui assets path');
check(is_file(__DIR__ . '/../vendor/atk4/ui/public/js/atkjs-ui.min.js'), 'bundled atkjs-ui.min.js exists');
check(is_file(__DIR__ . '/../vendor/atk4/ui/public/external/fomantic-ui/dist/semantic.min.css'), 'bundled fomantic-ui semantic.min.css exists');

// 4. Front controller & router security checks
$indexSrc = file_get_contents(__DIR__ . '/../public/index.php');
check(is_string($indexSrc) && str_contains($indexSrc, 'X-Frame-Options: DENY'), 'clickjacking header');
check(!str_contains($indexSrc, '$_GET[\'action\']'), 'no GET mutation');

// 5. No user logged in => cannot view ticket
check(SessionContext::user() === null, 'initial session null');
check(!SessionContext::canViewTicket(['phong_ban_id' => 1]), 'guest cannot view ticket');

echo "PASS: {$count} self-checks\n";
