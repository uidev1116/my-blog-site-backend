<?php

namespace Acms\Services\Config;

use SQL;
use Acms\Services\Facades\Database as DB;

class ModuleExport extends Export
{
    /**
     * @var int
     */
    protected $mid;

    /**
     * export module data
     *
     * @param int $mid
     *
     * @return void
     */
    public function exportModule($mid)
    {
        $this->mid = $mid;

        if (empty($this->mid)) {
            return;
        }

        // config
        $this->buildConfigYaml();

        // module
        $this->buildModuleYaml();

        // field
        $this->buildFieldYaml();
    }

    /**
     * @return void
     */
    protected function buildConfigYaml()
    {
        $SQL = SQL::newSelect('config');
        $SQL->addWhereOpr('config_module_id', $this->mid);
        $SQL->addWhereOpr('config_rule_id', null);
        $q = $SQL->get(dsn());
        $statement = DB::query($q, 'exec');
        $records = [];

        while ($r = DB::next($statement)) {
            $this->extractMetaIds($r);
            $records[] = $r;
        }
        $this->setYaml($records, 'config');
    }

    /**
     * @return void
     */
    protected function buildModuleYaml()
    {
        $SQL = SQL::newSelect('module');
        $SQL->addWhereOpr('module_id', $this->mid);
        $q = $SQL->get(dsn());
        $statement = DB::query($q, 'exec');
        $records = [];

        while ($r = DB::next($statement)) {
            $this->extractMetaIds($r);
            $records[] = $r;
        }
        $this->setYaml($records, 'module');
    }

    /**
     * @return void
     */
    protected function buildFieldYaml()
    {
        $SQL = SQL::newSelect('field');
        $SQL->addWhereOpr('field_mid', $this->mid);
        $field = DB::query($SQL->get(dsn()), 'all');
        $this->setYaml($field, 'field');
    }
}
