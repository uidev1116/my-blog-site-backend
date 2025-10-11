<?php

use Acms\Services\Facades\Category;

class ACMS_POST_Category_Insert extends ACMS_POST_Category
{
    function post()
    {
        $Category = $this->extract('category');
        $Category->setMethod('name', 'required');
        $Category->setMethod('code', 'required');
        $Category->setMethod('code', 'double', [$Category->get('scope'),  $Category->get('parent')]);
        $Category->setMethod('code', 'reserved', !isReserved($Category->get('code')));
        $Category->setMethod('code', 'string', isValidCode($Category->get('code')));
        $Category->setMethod('status', 'required');
        $Category->setMethod('status', 'in', ['open', 'close', 'secret']);
        $Category->setMethod('scope', 'required');
        $Category->setMethod('indexing', 'required');
        $Category->setMethod('indexing', 'in', ['on', 'off']);
        $Category->setMethod('config_set_id', 'value', $this->checkConfigSetScope($Category->get('config_set_id')));
        $Category->setMethod('config_set_scope', 'in', ['local', 'global']);
        $Category->setMethod('theme_set_id', 'value', $this->checkConfigSetScope($Category->get('theme_set_id')));
        $Category->setMethod('theme_set_scope', 'in', ['local', 'global']);
        $Category->setMethod('editor_set_id', 'value', $this->checkConfigSetScope($Category->get('editor_set_id')));
        $Category->setMethod('editor_set_scope', 'in', ['local', 'global']);
        $Category->setMethod('category', 'operable', Category::canCreate(BID));

        if (sessionWithEnterpriseAdministration()) {
            $this->workflowData = $this->extractWorkflow();
        }

        // parentを指定時に、scopeがparentと同じに設定されていなければinvalid
        $Category->setMethod(
            'scope',
            'tree',
            !($pid = $Category->get('parent')) ? true : $Category->get('scope') == ACMS_RAM::categoryScope($pid)
        );

        $Category->validate(new ACMS_Validator_Category());
        $Field = $this->extract('field', new ACMS_Validator());

        if ($this->Post->isValidAll()) {
            $DB     = DB::singleton(dsn());
            $SQL    = SQL::newSelect('category');
            $SQL->addWhereOpr('category_blog_id', BID);
            $SQL->setOrder('category_right', 'DESC');
            $SQL->setLimit(1);
            if ($row = $DB->query($SQL->get(dsn()), 'row')) {
                $sort   = $row['category_sort'] + 1;
                $left   = $row['category_right'] + 1;
                $right  = $row['category_right'] + 2;
            } else {
                $sort   = 1;
                $left   = 1;
                $right  = 2;
            }

            $cid    = $DB->query(SQL::nextval('category_id', dsn()), 'seq');
            $code   = $Category->get('code');
            $name   = $Category->get('name');
            $parent = $Category->get('parent');
            $status = $Category->get('status');

            $parentStatus = ACMS_RAM::categoryStatus($parent);
            if (!empty($parentStatus) && $parentStatus !== 'open') {
                $status = $parentStatus;
            }

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

            $SQL = SQL::newInsert('category');
            $SQL->addInsert('category_id', $cid);
            $SQL->addInsert('category_parent', 0);
            $SQL->addInsert('category_sort', $sort);
            $SQL->addInsert('category_left', $left);
            $SQL->addInsert('category_right', $right);
            $SQL->addInsert('category_blog_id', BID);
            $SQL->addInsert('category_status', $status);
            $SQL->addInsert('category_name', $name);
            $SQL->addInsert('category_scope', $Category->get('scope'));
            $SQL->addInsert('category_indexing', $Category->get('indexing'));
            $SQL->addInsert('category_code', $code);
            $SQL->addInsert('category_config_set_id', $configSetId);
            $SQL->addInsert('category_config_set_scope', $Category->get('config_set_scope', 'local'));
            $SQL->addInsert('category_theme_set_id', $themeSetId);
            $SQL->addInsert('category_theme_set_scope', $Category->get('theme_set_scope', 'local'));
            $SQL->addInsert('category_editor_set_id', $editorSetId);
            $SQL->addInsert('category_editor_set_scope', $Category->get('editor_set_scope', 'local'));
            $DB->query($SQL->get(dsn()), 'exec');
            Common::saveField('cid', $cid, $Field);

            //----------
            // geometry
            $this->saveGeometry('cid', $cid, $this->extract('geometry'));

            //----------
            // workflow
            if (sessionWithEnterpriseAdministration() && $this->workflowData) {
                $this->saveWorkflow($this->workflowData, BID, $cid);
            }

            // implment ACMS_POST_Category
            $this->changeParentCategory($cid, $parent);

            $Category->set('id', $cid);

            Common::saveFulltext('cid', $cid, Common::loadCategoryFulltext($cid));
            $this->Post->set('edit', 'insert');

            AcmsLogger::info('「' . $name . '」カテゴリーの作成をしました', [
                'status' => $status,
                'name' => $name,
                'scope' => $Category->get('scope'),
                'indexing' => $Category->get('indexing'),
                'code' => $code,
                'configSetId' => $configSetId,
                'themeSetId' => $themeSetId,
                'editorSetId' => $editorSetId,
                'field' => $Field,
            ]);
        } else {
            AcmsLogger::info('カテゴリーの作成に失敗しました', [
                'category' => $Category->_aryV,
                'field' => $Field->_aryV,
            ]);
        }

        return $this->Post;
    }
}
