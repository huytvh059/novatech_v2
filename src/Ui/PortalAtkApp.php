<?php
declare(strict_types=1);

namespace NovaTech\Ui;

use Atk4\Ui\App;

/**
 * Custom Atk4\Ui\App configured for clean front-controller routing and offline bundled assets.
 */
final class PortalAtkApp extends App
{
    protected string $urlBuildingIndexPage = '';
    protected string $urlBuildingExt = '';

    public function configureLocalAssets(string $publicVendorPath): void
    {
        $minified = !is_file($publicVendorPath . '/js/atkjs-ui.js');
        $this->cdn = [
            'atk' => '/atk-assets',
            'jquery' => '/atk-assets/external/jquery/dist',
            'fomantic-ui' => '/atk-assets/external/fomantic-ui/dist',
            'flatpickr' => '/atk-assets/external/flatpickr/dist',
            'highlight.js' => '/atk-assets/external/@highlightjs/cdn-assets',
            'chart.js' => '/atk-assets/external/chart.js/dist',
        ];
        // Ensure assets exist even when installed via --prefer-source
        unset($minified);
    }
}
