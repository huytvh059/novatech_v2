<?php
declare(strict_types=1);

namespace NovaTech;

use RuntimeException;

/**
 * Loads application configuration from outside webroot.
 */
final class Config
{
    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    /**
     * @return array<string, mixed>
     */
    public static function get(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $path = getenv('NOVATECH_CONFIG') ?: '/etc/novatech/config.php';
        if (!is_file($path)) {
            throw new RuntimeException('Thiếu tệp cấu hình ngoài webroot (' . $path . ').');
        }

        $config = require $path;
        if (!is_array($config) || !isset($config['db'])) {
            throw new RuntimeException('Cấu hình hệ thống không hợp lệ.');
        }

        date_default_timezone_set('Asia/Ho_Chi_Minh');
        if (!empty($config['error_log']) && is_string($config['error_log'])) {
            $logDir = dirname($config['error_log']);
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0750, true);
            }
            ini_set('error_log', $config['error_log']);
        }

        self::$cache = $config;

        return self::$cache;
    }

    public static function reset(): void
    {
        self::$cache = null;
    }
}
