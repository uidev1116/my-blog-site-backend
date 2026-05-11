<?php

class ACMS_POST_Form_Insert extends ACMS_POST_Form
{
    function post()
    {
        $Form = $this->extract('form');
        $Form->setMethod('code', 'required');
        $Form->setMethod('code', 'maxlength', '64');
        $Form->setMethod('code', 'regex', '@[a-zA-Z0-9_-]@');
        $Form->setMethod('code', 'double', $this->double($Form->get('code'), null, $Form->get('scope')));
        $Form->setMethod('name', 'required');
        $Form->setMethod('name', 'maxlength', '255');
        if (roleAvailableUser()) {
            $Form->setMethod('form', 'operative', roleAuthorization('form_edit', BID));
        } else {
            $Form->setMethod('form', 'operative', sessionWithFormAdministration());
        }
        $Form->validate(new ACMS_Validator());

        $Mail = $this->extract('mail');
        foreach ($Mail->listFields() as $fd) {
            if (!($val = $Mail->get($fd))) {
                $Mail->delete($fd);
            } else {
                $aryVal = [];
                foreach (explode(',', $val) as $_val) {
                    $_val   = trim($_val);
                    if (empty($_val)) {
                        continue;
                    }
                    $aryVal[]   = $_val;
                }
                $Mail->set($fd, $aryVal);
            }
        }

        $Option = $this->extract('option');
        $aryFd  = [];
        $aryMd  = [];
        $aryVal = [];
        foreach ($Option->getArray('field') as $i => $fd) {
            if (empty($fd)) {
                continue;
            }
            if (!($md = $Option->get('method', '', $i))) {
                continue;
            }
            $aryFd[]    = $fd;
            $aryMd[]    = $md;
            $aryVal[]   = $Option->get('value', '', $i);
        }
        $Option->set('field', $aryFd);
        $Option->set('method', $aryMd);
        $Option->set('value', $aryVal);

        $Form->addChild('mail', $Mail);
        $Form->addChild('option', $Option);
        $this->Post->removeChild('mail');
        $this->Post->removeChild('option');

        if ($this->Post->isValidAll()) {
            $DB = DB::singleton(dsn());
            $fmid = $DB->query(SQL::nextval('form_id', dsn()), 'seq');
            $SQL = SQL::newInsert('form');
            $SQL->addInsert('form_id', $fmid);
            $SQL->addInsert('form_code', $Form->get('code'));
            $SQL->addInsert('form_name', $Form->get('name'));
            $SQL->addInsert('form_scope', $Form->get('scope', 'local'));
            $SQL->addInsert('form_log', $Form->get('log', '1'));
            $SQL->addInsert('form_blog_id', BID);
            $Data   = new Field($Form, true);
            $Data->delete('code');
            $Data->delete('name');
            $Data->delete('log');
            $SQL->addInsert('form_data', acmsSerialize($Data));
            $DB->query($SQL->get(dsn()), 'exec');

            $this->Post->set('edit', 'insert');
            AcmsLogger::info('フォームID「' . $Form->get('name') . '（' . $Form->get('code') . '）」を作成しました');
        } else {
            AcmsLogger::info('フォームIDの作成に失敗しました', [
                'Form' => $Form,
            ]);
        }

        return $this->Post;
    }
}
