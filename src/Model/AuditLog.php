<?php
declare(strict_types=1);

namespace NovaTech\Model;

use Atk4\Data\Model;

class AuditLog extends Model
{
    public $table = 'audit_logs';
    public $caption = 'Nhật ký hoạt động hệ thống';
    public bool $readOnly = true;

    #[\Override]
    protected function init(): void
    {
        parent::init();

        $this->addField('user_id', ['caption' => 'User ID', 'type' => 'integer']);
        $this->addField('username', ['caption' => 'Tài khoản']);
        $this->addField('hanh_dong', ['caption' => 'Hành động']);
        $this->addField('doi_tuong', ['caption' => 'Đối tượng']);
        $this->addField('ip_nguon', ['caption' => 'IP nguồn']);
        $this->addField('ket_qua', ['caption' => 'Kết quả']);
        $this->addField('thoi_gian', ['caption' => 'Thời gian', 'type' => 'datetime']);

        $this->setOrder(['id' => 'desc']);
    }
}
