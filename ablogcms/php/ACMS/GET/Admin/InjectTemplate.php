<?php

use Acms\Services\Facades\Application;
use Acms\Services\Common\InjectTemplate;

class ACMS_GET_Admin_InjectTemplate extends ACMS_GET
{
    public function get()
    {
        $type = $this->identifier;
        if (empty($type)) {
            return '';
        }

        $acmsTplEngine = Application::make('template.acms');
        $inject = InjectTemplate::singleton();
        $all = $inject->get($type);
        $template = '';

        foreach ($all as $item) {
            $template .= "<!--#include file=\"$item\" vars=\"\"-->\n";
        }
        $acmsTplEngine->loadFromString($template, '/', config('theme'), BID);
        if (!$txt = $acmsTplEngine->getTemplate()) {
            return '';
        }
        if (isTemplateCacheEnabled()) {
            $txt = setGlobalVars($txt);
        }
        return $txt;
    }
}
