<?php

use Acms\Services\Facades\Common;
use Acms\Services\Facades\Application;

class ACMS_POST_Export_Wordpress extends ACMS_POST
{
    public function post()
    {
        if (!sessionWithAdministration()) {
            return $this->Post;
        }
        @set_time_limit(0);
        ignore_user_abort(true);
        set_time_limit(0);
        setlocale(LC_ALL, 'ja_JP.UTF-8');

        $lockService = Application::make('export-wxr-lock');
        if ($lockService->isLocked()) {
            $this->addError('エクスポートを中止しました。すでにエクスポート中の可能性があります。');
            return $this->Post;
        }

        Common::backgroundRedirect(HTTP_REQUEST_URL);
        $this->run();
        die();
    }

    protected function run()
    {
        /** @var Acms\Services\Export\Engines\WxrEngine $engine */
        $engine = Application::make('export-wxr');
        $field = $this->extract('field');
        $includeChildBlogs = $field->get('include_child_blogs') === 'on';
        $outputFile = MEDIA_STORAGE_DIR . 'export_wxr/' . 'wxr-' . date('Ymd_His') . '.xml';
        $engine->export(BID, $includeChildBlogs, $outputFile);
    }
}
