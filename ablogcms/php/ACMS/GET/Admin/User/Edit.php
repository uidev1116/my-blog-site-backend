<?php

class ACMS_GET_Admin_User_Edit extends ACMS_GET_Admin_Edit
{
    function edit(&$Tpl)
    {
        if (UID <> SUID and !sessionWithAdministration()) {
            die403();
        }

        $User = loadUser(UID);
        $User->delete('pass');
        $inputUser = $this->Post->getChild('user');
        if (!$inputUser->get('icon')) {
            $inputUser->delete('icon');
        }
        $User->overload($inputUser);
        $Geo =& $this->Post->getChild('geometry');

        if ($User->isNull()) {
            //---------
            // default
            $User->set('status', 'open');
            $User->set('auth', 'contributor');
            $User->set('indexing', 'on');
            $User->set('mail_magazine', 'on');
            $User->set('mail_mobile_magazine', 'on');
            $User->set('login_anywhere', 'off');
            $User->set('login_expire', '9999-12-31');
            $User->set('global_auth', 'on');
        } else {
            switch (getAuthConsideringRole(UID)) {
                case 'subscriber':
                    $User->set('actually_auth', 'subscriber');
                    break;
                case 'contributor':
                    $User->set('actually_auth', 'contributor');
                    break;
                case 'editor':
                    $User->set('actually_auth', 'editor');
                    break;
                case 'administrator':
                    $User->set('actually_auth', 'administrator');
                    break;
            }
            if (isRoleAvailableUser(UID)) {
                $User->set('role_management', 'yes');
            }
        }
        if (UID === SUID) {
            // ２段階認証チェック
            $sql = SQL::newSelect('user');
            $sql->setSelect('user_tfa_secret');
            $sql->addWhereOpr('user_id', UID);
            $secret = DB::query($sql->get(dsn()), 'one');
            if ($secret) {
                $User->set('two-factor-auth', 'on');
                $User->set('tfa-qr-image', Tfa::getSecretForQRCode($secret, $User->get('name')));
                $User->set('tfa-secret', Tfa::getSecretForManual($secret));
            }
        }
        if (!!UID) {
            $User->set('oldPass', $User->get('oldPass'));
            $Geo->overload(loadGeometry('uid', UID));
        }
        if (GETTEXT_TYPE !== 'user') {
            $User->delete('locale');
        }
        if (!sessionWithAdministration()) {
            $User->delete('status');
            $User->delete('auth');
            $User->delete('indexing');
            $User->delete('mode');
            $User->delete('login_anywhere');
            $User->delete('global_auth');
            $User->delete('login_expire');
            $User->delete('login_terminal_restriction');

            if (SUID !== UID) {
                $User->delete('locale');
            }
        } else {
            if (SUID == UID) {
                $User->delete('status');
                $User->delete('auth');
                $User->delete('login_expire');
            }

            if (RBID <> SBID) {
                $User->delete('login_anywhere');
                $User->delete('global_auth');
            }

            if (ACMS_RAM::userAuth(UID) === 'administrator') {
                $User->delete('login_terminal_restriction');
            }
        }

        $this->Post->addChild('user', $User);

        $Field  =& $this->Post->getChild('field');
        if ($Field->isNull() and !!UID) {
            $Field->overload(loadUserField(UID));
        }

        return true;
    }
}
