<?php

use Acms\Modules\Get\Helpers\Json2TplHelper;
use Acms\Services\Facades\Template as TemplateHelper;

class ACMS_GET_Json_2Tpl extends ACMS_GET
{
    /**
     * run
     *
     * @return string
     * @throws \Exception
     */
    public function get()
    {
        $uri = setGlobalVars(config('json_2tpl_source'));
        $expire = (int) config('json_2tpl_cache_expire', 120);
        $tpl = new Template($this->tpl, new ACMS_Corrector());

        if (!$uri) {
            return '';
        }

        TemplateHelper::buildModuleField($tpl, $this->mid, $this->showField);

        try {
            $json2TplHelper = new Json2TplHelper();
            $response = $json2TplHelper->getJsonCache($uri);
            if (empty($response)) {
                $response = $json2TplHelper->getContents($uri);
                $json2TplHelper->saveCache($uri, $response, $expire);
            }
            $vars = json_decode($response, true);
            if (is_array($vars) && $json2TplHelper->isVector($vars)) {
                $vars = [
                    'root' => $vars,
                ];
            }
            if (is_array($vars)) {
                return $tpl->render($vars);
            }
            return '';
        } catch (\Exception $e) {
            AcmsLogger::critical('「Json_2Tpl」モジュールで「' . $uri . '」から情報を取得できませんでした', [
                'detail' => $e->getMessage(),
            ]);
            if (isDebugMode()) {
                throw $e;
            }
            return '';
        }
    }
}
