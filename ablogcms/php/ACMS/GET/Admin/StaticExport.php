<?php

use Acms\Services\Facades\LocalStorage;

class ACMS_GET_Admin_StaticExport extends ACMS_GET_Admin
{
    function get()
    {
        if (!sessionWithAdministration()) {
            die403();
        }
        $tpl = new Template($this->tpl, new ACMS_Corrector());
        $logger = App::make('static-export.logger');
        assert($logger instanceof \Acms\Services\StaticExport\Logger);

        /**
         * 書き出し中チェック
         */
        if (LocalStorage::exists($logger->getDestinationPath())) {
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
