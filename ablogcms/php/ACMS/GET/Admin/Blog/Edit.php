<?php

class ACMS_GET_Admin_Blog_Edit extends ACMS_GET_Admin_Edit
{
    public function auth()
    {
        if (!sessionWithAdministration() && !Auth::checkShortcut(['bid' => BID])) {
            die403();
        }
        return true;
    }

    function edit(&$Tpl)
    {
        $Blog =& $this->Post->getChild('blog');
        $Field =& $this->Post->getChild('field');
        $Config =& $this->Post->getChild('config');
        $Geo =& $this->Post->getChild('geometry');

        if (sessionWithEnterpriseAdministration()) {
            $workflow =& $this->Post->getChild('workflow');

            if ('insert' <> $this->edit) {
                if ($workflow->isNull()) {
                    $workflow->overload(loadWorkflow(BID, null, false));
                }
            }
        }

        if ($Blog->isNull()) {
            if ('insert' <> $this->edit) {
                $Blog->overload(loadBlog(BID));
                $Field->overload(loadBlogField(BID));
                $Config->overload(Config::loadBlogField(BID));
                $Geo->overload(loadGeometry('bid', BID));
            } else {
                //---------
                // default
                $Blog->set('domain', DOMAIN);
                $Blog->set('status', 'open');
                $Blog->set('indexing', 'on');
            }
        }
        if ($this->Post->get('import') === 'success') {
            $Tpl->add('success');
        }

        return true;
    }
}
