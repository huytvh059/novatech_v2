<?php
declare(strict_types=1);

namespace NovaTech\Model;

use Atk4\Data\Model;

class TicketComment extends Model
{
    public $table = 'ticket_comments';
    public $caption = 'Trao đổi & Ghi chú xử lý';

    #[\Override]
    protected function init(): void
    {
        parent::init();

        $this->hasOne('ticket_id', ['model' => [Ticket::class], 'required' => true]);
        $this->hasOne('nguoi_gui', ['model' => [User::class], 'required' => true])
            ->addTitle(['caption' => 'Người gửi']);
        $this->addField('noi_dung', ['caption' => 'Nội dung', 'type' => 'text', 'required' => true]);
        $this->addField('ngay_gui', ['caption' => 'Thời gian', 'type' => 'datetime', 'readOnly' => true]);

        $this->setOrder(['ngay_gui' => 'asc', 'id' => 'asc']);
    }
}
