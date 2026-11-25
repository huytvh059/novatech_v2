<?php
declare(strict_types=1);

namespace NovaTech\Pages;

use Atk4\Data\Model\Scope;
use Atk4\Ui\Grid;
use Atk4\Ui\Message;
use Atk4\Ui\View;
use NovaTech\Auth\SessionContext;
use NovaTech\Db;
use NovaTech\Model\User;
use NovaTech\Ui\PortalAtkApp;
use NovaTech\Ui\PortalLayout;

final class DirectoryPage
{
    public static function render(PortalAtkApp $app): void
    {
        $layout = PortalLayout::initAdmin($app, 'Danh bạ liên hệ');
        $isIt = SessionContext::isIt();

        Message::addTo($layout, [
            $isIt
                ? 'Phòng IT xem toàn bộ danh bạ nhân sự đang hoạt động trong các phòng ban để phối hợp hỗ trợ kỹ thuật.'
                : 'Danh bạ chỉ hiển thị thông tin liên lạc của phòng IT để hỗ trợ sửa chữa kỹ thuật. Thông tin liên hệ giữa phòng Kế toán và Nhân sự được bảo mật riêng.',
            'type' => 'info',
            'icon' => $isIt ? 'address book' : 'wrench',
        ]);

        $seg = View::addTo($layout, ['ui' => 'segment']);
        $model = new User(Db::persistence());
        $model->addCondition('trang_thai', 'active');

        if (!$isIt) {
            // Kế toán & HR chỉ được thấy thông tin liên lạc của phòng IT
            $itDept = Db::one("SELECT id FROM departments WHERE ma_phong_ban = 'IT'");
            $itDeptId = $itDept !== null ? (int) $itDept['id'] : -1;
            $model->addCondition(Scope::createOr(
                ['phong_ban_id', $itDeptId],
                ['role', 'it']
            ));
        }

        $grid = Grid::addTo($seg, ['ipp' => 15]);
        $grid->setModel($model, ['ho_ten', 'phong_ban', 'email', 'so_may_le', 'auth_source']);
        $grid->addQuickSearch(['ho_ten', 'email', 'so_may_le', 'username'], true);
    }
}
