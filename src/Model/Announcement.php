<?php
declare(strict_types=1);

namespace NovaTech\Model;

use Atk4\Data\Model;
use Atk4\Ui\Form;

class Announcement extends Model
{
    public $table = 'announcements';
    public $caption = 'Thông báo nội bộ';
    public ?string $titleField = 'tieu_de';

    public const SCOPES = [
        'company' => 'Toàn công ty',
        'department' => 'Theo phòng ban',
    ];

    #[\Override]
    protected function init(): void
    {
        parent::init();

        $this->addField('tieu_de', ['caption' => 'Tiêu đề', 'required' => true]);
        $this->addField('noi_dung', [
            'caption' => 'Nội dung',
            'type' => 'text',
            'required' => true,
            'ui' => ['form' => [Form\Control\Textarea::class, 'rows' => 4]],
        ]);
        $this->addField('pham_vi', ['caption' => 'Phạm vi', 'required' => true, 'default' => 'company', 'values' => self::SCOPES]);
        $this->hasOne('phong_ban_id', ['model' => [Department::class], 'caption' => 'Phòng ban'])
            ->addTitle(['field' => 'phong_ban_title', 'caption' => 'Phòng ban']);
        $this->hasOne('tao_boi', ['model' => [User::class], 'caption' => 'Người đăng', 'required' => true])
            ->addTitle(['field' => 'tao_boi_title', 'caption' => 'Người đăng']);
        $this->addField('ngay_tao', ['caption' => 'Ngày đăng', 'type' => 'datetime', 'readOnly' => true]);

        $this->setOrder(['ngay_tao' => 'desc', 'id' => 'desc']);
    }
}
