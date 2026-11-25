<?php
declare(strict_types=1);

namespace NovaTech\Pages;

use Atk4\Ui\Button;
use Atk4\Ui\Card;
use Atk4\Ui\Columns;
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

final class DashboardPage
{
    public static function render(PortalAtkApp $app): void
    {
        $layout = PortalLayout::initAdmin($app, 'Tổng quan hệ thống');
        $u = SessionContext::user();
        $isIt = SessionContext::isIt();

        $deptFilterSql = $isIt ? '' : ' AND phong_ban_id = ?';
        $deptParams = $isIt ? [] : [(int) $u['phong_ban_id']];

        $openCount = (int) (Db::one("SELECT COUNT(*) AS n FROM tickets WHERE trang_thai IN ('new','received','in_progress')" . $deptFilterSql, $deptParams)['n'] ?? 0);
        $doneCount = (int) (Db::one("SELECT COUNT(*) AS n FROM tickets WHERE trang_thai IN ('completed','closed')" . $deptFilterSql, $deptParams)['n'] ?? 0);
        $assetCount = (int) (Db::one("SELECT COUNT(*) AS n FROM assets WHERE tinh_trang = 'active'")['n'] ?? 0);
        $userCount = (int) (Db::one("SELECT COUNT(*) AS n FROM users WHERE trang_thai = 'active'")['n'] ?? 0);

        // KPI Cards
        $cols = Columns::addTo($layout, ['width' => 4]);

        $c1 = Card::addTo($cols->addColumn());
        $c1->addContent(new Header(['Yêu cầu đang mở', 'size' => 4, 'icon' => 'clock outline']));
        $c1->addDescription((string) $openCount . ' ticket chờ / đang xử lý');

        $c2 = Card::addTo($cols->addColumn());
        $c2->addContent(new Header(['Đã hoàn thành / đóng', 'size' => 4, 'icon' => 'check circle outline']));
        $c2->addDescription((string) $doneCount . ' ticket đã xử lý xong');

        $c3 = Card::addTo($cols->addColumn());
        $c3->addContent(new Header(['Tài sản CNTT', 'size' => 4, 'icon' => 'server']));
        $c3->addDescription((string) $assetCount . ' máy chủ & máy trạm hoạt động');

        $c4 = Card::addTo($cols->addColumn());
        $c4->addContent(new Header(['Tài khoản nội bộ', 'size' => 4, 'icon' => 'users']));
        $c4->addDescription((string) $userCount . ' nhân sự đang hoạt động');

        View::addTo($layout, ['ui' => 'divider']);

        // Split content: Recent Tickets + Announcements
        $mainCols = Columns::addTo($layout);
        $leftCol = $mainCols->addColumn(10);
        $rightCol = $mainCols->addColumn(6);

        Header::addTo($leftCol, ['Yêu cầu hỗ trợ gần đây', 'size' => 3, 'icon' => 'ticket alternate']);
        $ticketModel = new Ticket(Db::persistence());
        if (!$isIt) {
            $ticketModel->addCondition('phong_ban_id', (int) $u['phong_ban_id']);
        }
        $ticketModel->setLimit(8);

        $grid = Grid::addTo($leftCol, ['menu' => false, 'paginator' => false]);
        $grid->setModel($ticketModel, ['tieu_de', 'phong_ban_title', 'uu_tien', 'trang_thai', 'ngay_cap_nhat']);
        $grid->addDecorator('tieu_de', [Table\Column\Link::class, ['/ticket'], ['id' => 'id']]);
        $grid->addDecorator('trang_thai', [
            Table\Column\Status::class,
            [
                'positive' => ['Hoàn thành', 'Đã đóng'],
                'info' => ['Đã tiếp nhận', 'Đang xử lý'],
                'warning' => ['Mới'],
            ],
        ]);
        Button::addTo($leftCol, ['Xem tất cả yêu cầu', 'class.basic primary' => true, 'icon' => 'list'])->link(['/tickets']);

        Header::addTo($rightCol, ['Thông báo nội bộ mới nhất', 'size' => 3, 'icon' => 'bullhorn']);
        $anns = Db::rows(
            "SELECT a.*, d.ten_phong_ban, u.ho_ten FROM announcements a LEFT JOIN departments d ON d.id = a.phong_ban_id JOIN users u ON u.id = a.tao_boi WHERE a.pham_vi = 'company' OR a.phong_ban_id = ? ORDER BY a.ngay_tao DESC, a.id DESC LIMIT 4",
            [(int) $u['phong_ban_id']]
        );
        if ($anns === []) {
            Message::addTo($rightCol, ['Chưa có thông báo mới.', 'type' => 'info']);
        } else {
            foreach ($anns as $a) {
                $scopeTag = $a['pham_vi'] === 'company' ? 'Toàn công ty' : ('Phòng ' . $a['ten_phong_ban']);
                $msg = Message::addTo($rightCol, [$a['tieu_de'] . ' [' . $scopeTag . ']', 'type' => 'info']);
                $msg->text->addParagraph((string) $a['noi_dung']);
                $msg->text->addParagraph('Đăng bởi ' . $a['ho_ten'] . ' · ' . $a['ngay_tao']);
            }
        }
    }
}
