<?php

namespace Acms\Services\Contracts;

use DB;
use Symfony\Component\Yaml\Yaml;

class Export
{
    /**
     * @var array
     */
    protected $tables;

    /**
     * set export tables
     *
     * @param array $tables
     *
     * @return void
     *
     * @throws \RuntimeException
     */
    public function setTables($tables = [])
    {
        if (!is_array($tables)) {
            throw new \RuntimeException('Not specified tables.');
        }
        $this->tables = $tables;
    }

    /**
     * @param string $txt
     * @return string
     */
    protected function fixPath($txt)
    {
        return preg_replace('@(001)/(.*)\.([^\.]{2,6})@ui', '001/$2.$3', $txt, -1);
    }

    /**
     * carriage returns \r and \r\n
     * Paragraph Separator (U+2028)
     * Line Separator (U+2029)
     * Next Line (NEL) (U+0085)
     *
     * @param $txt
     * @return string
     */
    protected function fixNextLine($txt)
    {
        return preg_replace('/(\xe2\x80[\xa8-\xa9]|\xc2\x85|\r\n|\r)/', "\n", $txt);
    }

    /**
     * @param resource $fp
     * @param array $queryList
     */
    protected function dumpYaml($fp, $queryList)
    {
        $db = DB::singleton(dsn());

        foreach ($queryList as $table => $q) {
            $statement = $db->query($q, 'exec', false);
            fwrite($fp, "$table:\n");
            while ($row = $db->next($statement)) {
                $this->fix($row, $table);
                $record = Yaml::dump(['dummy' => $row], 1);
                if ($record) {
                    $record = $this->fixYaml($record);
                    fwrite($fp, str_replace('dummy:', '    -', $record));
                }
            }
        }
    }

    /**
     * fix data
     *
     * @param array &$record
     * @param string $table
     *
     * @return void
     */
    protected function fix(&$record, $table)
    {
    }

    /**
     * @param string $txt
     * @return string
     */
    protected function fixYaml($txt)
    {
        return $this->fixPath($txt);
    }
}
