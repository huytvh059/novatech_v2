<?php
declare(strict_types=1);

namespace NovaTech\Pages;

use Atk4\Ui\Grid;
use Atk4\Ui\Message;
use Atk4\Ui\View;
use NovaTech\Db;
use NovaTech\Model\AuditLog;
use NovaTech\Ui\PortalAtkApp;
use NovaTech\Ui\PortalLayout;

final class AuditPage
{
    public static function render(PortalAtkApp $app): void
    {
        PortalLayout::requireRole($app, ['it'], '/audit');
        $layout = PortalLayout::initAdmin($app, 'Nhật ký hoạt động hệ thống (Audit Logs)');

        Message::addTo($layout, [
            'Ghi nhận toàn bộ hoạt động đăng nhập, đồng bộ quyền AD LDAP, truy cập trái phép (403), tạo/xử lý ticket và thay đổi tài sản.',
            'type' => 'info',
            'icon' => 'shield alternate',
        ]);

        $seg = View::addTo($layout, ['ui' => 'segment']);
        $model = new AuditLog(Db::persistence());
        $grid = Grid::addTo($seg, ['ipp' => 25]);
        $grid->setModel($model, ['thoi_gian', 'username', 'hanh_dong', 'doi_tuong', 'ip_nguon', 'ket_qua']);
        $grid->addQuickSearch(['username', 'hanh_dong', 'doi_tuong', 'ip_nguon', 'ket_qua'], true);
    }
}
