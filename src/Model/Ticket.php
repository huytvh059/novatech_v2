<?php
declare(strict_types=1);

namespace NovaTech\Model;

use Atk4\Data\Model;
use Atk4\Ui\Form;

class Ticket extends Model
{
    public $table = 'tickets';
    public $caption = 'Yêu cầu hỗ trợ kỹ thuật';
    public ?string $titleField = 'tieu_de';

    public const CATEGORIES = [
        'hardware' => 'Phần cứng / Thiết bị',
        'software' => 'Phần mềm',
        'network' => 'Kết nối mạng',
        'internal_service' => 'Dịch vụ nội bộ (AD/File/Web)',
        'other' => 'Khác',
    ];

    public const PRIORITIES = [
        'low' => 'Thấp',
        'medium' => 'Trung bình',
        'high' => 'Cao',
    ];

    public const STATUSES = [
        'new' => 'Mới',
        'received' => 'Đã tiếp nhận',
        'in_progress' => 'Đang xử lý',
        'completed' => 'Hoàn thành',
        'closed' => 'Đã đóng',
    ];

    public const ALLOWED_TRANSITIONS = [
        'new' => ['new', 'received', 'in_progress'],
        'received' => ['received', 'in_progress'],
        'in_progress' => ['in_progress', 'completed'],
        'completed' => ['completed'],
        'closed' => ['closed'],
    ];

    #[\Override]
    protected function init(): void
    {
        parent::init();

        $this->addField('tieu_de', ['caption' => 'Tiêu đề sự cố', 'required' => true]);
        $this->addField('loai_su_co', ['caption' => 'Phân loại sự cố', 'required' => true, 'default' => 'other', 'values' => self::CATEGORIES]);
        $this->addField('mo_ta', [
            'caption' => 'Mô tả chi tiết',
            'type' => 'text',
            'required' => true,
            'ui' => ['form' => [Form\Control\Textarea::class, 'rows' => 4]],
        ]);
        $this->addField('uu_tien', ['caption' => 'Mức độ ưu tiên', 'required' => true, 'default' => 'medium', 'values' => self::PRIORITIES]);
        $this->addField('trang_thai', ['caption' => 'Trạng thái', 'required' => true, 'default' => 'new', 'values' => self::STATUSES]);
        $this->addField('ket_qua_xu_ly', [
            'caption' => 'Kết quả xử lý',
            'type' => 'text',
            'nullable' => true,
            'ui' => ['form' => [Form\Control\Textarea::class, 'rows' => 3]],
        ]);

        $this->hasOne('phong_ban_id', ['model' => [Department::class], 'caption' => 'Phòng ban', 'required' => true])
            ->addTitle(['field' => 'phong_ban_title', 'caption' => 'Phòng ban']);
        $this->hasOne('nguoi_tao', ['model' => [User::class], 'caption' => 'Người gửi', 'required' => true])
            ->addTitle(['field' => 'nguoi_tao_title', 'caption' => 'Người gửi']);
        $this->hasOne('nguoi_xu_ly', ['model' => [User::class], 'caption' => 'IT xử lý'])
            ->addTitle(['field' => 'nguoi_xu_ly_title', 'caption' => 'IT xử lý']);
        $this->hasOne('asset_id', ['model' => [Asset::class], 'caption' => 'Thiết bị liên quan'])
            ->addTitle(['field' => 'asset_title', 'caption' => 'Thiết bị']);

        $this->addField('ngay_tao', ['caption' => 'Thời gian gửi', 'type' => 'datetime', 'readOnly' => true]);
        $this->addField('ngay_cap_nhat', ['caption' => 'Cập nhật lần cuối', 'type' => 'datetime', 'readOnly' => true]);
        $this->addField('ngay_hoan_thanh', ['caption' => 'Hoàn thành lúc', 'type' => 'datetime', 'readOnly' => true, 'nullable' => true]);
        $this->addField('ngay_dong', ['caption' => 'Đã đóng lúc', 'type' => 'datetime', 'readOnly' => true, 'nullable' => true]);

        $this->setOrder(['ngay_cap_nhat' => 'desc', 'id' => 'desc']);
    }
}
