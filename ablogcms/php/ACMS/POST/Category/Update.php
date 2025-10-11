<?php

use Acms\Services\Facades\Category;

class ACMS_POST_Category_Update extends ACMS_POST_Category
{
    protected function isScopeShared()
    {
        $sql = SQL::newSelect('entry');
        $sql->setSelect('entry_id');
        $sql->addWhereOpr('entry_category_id', CID);
        $sql->addWhereOpr('entry_blog_id', BID, '<>');
        $sql->setLimit(1);
        return !!DB::query($sql->get(dsn()), 'one');
    }

    protected function isSelf(int $cid, int $pid)
    {
        return $cid !== $pid;
    }

    protected function isChild(int $cid, int $pid)
    {
        $sql = SQL::newSelect('category');
        $sql->addWhereOpr('category_id', $pid);
        $sql->addWhereOpr('category_blog_id', BID);
        $sql->addWhereOpr('category_left', ACMS_RAM::categoryLeft($cid), '>');
        $sql->addWhereOpr('category_right', ACMS_RAM::categoryRight($cid), '<');
        return !(DB::query($sql->get(dsn()), 'one'));
    }

    public function post()
    {
        $Category = $this->extract('category');
        $Field = $this->extract('field', new ACMS_Validator());

        if (sessionWithEnterpriseAdministration()) {
            $this->workflowData = $this->extractWorkflow();
        }

        $this->validate($Category, $Field);

        if ($this->Post->isValidAll()) {
            $DB = DB::singleton(dsn());

            $name   = $Category->get('name');
            $code   = $Category->get('code');
            $status = $Category->get('status');
            $scope  = $Category->get('scope');
            $parent = $Category->get('parent');

            $parentStatus = ACMS_RAM::categoryStatus($parent);
            if (!empty($parentStatus) && $parentStatus !== 'open') {
                $status = $parentStatus;
            }
            if ($status !== 'open') {
                $SQL = SQL::newUpdate('category');
                $SQL->setUpdate('category_status', $status);
                $SQL->addWhereOpr('category_blog_id', BID);
                $SQL->addWhereOpr('category_left', ACMS_RAM::categoryLeft(CID), '>');
                $SQL->addWhereOpr('category_right', ACMS_RAM::categoryRight(CID), '<');
                $DB->query($SQL->get(dsn()), 'exec');
            }

            $SQL = SQL::newUpdate('category');
            $SQL->setUpdate('category_scope', $scope);
            $SQL->addWhereOpr('category_blog_id', BID);
            $SQL->addWhereOpr('category_left', ACMS_RAM::categoryLeft(CID), '>');
            $SQL->addWhereOpr('category_right', ACMS_RAM::categoryRight(CID), '<');
            $DB->query($SQL->get(dsn()), 'exec');

            $configSetId = $Category->get('config_set_id') ?: null;
            $themeSetId = $Category->get('theme_set_id') ?: null;
            $editorSetId = $Category->get('editor_set_id') ?: null;

            if (empty($configSetId)) {
                $Category->set('config_set_scope', 'local');
            }
            if (empty($themeSetId)) {
                $Category->set('theme_set_scope', 'local');
            }
            if (empty($editorSetId)) {
                $Category->set('editor_set_scope', 'local');
            }

            $SQL  = SQL::newUpdate('category');
            $SQL->addUpdate('category_status', $status);
            $SQL->addUpdate('category_name', $name);
            $SQL->addUpdate('category_scope', $scope);
            $SQL->addUpdate('category_indexing', $Category->get('indexing'));
            $SQL->addUpdate('category_code', $code);
            $SQL->addUpdate('category_config_set_id', $configSetId);
            $SQL->addUpdate('category_config_set_scope', $Category->get('config_set_scope', 'local'));
            $SQL->addUpdate('category_theme_set_id', $themeSetId);
            $SQL->addUpdate('category_theme_set_scope', $Category->get('theme_set_scope', 'local'));
            $SQL->addUpdate('category_editor_set_id', $editorSetId);
            $SQL->addUpdate('category_editor_set_scope', $Category->get('editor_set_scope', 'local'));
            $SQL->addWhereOpr('category_id', CID);
            $DB->query($SQL->get(dsn()), 'exec');
            Common::saveField('cid', CID, $Field);
            //----------
            // geometry
            $this->saveGeometry('cid', CID, $this->extract('geometry'));
            $this->changeParentCategory(CID, $parent);

            //----------
            // workflow
            if (sessionWithEnterpriseAdministration() && $this->workflowData) {
                $this->saveWorkflow($this->workflowData, BID, CID);
            }
            AcmsLogger::info('「' . ACMS_RAM::categoryName(CID) . '」カテゴリーの更新をしました', [
                'status' => $status,
                'name' => $name,
                'scope' => $scope,
                'indexing' => $Category->get('indexing'),
                'code' => $code,
                'configSetId' => $configSetId,
                'themeSetId' => $themeSetId,
                'editorSetId' => $editorSetId,
                'field' => $Field,
            ]);
        } else {
            AcmsLogger::info('「' . ACMS_RAM::categoryName(CID) . '」カテゴリーの更新に失敗しました', [
                'category' => $Category->_aryV,
                'field' => $Field->_aryV,
            ]);
        }
        $Category->setField('id', CID);
        Common::saveFulltext('cid', CID, Common::loadCategoryFulltext(CID));
        Cache::flush('temp');

        $this->Post->set('edit', 'update');

        return $this->Post;
    }

    /**
     *  バリデート
     *
     * @param \Field_Validation $Category
     * @param \Field_Validation $Field
     */
    protected function validate(
        \Field_Validation $Category,
        \Field_Validation $Field
    ) {
        $Category->setMethod('name', 'required');
        $Category->setMethod('code', 'required');
        $Category->setMethod('code', 'double', [$Category->get('scope'), $Category->get('parent'), CID]);
        $Category->setMethod('code', 'reserved', !isReserved($Category->get('code')));
        $Category->setMethod('code', 'string', isValidCode($Category->get('code')));
        $Category->setMethod('status', 'required');
        $Category->setMethod('status', 'in', ['open', 'close', 'secret']);
        $Category->setMethod('status', 'status');
        $Category->setMethod('scope', 'required');
        $Category->setMethod('indexing', 'required');
        $Category->setMethod('indexing', 'in', ['on', 'off']);
        $Category->setMethod('config_set_id', 'value', $this->checkConfigSetScope($Category->get('config_set_id')));
        $Category->setMethod('config_set_scope', 'in', ['local', 'global']);
        $Category->setMethod('theme_set_id', 'value', $this->checkConfigSetScope($Category->get('theme_set_id')));
        $Category->setMethod('theme_set_scope', 'in', ['local', 'global']);
        $Category->setMethod('editor_set_id', 'value', $this->checkConfigSetScope($Category->get('editor_set_id')));
        $Category->setMethod('editor_set_scope', 'in', ['local', 'global']);
        $Category->setMethod('category', 'operable', $this->isOperable());
        // scopeをlocalに変更しようとしたとき、すでに子ブログのエントリーが登録されていればinvalid
        if ($Category->get('scope') === 'local') {
            $Category->setMethod(
                'scope',
                'shared',
                !$this->isScopeShared()
            );
        }
        // parentを指定時に、scopeがparentと同じに設定されていなければinvalid
        $pid = intval($Category->get('parent'));
        if ($pid > 0) {
            $Category->setMethod(
                'scope',
                'tree',
                $Category->get('scope') === ACMS_RAM::categoryScope($pid)
            );
        }
        // 自分自身を親カテゴリーに設定しようとするとinvalid
        $Category->setMethod('parent', 'isSelf', $this->isSelf(CID, $pid));
        // 子カテゴリーをparentに設定しようとするとinvalid
        $Category->setMethod('parent', 'isChild', $this->isChild(CID, $pid));

        $Category->validate(new ACMS_Validator_Category());
        $Field->validate(new ACMS_Validator());
    }

    /**
     * カテゴリーの更新が可能なユーザーかどうか
     *
     */
    protected function isOperable(): bool
    {
        /** @var int<1, max>|null $categoryId */
        $categoryId = CID;
        if (is_null($categoryId)) {
            return false;
        }

        if (Category::canUpdate(BID)) {
            return true;
        }

        if ($this->shortcutAuthorization()) {
            return true;
        }

        return false;
    }

    /**
     *  ショートカットによる認可チェック
     *
     * @return bool
     */
    protected function shortcutAuthorization(): bool
    {
        return Auth::checkShortcut(['cid' => CID]);
    }
}
