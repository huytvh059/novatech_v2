<?php
declare(strict_types=1);

// Front controller duy nhất trong public/. Không đặt file cấu hình hoặc mã nguồn nghiệp vụ trong public/.
require __DIR__ . '/../vendor/autoload.php';

use NovaTech\Auth\SessionContext;
use NovaTech\Config;
use NovaTech\Db;
use NovaTech\Pages\AnnouncementsPage;
use NovaTech\Pages\AssetsPage;
use NovaTech\Pages\AuditPage;
use NovaTech\Pages\DashboardPage;
use NovaTech\Pages\DirectoryPage;
use NovaTech\Pages\LoginPage;
use NovaTech\Pages\PasswordPage;
use NovaTech\Pages\TicketsPage;
use NovaTech\Pages\TicketViewPage;
use NovaTech\Pages\UsersPage;
use NovaTech\Ui\PortalAtkApp;
use NovaTech\Ui\PortalLayout;

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

Config::get();
SessionContext::start();

$app = new PortalAtkApp([
    'title' => 'NovaTech IT Helpdesk',
    'callExit' => true,
]);
$app->configureLocalAssets(__DIR__ . '/../vendor/atk4/ui/public');

$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';

if ($path === '/login') {
    LoginPage::render($app);
    $app->run();
    exit;
}

$user = SessionContext::user();
if ($user === null) {
    $app->redirect(['/login']);
}

if ($path === '/logout') {
    Db::audit('logout', 'Đăng xuất');
    SessionContext::destroy();
    $app->redirect(['/login']);
}

if (!empty($user['must_change_password']) && $path !== '/password') {
    $app->redirect(['/password']);
}

match ($path) {
    '/', '/index.php' => DashboardPage::render($app),
    '/tickets' => TicketsPage::render($app),
    '/ticket' => TicketViewPage::render($app),
    '/assets' => AssetsPage::render($app),
    '/users' => UsersPage::render($app),
    '/directory' => DirectoryPage::render($app),
    '/announcements' => AnnouncementsPage::render($app),
    '/audit' => AuditPage::render($app),
    '/password' => PasswordPage::render($app),
    default => PortalLayout::abort($app, 404, 'Không tìm thấy trang (404)', 'Đường dẫn ' . $path . ' không tồn tại trên hệ thống.'),
};

$app->run();
