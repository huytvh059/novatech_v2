<?php
declare(strict_types=1);

namespace NovaTech\Pages;

use Atk4\Ui\Form;
use Atk4\Ui\Header;
use Atk4\Ui\Layout;
use Atk4\Ui\Message;
use Atk4\Ui\View;
use InvalidArgumentException;
use NovaTech\Auth\AuthFactory;
use NovaTech\Auth\SessionContext;
use NovaTech\Config;
use NovaTech\Ui\PortalAtkApp;
use Throwable;

final class LoginPage
{
    public static function render(PortalAtkApp $app): void
    {
        if (SessionContext::user() !== null) {
            $app->redirect(['/']);
        }

        $app->title = 'Đăng nhập — NovaTech IT Helpdesk';
        $app->initLayout([Layout\Centered::class]);
        /** @var Layout\Centered $layout */
        $layout = $app->layout;
        $layout->image = null;

        $authCfg = Config::get()['auth'] ?? ['provider' => 'db'];
        $providerMode = (string) ($authCfg['provider'] ?? 'db');

        Header::addTo($layout, [
            'NovaTech Enterprise Portal',
            'size' => 2,
            'icon' => 'shield alternate',
            'subHeader' => 'Hệ thống tiếp nhận & xử lý sự cố kỹ thuật nội bộ (OpenStack)',
        ]);

        if ($providerMode === 'ldap') {
            Message::addTo($layout, [
                'Chế độ xác thực: Active Directory LDAP (DC01)',
                'type' => 'info',
                'icon' => 'sitemap',
            ])->text->addParagraph('Đăng nhập bằng username, NOVATECH\\username hoặc user@ad.novatech.test. Vai trò và phòng ban được đồng bộ tự động từ nhóm AD (GG_IT, GG_HR, GG_KeToan).');
        } else {
            Message::addTo($layout, [
                'Chế độ xác thực: MariaDB Local (DB_Server)',
                'type' => 'info',
                'icon' => 'database',
            ])->text->addParagraph('Nhập tài khoản nội bộ được cấp trên DB_Server. Hỗ trợ chuyển đổi sang Active Directory LDAP qua cấu hình.');
        }

        $seg = View::addTo($layout, ['ui' => 'segment']);
        $form = Form::addTo($seg);
        $form->addControl('username', [
            'placeholder' => $providerMode === 'ldap' ? 'vd: kt01 hoặc NOVATECH\\kt01' : 'vd: kt01, hr01, it01',
        ], [
            'caption' => 'Tên đăng nhập',
            'required' => true,
        ]);
        $form->addControl('password', [
            Form\Control\Password::class,
        ], [
            'caption' => 'Mật khẩu',
            'required' => true,
        ]);
        $form->buttonSave->set('Đăng nhập hệ thống');
        $form->buttonSave->icon = 'sign in alternate';

        $form->onSubmit(static function (Form $form) use ($app) {
            $input = (string) $form->entity->get('username');
            $password = (string) $form->entity->get('password');

            if (strlen($password) > 1024 || str_contains($password, "\0")) {
                return $form->jsError('password', 'Mật khẩu không hợp lệ.');
            }

            try {
                [$provider, $username] = AuthFactory::resolve($input);
                $user = $provider->authenticate($username, $password);
            } catch (InvalidArgumentException $ex) {
                return $form->jsError('username', $ex->getMessage());
            } catch (Throwable $ex) {
                error_log('Login error: ' . $ex->getMessage());

                return $form->jsError('username', 'Dịch vụ xác thực tạm gián đoạn hoặc cấu hình LDAP chưa kết nối được DC01.');
            }

            if ($user === null) {
                return $form->jsError('password', 'Không thể đăng nhập. Kiểm tra thông tin hoặc thử lại sau 5 phút.');
            }

            SessionContext::establishLogin($user);

            return $app->jsRedirect(!empty($user['must_change_password']) ? ['/password'] : ['/']);
        });
    }
}
