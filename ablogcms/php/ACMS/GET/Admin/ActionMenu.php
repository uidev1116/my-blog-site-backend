<?php

class ACMS_GET_Admin_ActionMenu extends ACMS_GET
{
    function get()
    {
        if (
            0
            || !$this->checkPermission()
            || LAYOUT_PREVIEW
            || Preview::isPreviewMode()
        ) {
            return '';
        }

        $Tpl    = new Template($this->tpl, new ACMS_Corrector());
        $vars   = [];

        if (IS_DEVELOPMENT && defined('UNLICENSED_REASON')) {
            $Tpl->add('status#' . UNLICENSED_REASON);
        }

        $vars += [
            'name' => ACMS_RAM::userName(SUID),
            'icon' => loadUserIcon(SUID),
            'logout' => acmsLink(['_inherit' => true]),
        ];

        if (sessionWithContribution()) {
            if (IS_LICENSED) {
                $Tpl->add('insert', ['cid' => CID]);
                foreach (configArray('ping_weblog_updates_endpoint') as $val) {
                    $Tpl->add('ping_weblog_updates_endpoint:loop', [
                        'ping_weblog_updates_endpoint'  => $val,
                    ]);
                }
                foreach (configArray('ping_weblog_updates_extended_endpoint') as $val) {
                    $Tpl->add('ping_weblog_updates_extended_endpoint:loop', [
                        'ping_weblog_updates_extended_endpoint' => $val,
                    ]);
                }
            }
        }

        //-------
        // admin
        $Tpl->add('admin');

        //---------------------
        // approval infomation
        if (approvalAvailableUser()) {
            if ($amount = Approval::notificationCount()) {
                $Tpl->add('approval', [
                    'badge' => $amount,
                    'url'   => acmsLink([
                        'bid'   => BID,
                        'admin' => 'approval_notification',
                    ]),
                ]);
            }
        }

        $Tpl->add(null, $vars);
        return $Tpl->get();
    }

    /**
     * @return bool
     */
    protected function checkPermission()
    {
        if (timemachineMode()) {
            return false;
        }

        if (
            1
            and \ACMS_RAM::userGlobalAuth(SUID) !== 'on'
            and SBID !== BID
        ) {
            return false;
        }

        if (
            !(1
            and \ACMS_RAM::blogLeft(SBID) <= \ACMS_RAM::blogLeft(BID)
            and \ACMS_RAM::blogRight(SBID) >= \ACMS_RAM::blogRight(BID)
            )
        ) {
            return false;
        }

        if (in_array(\ACMS_RAM::userAuth(SUID), configArray('hide_action_menu_auth'), true)) {
            return false;
        }

        return true;
    }
}
