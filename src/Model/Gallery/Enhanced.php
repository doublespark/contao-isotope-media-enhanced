<?php

namespace Doublespark\IsotopeMediaEnhancedBundle\Model\Gallery;

use Contao\Environment;
use Contao\StringUtil;
use Isotope\Model\Gallery;
use Isotope\Template;

/**
 * Standard implements a lightbox gallery
 */
class Enhanced extends Gallery\Standard
{
    public function setFiles(array $arrFiles)
    {
        $arrFilesToSet = [];

        foreach($arrFiles as $file)
        {
            // Filter out images that are meant to be hidden
            if(isset($file['hidden']) && intval($file['hidden']) === 1)
            {
                continue;
            }

            $arrFilesToSet[] = $file;
        }

        $this->arrFiles = array_values($arrFilesToSet);
    }

    /**
     * Generate main image and return it as HTML string
     *
     * @return string
     */
    public function generateMainImage()
    {
        $hasImages = $this->hasImages();

        if (!$hasImages && !$this->hasPlaceholderImage()) {
            return '';
        }

        /** @var Template|object $objTemplate */
        $objTemplate = new Template($this->strTemplate);

        $mainImage = null;

        if($hasImages) {

            foreach ($this->arrFiles as $file) {
                if (isset($file['default']) && intval($file['default']) === 1) {
                    $mainImage = $file;
                    break;
                }
            }

            if (is_null($mainImage)) {
                $mainImage = $this->arrFiles[0];
            }
        }
        else
        {
            $mainImage = $this->getPlaceholderImage();
        }

        $this->addImageToTemplate(
            $objTemplate,
            'main',
            $mainImage,
            $hasImages
        );

        $objTemplate->javascript = '';

        if (Environment::get('isAjaxRequest')) {
            $strScripts = '';
            $arrTemplates = StringUtil::deserialize($this->lightbox_template, true);

            if (!empty($arrTemplates)) {
                foreach ($arrTemplates as $strTemplate) {
                    $objScript = new Template($strTemplate);
                    $strScripts .= $objScript->parse();
                }
            }

            $objTemplate->javascript = $strScripts;
        }

        return $objTemplate->parse();
    }
}
