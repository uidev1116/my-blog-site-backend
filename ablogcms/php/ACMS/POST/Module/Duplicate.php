<?php

use Acms\Services\Facades\Module;
use Acms\Services\Facades\Logger;
use Acms\Services\Facades\Common;

class ACMS_POST_Module_Duplicate extends ACMS_POST_Module
{
    public function post()
    {
        $this->Post->setMethod('module', 'midIsNull', ($mid = idval($this->Get->get('mid'))));
        $this->Post->setMethod('module', 'operative', Module::canDuplicate(BID));
        $this->Post->validate();

        if ($this->Post->isValidAll()) {
            $new = $this->dup($mid);

            $module = loadModule($mid);
            Logger::info('「' . $module->get('label') . '（' . $module->get('identifier') . '）」モジュールを複製しました', [
                'sourceMID' => $mid,
                'createdMID' => $new,
            ]);

            if ($this->Post->get('ajax', false)) {
                Common::setSafeHeadersWithoutCache(200, 'text/plain');
                die(strval($new));
            }

            // redirect new module_edit
            $url = acmsLink([
                'bid' => BID,
                'admin' => 'module_edit',
                'query' => [
                    'mid' => $new,
                    'edit' => 'update',
                ],
            ]);
            $this->redirect($url);
        } else {
            Logger::info('モジュールの複製に失敗しました', [
                'mid' => $mid,
            ]);
        }

        return $this->Post;
    }
}
