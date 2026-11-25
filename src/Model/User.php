<?php
declare(strict_types=1);

namespace NovaTech\Model;

use Atk4\Data\Model;

class User extends Model
{
    public $table = 'users';
    public $caption = 'Tài khoản';
    public ?string $titleField = 'ho_ten';

    public const ROLES = [
        'accounting' => 'Kế toán',
        'hr' => 'Nhân sự',
        'it' => 'Phòng IT',
        'employee' => 'Nhân viên',
    ];

    public const STATUSES = [
        'active' => 'Hoạt động',
        'locked' => 'Đã khóa',
    ];

    public const AUTH_SOURCES = [
        'db' => 'MariaDB Local',
        'ldap' => 'Active Directory LDAP',
    ];

    #[\Override]
    protected function init(): void
    {
        parent::init();

        $this->addField('username', ['caption' => 'Tên đăng nhập', 'required' => true]);
        $this->addField('password_hash', ['system' => true, 'neverSave' => false]);
        $this->addField('ho_ten', ['caption' => 'Họ và tên', 'required' => true]);
        $this->addField('email', ['caption' => 'Email', 'default' => '']);
        $this->addField('so_may_le', ['caption' => 'Số máy lẻ', 'default' => '']);

        $this->hasOne('phong_ban_id', ['model' => [Department::class], 'caption' => 'Phòng ban', 'required' => true])
            ->addTitle(['caption' => 'Phòng ban']);

        $this->addField('role', ['caption' => 'Vai trò', 'required' => true, 'default' => 'employee', 'values' => self::ROLES]);
        $this->addField('trang_thai', ['caption' => 'Trạng thái', 'required' => true, 'default' => 'active', 'values' => self::STATUSES]);
        $this->addField('auth_source', ['caption' => 'Nguồn xác thực', 'readOnly' => true, 'default' => 'db', 'values' => self::AUTH_SOURCES]);
        $this->addField('ad_guid', ['caption' => 'AD objectGUID', 'readOnly' => true, 'nullable' => true]);
        $this->addField('ad_synced_at', ['caption' => 'Đồng bộ AD lúc', 'type' => 'datetime', 'readOnly' => true, 'nullable' => true]);
        $this->addField('must_change_password', ['caption' => 'Đổi MK lần đầu', 'type' => 'boolean', 'system' => true, 'default' => true]);
        $this->addField('session_version', ['type' => 'integer', 'system' => true, 'default' => 1]);
        $this->addField('ngay_tao', ['caption' => 'Ngày tạo', 'type' => 'datetime', 'readOnly' => true]);

        $this->setOrder('id');
    }
}
