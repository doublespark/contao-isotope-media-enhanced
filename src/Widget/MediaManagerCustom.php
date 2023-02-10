<?php

namespace Doublespark\IsotopeMediaEnhancedBundle\Widget;

use Contao\Backend;
use Contao\Controller;
use Contao\Database;
use Contao\Environment;
use Contao\File;
use Contao\Image;
use Contao\Input;
use Contao\StringUtil;
use Contao\Widget;
use Isotope\Model\Gallery;
use Isotope\Widget\MediaManager;

/**
 * Provide methods to handle media files.
 */
class MediaManagerCustom extends MediaManager
{
    /**
     * Generate the widget and return it as string
     *
     * @return string
     */
    public function generate()
    {
        if ('uploadMediaManager' === Input::post('action')) {
            $this->ajaxUpload();
        }

        $blnLanguage = false;
        $arrFallback = $this->getFallbackData();

        // Adapt the temporary files
        if (\is_array($this->varValue) && !empty($this->varValue['files']) && \is_array($this->varValue['files'])) {
            foreach ($this->varValue['files'] as $v) {
                if (!is_file(TL_ROOT . '/' . $this->getFilePath($v))) {
                    continue;
                }

                $this->varValue[] = array(
                    'src'       => $v,
                    'alt'       => '',
                    'desc'      => '',
                    'default'   => '',
                    'hidden'    => '',
                    'link'      => '',
                    'translate' => 'none'
                );
            }

            unset($this->varValue['files']);
        }

        $varValueOriginal = $this->varValue;

        // Merge parent record data
        if ($arrFallback !== false) {
            $blnLanguage    = true;
            $this->varValue = Gallery::mergeMediaData($this->varValue, $arrFallback);
        }

        // Fix custom fields
        foreach($varValueOriginal as $k => $v)
        {
            if(isset($v['hidden']) && $v['hidden'])
            {
                $this->varValue[$k]['hidden'] = $v['hidden'];
            }
            else
            {
                unset($this->varValue[$k]['hidden']);
            }

            if(isset($v['default']) && $v['default'])
            {
                $this->varValue[$k]['default'] = $v['default'];
            }
            else
            {
                unset($this->varValue[$k]['default']);
            }
        }

        $arrButtons = array('up', 'down', 'delete', 'drag');
        $strCommand = 'cmd_' . $this->strField;

        // Change the order
        if (Input::get($strCommand) && is_numeric(Input::get('cid')) && Input::get('id') == $this->currentRecord) {
            switch (Input::get($strCommand)) {
                case 'up':
                    $this->varValue = array_move_up($this->varValue, Input::get('cid'));
                    break;

                case 'down':
                    $this->varValue = array_move_down($this->varValue, Input::get('cid'));
                    break;

                case 'delete':
                    $this->varValue = array_delete($this->varValue, Input::get('cid'));
                    break;
            }

            Database::getInstance()->prepare("UPDATE " . $this->strTable . " SET " . $this->strField . "=? WHERE id=?")
                     ->execute(serialize($this->varValue), $this->currentRecord);

            Controller::redirect(preg_replace('/&(amp;)?cid=[^&]*/i', '', preg_replace('/&(amp;)?' . preg_quote($strCommand, '/') . '=[^&]*/i', '', Environment::get('request'))));
        }

        $blnIsAjax = Environment::get('isAjaxRequest');
        $return = '';
        $upload = '';

        if (!$blnIsAjax) {
            $return .= '<div id="ctrl_' . $this->strId . '" class="tl_mediamanager">';
            $extensions = StringUtil::trimsplit(',', $this->extensions);

            $upload .= '<div id="fineuploader_'.$this->strId.'" class="upload_container"></div>
  <script>
    window.addEvent("domready", function() {
      Isotope.MediaManager.init($("fineuploader_'.$this->strId.'"), "'.$this->strId.'", '.json_encode($extensions).');
    });
  </script>
  <script type="text/template" id="qq-template">
    <div class="qq-uploader-selector qq-uploader">
        <div class="qq-upload-drop-area-selector qq-upload-drop-area" qq-hide-dropzone>
            <span>'.$GLOBALS['TL_LANG']['MSC']['mmDrop'].'</span>
        </div>
        <div class="qq-upload-button-selector qq-upload-button">
            <div class="tl_submit">'.$GLOBALS['TL_LANG']['MSC']['mmUpload'].'</div>
        </div>
        <span class="qq-drop-processing-selector qq-drop-processing">
            <span>'.$GLOBALS['TL_LANG']['MSC']['mmProcessing'].'</span>
            <span class="qq-drop-processing-spinner-selector qq-drop-processing-spinner"></span>
        </span>
        <ul class="qq-upload-list-selector qq-upload-list">
            <li>
                <div class="qq-progress-bar-container-selector">
                    <div class="qq-progress-bar-selector qq-progress-bar"></div>
                </div>
                <span class="qq-upload-spinner-selector qq-upload-spinner"></span>
                <span class="qq-edit-filename-icon-selector qq-edit-filename-icon"></span>
                <span class="qq-upload-file-selector qq-upload-file"></span>
                <input class="qq-edit-filename-selector qq-edit-filename" tabindex="0" type="text">
                <span class="qq-upload-size-selector qq-upload-size"></span>
                <span class="qq-upload-status-text-selector qq-upload-status-text"></span>
            </li>
        </ul>
    </div>
  </script>';
        }

        $return .= '<div>';

        if (!\is_array($this->varValue) || empty($this->varValue)) {
            return $return . $GLOBALS['TL_LANG']['MSC']['mmNoUploads'] . '</div>' . $upload . (!$blnIsAjax ? '</div>' : '');
        }

        // Add label and return wizard
        $return .= '<table>
  <thead>
  <tr>
    <td class="col_0 col_first">'.$GLOBALS['TL_LANG'][$this->strTable]['mmSrc'].'</td>
    <td class="col_1">'.$GLOBALS['TL_LANG'][$this->strTable]['mmAlt'].' / '.$GLOBALS['TL_LANG'][$this->strTable]['mmLink'].'</td>
    <td class="col_2">'.$GLOBALS['TL_LANG'][$this->strTable]['mmDesc'].'</td>
    <td class="col_3">'.$GLOBALS['TL_LANG'][$this->strTable]['mmTranslate'].'</td>
    <td class="col_4">Main</td>
    <td class="col_5">Visibility</td>
    <td class="col_6 col_last">&nbsp;</td>
  </tr>
  </thead>
  <tbody class="sortable">';

        // Add input fields
        for ($i=0, $count=\count($this->varValue); $i<$count; $i++) {
            $strFile = $this->getFilePath($this->varValue[$i]['src']);

            if (!is_file(TL_ROOT . '/' . $strFile)) {
                continue;
            }

            $objFile = new File($strFile);

            if ($objFile->isGdImage || $objFile->isSvgImage) {
                $strPreview = Image::get($strFile, 50, 50, 'box');
            } else {
                $strPreview = 'assets/contao/images/' . $objFile->icon;
            }

            $strTranslateText = ($blnLanguage && 'all' !== $this->varValue[$i]['translate']) ? ' disabled="disabled"' : '';
            $strTranslateNone = ($blnLanguage && 'none' === $this->varValue[$i]['translate']) ? ' disabled="disabled"' : '';

            $defaultChecked = !empty($this->varValue[$i]['default']) ? 'checked' : '';
            $hiddenChecked  = !empty($this->varValue[$i]['hidden']) ? 'checked' : '';

            $return .= '
  <tr>
    <td class="col_0 col_first"><input type="hidden" name="' . $this->strName . '['.$i.'][src]" value="' . StringUtil::specialchars($this->varValue[$i]['src']) . '"><a href="' . TL_FILES_URL . $strFile . '" onclick="Backend.openModalImage({\'width\':' . $objFile->width . ',\'title\':\'' . str_replace("'", "\\'", $GLOBALS['TL_LANG'][$this->strTable]['mmSrc']) . '\',\'url\':\'' . TL_FILES_URL . $strFile . '\'});return false"><img src="' . TL_ASSETS_URL . $strPreview . '" alt="' . StringUtil::specialchars($this->varValue[$i]['src']) . '"></a></td>
    <td class="col_1"><input type="text" class="tl_text_2" name="' . $this->strName . '['.$i.'][alt]" value="' . StringUtil::specialchars($this->varValue[$i]['alt'], true) . '"'.$strTranslateNone.'><br><input type="text" class="tl_text_2" name="' . $this->strName . '['.$i.'][link]" value="' . StringUtil::specialchars($this->varValue[$i]['link'], true) . '"'.$strTranslateText.'></td>
    <td class="col_2"><textarea name="' . $this->strName . '['.$i.'][desc]" cols="40" rows="3" class="tl_textarea"'.$strTranslateNone.' >' . StringUtil::specialchars($this->varValue[$i]['desc']) . '</textarea></td>
    <td class="col_3">
        '.($blnLanguage ? ('<input type="hidden" name="' . $this->strName . '['.$i.'][translate]" value="'.$this->varValue[$i]['translate'].'">') : '').'
        <fieldset class="radio_container">
            <span>
                <input id="' . $this->strName . '_'.$i.'_translate_none" name="' . $this->strName . '['.$i.'][translate]" type="radio" class="tl_radio" value="none"'.Widget::optionChecked('none', $this->varValue[$i]['translate']).($blnLanguage ? ' disabled="disabled"' : '').'>
                <label for="' . $this->strName . '_'.$i.'_translate_none" title="'.$GLOBALS['TL_LANG'][$this->strTable]['mmTranslateNone'][1].'">'.$GLOBALS['TL_LANG'][$this->strTable]['mmTranslateNone'][0].'</label></span>
            <span>
                <input id="' . $this->strName . '_'.$i.'_translate_text" name="' . $this->strName . '['.$i.'][translate]" type="radio" class="tl_radio" value="text"'.Widget::optionChecked('text', $this->varValue[$i]['translate']).($blnLanguage ? ' disabled="disabled"' : '').'>
                <label for="' . $this->strName . '_'.$i.'_translate_text" title="'.$GLOBALS['TL_LANG'][$this->strTable]['mmTranslateText'][1].'">'.$GLOBALS['TL_LANG'][$this->strTable]['mmTranslateText'][0].'</label></span>
            <span>
                <input id="' . $this->strName . '_'.$i.'_translate_all" name="' . $this->strName . '['.$i.'][translate]" type="radio" class="tl_radio" value="all"'.Widget::optionChecked('all', $this->varValue[$i]['translate']).($blnLanguage ? ' disabled="disabled"' : '').'>
                <label for="' . $this->strName . '_'.$i.'_translate_all" title="'.$GLOBALS['TL_LANG'][$this->strTable]['mmTranslateAll'][1].'">'.$GLOBALS['TL_LANG'][$this->strTable]['mmTranslateAll'][0].'</label></span>
        </fieldset>
    </td>
    <td class="col_4" style="text-align:center;">
        <input style="display:inline-block;" id="' . $this->strName . '_'.$i.'_default" onclick="selectOnlyThis(this)" class="tl_checkbox defaultSelector" type="checkbox" '.$defaultChecked.' name="' . $this->strName . '['.$i.'][default]" value="1"/ >
    </td>
    <td class="col_5" style="text-align:center;">
        <button style="display:inline-block;" data-input-id="' . $this->strName . '_'.$i.'_hidden" class="visibility-control">'. ($hiddenChecked ? Image::getHtml('invisible.svg') : Image::getHtml('visible.svg')).'</button>
        <input id="' . $this->strName . '_'.$i.'_hidden" class="tl_checkbox visibleSelector" type="hidden" '.$hiddenChecked.' name="' . $this->strName . '['.$i.'][hidden]" value="'.($hiddenChecked ? 1 : 0).'"/ >
    </td>
    <td class="col_6 col_last">';

            // Add buttons
            foreach ($arrButtons as $button) {
                if ('delete' === $button && $blnLanguage && 'all' !== $this->varValue[$i]['translate']) {
                    continue;
                }

                $class = ('up' === $button || 'down' === $button) ? ' class="button-move"' : '';

                if ('drag' === $button) {
                    $return .= Image::getHtml('drag.svg', '', 'class="drag-handle" title="' . sprintf($GLOBALS['TL_LANG']['MSC']['move']) . '"');
                } else {
                    $return .= '<a href="'.Backend::addToUrl('&amp;'.$strCommand.'='.$button.'&amp;cid='.$i.'&amp;id='.$this->currentRecord).'"' . $class . ' title="'.StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['lw_'.$button] ?? '').'" onclick="Isotope.MediaManager.act(this, \''.$button.'\',  \'ctrl_'.$this->strId.'\'); return false;">'.\Image::getHtml($button.'.svg', $GLOBALS['TL_LANG']['MSC']['lw_'.$button] ?? '', 'class="tl_listwizard_img"').'</a> ';
                }
            }

            $return .= '</td>
  </tr>';
        }

        return $return.'
  </tbody>
  </table>
  <script>function selectOnlyThis(id){var checkboxes = document.querySelectorAll("input.defaultSelector");Array.prototype.forEach.call(checkboxes,function(el){el.checked = false;});id.checked = true;}</script>
  <script>
        function initVisibility() {
            var buttons = document.querySelectorAll("button.visibility-control");
            var visibleIcon   = \''.Image::getHtml('visible.svg').'\';
            var invisibleIcon = \''.Image::getHtml('invisible.svg').'\';
            buttons.forEach(function(item){
                item.addEventListener("click",function(e){
                    e.preventDefault();                  
                    var targetInputId = e.target.parentElement.getAttribute("data-input-id");
                    var targetInput = document.getElementById(targetInputId);
                    targetInput.value = parseInt(targetInput.value) === 1 ? 0 : 1;           
                    if(parseInt(targetInput.value) === 1)
                    {
                        e.target.parentElement.innerHTML = invisibleIcon;
                    }
                    else
                    {
                        e.target.parentElement.innerHTML = visibleIcon;
                    }
                });
            })
        }
        initVisibility();
  </script>
  <style>button.visibility-control{background:none;border:none;}</style>
  </div>' . $upload . (!$blnIsAjax ? '</div>' : '');
    }
}
