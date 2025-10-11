<?php

namespace Acms\Services\Config;

use SQL;
use DB;
use ACMS_RAM;
use Config;
use Symfony\Component\Yaml\Yaml;

class Export
{
    /**
     * @var array
     */
    protected $yaml;

    /**
     * @var string
     */
    protected $metaIds;

    /**
     * @var int
     */
    protected $bid;

    /**
     * @var string
     */
    protected $metaTargetIds;

    /**
     * Export constructor.
     */
    public function __construct()
    {
        $this->yaml = [];
        $this->metaTargetIds = '/^(.*)_(bid|uid|cid|eid|aid)$/';
    }

    /**
     * @param \Field $field
     */
    public function exportPartsConfig($field)
    {
        $config = [];
        foreach ($field->listFields() as $fd) {
            $val = $field->getArray($fd);
            $config[$fd] = (1 == count($val)) ? $val[0] : $val;
        }
        $this->yaml = $config;
    }

    /**
     * export config data
     *
     * @param int $bid
     *
     * @return void
     */
    public function exportAll($bid)
    {
        $this->bid = $bid;

        if (empty($this->bid)) {
            return;
        }

        // config set
        $this->buildConfigSetYaml();

        // config
        $this->buildConfigYaml();

        // rule
        $this->buildRuleYaml();

        // module
        $this->buildModuleYaml();

        // field
        $this->buildFieldYaml();

        // banner mid
        $SQL = SQL::newSelect('module');
        $SQL->addSelect('module_id');
        $SQL->addWhereOpr('module_name', 'Banner');
        $SQL->addWhereOpr('module_blog_id', $this->bid);
        $list = DB::query($SQL->get(dsn()), 'list');
        $this->yaml['meta']['banner'] = $list;
    }

    /**
     * export default config data
     *
     * @param int $bid
     *
     * @return void
     */
    public function exportDefaultConfig($bid)
    {
        $Config = Config::load($bid, null, null, null);

        $config = [];
        foreach ($Config->listFields() as $fd) {
            $val = $Config->getArray($fd);
            $config[$fd] = (1 == count($val)) ? $val[0] : $val;
        }

        $this->yaml = $config;
    }

    /**
     * get data as array
     *
     * @return array
     */
    public function getArray()
    {
        return $this->yaml;
    }

    /**
     * get data as yaml
     *
     * @return string
     */
    public function getYaml()
    {
        return Yaml::dump($this->yaml, 2, 4);
    }

    protected function buildConfigSetYaml()
    {
        $SQL = SQL::newSelect('config_set');
        $SQL->addWhereOpr('config_set_blog_id', $this->bid);
        $q = $SQL->get(dsn());
        $statement = DB::query($q, 'exec');
        $records = [];

        while ($r = DB::next($statement)) {
            $records[] = $r;
        }
        $this->setYaml($records, 'config_set');
    }

    /**
     * @return void
     */
    protected function buildConfigYaml()
    {
        $SQL = SQL::newSelect('config');
        $SQL->addWhereOpr('config_blog_id', $this->bid);
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
    protected function buildRuleYaml()
    {
        $SQL = SQL::newSelect('rule');
        $SQL->addWhereOpr('rule_blog_id', $this->bid);
        $q = $SQL->get(dsn());
        $statement = DB::query($q, 'exec');
        $records = [];

        while ($r = DB::next($statement)) {
            $this->extractMetaIds($r);
            $records[] = $r;
        }
        $this->setYaml($records, 'rule');
    }

    /**
     * @return void
     */
    protected function buildModuleYaml()
    {
        $SQL = SQL::newSelect('module');
        $SQL->addWhereOpr('module_blog_id', $this->bid);
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
        $SQL->addWhereOpr('field_blog_id', $this->bid);
        $SQL->addWhereOpr('field_mid', null, '<>');
        $field = DB::query($SQL->get(dsn()), 'all');
        $this->setYaml($field, 'field');
    }

    /**
     * meta data を抽出
     *
     * @param array $record
     */
    protected function extractMetaIds($record)
    {
        foreach ($record as $key => $data) {
            if (!!$data && preg_match($this->metaTargetIds, $key, $matches)) {
                $type = $matches[2];
                if (is_numeric($data)) {
                    $this->yaml['meta'][$type][$data] = $this->getCode($type, $data);
                }
            }
        }
    }

    /**
     * get code for id
     *
     * @param string $type
     * @param int $id
     *
     * @return string|false
     */
    protected function getCode($type, $id)
    {
        switch ($type) {
            case 'bid':
                return ACMS_RAM::blogCode($id);
                break;
            case 'uid':
                return ACMS_RAM::userCode($id);
                break;
            case 'cid':
                return ACMS_RAM::categoryCode($id);
                break;
            case 'eid':
                return ACMS_RAM::entryCode($id);
                break;
            case 'aid':
                return ACMS_RAM::aliasCode($id);
                break;
            default:
                break;
        }
        return false;
    }

    /**
     * set data as yaml
     *
     * @param array $records
     * @param string $table
     *
     * @return void
     */
    protected function setYaml($records, $table)
    {
        if (isset($this->yaml[$table])) {
            $this->yaml[$table] = array_merge($this->yaml[$table], $records);
        } else {
            $this->yaml[$table] = $records;
        }
    }
}
