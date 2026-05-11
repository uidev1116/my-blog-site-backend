<?php

class ACMS_POST_User_Insert extends ACMS_POST_User
{
    function post()
    {
        $validator = new ACMS_Validator_User();

        $User = $this->extract('user');
        $User->setMethod('status', 'required');
        $User->setMethod('status', 'in', ['open', 'close']);
        $User->setMethod('name', 'required');
        $User->setMethod('name', 'maxlength', '255');
        $User->setMethod('code', 'maxlength', '64');
        $User->setMethod('code', 'regex', REGEX_VALID_ID);
        $User->setMethod('code', 'doubleCode');
        if ($User->get('code') !== '') {
            $User->setMethod('code', 'string', isValidCode($User->get('code')));
        }
        $User->setMethod('mail', 'required');
        $User->setMethod('mail', 'maxlength', '255');
        $User->setMethod('mail', 'email');
        $User->setMethod('mail', 'doubleMail');
        $User->setMethod('mail_magazine', 'in', ['on', 'off']);
        $User->setMethod('mail_mobile_magazine', 'in', ['on', 'off']);
        $User->setMethod('mail_mobile', 'maxlength', '255');
        $User->setMethod('mail_mobile', 'email');
        $User->setMethod('url', 'maxlength', '255');
        $User->setMethod('url', 'url');
        $User->setMethod('pass', 'required');
        $User->setMethod('pass', 'password');
        $User->setMethod('auth', 'required');
        $User->setMethod('auth', 'in', ['administrator', 'editor', 'contributor', 'subscriber']);
        $User->setMethod('indexing', 'required');
        $User->setMethod('indexing', 'in', ['on', 'off']);
        $User->setMethod('mode', 'in', ['debug', 'benchmark']);
        $User->setMethod('user', 'limit', ('subscriber' == $User->get('auth')) ? true : $this->isLimit());
        $User->setMethod('login_expire', 'regex', '@\d\d\d\d-\d\d-\d\d@');
        $User->setMethod('login_anywhere', 'in', ['on', 'off']);
        $User->setMethod('login_anywhere', 'anywhere', !( 1
            && 'on' == $User->get('login_anywhere')
            && !( 1
                && (empty($User->get('code')) || $validator->doubleCode($User->get('code'), ['uid' => UID]))
                && $validator->doubleMail($User->get('mail'), [])
            )
        ));
        $User->setMethod('user', 'operable', 1
            and IS_LICENSED
            and sessionWithAdministration());
        $User->validate(new ACMS_Validator_User());

        $Field = $this->extract('field', new ACMS_Validator());

        if ($this->Post->isValidAll()) {
            $DB     = DB::singleton(dsn());
            $SQL    = SQL::newSelect('user');
            $SQL->setSelect('user_sort');
            $SQL->setOrder('user_sort', 'DESC');
            $SQL->addWhereOpr('user_blog_id', BID);
            $sort   = intval($DB->query($SQL->get(dsn()), 'one')) + 1;
            $uid    = $DB->query(SQL::nextval('user_id', dsn()), 'seq');

            $SQL    = SQL::newInsert('user');
            $SQL->addInsert('user_id', $uid);
            $SQL->addInsert('user_sort', $sort);
            $SQL->addInsert('user_generated_datetime', date('Y-m-d H:i:s', REQUEST_TIME));
            $SQL->addInsert('user_blog_id', BID);
            $SQL->addInsert('user_code', strval($User->get('code')));
            $SQL->addInsert('user_status', $User->get('status'));
            $SQL->addInsert('user_name', $User->get('name'));
            $SQL->addInsert('user_pass', acmsUserPasswordHash($User->get('pass')));
            $SQL->addInsert('user_pass_generation', PASSWORD_ALGORITHM_GENERATION);
            $SQL->addInsert('user_mail', $User->get('mail'));
            $SQL->addInsert('user_mail_magazine', $User->get('mail_magazine'));
            $SQL->addInsert('user_mail_mobile', strval($User->get('mail_mobile')));
            $SQL->addInsert('user_mail_mobile_magazine', $User->get('mail_mobile_magazine'));
            $SQL->addInsert('user_url', strval($User->get('url')));
            $SQL->addInsert('user_auth', $User->get('auth'));
            $SQL->addInsert('user_mode', $User->get('mode'));
            $SQL->addInsert('user_locale', $User->get('locale'));
            $SQL->addInsert('user_indexing', $User->get('indexing'));
            $SQL->addInsert('user_login_anywhere', (SBID == RBID) ? $User->get('login_anywhere', 'off') : 'off');
            $SQL->addInsert('user_login_expire', $User->get('login_expire'));
            $SQL->addInsert('user_updated_datetime', date('Y-m-d H:i:s', REQUEST_TIME));
            if ($iconPath = Login::resizeUserIcon($User->get('icon@squarePath'))) {
                $SQL->addInsert('user_icon', $iconPath);
                $User->set('icon', $iconPath);
                $this->Post->set('icon', $iconPath);
            }
            $this->Post->getChild('user')->delete('icon');

            $DB->query($SQL->get(dsn()), 'exec');

            //--------
            // field
            Common::saveField('uid', $uid, $Field);

            //----------
            // geometry
            $this->saveGeometry('uid', $uid, $this->extract('geometry'));

            //----------
            // fulltext
            $SQL    = SQL::newSelect('user');
            $SQL->addWhereOpr('user_id', $uid);
            $SQL->addWhereOpr('user_blog_id', BID);
            if (!!($row = $DB->query($SQL->get(dsn()), 'row'))) {
                ACMS_RAM::user($uid, $row);
            }
            ACMS_RAM::cacheDelete(); // loadUser関数のキャッシュを削除
            Common::saveFulltext('uid', $uid, Common::loadUserFulltext($uid));
            $this->Post->set('edit', 'insert');

            AcmsLogger::info('ユーザー「' . $User->get('name') . '」を作成しました', [
                'uid' => $uid,
                'user' => $User->_aryField,
            ]);

            Webhook::call(BID, 'user', ['user:created'], $uid);
        } else {
            AcmsLogger::info('ユーザー作成に失敗しました', [
                'user' => $User->_aryV,
            ]);
        }

        return $this->Post;
    }
}
