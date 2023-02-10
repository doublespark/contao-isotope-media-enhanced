<?php

use Isotope\Model\Gallery;
use Doublespark\IsotopeMediaEnhancedBundle\Widget\MediaManagerCustom;
use Doublespark\IsotopeMediaEnhancedBundle\Model\Gallery\Enhanced;

// Custom media manager
$GLOBALS['BE_FFL']['mediaManager'] = MediaManagerCustom::class;

// Custom gallery handler
Gallery::registerModelType('ds_enhanced', Enhanced::class);