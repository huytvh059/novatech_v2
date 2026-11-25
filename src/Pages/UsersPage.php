<?php
declare(strict_types=1);

namespace NovaTech\Pages;

use Atk4\Ui\Button;
use Atk4\Ui\Columns;
use Atk4\Ui\Form;
use Atk4\Ui\Grid;
use Atk4\Ui\Header;
use Atk4\Ui\Js\Jquery;
use Atk4\Ui\Message;
use Atk4\Ui\Modal;
use Atk4\Ui\Table;
use Atk4\Ui\View;
use NovaTech\Auth\SessionContext;
use NovaTech\Db;
use NovaTech\Model\User;
use NovaTech\Ui\PortalAtkApp;
use NovaTech\Ui\PortalLayout;

final class UsersPage
{
    public static function render(PortalAtkApp $app): void
    {
        PortalLayout::requireRole($app, ['it'], '/users');
        $layout = PortalLayout::initAdmin($app, 'Quản lý Tài khoản & Đồng bộ Active Directory');
        $me = SessionContext::user();

        Message::addTo($layout, [
            'Tài khoản nguồn Active Directory (auth_source = ldap) được đồng bộ tự động từ DC01 (10.10.100.11) theo objectGUID & nhóm GG_IT / GG_HR / GG_KeToan. Không chỉnh sửa hoặc đặt lại mật khẩu tài khoản AD tại đây.',
            'type' => 'info',
            'icon' => 'sitemap',
        ]);

        $deptOptions = [];
        foreach (Db::rows('SELECT id, ten_phong_ban FROM departments ORDER BY id') as $d) {
            $deptOptions[(string) $d['id']] = $d['ten_phong_ban'];
        }

        // Grid danh sách tài khoản
        $cols = Columns::addTo($layout);
        $left = $cols->addColumn(11);
        $right = $cols->addColumn(5);

        $listSeg = View::addTo($left, ['ui' => 'segment']);
        Header::addTo($listSeg, ['Danh sách tài khoản hệ thống', 'size' => 3, 'icon' => 'users']);

        $userModel = new User(Db::persistence());
        $grid = Grid::addTo($listSeg, ['ipp' => 15]);
        $grid->setModel($userModel, ['username', 'ho_ten', 'phong_ban', 'role', 'auth_source', 'trang_thai', 'ad_synced_at']);
        $grid->addQuickSearch(['username', 'ho_ten', 'email'], true);
        $grid->addDecorator('trang_thai', [
            Table\Column\Status::class,
            [
                'positive' => ['Hoạt động'],
                'negative' => ['Đã khóa'],
            ],
        ]);

        // Modal / Action khóa-mở khóa nhanh cho tài khoản DB local
        $grid->addActionButton(['Khóa / Mở', 'icon' => 'lock'], static function (Jquery $j, $id) use ($app, $me, $grid) {
            $target = Db::one('SELECT * FROM users WHERE id = ?', [(int) $id]);
            if ($target === null) {
                return new \Atk4\Ui\Js\JsToast(['message' => 'Tài khoản không tồn tại.', 'class' => 'error']);
            }
            if ($target['auth_source'] === 'ldap') {
                Db::audit('forbidden', 'ad_user_managed_externally:' . $id, 'denied');

                return new \Atk4\Ui\Js\JsToast(['message' => 'Tài khoản Active Directory được quản lý trên DC01, không sửa tại web.', 'class' => 'warning']);
            }
            if ((int) $target['id'] === (int) $me['id']) {
                return new \Atk4\Ui\Js\JsToast(['message' => 'Không được tự khóa tài khoản đang đăng nhập.', 'class' => 'error']);
            }
            $nextStatus = $target['trang_thai'] === 'active' ? 'locked' : 'active';
            if ($target['role'] === 'it' && $nextStatus === 'locked') {
                $activeAdmins = (int) (Db::one("SELECT COUNT(*) AS n FROM users WHERE role = 'it' AND trang_thai = 'active'")['n'] ?? 0);
                if ($activeAdmins <= 1) {
                    return new \Atk4\Ui\Js\JsToast(['message' => 'Phải giữ ít nhất một tài khoản IT đang hoạt động.', 'class' => 'error']);
                }
            }

            $pdo = Db::pdo();
            $pdo->beginTransaction();
            Db::query('UPDATE users SET trang_thai = ?, session_version = session_version + 1 WHERE id = ?', [$nextStatus, (int) $id]);
            Db::audit('user_status_' . $nextStatus, 'users:' . $id);
            $pdo->commit();

            return new \Atk4\Ui\Js\JsBlock([
                $grid->jsReload(),
                new \Atk4\Ui\Js\JsToast(['message' => 'Đã cập nhật trạng thái tài khoản ' . $target['username'], 'class' => 'success']),
            ]);
        });

        // Xóa tài khoản (bảo vệ tự xóa, tài khoản IT cuối cùng, và lịch sử ticket/thông báo)
        $grid->addModalAction(['Xóa', 'icon' => 'trash', 'class.red' => true], 'Xóa tài khoản', static function (View $modal, $id) use ($me, $grid) {
            $target = Db::one('SELECT u.*, d.ten_phong_ban FROM users u JOIN departments d ON d.id = u.phong_ban_id WHERE u.id = ?', [(int) $id]);
            if ($target === null) {
                Message::addTo($modal, ['Tài khoản không tồn tại.', 'type' => 'error']);

                return;
            }
            if ((int) $target['id'] === (int) $me['id']) {
                Message::addTo($modal, ['Không được tự xóa tài khoản đang đăng nhập.', 'type' => 'error']);

                return;
            }
            if ($target['role'] === 'it') {
                $activeIt = (int) (Db::one("SELECT COUNT(*) AS n FROM users WHERE role = 'it' AND trang_thai = 'active'")['n'] ?? 0);
                if ($target['trang_thai'] === 'active' && $activeIt <= 1) {
                    Message::addTo($modal, ['Phải giữ ít nhất một tài khoản phòng IT đang hoạt động.', 'type' => 'error']);

                    return;
                }
            }

            $hasTickets = (int) (Db::one('SELECT COUNT(*) AS n FROM tickets WHERE nguoi_tao = ?', [(int) $id])['n'] ?? 0);
            $hasComments = (int) (Db::one('SELECT COUNT(*) AS n FROM ticket_comments WHERE nguoi_gui = ?', [(int) $id])['n'] ?? 0);
            $hasAnns = (int) (Db::one('SELECT COUNT(*) AS n FROM announcements WHERE tao_boi = ?', [(int) $id])['n'] ?? 0);
            if ($hasTickets > 0 || $hasComments > 0 || $hasAnns > 0) {
                Message::addTo($modal, [
                    'Không thể xóa tài khoản đã có dữ liệu lịch sử (' . $hasTickets . ' ticket, ' . $hasComments . ' bình luận, ' . $hasAnns . ' thông báo). Vui lòng dùng chức năng Khóa tài khoản để thu hồi truy cập.',
                    'type' => 'warning',
                    'icon' => 'exclamation triangle',
                ]);

                return;
            }

            if ($target['auth_source'] === 'ldap') {
                Message::addTo($modal, [
                    'Lưu ý: Đây là tài khoản đồng bộ từ Active Directory (DC01). Xóa khỏi CSDL portal sẽ không xóa tài khoản trên AD; nếu người dùng vẫn bật trên AD, hệ thống sẽ tự đồng bộ lại khi họ đăng nhập.',
                    'type' => 'warning',
                    'icon' => 'sitemap',
                ]);
            }

            Message::addTo($modal, [
                'Bạn có chắc chắn muốn xóa vĩnh viễn tài khoản "' . $target['username'] . '" (' . $target['ho_ten'] . ' · ' . $target['ten_phong_ban'] . ')?',
                'type' => 'error',
                'icon' => 'trash alternate',
            ]);

            $confirmForm = Form::addTo($modal);
            $confirmForm->addControl('confirm_delete', [Form\Control\Checkbox::class], ['caption' => 'Tôi xác nhận xóa tài khoản này']);
            $confirmForm->buttonSave->set('Xác nhận xóa tài khoản');
            $confirmForm->buttonSave->addClass('negative');
            $confirmForm->buttonSave->icon = 'trash';

            $confirmForm->onSubmit(static function (Form $form) use ($id, $me, $grid) {
                if (!$form->entity->get('confirm_delete')) {
                    return $form->jsError('confirm_delete', 'Vui lòng tích chọn xác nhận xóa.');
                }

                $pdo = Db::pdo();
                $pdo->beginTransaction();
                $locked = Db::one('SELECT * FROM users WHERE id = ? FOR UPDATE', [(int) $id]);
                if ($locked === null) {
                    $pdo->rollBack();

                    return $form->jsError('confirm_delete', 'Tài khoản không còn tồn tại.');
                }
                if ((int) $locked['id'] === (int) $me['id']) {
                    $pdo->rollBack();

                    return $form->jsError('confirm_delete', 'Không được tự xóa tài khoản đang đăng nhập.');
                }

                $refCount = (int) (Db::one('SELECT (SELECT COUNT(*) FROM tickets WHERE nguoi_tao = ?) + (SELECT COUNT(*) FROM ticket_comments WHERE nguoi_gui = ?) + (SELECT COUNT(*) FROM announcements WHERE tao_boi = ?) AS c', [(int) $id, (int) $id, (int) $id])['c'] ?? 0);
                if ($refCount > 0) {
                    $pdo->rollBack();

                    return $form->jsError('confirm_delete', 'Tài khoản đã phát sinh lịch sử dữ liệu, hãy dùng Khóa tài khoản.');
                }

                Db::query('UPDATE tickets SET nguoi_xu_ly = NULL WHERE nguoi_xu_ly = ?', [(int) $id]);
                Db::query('UPDATE audit_logs SET user_id = NULL WHERE user_id = ?', [(int) $id]);
                Db::query('DELETE FROM users WHERE id = ?', [(int) $id]);
                Db::audit('user_delete', 'users:' . $id . ' (' . $locked['username'] . ')');
                $pdo->commit();

                return new \Atk4\Ui\Js\JsBlock([
                    $grid->jsReload(),
                    $form->jsSuccess('Đã xóa tài khoản ' . $locked['username'] . '.'),
                ]);
            });
        });

        // Right: Form tạo tài khoản DB cục bộ mới
        $createSeg = View::addTo($right, ['ui' => 'segment']);
        Header::addTo($createSeg, ['Tạo tài khoản DB cục bộ', 'size' => 4, 'icon' => 'user plus']);
        $form = Form::addTo($createSeg);
        $form->addControl('username', [], ['caption' => 'Tên đăng nhập', 'required' => true]);
        $form->addControl('ho_ten', [], ['caption' => 'Họ và tên', 'required' => true]);
        $form->addControl('email', [], ['caption' => 'Email', 'required' => true]);
        $form->addControl('so_may_le', [], ['caption' => 'Số máy lẻ']);
        $form->addControl('phong_ban_id', [Form\Control\Dropdown::class, 'values' => $deptOptions], ['caption' => 'Phòng ban', 'required' => true]);
        $form->addControl('role', [Form\Control\Dropdown::class, 'values' => User::ROLES], ['caption' => 'Vai trò', 'default' => 'employee', 'required' => true]);
        $form->addControl('password', [Form\Control\Password::class], ['caption' => 'Mật khẩu tạm (12–72 ký tự)', 'required' => true]);
        $form->buttonSave->set('Tạo tài khoản');
        $form->buttonSave->icon = 'save';

        $form->onSubmit(static function (Form $form) use ($app) {
            $username = strtolower(trim((string) $form->entity->get('username')));
            $name = trim((string) $form->entity->get('ho_ten'));
            $email = trim((string) $form->entity->get('email'));
            $ext = trim((string) ($form->entity->get('so_may_le') ?? ''));
            $deptId = (int) $form->entity->get('phong_ban_id');
            $role = (string) $form->entity->get('role');
            $pass = (string) $form->entity->get('password');

            if (!preg_match('/\A[a-z0-9._-]{1,64}\z/', $username)) {
                return $form->jsError('username', 'Tên đăng nhập chỉ gồm chữ thường, số, dấu chấm, gạch ngang, gạch dưới.');
            }
            if (Db::one('SELECT id FROM users WHERE username = ?', [$username]) !== null) {
                return $form->jsError('username', 'Tên đăng nhập đã tồn tại.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $form->jsError('email', 'Email không hợp lệ.');
            }
            if (!isset(User::ROLES[$role])) {
                return $form->jsError('role', 'Vai trò không hợp lệ.');
            }
            if (strlen($pass) < 12 || strlen($pass) > 72) {
                return $form->jsError('password', 'Mật khẩu phải dài 12–72 byte.');
            }

            $pdo = Db::pdo();
            $pdo->beginTransaction();
            Db::query(
                "INSERT INTO users (username, password_hash, ho_ten, email, so_may_le, phong_ban_id, role, must_change_password, auth_source) VALUES (?, ?, ?, ?, ?, ?, ?, 1, 'db')",
                [$username, password_hash($pass, PASSWORD_BCRYPT), $name, $email, $ext, $deptId, $role]
            );
            $newId = (int) $pdo->lastInsertId();
            Db::audit('user_create', 'users:' . $newId . ' (' . $username . ')');
            $pdo->commit();

            PortalLayout::flash('Đã tạo tài khoản cục bộ ' . $username . '.');

            return $app->jsRedirect(['/users']);
        });
    }
}
