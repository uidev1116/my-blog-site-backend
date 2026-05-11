<?php

use Acms\Services\Facades\Common;
use Acms\Services\PageGeneration\PageGenerationService;

class ACMS_POST_Download extends ACMS_POST
{
    /**
     * @var bool
     */
    public $isCacheDelete  = false;

    function post()
    {
        $Q = Common::getUriObject($this->Post);
        if (ACMS_SID) {
            $phpSession = Session::handle();
            $phpSession->writeClose(); // セッションをクローズ（デッドロック対応）
        }
        $url = acmsLink($Q, true, true, false, true);
        if ($url === false) {
            throw new RuntimeException('URLが不正です');
        }
        try {
            $pageGenerationService = new PageGenerationService();
            $pageGenerationService->addPage(url: $url, destinationPathname: 'download', userAgent: null, withSession: true);
            $results = $pageGenerationService->run(maxParallel: 1, listener: null, withData: true);
            if (!isset($results[0])) {
                throw new RuntimeException('ページの取得に失敗しました');
            }
            $result = $results[0];
            $data = $result->getData();
            if ($result->isSuccess() && $result->getStatusCode() === 200 && $data !== null && $data !== '') {
                $charset = $this->Post->get('charset', 'UTF-8');
                if ($data && $charset && $charset !== 'UTF-8') {
                    $data = mb_convert_encoding($data, $charset, 'UTF-8');
                }
                if ($data) {
                    header('Content-Length: ' . strlen($data));
                }
                if (strpos(UA, 'MSIE')) {
                    header('Content-Type: text/download');
                } else {
                    header('Content-Disposition: attachment');
                    header('Content-Type: application/octet-stream');
                }
                ob_clean();
                flush();
                echo $data;
                exit;
            }
            throw new RuntimeException('ページの取得に失敗しました');
        } catch (\Exception $e) {
            AcmsLogger::warning('ダウンロードに失敗しました', Common::exceptionArray($e));
        }
        return $this->Post;
    }
}
