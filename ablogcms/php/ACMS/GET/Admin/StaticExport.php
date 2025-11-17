<?php

use Acms\Services\Facades\Application;

class ACMS_GET_Admin_StaticExport extends ACMS_GET_Admin
{
    function get()
    {
        if (!sessionWithAdministration()) {
            die403();
        }
        $tpl = new Template($this->tpl, new ACMS_Corrector());

        /**
         * 書き出し中チェック
         */
        $lockService = Application::make('static-export.lock');
        assert($lockService instanceof \Acms\Services\Common\Lock);
        if ($lockService->isLocked()) {
            return $tpl->render([
                'processing' => 1,
            ]);
        }

        $blogConfig = Config::loadDefaultField();
        $blogConfig->overload(Config::loadBlogConfig(BID));

        return $tpl->render(array_merge([
            'processing' => 0,
            'last-time-date' => $blogConfig->get('static-export-last-time-date', '1000-01-01'),
            'last-time-time' => $blogConfig->get('static-export-last-time-time', '00:00:00'),
        ], $this->buildField($this->Post, $tpl)));
    }
}
