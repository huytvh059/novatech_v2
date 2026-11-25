<?php
declare(strict_types=1);

namespace NovaTech\Pages;

use Atk4\Ui\Form;
use Atk4\Ui\Grid;
use Atk4\Ui\Header;
use Atk4\Ui\Js\Jquery;
use Atk4\Ui\Message;
use Atk4\Ui\View;
use NovaTech\Auth\SessionContext;
use NovaTech\Db;
use NovaTech\Model\Announcement;
use NovaTech\Ui\PortalAtkApp;
use NovaTech\Ui\PortalLayout;

final class AnnouncementsPage
{
    public static function render(PortalAtkApp $app): void
    {
        $layout = PortalLayout::initAdmin($app, 'Thông báo nội bộ');
        $u = SessionContext::user();
        $canManage = SessionContext::isHr() || SessionContext::isIt();

        if ($canManage) {
            $deptOptions = [];
            foreach (Db::rows('SELECT id, ten_phong_ban FROM departments ORDER BY id') as $d) {
                $deptOptions[(string) $d['id']] = $d['ten_phong_ban'];
            }

            $formSeg = View::addTo($layout, ['ui' => 'segment']);
            Header::addTo($formSeg, ['Đăng thông báo mới', 'size' => 3, 'icon' => 'bullhorn']);
            $form = Form::addTo($formSeg);
            $g = $form->addGroup(['width' => 'three']);
            $g->addControl('tieu_de', [], ['caption' => 'Tiêu đề', 'required' => true]);
            $g->addControl('pham_vi', [Form\Control\Dropdown::class, 'values' => Announcement::SCOPES], ['caption' => 'Phạm vi', 'default' => 'company']);
            $g->addControl('phong_ban_id', [Form\Control\Dropdown::class, 'values' => ['' => '— Chọn —'] + $deptOptions], ['caption' => 'Phòng ban (nếu chọn Theo phòng ban)']);
            $form->addControl('noi_dung', [Form\Control\Textarea::class, 'rows' => 3], ['caption' => 'Nội dung thông báo', 'required' => true]);
            $form->buttonSave->set('Đăng thông báo');
            $form->buttonSave->icon = 'send';

            $form->onSubmit(static function (Form $form) use ($app, $u) {
                $title = trim((string) $form->entity->get('tieu_de'));
                $scope = (string) $form->entity->get('pham_vi');
                $deptRaw = trim((string) ($form->entity->get('phong_ban_id') ?? ''));
                $body = trim((string) $form->entity->get('noi_dung'));

                if ($scope === 'department' && $deptRaw === '') {
                    return $form->jsError('phong_ban_id', 'Vui lòng chọn phòng ban nhận thông báo.');
                }
                $deptId = $scope === 'department' ? (int) $deptRaw : null;

                $pdo = Db::pdo();
                $pdo->beginTransaction();
                Db::query(
                    'INSERT INTO announcements (tieu_de, noi_dung, pham_vi, phong_ban_id, tao_boi) VALUES (?, ?, ?, ?, ?)',
                    [$title, $body, $scope, $deptId, (int) $u['id']]
                );
                $id = (int) $pdo->lastInsertId();
                Db::audit('announcement_create', 'announcements:' . $id);
                $pdo->commit();

                PortalLayout::flash('Đã đăng thông báo nội bộ.');

                return $app->jsRedirect(['/announcements']);
            });
        }

        $listSeg = View::addTo($layout, ['ui' => 'segment']);
        Header::addTo($listSeg, ['Bảng tin dành cho phòng ' . $u['ten_phong_ban'], 'size' => 3]);

        $model = new Announcement(Db::persistence());
        $model->addCondition(
            \Atk4\Data\Model\Scope::createOr(
                ['pham_vi', 'company'],
                ['phong_ban_id', (int) $u['phong_ban_id']]
            )
        );

        $grid = Grid::addTo($listSeg, ['ipp' => 15]);
        $grid->setModel($model, ['tieu_de', 'noi_dung', 'pham_vi', 'phong_ban_title', 'tao_boi_title', 'ngay_tao']);
        $grid->addQuickSearch(['tieu_de', 'noi_dung'], true);
    }
}
