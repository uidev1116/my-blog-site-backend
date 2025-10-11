<?php

use Acms\Services\Facades\LocalStorage;

class ACMS_POST_Search_GlobalVars extends ACMS_POST
{
    public $isCacheDelete = false;

    function post()
    {
        try {
            if (!sessionWithAdministration()) {
                throw new \RuntimeException('Permission denied.');
            }
            $tpl = LocalStorage::get(THEMES_DIR . 'system/acms-code/global-vars.json');
            if (empty($tpl)) {
                throw new \RuntimeException('Failed to get template.');
            }
            if (defined('I18N')) {
                $tpl = i18n($tpl);
            } else {
                $tpl = preg_replace(
                    '/<!--[\t 　]*(T|\/T|TRANS|\/TRANS)([\t 　]*)([^>]*?)-->/i',
                    '',
                    $tpl
                );
            }
            $json = setGlobalVars($tpl);

            if (HOOK_ENABLE) {
                $obj = json_decode($json);
                $Hook = ACMS_Hook::singleton();
                $exVars = new Field();
                $Hook->call('extendsGlobalVars', [& $exVars]);

                if (is_array($exVars->_aryField)) {
                    foreach ($exVars->_aryField as $key => $val) {
                        $varname = '%{' . $key . '}';
                        if (isset($val[0])) {
                            array_unshift($obj->items, [
                                "bid" => BID,
                                "title" => $varname,
                                "subtitle" => "custom vars",
                                "url" => $val[0],
                            ]);
                        }
                    }
                }
                $json = json_encode($obj);
            }
        } catch (\Exception $e) {
            $json = '{"title": "<!--T-->グローバル変数<!--/T-->","enTitle": "Global vars","items": []}';
        }

        Common::setSafeHeadersWithoutCache(200, 'application/json');
        echo($json);
        die();
    }
}
