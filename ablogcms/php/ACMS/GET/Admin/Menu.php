<?php

use Acms\Services\Update\System\CheckForUpdate;

class ACMS_GET_Admin_Menu extends ACMS_GET_Admin
{
    function linkCheck($type)
    {
        if (
            1
            && strpos($type, '_') === false
            && $type !== 'checklist'
            && $type !== 'update'
        ) {
            $type = $type . '_';
        }
        $reg    = '/^' . $type . '/';
        $stay   = ' class="stay"';
        if ($type == 'top_' && ADMIN == 'top') {
            return $stay;
        }
        if ($type === 'config_set_theme') {
            if (ADMIN === 'config_theme') {
                return $stay;
            }
        }
        if ($type === 'config_set_editor') {
            if (preg_match('/^config_(edit|unit|bulk-change|editor)/', ADMIN)) {
                return $stay;
            }
        }
        if ($type === 'config_set_base') {
            if (preg_match('/^config_(set_theme|set_editor|theme|edit|unit|bulk-change|editor)/', ADMIN)) {
                return '';
            }
            if (preg_match('/^config_/', ADMIN)) {
                return $stay;
            }
        }
        if (preg_match($reg, ADMIN)) {
            return $stay;
        } else {
            return '';
        }
    }

    function roleAuth(&$Tpl)
    {
        $Tpl->add('dashboard', [
            'url'   => acmsLink(['admin' => 'top', 'bid' => BID]),
            'stay'  => $this->linkCheck('top'),
        ]);

        if (roleAuthorization('entry_edit', BID, EID)) {
            if (editionWithProfessional()) {
                $approval = [
                    'url'   => acmsLink(['admin' => 'approval_notification', 'bid' => BID]),
                    'stay'  => $this->linkCheck('approval_notification'),
                ];
                if ($badge = Approval::notificationCount()) {
                    $approval['badge'] = $badge;
                }
                $Tpl->add('approval#notification', $approval);
            }

            $Tpl->add('entry#index', [
                'url'   => acmsLink(['admin' => 'entry_index', 'bid' => BID]),
                'stay'  => $this->linkCheck('entry_index'),
            ]);
            $Tpl->add('entry#trash', [
                'url'   => acmsLink(['admin' => 'entry_trash', 'bid' => BID]),
                'stay'  => $this->linkCheck('entry_trash'),
            ]);
            if (IS_LICENSED) {
                $Tpl->add('entry#insert');
            }
        }

        if (roleAuthorization('category_edit', BID)) {
            $Tpl->add('category#index', [
                'url'   => acmsLink(['admin' => 'category_index', 'bid' => BID]),
                'stay'  => $this->linkCheck('category'),
            ]);
            if (IS_LICENSED) {
                $Tpl->add('category#insert', [
                    'url'   => acmsLink(['admin' => 'category_edit', 'bid' => BID]),
                    'stay'  => $this->linkCheck('category'),
                ]);
            }
        }

        if (roleAuthorization('tag_edit', BID)) {
            $Tpl->add('tag', [
                'url'   => acmsLink(['admin' => 'tag_index', 'bid' => BID]),
                'stay'  => $this->linkCheck('tag'),
            ]);
        }

        if (roleAuthorization('media_upload', BID) || roleAuthorization('media_edit', BID)) {
            if (config('media_library') === 'on') {
                $Tpl->add('media#index', [
                    'url'   => acmsLink(['bid' => BID, 'admin' => 'media_index']),
                    'stay'  => $this->linkCheck('media'),
                ]);
            }
        }

        if (roleAuthorization('rule_edit', BID)) {
            $Tpl->add('rule#index', [
                'url'   => acmsLink(['admin' => 'rule_index', 'bid' => BID]),
                'stay'  => $this->linkCheck('rule'),
            ]);
            if (IS_LICENSED) {
                $Tpl->add('rule#insert', [
                    'url'   => acmsLink(['admin' => 'rule_edit', 'bid' => BID]),
                    'stay'  => $this->linkCheck('rule'),
                ]);
            }
        }

        if (roleAuthorization('publish_edit', BID) || roleAuthorization('publish_exec', BID)) {
            $Tpl->add('publish#index', [
                'url'   => acmsLink(['bid' => BID, 'admin' => 'publish_index']),
                'stay'  => $this->linkCheck('publish'),
            ]);
        }

        if (roleAuthorization('config_edit', BID) && IS_LICENSED) {
            $Tpl->add('config#set_base_index', [
                'url'   => acmsLink(['admin' => 'config_set_base_index', 'bid' => BID]),
                'stay'  => $this->linkCheck('config_set_base'),
            ]);
            $Tpl->add('config#set_theme_index', [
                'url'   => acmsLink(['admin' => 'config_set_theme_index', 'bid' => BID]),
                'stay'  => $this->linkCheck('config_set_theme'),
            ]);
            $Tpl->add('config#set_editor_index', [
                'url'   => acmsLink(['admin' => 'config_set_editor_index', 'bid' => BID]),
                'stay'  => $this->linkCheck('config_set_editor'),
            ]);
        }

        if (roleAuthorization('module_edit', BID)) {
            $Tpl->add('module#index', [
                'url'   => acmsLink(['admin' => 'module_index', 'bid' => BID]),
                'stay'  => $this->linkCheck('module'),
            ]);
            if (IS_LICENSED) {
                $Tpl->add('module#insert', [
                    'url'   => acmsLink(['admin' => 'module_edit', 'bid' => BID]),
                    'stay'  => $this->linkCheck('module'),
                ]);
            }
        }

        if (roleAuthorization('backup_export', BID) || roleAuthorization('backup_import', BID)) {
            $Tpl->add('backup#index', [
                'url'   => acmsLink(['bid' => BID, 'admin' => 'backup_index']),
                'stay'  => $this->linkCheck('backup'),
            ]);
        }

        if (roleAuthorization('form_view', BID) || roleAuthorization('form_edit', BID)) {
            $Tpl->add('form#index', [
                'url'   => acmsLink(['bid' => BID, 'admin' => 'form_index']),
                'stay'  => $this->linkCheck('form'),
            ]);
        }

        if (roleAuthorization('admin_etc', BID)) {
            $Tpl->add('comment', [
                'url'   => acmsLink(['bid' => BID, 'admin' => 'comment_index']),
                'stay'  => $this->linkCheck('comment'),
            ]);
            $Tpl->add('blog#index', [
                'url'   => acmsLink(['admin' => 'blog_index', 'bid' => BID]),
                'stay'  => $this->linkCheck('blog_index'),
            ]);
            $Tpl->add('blog#edit', [
                'url'   => acmsLink(['admin' => 'blog_edit', 'bid' => BID]),
                'stay'  => $this->linkCheck('blog_edit'),
            ]);
            $Tpl->add('webhook#index', [
                'url'   => acmsLink(['bid' => BID, 'admin' => 'webhook_index']),
                'stay'  => $this->linkCheck('webhook'),
            ]);
            $Tpl->add('alias#index', [
                'url'   => acmsLink(['admin' => 'alias_index', 'bid' => BID]),
                'stay'  => $this->linkCheck('alias'),
            ]);
            $Tpl->add('user#index', [
                'url'   => acmsLink(['admin' => 'user_index', 'bid' => BID]),
                'stay'  => $this->linkCheck('user'),
            ]);
            $Tpl->add('member#index', [
                'url'   => acmsLink(['admin' => 'member_index', 'bid' => BID]),
                'stay'  => $this->linkCheck('member'),
            ]);
            $Tpl->add('shortcut#index', [
                'url'   => acmsLink(['admin' => 'shortcut_index', 'bid' => BID]),
                'stay'  => $this->linkCheck('shortcut'),
            ]);
            $Tpl->add('schedule#index', [
                'url'   => acmsLink(['bid' => BID, 'admin' => 'schedule_index']),
                'stay'  => $this->linkCheck('schedule'),
            ]);
            $Tpl->add('import#index', [
                'url'   => acmsLink(['bid' => BID, 'admin' => 'import_index']),
                'stay'  => $this->linkCheck('import'),
            ]);
            $Tpl->add('export#index', [
                'url'   => acmsLink(['bid' => BID, 'admin' => 'export_index']),
                'stay'  => $this->linkCheck('export'),
            ]);
            $Tpl->add('app#index', [
                'url'   => acmsLink(['bid' => BID, 'admin' => 'app_index']),
                'stay'  => $this->linkCheck('app_index'),
            ]);
            $Tpl->add('checklist', [
                'url'   => acmsLink(['bid' => BID, 'admin' => 'checklist']),
                'stay'  => $this->linkCheck('checklist'),
            ]);
            $Tpl->add('cart#menu', [
                'url'   => acmsLink(['bid' => BID, 'admin' => 'cart_menu']),
                'stay'  => $this->linkCheck('cart_menu'),
            ]);
            if (BID === RBID) {
                $Tpl->add('audit_log', [
                    'url' => acmsLink(['bid' => BID, 'admin' => 'audit_log']),
                    'stay' => $this->linkCheck('audit_log'),
                ]);
            }
            if (IS_LICENSED) {
                $Tpl->add('user#insert', [
                    'url'   => acmsLink(['admin' => 'user_edit', 'bid' => BID]),
                    'stay'  => $this->linkCheck('user#insert'),
                ]);
                if (isBlogGlobal(SBID)) {
                    $Tpl->add('blog#insert', [
                        'url'   => acmsLink([
                            'admin' => 'blog_edit',
                            'alt'   => 'insert',
                        ]),
                        'stay'  => $this->linkCheck('blog'),
                    ]);
                }
            }
        }
    }

    function normalAuth(&$Tpl)
    {
        $Tpl->add('dashboard', [
            'url'   => acmsLink(['admin' => 'top', 'bid' => BID]),
            'stay'  => $this->linkCheck('top'),
        ]);

        //--------------
        // contribution
        if (sessionWithContribution()) {
            if (editionWithProfessional()) {
                $approval = [
                    'url'   => acmsLink(['admin' => 'approval_notification', 'bid' => BID]),
                    'stay'  => $this->linkCheck('approval_notification'),
                ];
                if ($badge = Approval::notificationCount()) {
                    $approval['badge'] = $badge;
                }
                $Tpl->add('approval#notification', $approval);
            }

            $Tpl->add('entry#index', [
                'url'   => acmsLink(['admin' => 'entry_index', 'bid' => BID]),
                'stay'  => $this->linkCheck('entry_index'),
            ]);
            $Tpl->add('entry#trash', [
                'url'   => acmsLink(['admin' => 'entry_trash', 'bid' => BID]),
                'stay'  => $this->linkCheck('entry_trash'),
            ]);
            if (config('media_library') === 'on') {
                $Tpl->add('media#index', [
                    'url'   => acmsLink(['bid' => BID, 'admin' => 'media_index']),
                    'stay'  => $this->linkCheck('media'),
                ]);
            }
            if (IS_LICENSED) {
                $Tpl->add('entry#insert');
            }

            //--------------
            // compilation
            if (sessionWithCompilation()) {
                $Tpl->add('category#index', [
                    'url'   => acmsLink(['admin' => 'category_index', 'bid' => BID]),
                    'stay'  => $this->linkCheck('category'),
                ]);
                $Tpl->add('tag', [
                    'url'   => acmsLink(['admin' => 'tag_index', 'bid' => BID]),
                    'stay'  => $this->linkCheck('tag'),
                ]);
                $Tpl->add('comment', [
                    'url'   => acmsLink(['bid' => BID, 'admin' => 'comment_index']),
                    'stay'  => $this->linkCheck('comment'),
                ]);
                if (IS_LICENSED) {
                    $Tpl->add('category#insert', [
                        'url'   => acmsLink(['admin' => 'category_edit', 'bid' => BID]),
                        'stay'  => $this->linkCheck('category'),
                    ]);
                }
                if (config('media_library') === 'on') {
                    $Tpl->add('media#index', [
                        'url'   => acmsLink(['bid' => BID, 'admin' => 'media_index']),
                        'stay'  => $this->linkCheck('media'),
                    ]);
                }
                $Tpl->add('form#index', [
                    'url'   => acmsLink(['bid' => BID, 'admin' => 'form_index']),
                    'stay'  => $this->linkCheck('form'),
                ]);
                $Tpl->add('schedule#index', [
                    'url'   => acmsLink(['bid' => BID, 'admin' => 'schedule_index']),
                    'stay'  => $this->linkCheck('schedule'),
                ]);

                //----------------
                // administration
                if (sessionWithAdministration()) {
                    $Tpl->add('blog#index', [
                        'url'   => acmsLink(['admin' => 'blog_index', 'bid' => BID]),
                        'stay'  => $this->linkCheck('blog_index'),
                    ]);
                    $Tpl->add('blog#edit', [
                        'url'   => acmsLink(['admin' => 'blog_edit', 'bid' => BID]),
                        'stay'  => $this->linkCheck('blog_edit'),
                    ]);
                    $Tpl->add('alias#index', [
                        'url'   => acmsLink(['admin' => 'alias_index', 'bid' => BID]),
                        'stay'  => $this->linkCheck('alias'),
                    ]);
                    $Tpl->add('user#index', [
                        'url'   => acmsLink(['admin' => 'user_index', 'bid' => BID]),
                        'stay'  => $this->linkCheck('user'),
                    ]);
                    $Tpl->add('member#index', [
                        'url'   => acmsLink(['admin' => 'member_index', 'bid' => BID]),
                        'stay'  => $this->linkCheck('member'),
                    ]);
                    $Tpl->add('rule#index', [
                        'url'   => acmsLink(['admin' => 'rule_index', 'bid' => BID]),
                        'stay'  => $this->linkCheck('rule'),
                    ]);
                    $Tpl->add('module#index', [
                        'url'   => acmsLink(['admin' => 'module_index', 'bid' => BID]),
                        'stay'  => $this->linkCheck('module'),
                    ]);
                    $Tpl->add('shortcut#index', [
                        'url'   => acmsLink(['admin' => 'shortcut_index', 'bid' => BID]),
                        'stay'  => $this->linkCheck('shortcut'),
                    ]);
                    $Tpl->add('publish#index', [
                        'url'   => acmsLink(['bid' => BID, 'admin' => 'publish_index']),
                        'stay'  => $this->linkCheck('publish'),
                    ]);
                    $Tpl->add('backup#index', [
                        'url'   => acmsLink(['bid' => BID, 'admin' => 'backup_index']),
                        'stay'  => $this->linkCheck('backup'),
                    ]);
                    $Tpl->add('import#index', [
                        'url'   => acmsLink(['bid' => BID, 'admin' => 'import_index']),
                        'stay'  => $this->linkCheck('import'),
                    ]);
                    $Tpl->add('export#index', [
                        'url'   => acmsLink(['bid' => BID, 'admin' => 'export_index']),
                        'stay'  => $this->linkCheck('export'),
                    ]);
                    $Tpl->add('app#index', [
                        'url'   => acmsLink(['bid' => BID, 'admin' => 'app_index']),
                        'stay'  => $this->linkCheck('app_index'),
                    ]);
                    $Tpl->add('webhook#index', [
                        'url'   => acmsLink(['bid' => BID, 'admin' => 'webhook_index']),
                        'stay'  => $this->linkCheck('webhook'),
                    ]);
                    $Tpl->add('checklist', [
                        'url'   => acmsLink(['bid' => BID, 'admin' => 'checklist']),
                        'stay'  => $this->linkCheck('checklist'),
                    ]);
                    $Tpl->add('cart#menu', [
                        'url'   => acmsLink(['bid' => BID, 'admin' => 'cart_menu']),
                        'stay'  => $this->linkCheck('cart_menu'),
                    ]);
                    $Tpl->add('fix#index', [
                        'url'   => acmsLink(['admin' => 'fix_index', 'bid' => BID]),
                        'stay'  => $this->linkCheck('fix'),
                    ]);

                    if (IS_LICENSED) {
                        $Tpl->add('user#insert', [
                            'url'   => acmsLink(['admin' => 'user_edit', 'bid' => BID]),
                            'stay'  => $this->linkCheck('user#insert'),
                        ]);
                        $Tpl->add('config#set_base_index', [
                            'url'   => acmsLink(['admin' => 'config_set_base_index', 'bid' => BID]),
                            'stay'  => $this->linkCheck('config_set_base'),
                        ]);
                        $Tpl->add('config#set_theme_index', [
                            'url'   => acmsLink(['admin' => 'config_set_theme_index', 'bid' => BID]),
                            'stay'  => $this->linkCheck('config_set_theme'),
                        ]);
                        $Tpl->add('config#set_editor_index', [
                            'url'   => acmsLink(['admin' => 'config_set_editor_index', 'bid' => BID]),
                            'stay'  => $this->linkCheck('config_set_editor'),
                        ]);
                        $Tpl->add('rule#insert', [
                            'url'   => acmsLink(['admin' => 'rule_edit', 'bid' => BID]),
                            'stay'  => $this->linkCheck('rule'),
                        ]);
                        $Tpl->add('module#insert', [
                            'url'   => acmsLink(['admin' => 'module_edit', 'bid' => BID]),
                            'stay'  => $this->linkCheck('module'),
                        ]);
                    }

                    if (BID === RBID) {
                        $Tpl->add('audit_log', [
                            'url' => acmsLink(['bid' => BID, 'admin' => 'audit_log']),
                            'stay' => $this->linkCheck('audit_log'),
                        ]);
                    }

                    if (IS_LICENSED) {
                        if (isBlogGlobal(SBID)) {
                            $Tpl->add('blog#insert', [
                                'url'   => acmsLink([
                                    'admin' => 'blog_edit',
                                    'alt'   => 'insert',
                                ]),
                                'stay'  => $this->linkCheck('blog'),
                            ]);
                        }
                    }
                }
            }
        }
    }

    function get()
    {
        if (!sessionWithSubscription(BID)) {
            page404();
        }
        $Tpl = new Template($this->tpl, new ACMS_Corrector());

        //--------
        // update
        if (
            1
            && BID === RBID
            && sessionWithAdministration()
            && config('system_update_range') !== 'none'
        ) {
            $update = 0;
            $checkUpdateService = App::make('update.check');
            $range = CheckForUpdate::PATCH_VERSION;
            if (config('system_update_range') === 'minor') {
                $range = CheckForUpdate::MINOR_VERSION;
            }
            if ($checkUpdateService->checkUseCache(phpversion(), $range)) {
                $update = 1;
            }
            $Tpl->add('update', [
                'url' => acmsLink(['admin' => 'update', 'bid' => BID]),
                'stay' => $this->linkCheck('update'),
                'update' => $update,
            ]);
        }

        //--------------
        // subscription
        if (IS_LICENSED) {
            $Tpl->add('user#profile', [
                'url'   => acmsLink([
                    'bid'   => SBID,
                    'uid'   => SUID,
                    'admin' => 'user_edit',
                ]),
                'icon'  => loadUserIcon(SUID),
            ]);
        }

        //------------
        // enterprise
        if (sessionWithEnterpriseAdministration()) {
            if (BID == RBID) {
                $Tpl->add('role#index', [
                    'url'   => acmsLink(['admin' => 'role_index', 'bid' => RBID]),
                    'stay'  => $this->linkCheck('role'),
                ]);
                $Tpl->add('usergroup#index', [
                    'url'   => acmsLink(['admin' => 'usergroup_index', 'bid' => RBID]),
                    'stay'  => $this->linkCheck('usergroup'),
                ]);
                if (enableApproval()) {
                    $Tpl->add('approval#index', [
                        'url'   => acmsLink(['admin' => 'approval_index', 'bid' => RBID]),
                        'stay'  => $this->linkCheck('approval_index'),
                    ]);
                }
            }
        }

        //--------------
        // professional
        if (
            1
            && !sessionWithEnterpriseAdministration()
            && sessionWithProfessionalAdministration()
            && BID == RBID
        ) {
            $Tpl->add('approval#index', [
                'url'   => acmsLink(['admin' => 'approval_index', 'bid' => RBID]),
                'stay'  => $this->linkCheck('approval_index'),
            ]);
        }

        //----------------------------
        // professional or enterprise
        if (sessionWithProfessionalAdministration() || sessionWithEnterpriseAdministration()) {
            $Tpl->add('static_export#index', [
                'url'   => acmsLink(['admin' => 'static-export_index', 'bid' => BID]),
                'stay'  => $this->linkCheck('static-export_index'),
            ]);
        }

        if (roleAvailableUser()) {
            $this->roleAuth($Tpl);
        } else {
            $this->normalAuth($Tpl);
        }

        return $Tpl->get();
    }
}
