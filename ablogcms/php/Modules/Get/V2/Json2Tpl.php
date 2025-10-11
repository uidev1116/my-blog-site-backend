<?php

namespace Acms\Modules\Get\V2;

use Acms\Modules\Get\V2\Base;
use Acms\Modules\Get\Helpers\Json2TplHelper;
use Acms\Services\Facades\Logger;

class Json2Tpl extends Base
{
    /**
     * @inheritDoc
     */
    public function get(): array
    {
        $config = $this->loadModuleConfig();
        $uri = setGlobalVars($config->get('json_2tpl_source'));
        $expire = (int) $config->get('json_2tpl_cache_expire', 120);
        try {
            $json2TplHelper = new Json2TplHelper();
            $response = $json2TplHelper->getJsonCache($uri);
            if (!$response) {
                $response = $json2TplHelper->getContents($uri);
                $json2TplHelper->saveCache($uri, $response, $expire);
            }
            if (!is_string($response)) {
                throw new \RuntimeException('Response is not a valid JSON string.');
            }
            return [
                'data' => json_decode($response, true),
                'moduleFields' => $this->buildModuleField(),
            ];
        } catch (\Exception $e) {
            Logger::critical('「V2_Json2Tpl」モジュールで「' . $uri . '」から情報を取得できませんでした', [
                'detail' => $e->getMessage(),
            ]);
            if (isDebugMode()) {
                throw $e;
            }
            return [];
        }
    }
}
