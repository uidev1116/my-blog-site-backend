<?php

use Acms\Services\Facades\Logger;
use Acms\Services\Facades\Common;

class ACMS_GET_Admin_Field_ValuesGroupJson extends ACMS_GET
{
    public function get()
    {
        try {
            $this->validate($this->Get);
        } catch (\Throwable $th) {
            Logger::error($th->getMessage(), Common::exceptionArray($th));
            return Common::responseJson([
                'status' => 'failure',
                'message' => $th->getMessage(),
            ]);
        }
        /** @var 'entry' | 'category' | 'blog' | 'user' | 'module' $type */
        $type = $this->Get->get('type');
        $keys = $this->Get->getArray('key');
        $data = $this->find($type, $keys);

        return Common::responseJson([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    /**
     * パラメータのバリデート
     * @param \Field $get
     * @return void
     * @throws \InvalidArgumentException
     */
    protected function validate(\Field $get): void
    {
        if (empty($get->get('type'))) {
            throw new \InvalidArgumentException('type パラメーターがありません');
        }
        if (!in_array($get->get('type'), ['entry', 'category', 'blog', 'user', 'module'], true)) {
            throw new \InvalidArgumentException('type パラメーターが不正です');
        }
    }

    /**
     * sqlを組み立てる
     * @param 'entry' | 'category' | 'blog' | 'user' | 'module' $type
     * @param string[] $keys
     * @return SQL_Select
     */
    protected function buildSql(string $type, array $keys): SQL_Select
    {
        $sql = SQL::newSelect('field');
        $sql->addSelect('field_key');
        $sql->addSelect('field_value');
        $sql->addWhereIn('field_key', $keys);
        $sql->addWhereOpr($this->toColumnName($type), null, '!=');
        $sql->addGroup('field_key');
        $sql->addGroup('field_value');
        return $sql;
    }

    /**
     * データを取得する
     * @param 'entry' | 'category' | 'blog' | 'user' | 'module' $type
     * @param string[] $keys
     * @return array{ string: string[] }
     */
    protected function find(string $type, array $keys): array
    {
        $sql = $this->buildSql($type, $keys);
        $rows = DB::query($sql->get(dsn()), 'all');

        $data = [];
        foreach ($rows as $row) {
            $key = $row['field_key'];
            $value = $row['field_value'];
            if (!isset($data[$key])) {
                $data[$key] = [];
            }
            $data[$key][] = $value;
        }

        return $data;
    }

    /**
     * タイプをカラム名に変換
     * @param 'entry' | 'category' | 'blog' | 'user' | 'module' $type
     * @return 'field_eid' | 'field_cid' | 'field_uid' | 'field_bid' | 'field_mid'
     */
    private function toColumnName(string $type): string
    {
        switch ($type) {
            case 'entry':
                return 'field_eid';
            case 'category':
                return 'field_cid';
            case 'blog':
                return 'field_bid';
            case 'user':
                return 'field_uid';
            case 'module':
                return 'field_mid';
            default:
                throw new \InvalidArgumentException('type パラメーターが不正です');
        }
    }
}
