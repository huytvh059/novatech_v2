<?php
declare(strict_types=1);

namespace NovaTech\Pages;

use Atk4\Ui\Form;
use Atk4\Ui\Header;
use Atk4\Ui\Message;
use Atk4\Ui\View;
use NovaTech\Auth\SessionContext;
use NovaTech\Db;
use NovaTech\Ui\PortalAtkApp;
use NovaTech\Ui\PortalLayout;

final class PasswordPage
{
    public static function render(PortalAtkApp $app): void
    {
        $layout = PortalLayout::initAdmin($app, 'Đổi mật khẩu & Bảo mật tài khoản');
        $u = SessionContext::user();

        if (($u['auth_source'] ?? 'db') === 'ldap') {
            Message::addTo($layout, [
                'Tài khoản Active Directory (DC01)',
                'type' => 'warning',
                'icon' => 'sitemap',
            ])->text->addParagraph(
                'Tài khoản của bạn được xác thực trực tiếp qua Active Directory LDAP. Vui lòng đổi mật khẩu trên máy Windows thuộc domain (Ctrl+Alt+Delete → Change a password) hoặc liên hệ quản trị viên DC01.'
            );

            return;
        }

        if (!empty($u['must_change_password'])) {
            Message::addTo($layout, [
                'Yêu cầu đổi mật khẩu lần đầu',
                'type' => 'warning',
                'icon' => 'exclamation triangle',
            ])->text->addParagraph('Bạn đang sử dụng mật khẩu tạm. Vui lòng đổi sang mật khẩu mới (12–72 ký tự) trước khi tiếp tục sử dụng hệ thống.');
        }

        $seg = View::addTo($layout, ['ui' => 'segment']);
        Header::addTo($seg, ['Đổi mật khẩu tài khoản nội bộ', 'size' => 3, 'icon' => 'key']);

        $form = Form::addTo($seg);
        $form->addControl('current_password', [Form\Control\Password::class], ['caption' => 'Mật khẩu hiện tại', 'required' => true]);
        $form->addControl('new_password', [Form\Control\Password::class], ['caption' => 'Mật khẩu mới (12–72 ký tự)', 'required' => true]);
        $form->addControl('confirm_password', [Form\Control\Password::class], ['caption' => 'Xác nhận mật khẩu mới', 'required' => true]);
        $form->buttonSave->set('Cập nhật mật khẩu');
        $form->buttonSave->icon = 'save';

        $form->onSubmit(static function (Form $form) use ($app, $u) {
            $old = (string) $form->entity->get('current_password');
            $new = (string) $form->entity->get('new_password');
            $confirm = (string) $form->entity->get('confirm_password');

            if (!password_verify($old, (string) $u['password_hash'])) {
                return $form->jsError('current_password', 'Mật khẩu hiện tại không đúng.');
            }
            if (strlen($new) < 12 || strlen($new) > 72) {
                return $form->jsError('new_password', 'Mật khẩu mới phải dài từ 12 đến 72 byte.');
            }
            if (!hash_equals($new, $confirm)) {
                return $form->jsError('confirm_password', 'Xác nhận mật khẩu không khớp.');
            }
            if (password_verify($new, (string) $u['password_hash'])) {
                return $form->jsError('new_password', 'Mật khẩu mới phải khác mật khẩu hiện tại.');
            }

            $pdo = Db::pdo();
            $pdo->beginTransaction();
            Db::query(
                'UPDATE users SET password_hash = ?, must_change_password = 0, session_version = session_version + 1 WHERE id = ?',
                [password_hash($new, PASSWORD_BCRYPT), (int) $u['id']]
            );
            Db::audit('password_change', 'users:' . $u['id']);
            $pdo->commit();

            $_SESSION['version'] = (int) $u['session_version'] + 1;
            session_regenerate_id(true);
            PortalLayout::flash('Đã đổi mật khẩu thành công.');

            return $app->jsRedirect(['/']);
        });
    }
}
