<?php
declare(strict_types=1);

namespace NovaTech\Pages;

use Atk4\Data\Model;
use Atk4\Ui\Crud;
use Atk4\Ui\Message;
use Atk4\Ui\View;
use NovaTech\Db;
use NovaTech\Model\Asset;
use NovaTech\Ui\PortalAtkApp;
use NovaTech\Ui\PortalLayout;

final class AssetsPage
{
    public static function render(PortalAtkApp $app): void
    {
        PortalLayout::requireRole($app, ['it'], '/assets');
        $layout = PortalLayout::initAdmin($app, 'Quản lý Tài sản & Hạ tầng CNTT');

        Message::addTo($layout, [
            'Danh mục máy chủ & máy trạm NovaTech theo IP Plan (chỉ phòng IT được phép quản lý).',
            'type' => 'info',
            'icon' => 'server',
        ]);

        $seg = View::addTo($layout, ['ui' => 'segment']);
        $model = new Asset(Db::persistence());

        $model->onHook(Model::HOOK_AFTER_SAVE, static function (Asset $entity, bool $isUpdate): void {
            Db::audit($isUpdate ? 'asset_update' : 'asset_create', 'assets:' . $entity->getId() . ' (' . $entity->get('ten_may') . ')');
        });
        $model->onHook(Model::HOOK_AFTER_DELETE, static function (Asset $entity): void {
            Db::audit('asset_delete', 'assets:' . $entity->getId() . ' (' . $entity->get('ten_may') . ')');
        });

        $crud = Crud::addTo($seg, [
            'ipp' => 15,
            'displayFields' => ['ten_may', 'vai_tro', 'mang', 'fixed_ip', 'floating_ip', 'he_dieu_hanh', 'phong_ban', 'tinh_trang'],
            'editFields' => ['ten_may', 'vai_tro', 'mang', 'fixed_ip', 'floating_ip', 'he_dieu_hanh', 'phong_ban_id', 'tinh_trang', 'ghi_chu'],
        ]);
        $crud->setModel($model);
        $crud->addQuickSearch(['ten_may', 'vai_tro', 'mang', 'fixed_ip', 'floating_ip'], true);
    }
}
