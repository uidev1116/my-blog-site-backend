<?php

/**
 * SQL_Where
 *
 * SQLヘルパのSequenceメソッド群です。
 *
 * @package php
 */
class SQL_Sequence extends SQL
{
    /**
     * @var 'nextval'|'currval'|'setval'|'optimize'
     */
    public $_method    = 'nextval';

    /**
     * @var string|null
     */
    public $_sequence  = null;

    /**
     * @var int|null
     */
    public $_value     = null;

    /**
     * @var bool
     */
    public $_plugin    = false;

    /**
     * @param string $seq
     * @return true
     */
    public function setSequence($seq)
    {
        $this->_sequence    = $seq;
        return true;
    }

    /**
     * @param 'nextval'|'currval'|'setval'|'optimize' $method
     * @return true
     */
    public function setMethod($method)
    {
        $this->_method  = $method;
        return true;
    }

    /**
     * @param int $val
     * @return true
     */
    public function setValue($val)
    {
        $this->_value   = $val;
        return true;
    }

    /**
     * @param bool $plugin
     * @return true
     */
    public function setPluginFlag($plugin)
    {
        $this->_plugin  = $plugin;
        return true;
    }

    /**
     * @inheritDoc
     */
    public function get($dsn = null)
    {
        if (!$this->_sequence) {
            throw new InvalidArgumentException('Sequence name is not set for SQL_Sequence');
        }
        $tb = ($this->_plugin) ? 'sequence_plugin' : 'sequence';
        $fd = 'sequence_' . $this->_sequence;

        $q  = '';
        switch ($this->_method) {
            case 'optimize':
                $table = substr($this->_sequence, 0, -3);
                $SUB = SQL::newSelect($table);
                $SUB->setSelect($this->_sequence);
                $SUB->setLimit(1);
                $SUB->setOrder($this->_sequence, 'DESC');
                $SQL = SQL::newUpdate($tb);
                if ($this->_plugin) {
                    $SQL->addUpdate('sequence_plugin_value', $SUB);
                    $SQL->addWhereOpr('sequence_plugin_key', $fd);
                } else {
                    $SQL->setUpdate($fd, $SUB);
                }
                $q = $SQL->get($dsn);
                break;
            case 'currval':
                $SQL    = SQL::newSelect($tb);
                if ($this->_plugin) {
                    $SQL->setSelect('sequence_plugin_value');
                    $SQL->addWhereOpr('sequence_plugin_key', $fd);
                } else {
                    $SQL->setSelect($fd);
                }
                $q  = $SQL->get($dsn);
                break;
            case 'setval':
                $SQL    = SQL::newUpdate($tb);
                if ($this->_plugin) {
                    $SQL->addUpdate('sequence_plugin_value', $this->_value);
                    $SQL->addWhereOpr('sequence_plugin_key', $fd);
                } else {
                    $SQL->setUpdate($fd, $this->_value);
                }
                $q  = $SQL->get($dsn);
                break;
            case 'nextval':
            default:
                $SQL    = SQL::newUpdate($tb);
                if ($this->_plugin) {
                    $SQL->addUpdate('sequence_plugin_value', SQL::newFunction(SQL::newOpr('sequence_plugin_value', 1, '+'), 'LAST_INSERT_ID'));
                    $SQL->addWhereOpr('sequence_plugin_key', $fd);
                } else {
                    $SQL->setUpdate(
                        $fd, //SQL::newOpr($fd, 1, '+')
                        SQL::newFunction(SQL::newOpr($fd, 1, '+'), 'LAST_INSERT_ID')
                    );
                }
                $q  = $SQL->get($dsn);
                break;
        }

        return $q;
    }
}
