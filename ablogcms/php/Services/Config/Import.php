<?php

namespace Acms\Services\Config;

use SQL;
use DB;
use Module;
use Common;
use Config;
use Cache;
use AcmsLogger;

class Import
{
    /**
     * @var array
     */
    protected $yaml;

    /**
     * @var int
     */
    protected $bid;

    /**
     * @var array
     */
    protected $meta;

    /**
     * @var array
     */
    protected $failedMeta;

    /**
     * @var array
     */
    protected $failedContents;

    /**
     * @var array
     */
    protected $newIDs;

    /**
     * @var array
     */
    protected $oldModules;

    /**
     * import config from yaml
     *
     * @param int $bid
     * @param array $yaml
     *
     * @return void
     *
     * @throws \Exception
     */
    public function run($bid, $yaml)
    {
        if (!$this->checkAuth()) {
            AcmsLogger::info('権限がないため、コンフィグのインポートに失敗しました');
            die403();
        }
        $this->yaml = $yaml;
        $this->bid = $bid;
        $this->failedMeta = [];

        try {
            $this->valid();
            $this->oldModule();
            $this->dropData();
            $this->fixSequence();
            $this->registerNewIDs();
            $this->import();
            $this->fixException();
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * @return array
     */
    public function getFailedContents()
    {
        return $this->failedContents;
    }

    /**
     * fix sequence
     *
     * @return void
     */
    protected function fixSequence()
    {
        DB::query(SQL::optimizeSeq('config_set_id', dsn()), 'seq');
        DB::query(SQL::optimizeSeq('module_id', dsn()), 'seq');
        DB::query(SQL::optimizeSeq('rule_id', dsn()), 'seq');
    }

    /**
     * import
     *
     * @return void
     */
    protected function import()
    {
        $this->insertData('config_set');
        $this->insertData('rule');
        $this->insertData('module');
        $this->insertData('config');
        $this->insertData('field');
    }

    /**
     * 古いモジュールIDを変換用に一時保存
     *
     * @return void
     */
    protected function oldModule()
    {
        $SQL = SQL::newSelect('module');
        $SQL->addWhereOpr('module_blog_id', $this->bid);
        $all = DB::query($SQL->get(dsn()), 'all');

        foreach ($all as $module) {
            $this->oldModules[$module['module_id']] = $module['module_identifier'];
        }
    }

    /**
     * 古いモジュールIDから新しいモジュールIDを取得
     *
     * @param int $old_mid
     * @return int
     */
    protected function getNewModuleId($old_mid)
    {
        if (!isset($this->oldModules[$old_mid])) {
            return $old_mid;
        }
        $identifier = $this->oldModules[$old_mid];

        $SQL = SQL::newSelect('module');
        $SQL->addSelect('module_id');
        $SQL->addWhereOpr('module_identifier', $identifier);
        $SQL->addWhereOpr('module_blog_id', $this->bid);

        if ($new = DB::query($SQL->get(dsn()), 'one')) {
            return $new;
        }
        return $old_mid;
    }

    /**
     * バナーモジュールとモジュールフィールドのファイル・画像のmidを更新
     *
     * @return void
     */
    protected function fixException()
    {
        // banner
        $SQL = SQL::newSelect('config');
        $SQL->addSelect('config_module_id');
        $SQL->addWhereOpr('config_module_id', null, '<>');
        $SQL->addWhereOpr('config_blog_id', $this->bid);
        $mids = DB::query($SQL->get(dsn()), 'list');
        foreach ($mids as $mid) {
            $SQL = SQL::newUpdate('config');
            $SQL->addUpdate('config_module_id', $this->getNewModuleId($mid));
            $SQL->addWhereOpr('config_module_id', $mid);
            $SQL->addWhereOpr('config_blog_id', $this->bid);
            DB::query($SQL->get(dsn()), 'exec');
            Config::forgetCache(BID, null, $mid);
        }

        // module filed
        $SQL = SQL::newSelect('field');
        $SQL->addSelect('field_mid');
        $SQL->addWhereOpr('field_key', '%@%', 'LIKE');
        $SQL->addWhereOpr('field_blog_id', $this->bid);
        $SQL->addWhereOpr('field_mid', null, '<>');
        $mids = DB::query($SQL->get(dsn()), 'list');
        foreach ($mids as $mid) {
            $SQL = SQL::newUpdate('field');
            $SQL->addUpdate('field_mid', $this->getNewModuleId($mid));
            $SQL->addWhereOpr('field_mid', $mid);
            $SQL->addWhereOpr('field_key', '%@%', 'LIKE');
            $SQL->addWhereOpr('field_blog_id', $this->bid);
            DB::query($SQL->get(dsn()), 'exec');
            Common::deleteFieldCache('mid', $mid);
        }

        // Layout Module
        $SQL = SQL::newSelect('layout_grid');
        $all = DB::query($SQL->get(dsn()), 'all');
        foreach ($all as $row) {
            $SQL = SQL::newUpdate('layout_grid');
            $SQL->addUpdate('layout_grid_mid', $this->getNewModuleId($row['layout_grid_mid']));
            $SQL->addWhereOpr('layout_grid_parent', $row['layout_grid_parent']);
            $SQL->addWhereOpr('layout_grid_col', $row['layout_grid_col']);
            $SQL->addWhereOpr('layout_grid_row', $row['layout_grid_row']);
            DB::query($SQL->get(dsn()), 'exec');
        }

        // Module unit
        $SQL = SQL::newSelect('column');
        $SQL->addWhereOpr('column_type', 'module%', 'LIKE');
        $all = DB::query($SQL->get(dsn()), 'all');
        foreach ($all as $row) {
            $SQL = SQL::newUpdate('column');
            $SQL->addUpdate('column_field_1', $this->getNewModuleId($row['column_field_1']));
            $SQL->addWhereOpr('column_id', (string)$row['column_id']);
            DB::query($SQL->get(dsn()), 'exec');
        }

        $SQL = SQL::newSelect('column_rev');
        $SQL->addWhereOpr('column_type', 'module%', 'LIKE');
        $all = DB::query($SQL->get(dsn()), 'all');
        foreach ($all as $row) {
            $SQL = SQL::newUpdate('column_rev');
            $SQL->addUpdate('column_field_1', $this->getNewModuleId($row['column_field_1']));
            $SQL->addWhereOpr('column_id', (string)$row['column_id']);
            $SQL->addWhereOpr('column_rev_id', $row['column_rev_id']);
            DB::query($SQL->get(dsn()), 'exec');
        }
    }

    /**
     * validate data
     *
     * @return void
     */
    protected function valid()
    {
        if (!isset($this->yaml['meta'])) {
            return;
        }

        foreach ($this->yaml['meta'] as $type => $item) {
            foreach ($item as $id => $code) {
                if ($new_id = $this->getIdFromCode($type, $code)) {
                    $this->meta[$type][$id] = $new_id;
                }
            }
        }
    }

    /**
     * drop data
     *
     * @return void
     */
    protected function dropData()
    {
        // config_set
        if (isset($this->yaml['config_set'])) {
            $SQL = SQL::newDelete('config_set');
            $SQL->addWhereOpr('config_set_blog_id', $this->bid);
            DB::query($SQL->get(dsn()), 'exec');
        }

        // config
        if (isset($this->yaml['config'])) {
            $SQL = SQL::newSelect('module');
            $SQL->addSelect('module_id');
            $SQL->addWhereOpr('module_name', 'Banner');
            $SQL->addWhereOpr('module_blog_id', $this->bid);
            $banner_mids = DB::query($SQL->get(dsn()), 'list');

            $SQL = SQL::newDelete('config');
            $WHERE = SQL::newWhere();
            $WHERE->addWhereOpr('config_module_id', null, '=', 'OR');
            $WHERE->addWhereNotIn('config_module_id', $banner_mids, 'OR');
            $SQL->addWhere($WHERE);
            $SQL->addWhereOpr('config_blog_id', $this->bid);
            DB::query($SQL->get(dsn()), 'exec');
        }

        // rule
        if (isset($this->yaml['rule'])) {
            $SQL = SQL::newDelete('rule');
            $SQL->addWhereOpr('rule_blog_id', $this->bid);
            DB::query($SQL->get(dsn()), 'exec');
        }

        // module
        if (isset($this->yaml['module'])) {
            $SQL = SQL::newDelete('module');
            $SQL->addWhereOpr('module_blog_id', $this->bid);
            DB::query($SQL->get(dsn()), 'exec');
        }

        // module field
        if (isset($this->yaml['field'])) {
            $SQL = SQL::newDelete('field');
            $SQL->addWhereOpr('field_key', '%@%', 'NOT LIKE');
            $SQL->addWhereOpr('field_blog_id', $this->bid);
            $SQL->addWhereOpr('field_mid', null, '<>');
            DB::query($SQL->get(dsn()), 'exec');
        }

        Config::cacheClear();
        Cache::flush('temp');
    }

    /**
     * @return void
     */
    protected function registerNewIDs()
    {
        $tables = [
            'config_set', 'rule', 'module'
        ];

        foreach ($tables as $table) {
            $this->registerNewID($table);
        }
    }

    /**
     * @param string $table
     *
     * @return void
     */
    protected function registerNewID($table)
    {
        if (!$this->existsYaml($table)) {
            return;
        }
        foreach ($this->yaml[$table] as $record) {
            if (!isset($record[$table . '_id'])) {
                continue;
            }
            $id = $record[$table . '_id'];
            if (isset($this->newIDs[$table][$id])) {
                continue;
            }
            $this->newIDs[$table][$id] = DB::query(SQL::nextval($table . '_id', dsn()), 'seq');
        }
    }

    /**
     * @param string $table
     *
     * @return void
     */
    protected function insertData($table)
    {
        if (!$this->existsYaml($table)) {
            return;
        }
        foreach ($this->yaml[$table] as $record) {
            $SQL = SQL::newInsert($table);
            $id = 0;
            foreach ($record as $field => $value) {
                $method = $table . 'Fix';
                if (is_callable([$this, $method])) {
                    /** @var callable $callback */
                    $callback = [$this, $method];
                    $value = call_user_func_array($callback, [$field, $value]);
                }
                if ($value !== false) {
                    $SQL->addInsert($field, $value);
                }
                if (substr($field, strlen($table . '_')) === 'id') {
                    $id = $value;
                }
            }
            if ($table === 'field' && preg_match('/[^@]+@[^@]+/', $record['field_key'])) {
                continue; // モジュールフィールドのファイル、画像はインポートしない
            }
            if ($table === 'config') {
                $mid = $record['config_module_id'];
                // @phpstan-ignore-next-line
                if (!empty($mid) && isset($this->yaml['meta']['banner']) && in_array($mid, $this->yaml['meta']['banner'])) {
                    continue; // バナーモジュールのコンテンツはインポートしない
                }
            }
            if ($table === 'module') {
                $identifier = isset($record['module_identifier']) ? $record['module_identifier'] : '';
                $scope = isset($record['module_scope']) ? $record['module_scope'] : 'local';
                if (!Module::double($identifier, $id, $scope)) {
                    $this->failedContents[] = [
                        'table' => 'module',
                        'type' => 'overlap',
                        'id' => $id,
                        'identifier' => $identifier,
                    ];
                    $this->failedMeta = [];
                    continue;
                }
            }
            if (!empty($this->failedMeta)) {
                $this->failedContents[] = [
                    'table' => $table,
                    'type' => 'conversion',
                    'id' => $id,
                    'unlink' => $this->failedMeta,
                ];
            }
            $this->failedMeta = [];

            DB::query($SQL->get(dsn()), 'exec');
        }
    }

    /**
     * config_set テーブルの修正
     *
     * @param string $field
     * @param mixed $value
     *
     * @return mixed
     */
    protected function config_setFix($field, $value)
    {
        if ($field === 'config_set_id') {
            $value = $this->getNewID('config_set', $value);
        } elseif ($field === 'config_set_blog_id') {
            $value = $this->bid;
        }
        return $value;
    }

    /**
     * config テーブルの修正
     *
     * @param string $field
     * @param mixed $value
     *
     * @return mixed
     */
    protected function configFix($field, $value)
    {
        if ($field === 'config_set_id') {
            $value = $this->getNewID('config_set', $value);
        } elseif ($field === 'config_rule_id') {
            $value = $this->getNewID('rule', $value);
        } elseif ($field === 'config_module_id') {
            $value = $this->getNewID('module', $value);
        } elseif ($field === 'config_blog_id') {
            $value = $this->bid;
        }
        return $value;
    }

    /**
     * rule テーブルの修正
     *
     * @param string $field
     * @param mixed $value
     *
     * @return mixed
     */
    protected function ruleFix($field, $value)
    {
        if ($field === 'rule_id') {
            $value = $this->getNewID('rule', $value);
        } elseif ($field === 'rule_uid') {
            $value = $this->getCurrentID('uid', $value);
        } elseif ($field === 'rule_cid') {
            $value = $this->getCurrentID('cid', $value);
        } elseif ($field === 'rule_eid') {
            $value = $this->getCurrentID('eid', $value);
        } elseif ($field === 'rule_aid') {
            $value = $this->getCurrentID('aid', $value);
        } elseif ($field === 'rule_blog_id') {
            $value = $this->bid;
        }
        return $value;
    }

    /**
     * module テーブルの修正
     *
     * @param string $field
     * @param mixed $value
     *
     * @return mixed
     */
    protected function moduleFix($field, $value)
    {
        if ($field === 'module_id') {
            $value = $this->getNewID('module', $value);
        } elseif ($field === 'module_bid') {
            $value = $this->getCurrentID('bid', $value);
        } elseif ($field === 'module_uid') {
            $value = $this->getCurrentID('uid', $value);
        } elseif ($field === 'module_cid') {
            $value = $this->getCurrentID('cid', $value);
        } elseif ($field === 'module_eid') {
            $value = $this->getCurrentID('eid', $value);
        } elseif ($field === 'module_blog_id') {
            $value = $this->bid;
        }
        return $value;
    }

    /**
     * フィールド テーブルの修正
     *
     * @param string $field
     * @param mixed $value
     *
     * @return int
     */
    protected function fieldFix($field, $value)
    {
        if ($field === 'field_mid') {
            $value = $this->getNewID('module', $value);
        } elseif ($field === 'field_blog_id') {
            $value = $this->bid;
        }
        return $value;
    }

    /**
     * check yaml data
     *
     * @param $table
     *
     * @return bool
     */
    protected function existsYaml($table)
    {
        if (!isset($this->yaml[$table])) {
            return false;
        }
        $data = $this->yaml[$table];
        if (!is_array($data)) {
            return false;
        }
        return true;
    }

    /**
     * @param string $table
     * @param int $id
     *
     * @return int|null
     */
    protected function getNewID($table, $id)
    {
        if (empty($id)) {
            return null;
        }
        if (!isset($this->newIDs[$table][$id])) {
            return $id;
        }
        return $this->newIDs[$table][$id];
    }

    /**
     * @param string $type
     * @param int $id
     *
     * @return int|null
     */
    protected function getCurrentID($type, $id)
    {
        if (!is_numeric($id)) {
            return $id;
        }
        if (empty($id)) {
            return null;
        }
        if (!isset($this->meta[$type][$id])) {
            $code = isset($this->yaml['meta'][$type][$id]) ? $this->yaml['meta'][$type][$id] : 'unknown';
            $this->failedMeta[] = [
                'type' => $type,
                'code' => $code,
            ];
            return null;
        }
        return $this->meta[$type][$id];
    }

    /**
     * get id from code
     *
     * @param string $type
     * @param string $code
     * @param bool $hierarchy
     *
     * @return int | bool
     */
    protected function getIdFromCode($type, $code, $hierarchy = false)
    {
        $SQL = false;
        switch ($type) {
            case 'bid':
                $SQL = SQL::newSelect('blog');
                $SQL->setSelect('blog_id');
                $SQL->addWhereOpr('blog_code', $code);
                break;
            case 'uid':
                $SQL = SQL::newSelect('user');
                $SQL->setSelect('user_id');
                $SQL->addWhereOpr('user_code', $code);
                if (!$hierarchy) {
                    $SQL->addWhereOpr('user_blog_id', $this->bid);
                }
                break;
            case 'cid':
                $SQL = SQL::newSelect('category');
                $SQL->setSelect('category_id');
                $SQL->addWhereOpr('category_code', $code);
                if (!$hierarchy) {
                    $SQL->addWhereOpr('category_blog_id', $this->bid);
                }
                break;
            case 'eid':
                $SQL = SQL::newSelect('entry');
                $SQL->setSelect('entry_id');
                $SQL->addWhereOpr('entry_code', $code);
                if (!$hierarchy) {
                    $SQL->addWhereOpr('entry_blog_id', $this->bid);
                }
                break;
            case 'aid':
                $SQL = SQL::newSelect('alias');
                $SQL->setSelect('alias_id');
                $SQL->addWhereOpr('alias_domain', $code);
                $SQL->addWhereOpr('alias_code', $code);
                if (!$hierarchy) {
                    $SQL->addWhereOpr('alias_blog_id', $this->bid);
                }
                break;
            default:
                return false;
                break;
        }

        $ids = DB::query($SQL->get(dsn()), 'all');
        if (count($ids) > 1) {
            return false;
        }
        if ($id = DB::query($SQL->get(dsn()), 'one')) {
            return $id;
        }
        // idが見つからなかった場合、その他のブログから検索
        if (!$hierarchy) {
            return $this->getIdFromCode($type, $code, true);
        }
        return false;
    }

    /**
     * check auth
     *
     * @return bool
     */
    private function checkAuth()
    {
        return sessionWithAdministration();
    }
}
