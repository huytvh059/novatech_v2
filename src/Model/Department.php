<?php
declare(strict_types=1);

namespace NovaTech\Model;

use Atk4\Data\Model;

class Department extends Model
{
    public $table = 'departments';
    public $caption = 'Phòng ban';
    public ?string $titleField = 'ten_phong_ban';

    #[\Override]
    protected function init(): void
    {
        parent::init();

        $this->addField('ma_phong_ban', ['caption' => 'Mã phòng ban', 'required' => true]);
        $this->addField('ten_phong_ban', ['caption' => 'Tên phòng ban', 'required' => true]);
        $this->addField('nhom_ad', ['caption' => 'Nhóm AD', 'required' => true]);
    }
}
