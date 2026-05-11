<?php

class ACMS_POST_Rule_Insert extends ACMS_POST_Rule
{
    function post()
    {
        $Rule = $this->extract('rule');
        if ('global' !== $Rule->get('scope')) {
            $Rule->set('scope', 'local');
        }
        $Rule->setMethod('name', 'required');
        $Rule->setMethod('name', 'maxlength', '64');
        $Rule->setMethod('status', 'in', ['open', 'close']);
        $Rule->setMethod('rule', 'invalidLicence', IS_LICENSED);
        $Rule->setMethod('description', 'maxlength', '255');
        $Rule->validate(new ACMS_Validator());
        $this->fix($Rule);

        if (roleAvailableUser()) {
            $Rule->setMethod('rule', 'operative', roleAuthorization('rule_edit', BID));
        } else {
            $Rule->setMethod('rule', 'operative', sessionWithAdministration());
        }

        if ($this->Post->isValidAll()) {
            $DB     = DB::singleton(dsn());

            $rid    = $DB->query(SQL::nextval('rule_id', dsn()), 'seq');

            $SQL    = SQL::newSelect('rule');
            $SQL->setSelect('rule_sort', 'rule_amount', null, 'MAX');
            $SQL->addWhereOpr('rule_blog_id', BID);
            $sort   = intval($DB->query($SQL->get(dsn()), 'one')) + 1;

            $SQL    = SQL::newInsert('rule');
            $SQL->addInsert('rule_id', $rid);
            $SQL->addInsert('rule_blog_id', BID);
            $SQL->addInsert('rule_sort', $sort);
            $SQL->addInsert('rule_name', $Rule->get('name'));
            $SQL->addInsert('rule_status', $Rule->get('status'));
            $SQL->addInsert('rule_description', strval($Rule->get('description')));
            $SQL->addInsert('rule_scope', $Rule->get('scope'));
            $SQL->addInsert('rule_bid', $Rule->isNull('bid') ? null : $Rule->get('bid'));
            $SQL->addInsert('rule_bid_case', $Rule->isNull('bid_case') ? null : $Rule->get('bid_case'));
            $SQL->addInsert('rule_aid', $Rule->isNull('aid') ? null : $Rule->get('aid'));
            $SQL->addInsert('rule_aid_case', $Rule->isNull('aid_case') ? null : $Rule->get('aid_case'));
            $SQL->addInsert('rule_uid', $Rule->isNull('uid') ? null : $Rule->get('uid'));
            $SQL->addInsert('rule_uid_case', $Rule->isNull('uid_case') ? null : $Rule->get('uid_case'));
            $SQL->addInsert('rule_cid', $Rule->isNull('cid') ? null : $Rule->get('cid'));
            $SQL->addInsert('rule_cid_case', $Rule->isNull('cid_case') ? null : $Rule->get('cid_case'));
            $SQL->addInsert('rule_eid', $Rule->isNull('eid') ? null : $Rule->get('eid'));
            $SQL->addInsert('rule_eid_case', $Rule->isNull('eid_case') ? null : $Rule->get('eid_case'));
            $SQL->addInsert('rule_ucd', $Rule->isNull('ucd') ? null : $Rule->get('ucd'));
            $SQL->addInsert('rule_ucd_case', $Rule->isNull('ucd_case') ? null : $Rule->get('ucd_case'));
            $SQL->addInsert('rule_ccd', $Rule->isNull('ccd') ? null : $Rule->get('ccd'));
            $SQL->addInsert('rule_ccd_case', $Rule->isNull('ccd_case') ? null : $Rule->get('ccd_case'));
            $SQL->addInsert('rule_ecd', $Rule->isNull('ecd') ? null : $Rule->get('ecd'));
            $SQL->addInsert('rule_ecd_case', $Rule->isNull('ecd_case') ? null : $Rule->get('ecd_case'));
            $SQL->addInsert('rule_session_uid', $Rule->isNull('session_uid') ? null : $Rule->get('uid'));
            $SQL->addInsert('rule_session_uid_case', $Rule->isNull('session_uid_case') ? null : $Rule->get('uid_case'));
            $SQL->addInsert('rule_authority', $Rule->isNull('authority') ? null : $Rule->get('authority'));
            $SQL->addInsert('rule_authority_case', $Rule->isNull('authority_case') ? null : $Rule->get('authority_case'));
            $SQL->addInsert('rule_ua_case', $Rule->isNull('ua_case') ? null : $Rule->get('ua_case'));
            $SQL->addInsert('rule_ua', $Rule->isNull('ua') ? null : $Rule->get('ua'));
            $SQL->addInsert('rule_cookie_case', $Rule->isNull('cookie_case') ? null : $Rule->get('cookie_case'));
            $SQL->addInsert('rule_cookie_key', $Rule->isNull('cookie_key') ? null : $Rule->get('cookie_key'));
            $SQL->addInsert('rule_cookie_val', $Rule->isNull('cookie_val') ? null : $Rule->get('cookie_val'));
            $SQL->addInsert('rule_custom', $Rule->isNull('custom') ? null : $Rule->get('custom'));
            $SQL->addInsert('rule_custom_case', $Rule->isNull('custom_case') ? null : $Rule->get('custom_case'));
            $start  = null;
            $end    = null;
            $SQL->addInsert('rule_term_case', $Rule->isNull('term_case') ? null : $Rule->get('term_case'));

            if (!$Rule->isNull('term_case')) {
                $typeAry = array_filter($Rule->getArray('term_type'));
                $type = array_shift($typeAry);
                $SQL->addInsert('rule_term_type', empty($type) ? null : $type);

                $start  = '1000-01-01';
                $time   = $Rule->isNull('term_start_time') ? '00:00:00' : $Rule->get('term_start_time');
                if (!$Rule->isNull('term_start_date')) {
                    $start = $Rule->get('term_start_date');
                }
                $start  = $start . ' ' . $time;
                $SQL->addInsert('rule_term_start', $start);

                $end    = '9999-12-31';
                $time   = $Rule->isNull('term_end_time') ? '23:59:59' : $Rule->get('term_end_time');
                if (!$Rule->isNull('term_end_date')) {
                    $end    = $Rule->get('term_end_date');
                }
                $end    = $end . ' ' . $time;
                $SQL->addInsert('rule_term_end', $end);
            }

            $DB->query($SQL->get(dsn()), 'exec');

            $this->Post->set('edit', 'insert');

            AcmsLogger::info('「' . $Rule->get('name') . '」ルールを作成しました', [
                'ruleID' => $rid,
                'data' => $Rule->_aryField,
            ]);
        } else {
            AcmsLogger::info('ルールの作成に失敗しました', [
                'data' => $Rule,
            ]);
        }

        return $this->Post;
    }
}
