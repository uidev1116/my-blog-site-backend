<?php

use Acms\Services\Facades\Module;

class ACMS_POST_Module extends ACMS_POST
{
    function fix(&$Module)
    {
        if ($Module->get('eid')) {
            if ($Module->get('eid') == strval(intval($Module->get('eid')))) {
                if ($bid = ACMS_RAM::entryBlog($Module->get('eid'))) {
                    $Module->setField('bid', $bid);
                    if ($cid = ACMS_RAM::entryCategory($Module->get('eid'))) {
                        $Module->setField('cid', $cid);
                    } else {
                        $Module->setField('cid');
                    }
                } else {
                    $Module->setValidator('eid', 'exists', false);
                }
            } elseif (strpos($Module->get('eid'), ',') !== false) {
                $aryBid = [];
                $aryCid = [];
                $Module->setField('cid');
                foreach (explode(',', $Module->get('eid')) as $_eid) {
                    if ($_bid = ACMS_RAM::entryBlog($_eid)) {
                        $aryBid[] = $_bid;
                        if ($_cid = ACMS_RAM::entryCategory($_eid)) {
                            $aryCid[] = $_cid;
                        }
                    } else {
                        $Module->setValidator('eid', 'exists', false);
                    }
                }
                $aryBid = array_unique($aryBid);
                $aryCid = array_unique($aryCid);
                if (!empty($aryBid)) {
                    $Module->setField('bid', implode(',', $aryBid));
                }
                if (!empty($aryCid)) {
                    $Module->setField('cid', implode(',', $aryCid));
                }
            }
        }

        if (strpos($Module->get('bid'), ',') !== false) {
            $Module->set('bid_axis', 'self');
        }
        if (strpos($Module->get('cid'), ',') !== false) {
            $Module->set('cid_axis', 'self');
        }

        if ($Module->get('start_date') and !$Module->get('end_date')) {
            $Module->set('end_date', '9999-12-31');
        }
        if ($Module->get('end_date') and !$Module->get('start_date')) {
            $Module->set('start_date', '1000-01-01');
        }
        if ($Module->get('start_date') and !$Module->get('start_time')) {
            $Module->set('start_time', '00:00:00');
        }
        if ($Module->get('end_date') and !$Module->get('end_time')) {
            $Module->set('end_time', '23:59:59');
        }

        return true;
    }

    /**
     * ToDo: deprecated method 2.7.0
     */
    function double($identifier, $mid, $scope, $bid = BID)
    {
        return Module::double($identifier, $mid, $scope, $bid);
    }

    /**
     * ToDo: deprecated method 2.7.0
     */
    function dup($mid)
    {
        return Module::dup($mid);
    }
}

class ACMS_Validator_Module extends ACMS_Validator
{
    function double($identifier, $arg)
    {
        $scope  = isset($arg[0]) ? $arg[0] : 'local';
        $mid    = isset($arg[1]) ? $arg[1] : null;

        return Module::double($identifier, $mid, $scope);
    }

    function intOrGlobalVars($val)
    {
        return ( 0
            or empty($val)
            or $val == strval(intval($val))
            or $val <> setGlobalVars($val)
        ) ? true : false;
    }

    function safeName($val)
    {
        if ($val === '' || $val === null) {
            return true;
        }
        if (!is_string($val)) {
            return false;
        }
        return Module::isSafeModuleName($val);
    }
}
