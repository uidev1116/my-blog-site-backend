<?php

use Acms\Services\Facades\Category;
use Acms\Services\Facades\Auth;

class ACMS_GET_Admin_Category_Edit extends ACMS_GET_Admin_Edit
{
    function auth()
    {
        /** @var int|null $categoryId */
        $categoryId = CID;
        if (is_null($categoryId)) {
            if (Category::canCreate(BID)) {
                return true;
            }
            die403();
        }
        if (Category::canUpdate(BID)) {
            return true;
        }

        if (Auth::checkShortcut(['cid' => $categoryId])) {
            return true;
        }
        die403();
    }

    function edit(&$Tpl)
    {
        $Category   =& $this->Post->getChild('category');
        $Field      =& $this->Post->getChild('field');
        $Geo        =& $this->Post->getChild('geometry');

        if (sessionWithEnterpriseAdministration()) {
            $workflow =& $this->Post->getChild('workflow');

            if (CID) {
                if ($workflow->isNull()) {
                    $workflow->overload(loadWorkflow(BID, CID, false));
                }
            }
        }

        if ($Category->isNull()) {
            if (CID) {
                $Category->overload(loadCategory(CID));
                $Field->overload(loadCategoryField(CID));
                $Geo->overload(loadGeometry('cid', CID));
            } else {
                $Category->set('status', 'open');
                $Category->set('scope', 'local');
                $Category->set('indexing', 'on');
                if ($pcid = $this->Get->get('pcid')) {
                    $Category->set('parent', $pcid);
                }
            }
        }
        if (!!($pid = $Category->get('parent')) && $pid != 0) {
            $Category->set('parent_name', ACMS_RAM::categoryName($pid));
            $Category->set('parent_code', ACMS_RAM::categoryCode($pid));
        }
        //if ( !isBlogGlobal(BID) ) $Category->setField('scope', null);

        return true;
    }
}
