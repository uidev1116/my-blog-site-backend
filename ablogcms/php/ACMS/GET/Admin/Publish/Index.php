<?php

class ACMS_GET_Admin_Publish_Index extends ACMS_GET_Admin_Publish
{
    public function get()
    {
        $Tpl    = new Template($this->tpl, new ACMS_Corrector());
        if (roleAvailableUser()) {
            if (!roleAuthorization('publish_edit', BID) && !roleAuthorization('publish_exec', BID)) {
                die403();
            }
        } else {
            if (!sessionWithAdministration()) {
                die403();
            }
        }
        if (BID != 1) {
            $ParentConfig = loadConfig(ACMS_RAM::blogParent(BID));
            if ('on' != $ParentConfig->get('publish_children_allow')) {
                $Tpl->add('notAllow');
                $Tpl->add(null, ['notice_mess' => 'show']);
                return $Tpl->get();
            }
        }

        $Config =& $this->Post->getChild('config');
        $Apply  = false;
        if ($Config->isNull()) {
            $Config->overload(loadConfig(BID));
        } else {
            $Apply = true;
        }

        $resources  = $Config->getArray('publish_resource_uri');
        $layoutOnly = $Config->getArray('publish_layout_only');
        $tgtTheme   = $Config->getArray('publish_target_theme');
        $tgtPath    = $Config->getArray('publish_target_path');

        $Error      = $this->Post->getChild('error');

        $resourceCnt    = count($resources);
        $layoutOnlyCnt  = count($layoutOnly);
        $tgtThemeCnt    = count($tgtTheme);
        $tgtPathCnt     = count($tgtPath);

        $max    = min($resourceCnt, $layoutOnlyCnt, $tgtThemeCnt, $tgtPathCnt);

        for ($i = 0; $i < $max; $i++) {
            $vars   = [
                'publish_resource_uri'  => $resources[$i],
                'publish_layout_only'   => $layoutOnly[$i],
                'publish_target_theme'  => $tgtTheme[$i],
                'publish_target_path'   => $tgtPath[$i],
            ];

            $p  = md5(implode('', $vars));

            if ($Error->isExists($p)) {
                $Tpl->add($Error->get($p));
            }

            $vars['publish_target_theme:selected#' . $tgtTheme[$i]]   = config('attr_selected');
            $vars['publish_layout_only:selected#' . $layoutOnly[$i]]  = config('attr_selected');
            $Tpl->add('publish:loop', $vars);
        }

        $childAllow = $Config->get('publish_children_allow');
        $Tpl->add('allow', [
            'publish_children_allow:checked#' . $childAllow => config('attr_checked')
        ]);

        if ($Apply) {
            $Tpl->add('apply');
            $Tpl->add(null, ['notice_mess' => 'show']);
        }

        return $Tpl->get();
    }
}
