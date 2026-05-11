<?php

namespace Acms\Traits\CsvImport;

/**
 * @property array $labels
 * @property array $data
 * @property int|null $csvId
 * @property bool $isUpdate
 */
trait UpdateByFieldKey
{
    /**
     * フィールドIDカラム名を返す
     *
     * @return string
     */
    protected function getFieldIdColumn(): string
    {
        return 'field_eid';
    }

    /**
     * カスタムフィールドキーで特定したIDを適用する
     *
     * @param int $id
     * @return void
     */
    protected function applyFoundId(int $id): void
    {
        $this->csvId = $id;
        $this->isUpdate = true;
    }

    /**
     * updateKeyの検索対象から除外するラベルか判定する
     *
     * @param string $key
     * @return bool
     */
    protected function shouldSkipUpdateKeyLabel(string $key): bool
    {
        return false;
    }

    /**
     * カスタムフィールドキーによる更新対象の特定（プロフェッショナルライセンス以上）
     *
     * @return bool
     * @throws \RuntimeException
     */
    public function updateKey(): bool
    {
        if (!editionWithProfessional()) {
            return false;
        }
        $updateKey = null;
        foreach ($this->labels as $key) {
            if ($this->shouldSkipUpdateKeyLabel($key)) {
                continue;
            }
            if (preg_match('/^\*/', $key)) {
                $updateKey = ltrim($key, '*');
                break;
            }
        }
        if ($updateKey === null || !isset($this->data['*' . $updateKey])) {
            return false;
        }
        $DB = \DB::singleton(dsn());
        $SQL = \SQL::newSelect('field');
        $SQL->addSelect($this->getFieldIdColumn());
        $SQL->addWhereOpr('field_blog_id', BID);
        $SQL->addWhereOpr('field_key', $updateKey);
        $SQL->addWhereOpr('field_value', $this->data['*' . $updateKey]);
        $all = $DB->query($SQL->get(dsn()), 'all');

        if (count($all) === 1) {
            $this->applyFoundId((int) $all[0][$this->getFieldIdColumn()]);
        } elseif (count($all) > 1) {
            throw new \RuntimeException('重複するキーがあったため途中で処理を中止しました。');
        }
        return true;
    }
}
