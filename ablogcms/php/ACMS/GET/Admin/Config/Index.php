<?php

use Acms\Services\Facades\Config;

class ACMS_GET_Admin_Config_Index extends ACMS_GET_Admin
{
    public function get()
    {
        if (!Config::canViewIndex(BID)) {
            die403();
        }
        $rid = intval($this->Get->get('rid'));
        $setid = intval($this->Get->get('setid'));
        $Tpl = new Template($this->tpl, new ACMS_Corrector());

        $aryAdmin = [
            'config_function', 'config_cache', 'config_login', 'config_api', 'config_output', 'config_property',
            'config_mail', 'config_access',
            'config_edit', 'config_unit', 'config_block-editor', 'config_bulk-change',
            'config_import', 'config_import-part', 'config_export', 'config_reset',
            'config_common', 'config_system-error', 'styleguide_acms', 'styleguide_acms-admin',
            'customfield_maker', 'i18n_index', 'config_index', 'config_admin-menu',
            'config_deprecated_theme', 'config_deprecated_edit', 'config_deprecated_unit',
            'config_deprecated_bulk-change',
        ];

        //--------
        // module
        $aryModule = [
            'Entry_Body',
            'Entry_List',
            'Entry_Photo',
            'Entry_Headline',
            'Entry_Summary',
            'Entry_ArchiveList',
            'Entry_TagRelational',
            'Entry_Continue',
            'Entry_Calendar',
            'Entry_GeoList',
            'Category_List',
            'Category_EntryList',
            'Category_EntrySummary',
            'Category_GeoList',
            'Unit_List',
            'Tag_Cloud',
            'Tag_Filter',
            'Calendar_Month',
            'Calendar_Year',
            'Topicpath',
            'Comment_Body',
            'Comment_List',
            'User_Profile',
            'User_Search',
            'User_GeoList',
            'Blog_ChildList',
            'Blog_GeoList',
            'Json_2Tpl',
            'Feed_Rss2',
            'Feed_ExList',
            'Sitemap',
            'Ogp',
            'Shop2_Cart_List',
            'Links',
            'Banner',
            'Media_Banner',
            'Navigation',
            'Plugin_Schedule',
            'Schedule',
            'Alias_List',
            'Field_ValueList',
        ];

        foreach ($aryModule as $module) {
            if ($module = preg_replace('@(?<=[a-zA-Z0-9])([A-Z])@', '-$1', $module)) {
                $aryAdmin[] = 'config_' . strtolower($module);
            }
        }

        foreach ($aryAdmin as $admin) {
            $AP = [
                'bid'   => BID,
                'admin' => $admin,
            ];
            if ($rid || $setid) {
                $AP['query'] = [
                    'rid' => $rid,
                    'setid' => $setid,
                ];
            }
            $Tpl->add($admin, [
                'url'   => acmsLink($AP),
            ]);
        }

        return $Tpl->get();
    }
}
