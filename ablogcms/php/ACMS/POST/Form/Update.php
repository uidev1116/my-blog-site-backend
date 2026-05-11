<?php

class ACMS_POST_Form_Update extends ACMS_POST_Form
{
    public function post()
    {
        $Form = $this->extract('form');
        $Form->setMethod('form', 'fmidIsNull', ($fmid = intval($this->Get->get('fmid'))));
        $Form->setMethod('code', 'required');
        $Form->setMethod('code', 'maxlength', '64');
        $Form->setMethod('code', 'regex', '@[a-zA-Z0-9_-]@');
        $Form->setMethod('code', 'double', $this->double($Form->get('code'), $fmid, $Form->get('scope')));
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
            if (
                !in_array($fd, [
                    'To', 'From', 'Cc', 'Bcc', 'Reply-To',
                    'AdminTo', 'AdminFrom', 'AdminCc', 'AdminBcc', 'AdminReply-To'
                ], true)
            ) {
                continue;
            }
            if ($val = $Mail->get($fd)) {
                $aryVal = [];
                foreach (explode(',', $val) as $_val) {
                    $_val = trim($_val);
                    if (empty($_val)) {
                        continue;
                    }
                    $aryVal[] = $_val;
                }
                $Mail->set($fd, $aryVal);
            } else {
                $Mail->delete($fd);
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
            $SQL = SQL::newUpdate('form');
            $SQL->addUpdate('form_code', $Form->get('code'));
            $SQL->addUpdate('form_name', $Form->get('name'));
            $SQL->addUpdate('form_scope', $Form->get('scope', 'local'));
            $SQL->addUpdate('form_log', $Form->get('log', '1'));
            $Data = new Field($Form, true);
            $Data->delete('code');
            $Data->delete('name');
            $Data->delete('log');
            $SQL->addUpdate('form_data', acmsSerialize($Data));
            $SQL->addWhereOpr('form_id', $fmid);
            $SQL->addWhereOpr('form_blog_id', BID);
            $DB->query($SQL->get(dsn()), 'exec');

            $this->Post->set('edit', 'update');

            AcmsLogger::info('フォームID「' . $Form->get('name') . '（' . $Form->get('code') . '）」を更新しました');
        } else {
            AcmsLogger::info('フォームID「' . ACMS_RAM::formName($fmid) . '（' . ACMS_RAM::formCode($fmid) . '）」を更新に失敗しました', [
                'Form' => $Form->_aryV,
            ]);
        }
        return $this->Post;
    }
}
