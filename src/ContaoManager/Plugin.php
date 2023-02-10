<?php

namespace Doublespark\IsotopeMediaEnhancedBundle\ContaoManager;

use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Doublespark\IsotopeMediaEnhancedBundle\IsotopeMediaEnhancedBundle;

class Plugin implements BundlePluginInterface
{
    /**
     * {@inheritdoc}
     */
    public function getBundles(ParserInterface $parser): array
    {
        return [
            BundleConfig::create(IsotopeMediaEnhancedBundle::class)->setLoadAfter(['isotope'])
        ];
    }
}