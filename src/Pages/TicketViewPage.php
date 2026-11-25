<?php
declare(strict_types=1);

namespace NovaTech\Pages;

use Atk4\Ui\Button;
use Atk4\Ui\Card;
use Atk4\Ui\Columns;
use Atk4\Ui\Form;
use Atk4\Ui\Header;
use Atk4\Ui\Message;
use Atk4\Ui\View;
use NovaTech\Auth\SessionContext;
use NovaTech\Db;
use NovaTech\Model\Ticket;
use NovaTech\Ui\PortalAtkApp;
use NovaTech\Ui\PortalLayout;

final class TicketViewPage
{
    public static function render(PortalAtkApp $app): void
    {
        $idRaw = $app->tryGetRequestQueryParam('id');
        $id = filter_var($idRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            PortalLayout::abort($app, 400, 'Mã yêu cầu không hợp lệ', 'Vui lòng chọn yêu cầu hỗ trợ hợp lệ từ danh sách.');
        }

        $ticket = Db::one(
            'SELECT t.*, d.ten_phong_ban, u.ho_ten AS creator_name, p.ho_ten AS handler_name, a.ten_may, a.fixed_ip FROM tickets t JOIN departments d ON d.id = t.phong_ban_id JOIN users u ON u.id = t.nguoi_tao LEFT JOIN users p ON p.id = t.nguoi_xu_ly LEFT JOIN assets a ON a.id = t.asset_id WHERE t.id = ?',
            [$id]
        );
        if ($ticket === null) {
            PortalLayout::abort($app, 404, 'Không tìm thấy yêu cầu', 'Yêu cầu #' . $id . ' không tồn tại.');
        }

        // Bảo vệ cô lập giữa các phòng ban (Test 16)
        if (!SessionContext::canViewTicket($ticket)) {
            Db::audit('forbidden', 'tickets:' . $id, 'denied');
            PortalLayout::abort($app, 403, 'Truy cập bị từ chối (HTTP 403)', 'Bạn không có quyền xem yêu cầu hỗ trợ thuộc phòng ban khác.');
        }

        $u = SessionContext::user();
        $isIt = SessionContext::isIt();
        $codeStr = '#' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
        $layout = PortalLayout::initAdmin($app, 'Chi tiết yêu cầu ' . $codeStr);
        $app->stickyGet('id');

        Button::addTo($layout, ['Quay lại danh sách', 'icon' => 'arrow left'])->link(['/tickets']);
        View::addTo($layout, ['ui' => 'divider']);

        $cols = Columns::addTo($layout);
        $leftCol = $cols->addColumn(10);
        $rightCol = $cols->addColumn(6);

        // Left: Ticket Details + Comments
        $detailSeg = View::addTo($leftCol, ['ui' => 'segment']);
        $statusLabel = Ticket::STATUSES[$ticket['trang_thai']] ?? $ticket['trang_thai'];
        $priorityLabel = Ticket::PRIORITIES[$ticket['uu_tien']] ?? $ticket['uu_tien'];
        $catLabel = Ticket::CATEGORIES[$ticket['loai_su_co']] ?? $ticket['loai_su_co'];

        Header::addTo($detailSeg, [
            $ticket['tieu_de'],
            'size' => 3,
            'subHeader' => 'Phòng ban: ' . $ticket['ten_phong_ban'] . ' · Người gửi: ' . $ticket['creator_name'] . ' · Trạng thái: ' . $statusLabel,
        ]);

        $metaCard = Card::addTo($detailSeg, ['useTable' => true])->addClass('fluid');
        $metaModel = new Ticket(Db::persistence());
        $metaEntity = $metaModel->load($id);
        $metaCard->setEntity($metaEntity, ['loai_su_co', 'uu_tien', 'trang_thai', 'asset_title', 'nguoi_xu_ly_title', 'ngay_tao', 'ngay_cap_nhat', 'ngay_hoan_thanh', 'ngay_dong']);

        $descMsg = Message::addTo($detailSeg, ['Mô tả sự cố (' . $catLabel . ' · Ưu tiên ' . $priorityLabel . ')', 'type' => 'info']);
        $descMsg->text->addParagraph((string) $ticket['mo_ta']);

        if (!empty($ticket['ket_qua_xu_ly'])) {
            $resMsg = Message::addTo($detailSeg, ['Kết quả xử lý của phòng IT', 'type' => 'success', 'icon' => 'check']);
            $resMsg->text->addParagraph((string) $ticket['ket_qua_xu_ly']);
        }

        // Comments history
        $commSeg = View::addTo($leftCol, ['ui' => 'segment']);
        Header::addTo($commSeg, ['Lịch sử trao đổi & cập nhật', 'size' => 4, 'icon' => 'comments']);
        $comments = Db::rows(
            'SELECT c.*, u.ho_ten, u.role FROM ticket_comments c JOIN users u ON u.id = c.nguoi_gui WHERE c.ticket_id = ? ORDER BY c.ngay_gui ASC, c.id ASC',
            [$id]
        );
        if ($comments === []) {
            Message::addTo($commSeg, ['Chưa có bình luận hoặc ghi chú xử lý nào.', 'type' => 'info']);
        } else {
            foreach ($comments as $c) {
                $m = Message::addTo($commSeg, [$c['ho_ten'] . ' · ' . $c['ngay_gui']]);
                $m->text->addParagraph((string) $c['noi_dung']);
            }
        }

        if ($ticket['trang_thai'] !== 'closed') {
            $commentForm = Form::addTo($commSeg);
            $commentForm->addControl('noi_dung', [Form\Control\Textarea::class, 'rows' => 2], ['caption' => 'Thêm ghi chú / phản hồi', 'required' => true]);
            $commentForm->buttonSave->set('Gửi ghi chú');
            $commentForm->buttonSave->icon = 'comment';
            $commentForm->onSubmit(static function (Form $form) use ($app, $id, $u) {
                $body = trim((string) $form->entity->get('noi_dung'));
                if ($body === '' || mb_strlen($body) > 5000) {
                    return $form->jsError('noi_dung', 'Nội dung trao đổi từ 1 đến 5000 ký tự.');
                }
                $pdo = Db::pdo();
                $pdo->beginTransaction();
                Db::query('INSERT INTO ticket_comments (ticket_id, nguoi_gui, noi_dung) VALUES (?, ?, ?)', [$id, (int) $u['id'], $body]);
                Db::query('UPDATE tickets SET ngay_cap_nhat = NOW() WHERE id = ?', [$id]);
                Db::audit('ticket_comment', 'tickets:' . $id);
                $pdo->commit();

                PortalLayout::flash('Đã thêm ghi chú trao đổi.');

                return $app->jsRedirect(['/ticket', 'id' => $id]);
            });
        }

        // Right: Workflow Actions
        $actionSeg = View::addTo($rightCol, ['ui' => 'segment']);
        Header::addTo($actionSeg, ['Xử lý & Chuyển trạng thái', 'size' => 4, 'icon' => 'tasks']);

        if ($isIt) {
            $allowedNext = Ticket::ALLOWED_TRANSITIONS[$ticket['trang_thai']] ?? [$ticket['trang_thai']];
            $statusOptions = [];
            foreach ($allowedNext as $code) {
                $statusOptions[$code] = Ticket::STATUSES[$code];
            }

            $handlerOptions = ['' => '— Chọn kỹ thuật viên IT —'];
            foreach (Db::rows("SELECT u.id, u.ho_ten FROM users u JOIN departments d ON d.id = u.phong_ban_id WHERE (u.role = 'it' OR d.ma_phong_ban = 'IT') AND u.trang_thai = 'active' ORDER BY u.ho_ten") as $h) {
                $handlerOptions[(string) $h['id']] = $h['ho_ten'];
            }

            if (in_array($ticket['trang_thai'], ['completed', 'closed'], true)) {
                Message::addTo($actionSeg, [
                    'Yêu cầu đã ở trạng thái ' . $statusLabel . '.',
                    'type' => 'success',
                    'icon' => 'check circle',
                ]);
            } else {
                $wfForm = Form::addTo($actionSeg);
                $wfForm->addControl('nguoi_xu_ly', [
                    Form\Control\Dropdown::class,
                    'values' => $handlerOptions,
                ], [
                    'caption' => 'Phân công IT xử lý',
                    'default' => (string) ($ticket['nguoi_xu_ly'] ?? $u['id']),
                ]);
                $wfForm->addControl('trang_thai', [
                    Form\Control\Dropdown::class,
                    'values' => $statusOptions,
                ], [
                    'caption' => 'Trạng thái xử lý',
                    'default' => $ticket['trang_thai'],
                ]);
                $wfForm->addControl('ket_qua_xu_ly', [
                    Form\Control\Textarea::class,
                    'rows' => 3,
                ], [
                    'caption' => 'Kết quả xử lý / Khắc phục',
                    'default' => (string) ($ticket['ket_qua_xu_ly'] ?? ''),
                ]);
                $wfForm->buttonSave->set('Cập nhật xử lý');
                $wfForm->buttonSave->icon = 'save';

                $wfForm->onSubmit(static function (Form $form) use ($app, $id, $u) {
                    $newStatus = (string) $form->entity->get('trang_thai');
                    $handlerRaw = trim((string) ($form->entity->get('nguoi_xu_ly') ?? ''));
                    $resolution = trim((string) ($form->entity->get('ket_qua_xu_ly') ?? ''));

                    $handlerId = $handlerRaw === '' ? null : (int) $handlerRaw;
                    if ($handlerId !== null && Db::one("SELECT u.id FROM users u JOIN departments d ON d.id = u.phong_ban_id WHERE u.id = ? AND (u.role = 'it' OR d.ma_phong_ban = 'IT') AND u.trang_thai = 'active'", [$handlerId]) === null) {
                        return $form->jsError('nguoi_xu_ly', 'Người xử lý phải thuộc phòng IT đang hoạt động.');
                    }

                    $pdo = Db::pdo();
                    $pdo->beginTransaction();
                    $locked = Db::one('SELECT * FROM tickets WHERE id = ? FOR UPDATE', [$id]);
                    if ($locked === null) {
                        $pdo->rollBack();

                        return $form->jsError('trang_thai', 'Ticket không còn tồn tại.');
                    }

                    $allowed = Ticket::ALLOWED_TRANSITIONS[$locked['trang_thai']] ?? [];
                    if (!in_array($newStatus, $allowed, true)) {
                        $pdo->rollBack();

                        return $form->jsError('trang_thai', 'Luồng trạng thái không hợp lệ. Chỉ chuyển tuần tự: Mới → Đã tiếp nhận → Đang xử lý → Hoàn thành.');
                    }

                    if ($newStatus !== 'new' && $handlerId === null) {
                        $pdo->rollBack();

                        return $form->jsError('nguoi_xu_ly', 'Phải chọn kỹ thuật viên IT tiếp nhận trước khi chuyển trạng thái.');
                    }

                    if ($newStatus === 'completed' && $resolution === '') {
                        $pdo->rollBack();

                        return $form->jsError('ket_qua_xu_ly', 'Vui lòng ghi nhận kết quả sửa chữa trước khi chuyển Hoàn thành.');
                    }

                    Db::query(
                        "UPDATE tickets SET trang_thai = ?, nguoi_xu_ly = ?, ket_qua_xu_ly = ?, ngay_cap_nhat = NOW(), ngay_hoan_thanh = IF(? = 'completed', COALESCE(ngay_hoan_thanh, NOW()), ngay_hoan_thanh) WHERE id = ?",
                        [$newStatus, $handlerId, $resolution !== '' ? $resolution : null, $newStatus, $id]
                    );

                    if ($locked['trang_thai'] !== $newStatus) {
                        $msg = 'Cập nhật trạng thái: ' . (Ticket::STATUSES[$locked['trang_thai']] ?? $locked['trang_thai']) . ' → ' . (Ticket::STATUSES[$newStatus] ?? $newStatus);
                        Db::query('INSERT INTO ticket_comments (ticket_id, nguoi_gui, noi_dung) VALUES (?, ?, ?)', [$id, (int) $u['id'], $msg]);
                    }
                    Db::audit('ticket_update', 'tickets:' . $id . ' (' . $newStatus . ')');
                    $pdo->commit();

                    PortalLayout::flash('Đã cập nhật trạng thái xử lý yêu cầu.');

                    return $app->jsRedirect(['/ticket', 'id' => $id]);
                });
            }
        } else {
            // Phòng ban gửi yêu cầu: theo dõi trạng thái & xác nhận kết quả khi Hoàn thành -> Đã đóng
            if ($ticket['trang_thai'] === 'completed') {
                Message::addTo($actionSeg, [
                    'IT đã xử lý hoàn thành yêu cầu này. Vui lòng kiểm tra thực tế và xác nhận đóng yêu cầu.',
                    'type' => 'warning',
                    'icon' => 'check double',
                ]);
                $confirmForm = Form::addTo($actionSeg);
                $confirmForm->addControl('xac_nhan', [Form\Control\Checkbox::class], ['caption' => 'Tôi xác nhận sự cố đã được khắc phục hoàn toàn']);
                $confirmForm->buttonSave->set('Xác nhận kết quả & Đóng yêu cầu');
                $confirmForm->buttonSave->addClass('positive');
                $confirmForm->buttonSave->icon = 'check';

                $confirmForm->onSubmit(static function (Form $form) use ($app, $id, $u) {
                    if (!$form->entity->get('xac_nhan')) {
                        return $form->jsError('xac_nhan', 'Vui lòng tích chọn xác nhận kết quả.');
                    }

                    $pdo = Db::pdo();
                    $pdo->beginTransaction();
                    $locked = Db::one('SELECT * FROM tickets WHERE id = ? FOR UPDATE', [$id]);
                    if ($locked === null || $locked['trang_thai'] !== 'completed') {
                        $pdo->rollBack();

                        return $form->jsError('xac_nhan', 'Yêu cầu chưa ở trạng thái Hoàn thành.');
                    }
                    Db::query("UPDATE tickets SET trang_thai = 'closed', ngay_cap_nhat = NOW(), ngay_dong = NOW() WHERE id = ?", [$id]);
                    Db::query('INSERT INTO ticket_comments (ticket_id, nguoi_gui, noi_dung) VALUES (?, ?, ?)', [$id, (int) $u['id'], 'Phòng ban gửi yêu cầu đã kiểm tra và xác nhận kết quả (Đã đóng).']);
                    Db::audit('ticket_confirm_close', 'tickets:' . $id);
                    $pdo->commit();

                    PortalLayout::flash('Đã xác nhận kết quả và đóng yêu cầu.');

                    return $app->jsRedirect(['/ticket', 'id' => $id]);
                });
            } else {
                Message::addTo($actionSeg, [
                    'Trạng thái hiện tại: ' . $statusLabel . '. Khi IT hoàn thành sửa chữa, nút xác nhận kết quả sẽ hiển thị tại đây.',
                    'type' => 'info',
                    'icon' => 'info circle',
                ]);
            }
        }
    }
}
