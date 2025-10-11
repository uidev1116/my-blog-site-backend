<?php

use Acms\Services\Facades\Module;
use Acms\Services\Facades\Common;
use Acms\Services\Facades\Database as DB;
use Acms\Services\Facades\Logger;

class ACMS_POST_Module_Insert extends ACMS_POST_Module
{
    public function post()
    {
        $this->Post->set('module', [
            'name', 'status', 'identifier', 'label', 'description', 'cache', 'scope', 'custom_field', 'layout_use', 'api_use',
            'bid', 'uid', 'cid', 'eid', 'keyword', 'tag', 'field_',
            'start_date', 'start_time', 'end_date', 'end_time',
            'page', 'order',
            'uid_scope', 'cid_scope', 'eid_scope', 'keyword_scope', 'tag_scope', 'field_scope',
            'start_scope', 'end_scope', 'page_scope', 'order_scope',
            'bid_axis', 'cid_axis',
        ]);
        $Module = $this->extract('module');

        $Module->setMethod('name', 'required');
        $Module->setMethod('name', 'safeName');
        $Module->setMethod('module', 'invalidLicense', IS_LICENSED);
        $Module->setMethod('identifier', 'double', [$Module->get('scope') ?: 'local']);
        $Module->setMethod('label', 'required');

        if (!Module::isAllowedMultipleArguments($Module)) {
            $Module->setMethod('bid', 'intOrGlobalVars');
            $Module->setMethod('uid', 'intOrGlobalVars');
            $Module->setMethod('cid', 'intOrGlobalVars');
            $Module->setMethod('eid', 'intOrGlobalVars');
        }
        $Module->setMethod('page', 'intOrGlobalVars');

        $Module->setMethod('module', 'operative', Module::canCreate(BID));


        $Module->validate(new ACMS_Validator_Module());
        $this->fix($Module);

        $Field = $this->extract('field', new ACMS_Validator());

        if ($this->Post->isValidAll()) {
            $DB = DB::singleton(dsn());
            /** @var int $mid */
            $mid = $DB->query(SQL::nextval('module_id', dsn()), 'seq');
            $start = $Module->get('start_date') ? ($Module->get('start_date') . ' ' . $Module->get('start_time')) : null;
            $end = $Module->get('end_date') ? ($Module->get('end_date') . ' ' . $Module->get('end_time')) : null;
            $SQL = SQL::newInsert('module');
            $SQL->addInsert('module_id', $mid);
            $SQL->addInsert('module_blog_id', BID);
            $SQL->addInsert('module_name', $Module->get('name'));
            $SQL->addInsert('module_identifier', strval($Module->get('identifier')));
            $SQL->addInsert('module_label', $Module->get('label'));
            $SQL->addInsert('module_description', strval($Module->get('description')));
            $SQL->addInsert('module_status', $Module->get('status', 'open'));
            $SQL->addInsert('module_scope', $Module->get('scope') ?: 'local');
            $SQL->addInsert('module_cache', intval($Module->get('cache', 0)));
            $SQL->addInsert('module_bid', $Module->get('bid'));
            $SQL->addInsert('module_uid', $Module->get('uid'));
            $SQL->addInsert('module_cid', $Module->get('cid'));
            $SQL->addInsert('module_eid', $Module->get('eid'));
            $SQL->addInsert('module_keyword', $Module->get('keyword'));
            $SQL->addInsert('module_tag', $Module->get('tag'));
            $SQL->addInsert('module_field', $Module->get('field_'));
            $SQL->addInsert('module_start', $start);
            $SQL->addInsert('module_end', $end);
            $SQL->addInsert('module_page', $Module->get('page'));
            $SQL->addInsert('module_order', $Module->get('order'));
            $SQL->addInsert('module_uid_scope', $Module->get('uid_scope'));
            $SQL->addInsert('module_cid_scope', $Module->get('cid_scope'));
            $SQL->addInsert('module_eid_scope', $Module->get('eid_scope'));
            $SQL->addInsert('module_keyword_scope', $Module->get('keyword_scope'));
            $SQL->addInsert('module_tag_scope', $Module->get('tag_scope'));
            $SQL->addInsert('module_field_scope', $Module->get('field_scope'));
            $SQL->addInsert('module_start_scope', $Module->get('start_scope'));
            $SQL->addInsert('module_end_scope', $Module->get('end_scope'));
            $SQL->addInsert('module_page_scope', $Module->get('page_scope'));
            $SQL->addInsert('module_order_scope', $Module->get('order_scope'));
            $SQL->addInsert('module_bid_axis', $Module->get('bid_axis'));
            $SQL->addInsert('module_cid_axis', $Module->get('cid_axis'));
            $SQL->addInsert('module_custom_field', $Module->get('custom_field', 1));
            $SQL->addInsert('module_layout_use', $Module->get('layout_use', 1));
            $SQL->addInsert('module_api_use', $Module->get('api_use', 'off'));
            $SQL->addInsert('module_created_datetime', date('Y-m-d H:i:s', REQUEST_TIME));
            $SQL->addInsert('module_updated_datetime', date('Y-m-d H:i:s', REQUEST_TIME));

            $DB->query($SQL->get(dsn()), 'exec');

            //-------
            // field
            Common::saveField('mid', $mid, $Field);

            $Module->set('id', $mid);
            $this->Post->set('edit', 'insert');

            $key    = 'admin';
            $val    = 'module_edit';
            if (ADMIN !== 'module_edit') {
                $key    = 'tpl';
                $val    = 'ajax/module/edit.html';
            }

            Logger::info('「' . $Module->get('label') . '（' . $Module->get('identifier') . '）」モジュールを作成しました', [
                'mid' => $mid,
                'module' => $Module->_aryField,
            ]);

            /** @phpstan-ignore argument.type */
            $this->redirect(acmsLink([
                'bid'   => BID,
                $key    => $val,
                'query' => [
                    'mid'   => $mid,
                    'edit'  => 'update',
                    'msg'   => 'new',
                ],
            ]));
        } else {
            $this->Post->set('validate', true);

            Logger::info('モジュールの作成に失敗しました', [
                'module' => $Module,
                'field' => $Field,
            ]);
        }

        return $this->Post;
    }
}
