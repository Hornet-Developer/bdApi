<?php

class bdApi_Extend_BbCode_Formatter_Base extends XFCP_bdApi_Extend_BbCode_Formatter_Base
{
    CONST CHR_HEADER_VALUE_EXCEPT_YOUTUBE = '!youtube';

    /** @var array|null */
    protected $_bdApiChr = null;

    /** @var string */
    protected $_bdApiMediaHtmlTagsRegEx = '#<(audio|canvas|embed|iframe|object|source|video)(\s|>)#i';

    /** @var bdApi_Template_Simulation_Template|null */
    protected $_bdApiNoNameTemplate;

    /** @var int */
    protected $_bdApiTagCount = 0;

    /** @var array|null */
    protected $_bdApiTagMediaInfo = null;

    protected $_smilieTemplate = '<span class="smilie" data-image-url="%1$s" data-title="%3$s">%2$s</span>';

    protected $_smilieSpriteTemplate = '<span class="smilie" data-title="%3$s">%2$s</span>';

    public function renderTagMedia(array $tag, array $rendererStates)
    {
        $this->_bdApiTagMediaInfo = null;

        $rendered = parent::renderTagMedia($tag, $rendererStates);

        if ($this->_bdApiChr !== null && is_array($this->_bdApiTagMediaInfo)) {
            $mediaKey = $this->_bdApiTagMediaInfo['mediaKey'];
            $siteId = $this->_bdApiTagMediaInfo['siteId'];
            switch ($siteId) {
                case 'youtube':
                    $rendered = str_replace(
                        '<iframe ',
                        "<iframe data-chr-thumbnail=\"https://img.youtube.com/vi/$mediaKey/0.jpg\" ",
                        $rendered
                    );
                    break;
            }
        }

        return $rendered;
    }

    public function renderValidTag(array $tagInfo, array $tag, array $rendererStates)
    {
        if ($this->_bdApiChr === null || $this->_bdApiNoNameTemplate === null) {
            return parent::renderValidTag($tagInfo, $tag, $rendererStates);
        }

        $this->_bdApiTagCount++;
        $tagCount = $this->_bdApiTagCount;
        $template = $this->_bdApiNoNameTemplate;

        $renderContext = $template->bdApi_setRenderContext('tag_' . $tag['tag']);
        $rendered = parent::renderValidTag($tagInfo, $tag, $rendererStates);

        if ($this->_bdApiTagCount === $tagCount && preg_match($this->_bdApiMediaHtmlTagsRegEx, $rendered)) {
            $requiredExternals = $template->bdApi_unsetAndGetRequiredExternalsByContext($renderContext);
            return $this->_bdApi_renderCHR($rendered, $requiredExternals);
        }

        return $rendered;
    }

    public function renderTree(array $tree, array $extraStates = array())
    {
        $this->_bdApiTagCount = 0;

        /** @var XenForo_FrontController $fc */
        $fc = XenForo_Application::isRegistered('fc') ? XenForo_Application::get('fc') : null;
        if ($fc) {
            $chrHeader = $fc->getRequest()->getHeader('Api-Bb-Code-Chr');
            if (!empty($chrHeader)) {
                $this->_bdApiChr = explode(',', $chrHeader);
            }
        }

        if ($this->_bdApiNoNameTemplate === null && !empty($this->_view)) {
            /** @var bdApi_Template_Simulation_View $view */
            $view = $this->_view;
            $this->_bdApiNoNameTemplate = $view->createTemplateObject('');
        }
        if ($this->_bdApiNoNameTemplate !== null) {
            $this->_bdApiNoNameTemplate->clearRequiredExternalsForApi();
        }

        $extraStates['isApi'] = true;
        $rendered = parent::renderTree($tree, $extraStates);

        if ($this->_bdApiNoNameTemplate !== null) {
            $requiredExternalsHtml = $this->_bdApiNoNameTemplate->getRequiredExternalsAsHtmlForApi();
            if (strlen($requiredExternalsHtml) > 0) {
                $rendered = sprintf('<!--%s-->%s', preg_replace('/([\n\t])/', '', $requiredExternalsHtml), $rendered);
            }
        }

        return $rendered;
    }

    protected function _bdApi_renderCHR($html, array $requiredExternals)
    {
        $requiredArray = array();
        foreach ($requiredExternals as $type => $values) {
            if ($type === 'js' || $type === 'css') {
                $requiredArray[$type] = array_keys($values);
            } else {
                $requiredArray[$type] = array_values($values);
            }
        }

        $linkTimestamp = time() + 86400;
        $linkParams = array(
            'html' => bdApi_Crypt::encryptTypeOne($html, $linkTimestamp),
            'required' => count($requiredArray) > 0
                ? bdApi_Crypt::encryptTypeOne(json_encode($requiredArray), $linkTimestamp) : '',
            'timestamp' => $linkTimestamp,
        );
        $href = bdApi_Data_Helper_Core::safeBuildApiLink('tools/chr', null, $linkParams);

        $attributes = 'data-chr="true"';
        $label = '';

        if (preg_match('#data-chr-thumbnail="([^"]+)"#', $html, $thumbnailMatches)) {
            $attributes .= ' ' . $thumbnailMatches[0];
            /** @noinspection HtmlRequiredAltAttribute, HtmlUnknownTarget */
            $label = sprintf('<img src="%s" />', $thumbnailMatches[1]);
        }
        if (preg_match('#https?://([^/]+)/#', $html, $domainMatches)) {
            $domain = htmlentities($domainMatches[1]);
            $attributes .= sprintf(' data-chr-domain="%s"', $domain);
            if ($label === '') {
                $label = $domain;
            }
        }
        if ($label === '') {
            $label = md5($html);
        }

        /** @noinspection HtmlUnknownAttribute, HtmlUnknownTarget */
        return sprintf(
            "<div style=\"text-align: center\"><a %s href=\"%s\">%s</a></div><br />\n",
            $attributes,
            htmlentities($href),
            $label
        );
    }

    protected function _getMediaSiteHtmlFromCallback($mediaKey, array $site, $siteId)
    {
        $this->_bdApiTagMediaInfo = array('mediaKey' => $mediaKey, 'siteId' => $siteId);

        if ($this->_bdApiChr !== null &&
            $siteId === 'youtube' &&
            in_array(self::CHR_HEADER_VALUE_EXCEPT_YOUTUBE, $this->_bdApiChr)
        ) {
            // this will skip youtube iframe from being wrapped in the custom-html-rendering code
            $this->_bdApiTagCount++;
        }

        return parent::_getMediaSiteHtmlFromCallback($mediaKey, $site, $siteId);
    }
}
