<?php

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

            $json = \App::licenseActivation($licenseFilePath);
            if (empty($json)) {
                throw new \RuntimeException(i18n('ライセンスアクティベーションで不明なエラーが発生しました'));
            }
            if ($json && $json->status === 'failed') {
                throw new \RuntimeException($json->message);
            }
            $this->addMessage(i18n('サブスクリプションライセンスの有効化に成功しました。'));
            AcmsLogger::info('サブスクリプションライセンスのアクティベーションに成功しました');
        } catch (\Exception $e) {
            $this->addError($e->getMessage());
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
