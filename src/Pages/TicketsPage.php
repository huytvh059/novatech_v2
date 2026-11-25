<?php
declare(strict_types=1);

namespace NovaTech\Pages;

use Atk4\Ui\Button;
use Atk4\Ui\Form;
use Atk4\Ui\Grid;
use Atk4\Ui\Header;
use Atk4\Ui\Message;
use Atk4\Ui\Table;
use Atk4\Ui\View;
use NovaTech\Auth\SessionContext;
use NovaTech\Db;
use NovaTech\Model\Ticket;
use NovaTech\Ui\PortalAtkApp;
use NovaTech\Ui\PortalLayout;

final class TicketsPage
{
    public static function render(PortalAtkApp $app): void
    {
        $layout = PortalLayout::initAdmin($app, 'Trung tâm hỗ trợ kỹ thuật (IT Helpdesk)');
        $u = SessionContext::user();
        $isIt = SessionContext::isIt();

        // Form tạo yêu cầu hỗ trợ mới: Kế toán & HR gửi yêu cầu hỗ trợ đến phòng IT (theo ma trận Bai_2_NovaTech_Helpdesk.md)
        if (SessionContext::canSubmitTickets()) {
            $createSeg = View::addTo($layout, ['ui' => 'segment']);
            Header::addTo($createSeg, ['Gửi yêu cầu hỗ trợ kỹ thuật mới', 'size' => 3, 'icon' => 'plus circle']);

            $assetOptions = ['' => '— Không chọn thiết bị cụ thể —'];
            foreach (Db::rows('SELECT id, ten_may, fixed_ip FROM assets ORDER BY ten_may') as $a) {
                $assetOptions[(string) $a['id']] = $a['ten_may'] . ' (' . $a['fixed_ip'] . ')';
            }

            $form = Form::addTo($createSeg);
            $g1 = $form->addGroup(['width' => 'two']);
            $g1->addControl('tieu_de', ['placeholder' => 'Ví dụ: Không truy cập được thư mục \\\\10.10.100.12\\KeToan'], ['caption' => 'Tiêu đề sự cố', 'required' => true]);
            $g1->addControl('loai_su_co', [Form\Control\Dropdown::class, 'values' => Ticket::CATEGORIES], ['caption' => 'Phân loại sự cố', 'default' => 'network']);

            $g2 = $form->addGroup(['width' => 'two']);
            $g2->addControl('uu_tien', [Form\Control\Dropdown::class, 'values' => Ticket::PRIORITIES], ['caption' => 'Mức độ ưu tiên', 'default' => 'medium']);
            $g2->addControl('asset_id', [Form\Control\Dropdown::class, 'values' => $assetOptions], ['caption' => 'Thiết bị gặp sự cố']);

            $form->addControl('mo_ta', [Form\Control\Textarea::class, 'rows' => 3], ['caption' => 'Nội dung mô tả chi tiết', 'required' => true]);
            $form->buttonSave->set('Gửi yêu cầu hỗ trợ');
            $form->buttonSave->icon = 'paper plane';

            $form->onSubmit(static function (Form $form) use ($app, $u) {
                if (!SessionContext::canSubmitTickets()) {
                    return $form->jsError('tieu_de', 'Phòng IT là đơn vị tiếp nhận và xử lý yêu cầu.');
                }
                $title = trim((string) $form->entity->get('tieu_de'));
                $category = (string) $form->entity->get('loai_su_co');
                $priority = (string) $form->entity->get('uu_tien');
                $assetRaw = trim((string) ($form->entity->get('asset_id') ?? ''));
                $desc = trim((string) $form->entity->get('mo_ta'));

                if ($title === '' || mb_strlen($title) > 200) {
                    return $form->jsError('tieu_de', 'Tiêu đề phải từ 1 đến 200 ký tự.');
                }
                if (!isset(Ticket::CATEGORIES[$category])) {
                    return $form->jsError('loai_su_co', 'Phân loại không hợp lệ.');
                }
                if (!isset(Ticket::PRIORITIES[$priority])) {
                    return $form->jsError('uu_tien', 'Mức ưu tiên không hợp lệ.');
                }
                if ($desc === '' || mb_strlen($desc) > 10000) {
                    return $form->jsError('mo_ta', 'Mô tả chi tiết không hợp lệ.');
                }

                $assetId = null;
                if ($assetRaw !== '') {
                    $aid = (int) $assetRaw;
                    if ($aid <= 0 || Db::one('SELECT id FROM assets WHERE id = ?', [$aid]) === null) {
                        return $form->jsError('asset_id', 'Thiết bị không tồn tại.');
                    }
                    $assetId = $aid;
                }

                $pdo = Db::pdo();
                $pdo->beginTransaction();
                Db::query(
                    "INSERT INTO tickets (tieu_de, loai_su_co, mo_ta, uu_tien, trang_thai, phong_ban_id, nguoi_tao, asset_id) VALUES (?, ?, ?, ?, 'new', ?, ?, ?)",
                    [$title, $category, $desc, $priority, (int) $u['phong_ban_id'], (int) $u['id'], $assetId]
                );
                $ticketId = (int) $pdo->lastInsertId();
                Db::audit('ticket_create', 'tickets:' . $ticketId);
                $pdo->commit();

                PortalLayout::flash('Đã gửi yêu cầu hỗ trợ #' . $ticketId . ' đến phòng IT thành công.');

                return $app->jsRedirect(['/ticket', 'id' => $ticketId]);
            });
        } else {
            Message::addTo($layout, [
                'Chế độ tiếp nhận & xử lý (Phòng IT): Bạn đang xem toàn bộ yêu cầu hỗ trợ được gửi từ phòng Kế toán, Nhân sự và nội bộ.',
                'type' => 'info',
                'icon' => 'wrench',
            ]);
        }

        // Danh sách yêu cầu hỗ trợ (Cô lập theo phòng ban nếu không phải IT)
        $listSeg = View::addTo($layout, ['ui' => 'segment']);
        Header::addTo($listSeg, [
            $isIt ? 'Toàn bộ yêu cầu hỗ trợ của các phòng ban' : ('Yêu cầu hỗ trợ của phòng ' . $u['ten_phong_ban']),
            'size' => 3,
            'icon' => 'list alternate',
        ]);

        if (!$isIt) {
            Message::addTo($listSeg, [
                'Phân quyền phòng ban: Bạn chỉ xem được các yêu cầu thuộc phòng ' . $u['ten_phong_ban'] . '. Yêu cầu của phòng ban khác được bảo vệ.',
                'type' => 'info',
                'icon' => 'lock',
            ]);
        }

        $statusFilter = (string) ($app->tryGetRequestQueryParam('status') ?? '');
        $filterBar = View::addTo($listSeg, ['ui' => 'small buttons'])->setStyle(['margin-bottom' => '0.75rem']);
        Button::addTo($filterBar, ['Tất cả', 'class.primary' => $statusFilter === ''])->link(['/tickets']);
        foreach (Ticket::STATUSES as $code => $caption) {
            Button::addTo($filterBar, [$caption, 'class.primary' => $statusFilter === $code])->link(['/tickets', 'status' => $code]);
        }

        $ticketModel = new Ticket(Db::persistence());
        if (!$isIt) {
            $ticketModel->addCondition('phong_ban_id', (int) $u['phong_ban_id']);
        }
        if ($statusFilter !== '' && isset(Ticket::STATUSES[$statusFilter])) {
            $ticketModel->addCondition('trang_thai', $statusFilter);
        }

        $grid = Grid::addTo($listSeg, ['ipp' => 15]);
        $grid->setModel($ticketModel, [
            'tieu_de',
            'phong_ban_title',
            'nguoi_tao_title',
            'loai_su_co',
            'uu_tien',
            'trang_thai',
            'nguoi_xu_ly_title',
            'ngay_tao',
        ]);
        $grid->addQuickSearch(['tieu_de', 'mo_ta'], true);
        $grid->addDecorator('tieu_de', [Table\Column\Link::class, ['/ticket'], ['id' => 'id']]);
        $grid->addDecorator('trang_thai', [
            Table\Column\Status::class,
            [
                'positive' => ['Hoàn thành', 'Đã đóng'],
                'info' => ['Đã tiếp nhận', 'Đang xử lý'],
                'warning' => ['Mới'],
            ],
        ]);
    }
}
