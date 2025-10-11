<?php

class ACMS_GET_Admin_Role_Edit extends ACMS_GET_Admin_Edit
{
    function edit(&$Tpl)
    {
        if (BID !== 1 || !sessionWithEnterpriseAdministration()) {
            die403();
        }
        $Role  =& $this->Post->getChild('role');
        if ($Role->isNull()) {
            if ($rid = intval($this->Get->get('rid'))) {
                $Role->overload(loadRole($rid));
            } else {
                $Role->set('entry_view', 'on');
            }
        }
        if ($auth = $this->checkAuth($Role)) {
            $Role->set('auth', $auth);
        }
        return true;
    }

    function checkAuth($Role)
    {
        if ($Role->get('admin_etc') === 'on') {
            return 'administrator';
        } elseif (
            1
            && $Role->get('entry_edit') === 'on'
            && $Role->get('entry_edit_all') === 'on'
            && $Role->get('entry_delete') === 'on'
            && $Role->get('category_create') === 'on'
            && $Role->get('category_edit') === 'on'
            && $Role->get('tag_edit') === 'on'
        ) {
            return 'editor';
        } elseif (
            1
            && $Role->get('entry_edit') === 'on'
        ) {
            return 'contributor';
        } else {
            return 'subscriber';
        }
    }
}
