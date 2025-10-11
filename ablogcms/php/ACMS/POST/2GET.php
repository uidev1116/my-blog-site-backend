<?php

use Acms\Services\Facades\Common;
use Acms\Services\Logger\Deprecated;

class ACMS_POST_2GET extends ACMS_POST
{
    public $isCacheDelete  = false;

    protected $isCSRF = false;

    public function post()
    {
        if (get_called_class() === __CLASS__ && is_ajax()) {
            Deprecated::once('Ajax による 2GET モジュール', [
                'since' => '3.2.0',
                'alternative' => ' 2GET_Ajax モジュール',
            ]);
        }
        $post = new Field($this->Post);
        if ($post->get('nocache') === 'yes') {
            $post->add('query', 'nocache');
        }
        $this->executeRedirect($post); // @phpstan-ignore-line
    }

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
            'explicitTpl' => false,
            'ignoreTplIfAjax' => false,
        ]));
    }
}
