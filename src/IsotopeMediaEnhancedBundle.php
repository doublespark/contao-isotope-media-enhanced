<?php

namespace Doublespark\IsotopeMediaEnhancedBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class IsotopeMediaEnhancedBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}