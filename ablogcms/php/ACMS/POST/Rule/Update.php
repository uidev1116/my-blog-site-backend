<?php

class ACMS_POST_Rule_Update extends ACMS_POST_Rule
{
    public function post()
    {
        $Rule = $this->extract('rule');
        $this->validate($Rule);

        $ruleId = intval($this->Get->get('rid'));

        $this->fix($Rule);

        if ($this->Post->isValidAll()) {
            $DB = DB::singleton(dsn());
            $SQL = SQL::newUpdate('rule');
            $SQL->addUpdate('rule_name', $Rule->get('name'));
            $SQL->addUpdate('rule_status', $Rule->get('status'));
            $SQL->addUpdate('rule_description', strval($Rule->get('description')));
            $SQL->addUpdate('rule_scope', $Rule->get('scope') ?: 'local');
            $SQL->addUpdate('rule_bid', $Rule->isNull('bid') ? null : $Rule->get('bid'));
            $SQL->addUpdate('rule_bid_case', $Rule->isNull('bid_case') ? null : $Rule->get('bid_case'));
            $SQL->addUpdate('rule_aid', $Rule->isNull('aid') ? null : $Rule->get('aid'));
            $SQL->addUpdate('rule_aid_case', $Rule->isNull('aid_case') ? null : $Rule->get('aid_case'));
            $SQL->addUpdate('rule_uid', $Rule->isNull('uid') ? null : $Rule->get('uid'));
            $SQL->addUpdate('rule_uid_case', $Rule->isNull('uid_case') ? null : $Rule->get('uid_case'));
            $SQL->addUpdate('rule_cid', $Rule->isNull('cid') ? null : $Rule->get('cid'));
            $SQL->addUpdate('rule_cid_case', $Rule->isNull('cid_case') ? null : $Rule->get('cid_case'));
            $SQL->addUpdate('rule_eid', $Rule->isNull('eid') ? null : $Rule->get('eid'));
            $SQL->addUpdate('rule_eid_case', $Rule->isNull('eid_case') ? null : $Rule->get('eid_case'));
            $SQL->addUpdate('rule_ucd', $Rule->isNull('ucd') ? null : $Rule->get('ucd'));
            $SQL->addUpdate('rule_ucd_case', $Rule->isNull('ucd_case') ? null : $Rule->get('ucd_case'));
            $SQL->addUpdate('rule_ccd', $Rule->isNull('ccd') ? null : $Rule->get('ccd'));
            $SQL->addUpdate('rule_ccd_case', $Rule->isNull('ccd_case') ? null : $Rule->get('ccd_case'));
            $SQL->addUpdate('rule_ecd', $Rule->isNull('ecd') ? null : $Rule->get('ecd'));
            $SQL->addUpdate('rule_ecd_case', $Rule->isNull('ecd_case') ? null : $Rule->get('ecd_case'));
            $SQL->addUpdate('rule_session_uid', $Rule->isNull('session_uid') ? null : $Rule->get('session_uid'));
            $SQL->addUpdate('rule_session_uid_case', $Rule->isNull('session_uid_case') ? null : $Rule->get('session_uid_case'));
            $SQL->addUpdate('rule_authority', $Rule->isNull('authority') ? null : $Rule->get('authority'));
            $SQL->addUpdate('rule_authority_case', $Rule->isNull('authority_case') ? null : $Rule->get('authority_case'));
            $SQL->addUpdate('rule_ua_case', $Rule->isNull('ua_case') ? null : $Rule->get('ua_case'));
            $SQL->addUpdate('rule_ua', $Rule->isNull('ua') ? null : $Rule->get('ua'));
            $SQL->addUpdate('rule_cookie_case', $Rule->isNull('cookie_case') ? null : $Rule->get('cookie_case'));
            $SQL->addUpdate('rule_cookie_key', $Rule->isNull('cookie_key') ? null : $Rule->get('cookie_key'));
            $SQL->addUpdate('rule_cookie_val', $Rule->isNull('cookie_val') ? null : $Rule->get('cookie_val'));
            $SQL->addUpdate('rule_custom', $Rule->isNull('custom') ? null : $Rule->get('custom'));
            $SQL->addUpdate('rule_custom_case', $Rule->isNull('custom_case') ? null : $Rule->get('custom_case'));
            $start = null;
            $end = null;
            $SQL->addUpdate('rule_term_case', $Rule->isNull('term_case') ? null : $Rule->get('term_case'));

            if (!$Rule->isNull('term_case')) {
                $typeAry = array_filter($Rule->getArray('term_type'));
                $type = array_shift($typeAry);
                $SQL->addUpdate('rule_term_type', empty($type) ? null : $type);

                $start = '1000-01-01';
                $time = $Rule->isNull('term_start_time') ? '00:00:00' : $Rule->get('term_start_time');
                if (!$Rule->isNull('term_start_date')) {
                    $start = $Rule->get('term_start_date');
                }
                $start = $start . ' ' . $time;
                $SQL->addUpdate('rule_term_start', $start);

                $end = '9999-12-31';
                $time = $Rule->isNull('term_end_time') ? '23:59:59' : $Rule->get('term_end_time');
                if (!$Rule->isNull('term_end_date')) {
                    $end  = $Rule->get('term_end_date');
                }
                $end = $end . ' ' . $time;
                $SQL->addUpdate('rule_term_end', $end);
            }

            $SQL->addWhereOpr('rule_id', $ruleId);
            $SQL->addWhereOpr('rule_blog_id', BID);
            $DB->query($SQL->get(dsn()), 'exec');

            ACMS_RAM::rule($ruleId, null);

            $this->Post->set('edit', 'update');

            AcmsLogger::info('「' . $Rule->get('name') . '」ルールを更新しました', [
                'ruleID' => $ruleId,
                'data' => $Rule->_aryField,
            ]);
        } else {
            AcmsLogger::info('ルールの更新に失敗しました', [
                'ruleID' => $ruleId,
                'data' => $Rule,
            ]);
        }

        return $this->Post;
    }


    /**
     * バリデート
     *
     * @param \Field_Validation $Rule
     */
    protected function validate(\Field_Validation $Rule)
    {
        $Rule->setMethod('name', 'required');
        $Rule->setMethod('name', 'maxlength', '64');
        $Rule->setMethod('status', 'in', ['open', 'close']);
        $Rule->setMethod('rule', 'ridIsNull', idval($this->Get->get('rid')) > 0);
        $Rule->setMethod('rule', 'invalidLicence', IS_LICENSED);
        $Rule->setMethod('rule', 'operative', $this->isOperable());
        $Rule->setMethod('description', 'maxlength', '255');

        $Rule->validate(new ACMS_Validator());
    }

    /**
     * ルールの更新権限があるかどうか
     *
     * @return bool
     **/
    protected function isOperable(): bool
    {
        if (roleAvailableUser()) {
            if (roleAuthorization('rule_edit', BID)) {
                return true;
            }

            return false;
        }

        if (sessionWithAdministration()) {
            return true;
        }

        return false;
    }
}
