<?php
declare(strict_types=1);

namespace NovaTech\Ui;

use Atk4\Ui\Button;
use Atk4\Ui\Header;
use Atk4\Ui\Layout;
use Atk4\Ui\Menu;
use Atk4\Ui\Message;
use Atk4\Ui\View;
use NovaTech\Auth\SessionContext;
use NovaTech\Config;
use NovaTech\Db;
use NovaTech\Model\User;

/**
 * Builds the shared Admin layout (left sidebar, top bar, role badges) and RBAC helpers.
 */
final class PortalLayout
{
    public static function initAdmin(PortalAtkApp $app, string $pageTitle): Layout\Admin
    {
        $app->title = $pageTitle . ' — NovaTech IT Helpdesk';
        $app->initLayout([Layout\Admin::class]);
        /** @var Layout\Admin $layout */
        $layout = $app->layout;

        $u = SessionContext::user();
        $roleLabel = User::ROLES[$u['role'] ?? 'employee'] ?? ($u['role'] ?? '');
        $deptLabel = (string) ($u['ten_phong_ban'] ?? '');
        $authProvider = (string) (Config::get()['auth']['provider'] ?? 'db');
        $authLabel = ($u['auth_source'] ?? 'db') === 'ldap' ? 'AD LDAP' : 'DB Local';

        // Left navigation menu tailored by role
        $left = $layout->menuLeft;
        $left->addItem(['Tổng quan', 'icon' => 'home'], ['/']);
        $left->addItem(['Trung tâm hỗ trợ (Helpdesk)', 'icon' => 'life ring'], ['/tickets']);
        $left->addItem(['Thông báo nội bộ', 'icon' => 'bullhorn'], ['/announcements']);
        $left->addItem(['Danh bạ nhân viên', 'icon' => 'address book'], ['/directory']);

        if (SessionContext::isIt()) {
            $itGroup = $layout->addMenuGroup(['Quản trị IT', 'icon' => 'server']);
            $layout->addMenuItem(['Tài sản & Hạ tầng VM', 'icon' => 'hdd'], ['/assets'], $itGroup);
            $layout->addMenuItem(['Quản lý tài khoản & AD Sync', 'icon' => 'users'], ['/users'], $itGroup);
            $layout->addMenuItem(['Nhật ký hoạt động (Audit)', 'icon' => 'shield alternate'], ['/audit'], $itGroup);
        }

        $left->addItem(['Đổi mật khẩu / Bảo mật', 'icon' => 'key'], ['/password']);

        // Top right menu
        $right = $layout->menuRight;
        $right->addItem([
            ($authProvider === 'ldap' ? 'Mode: AD LDAP' : 'Mode: DB Local'),
            'icon' => $authProvider === 'ldap' ? 'sitemap' : 'database',
            'class' => [$authProvider === 'ldap' ? 'teal' : 'blue'],
        ]);

        $userMenu = $right->addMenu([
            ($u['ho_ten'] ?? $u['username'] ?? '') . ' (' . $deptLabel . ' · ' . $roleLabel . ')',
            'icon' => 'user circle',
        ]);
        $userMenu->addItem(['Nguồn: ' . $authLabel, 'icon' => 'id badge']);
        $userMenu->addItem(['Đổi mật khẩu', 'icon' => 'key'], ['/password']);
        $userMenu->addDivider();
        $logoutItem = $userMenu->addItem(['Đăng xuất', 'icon' => 'sign out alternate']);
        $logoutItem->on('click', $app->jsRedirect(['/logout']));

        $layout->template->trySet(
            'Footer',
            'NovaTech Enterprise Portal · Agile Toolkit UI (atk4/ui) · Web_Server (10.10.190.11) ↔ DB_Server (10.10.100.13) · DC01 (10.10.100.11)'
        );

        // Page header bar
        $headerSeg = View::addTo($layout, ['ui' => 'basic clearing segment'])->setStyle(['padding' => '0', 'margin-bottom' => '1rem']);
        Header::addTo($headerSeg, [$pageTitle, 'size' => 2, 'subHeader' => 'Phòng ban: ' . $deptLabel . ' · Vai trò: ' . $roleLabel . ' · Xác thực: ' . $authLabel, 'class.left floated' => true]);
        if (SessionContext::canSubmitTickets()) {
            Button::addTo($headerSeg, ['+ Gửi yêu cầu hỗ trợ', 'class.primary right floated' => true, 'icon' => 'plus'])
                ->link(['/tickets', 'new' => 1]);
        }

        if (!empty($_SESSION['_flash_ok'])) {
            Message::addTo($layout, [(string) $_SESSION['_flash_ok'], 'type' => 'success', 'icon' => 'check circle']);
            unset($_SESSION['_flash_ok']);
        }

        return $layout;
    }

    public static function flash(string $message): void
    {
        $_SESSION['_flash_ok'] = $message;
    }

    /**
     * @param list<string> $allowedRoles
     */
    public static function requireRole(PortalAtkApp $app, array $allowedRoles, string $resource): void
    {
        $u = SessionContext::user();
        $allowed = $u !== null && (
            in_array($u['role'], $allowedRoles, true)
            || (in_array('it', $allowedRoles, true) && SessionContext::isIt())
        );
        if (!$allowed) {
            Db::audit('forbidden', $resource, 'denied');
            self::abort($app, 403, 'Truy cập bị từ chối (HTTP 403)', 'Tài khoản thuộc phòng ' . ($u['ten_phong_ban'] ?? 'hiện tại') . ' không có quyền truy cập chức năng quản trị này.');
        }
    }

    public static function abort(PortalAtkApp $app, int $statusCode, string $title, string $message): never
    {
        $app->setResponseStatusCode($statusCode);
        if (SessionContext::user() !== null) {
            $layout = self::initAdmin($app, $title);
            Message::addTo($layout, [$title, 'type' => 'error', 'icon' => 'ban'])
                ->text->addParagraph($message);
            Button::addTo($layout, ['Quay về Tổng quan', 'icon' => 'arrow left'])->link(['/']);
        } else {
            $app->initLayout([Layout\Centered::class]);
            /** @var Layout\Centered $layout */
            $layout = $app->layout;
            $layout->image = null;
            Message::addTo($layout, [$title, 'type' => 'error', 'icon' => 'ban'])
                ->text->addParagraph($message);
            Button::addTo($layout, ['Đăng nhập', 'icon' => 'sign in'])->link(['/login']);
        }
        $app->run();
        $app->callExit();
    }
}
