<?php

use Acms\Services\Facades\Application;

class ACMS_POST_License_Activation extends ACMS_POST
{
    public function post()
    {
        if (!sessionWithAdministration()) {
            die403();
        }
        try {
            $licenseFilePath = CACHE_DIR . 'license.php';
            LocalStorage::remove($licenseFilePath);

            Application::licenseActivation($licenseFilePath);

            $this->addMessage(i18n('サブスクリプションライセンスの有効化に成功しました。'));
            AcmsLogger::info('サブスクリプションライセンスのアクティベーションに成功しました');
        } catch (\Exception $e) {
            $this->addError("サブスクリプションライセンスのアクティベーションに失敗しました: " . $e->getMessage());
            AcmsLogger::warning('サブスクリプションライセンスのアクティベーションに失敗しました', [
                'message' => $e->getMessage()
            ]);
        }
        $this->redirect(acmsLink([
            'bid' => BID,
            'admin' => 'top',
        ]));
    }
}
