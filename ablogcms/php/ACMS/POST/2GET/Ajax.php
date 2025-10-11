<?php

use Acms\Services\Facades\Common;

class ACMS_POST_2GET_Ajax extends ACMS_POST_2GET
{
    /**
     * リダイレクト実行
     * @param Field $post
     * @return void
     */
    protected function executeRedirect(Field $post): void
    {
        $this->redirect(acmsLink(Common::getUriObject($post), [
            'inherit' => true,
            'isDeep' => true,
            'baseId' => false,
            'explicitTpl' => true,
            'ignoreTplIfAjax' => false,
        ]));
    }
}
