<?php
declare(strict_types=1);

namespace NovaTech\Model;

use Atk4\Data\Model;

class Asset extends Model
{
    public $table = 'assets';
    public $caption = 'Tài sản & Hạ tầng CNTT';
    public ?string $titleField = 'ten_may';

    public const NETWORKS = [
        'Net_LAN (SN_Servers)' => 'Net_LAN (SN_Servers)',
        'Net_DMZ' => 'Net_DMZ',
        'Net_LAN (SN_IT)' => 'Net_LAN (SN_IT)',
        'Net_LAN (SN_HR)' => 'Net_LAN (SN_HR)',
        'Net_LAN (SN_KeToan)' => 'Net_LAN (SN_KeToan)',
    ];

    public const STATUSES = [
        'active' => 'Hoạt động',
        'maintenance' => 'Bảo trì',
        'inactive' => 'Ngừng hoạt động',
    ];

    #[\Override]
    protected function init(): void
    {
        parent::init();

        $this->addField('ten_may', ['caption' => 'Tên máy', 'required' => true]);
        $this->addField('vai_tro', ['caption' => 'Vai trò', 'required' => true]);
        $this->addField('mang', ['caption' => 'Phân đoạn mạng', 'required' => true, 'values' => self::NETWORKS]);
        $this->addField('fixed_ip', ['caption' => 'Fixed IP', 'required' => true]);
        $this->addField('floating_ip', ['caption' => 'Floating IP', 'nullable' => true]);
        $this->addField('he_dieu_hanh', ['caption' => 'Hệ điều hành', 'required' => true]);
        $this->hasOne('phong_ban_id', ['model' => [Department::class], 'caption' => 'Phòng ban'])
            ->addTitle(['caption' => 'Phòng ban']);
        $this->addField('tinh_trang', ['caption' => 'Tình trạng', 'required' => true, 'default' => 'active', 'values' => self::STATUSES]);
        $this->addField('ghi_chu', ['caption' => 'Ghi chú', 'default' => '']);

        $this->setOrder('id');
    }

    #[\Override]
    public function validate(?string $intent = null): array
    {
        $errors = parent::validate($intent);

        $fixedIp = trim((string) $this->get('fixed_ip'));
        if (!filter_var($fixedIp, FILTER_VALIDATE_IP)) {
            $errors['fixed_ip'] = 'Fixed IP không hợp lệ.';
        }

        $floatingIp = trim((string) ($this->get('floating_ip') ?? ''));
        if ($floatingIp !== '' && !filter_var($floatingIp, FILTER_VALIDATE_IP)) {
            $errors['floating_ip'] = 'Floating IP không hợp lệ.';
        }

        $tenMay = trim((string) $this->get('ten_may'));
        $dup = $this->getModel()->tryLoadBy('ten_may', $tenMay);
        if ($dup !== null && (int) $dup->getId() !== (int) $this->getId()) {
            $errors['ten_may'] = 'Tên máy đã tồn tại.';
        }

        return $errors;
    }
}
