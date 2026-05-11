<?php

class ACMS_POST_Blog_Update extends ACMS_POST_Blog
{
    function post()
    {
        $Blog = $this->extract('blog');
        $deleteField = new Field();
        $Field  = $this->extract('field', new ACMS_Validator(), $deleteField);
        $Config = $this->extract('config', new ACMS_Validator());
        $this->validate($Blog, $Field, $Config);

        if (sessionWithEnterpriseAdministration()) {
            $this->workflowData = $this->extractWorkflow();
        }

        if ($this->Post->isValidAll()) {
            $DB = DB::singleton(dsn());

            $status = $Blog->get('status');
            if ('open' <> $status) {
                $aryStatus  = ['open'];
                if ('close' == $status) {
                    $aryStatus[]  = 'secret';
                }
                $SQL    = SQL::newUpdate('blog');
                $SQL->setUpdate('blog_status', $status);
                $SQL->addWhereIn('blog_status', $aryStatus);
                $SQL->addWhereOpr('blog_left', ACMS_RAM::blogLeft(BID), '>');
                $SQL->addWhereOpr('blog_right', ACMS_RAM::blogRight(BID), '<');
                $DB->query($SQL->get(dsn()), 'exec');
            }

            $configSetId = $Blog->get('config_set_id') ?: null;
            $themeSetId = $Blog->get('theme_set_id') ?: null;
            $editorSetId = $Blog->get('editor_set_id') ?: null;

            if (empty($configSetId)) {
                $Blog->set('config_set_scope', 'local');
            }
            if (empty($themeSetId)) {
                $Blog->set('theme_set_scope', 'local');
            }
            if (empty($editorSetId)) {
                $Blog->set('editor_set_scope', 'local');
            }

            $SQL = SQL::newUpdate('blog');
            $SQL->addUpdate('blog_status', $status);
            $SQL->addUpdate('blog_name', $Blog->get('name'));
            $SQL->addUpdate('blog_code', trim(strval($Blog->get('code')), '/'));
            $SQL->addUpdate('blog_domain', $Blog->get('domain'));
            $SQL->addUpdate('blog_indexing', strval($Blog->get('indexing')));
            $SQL->addUpdate('blog_config_set_id', $configSetId);
            $SQL->addUpdate('blog_config_set_scope', $Blog->get('config_set_scope', 'local'));
            $SQL->addUpdate('blog_theme_set_id', $themeSetId);
            $SQL->addUpdate('blog_theme_set_scope', $Blog->get('theme_set_scope', 'local'));
            $SQL->addUpdate('blog_editor_set_id', $editorSetId);
            $SQL->addUpdate('blog_editor_set_scope', $Blog->get('editor_set_scope', 'local'));
            $SQL->addWhereOpr('blog_id', BID);
            $DB->query($SQL->get(dsn()), 'exec');

            Cache::flush('temp');

            //-------
            // field
            Common::saveField('bid', BID, $Field, $deleteField);

            //--------
            // config
            Config::saveConfig($Config, BID);
            Config::set('blog_theme_color', $Config->get('blog_theme_color'));
            Config::set('blog_theme_logo@squarePath', $Config->get('blog_theme_logo@squarePath'));


            //----------
            // geometry
            $this->saveGeometry('bid', BID, $this->extract('geometry'));

            //----------
            // workflow
            if (sessionWithEnterpriseAdministration() && $this->workflowData) {
                $this->saveWorkflow($this->workflowData, BID);
            }

            //------------
            // update RAM
            $SQL    = SQL::newSelect('blog');
            $SQL->addWhereOpr('blog_id', BID);
            if ($row = $DB->query($SQL->get(dsn()), 'row')) {
                ACMS_RAM::blog(BID, $row);
            }

            //-------------
            // for display
            $Blog->set('id', BID);

            $this->Post->set('edit', 'update');
            Common::saveFulltext('bid', BID, Common::loadBlogFulltext(BID));

            AcmsLogger::info('「' . ACMS_RAM::blogName(BID) . '」ブログを更新しました');
        } else {
            AcmsLogger::info('ブログの更新に失敗しました', [
                'blogValidator' => $Blog->_aryV,
                'fieldValidator' => $Field->_aryV,
                'configValidator' => $Config->_aryV,
            ]);
        }

        return $this->Post;
    }

    /**
     *  バリデート
     *
     * @param \Field_Validation $Blog
     * @param \Field_Validation $Field
     * @param \Field_Validation $Config
     */
    protected function validate(
        \Field_Validation $Blog,
        \Field_Validation $Field,
        \Field_Validation $Config
    ) {
        $Blog->setMethod('status', 'required');
        $Blog->setMethod('status', 'in', ['open', 'close', 'secret']);
        $Blog->setMethod('status', 'status', Blog::isValidStatus($Blog->get('status'), true));
        $Blog->setMethod('status', 'root', !(RBID == BID and 'close' == $Blog->get('status')));
        $Blog->setMethod('name', 'required');
        $Blog->setMethod('name', 'maxlength', '255');
        $Blog->setMethod('domain', 'required');
        $Blog->setMethod('domain', 'maxlength', '128');
        $Blog->setMethod('domain', 'domain', Blog::isDomain($Blog->get('domain'), $this->Get->get('aid'), false, true));
        $Blog->setMethod('code', 'maxlength', '255');
        $Blog->setMethod('code', 'exists', Blog::isCodeExists($Blog->get('domain'), $Blog->get('code'), BID));
        $Blog->setMethod('code', 'reserved', !isReserved($Blog->get('code')));
        if ($Blog->get('code') !== '') {
            $Blog->setMethod('code', 'string', isValidCode($Blog->get('code'), allowSlash: true));
        }
        $Blog->setMethod('config_set_id', 'value', $this->checkConfigSetScope($Blog->get('config_set_id')));
        $Blog->setMethod('config_set_scope', 'in', ['local', 'global']);
        $Blog->setMethod('theme_set_id', 'value', $this->checkConfigSetScope($Blog->get('theme_set_id')));
        $Blog->setMethod('theme_set_scope', 'in', ['local', 'global']);
        $Blog->setMethod('editor_set_id', 'value', $this->checkConfigSetScope($Blog->get('editor_set_id')));
        $Blog->setMethod('editor_set_scope', 'in', ['local', 'global']);
        $Blog->setMethod('indexing', 'required');
        $Blog->setMethod('indexing', 'in', ['on', 'off']);
        $Blog->setMethod('blog', 'operable', $this->isOperable());
        $Blog->validate(new ACMS_Validator());
        $Field->validate(new ACMS_Validator());
        $Config->validate(new ACMS_Validator());
    }

    /**
     * ブログの更新が可能なユーザーかどうか
     */
    protected function isOperable(): bool
    {
        if (sessionWithAdministration()) {
            return true;
        }

        if (Auth::checkShortcut(['bid' => BID])) {
            return true;
        }

        return false;
    }
}
