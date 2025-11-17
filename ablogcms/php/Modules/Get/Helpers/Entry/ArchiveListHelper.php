<?php

namespace Acms\Modules\Get\Helpers\Entry;

use SQL;
use SQL_Select;
use ACMS_Filter;

class ArchiveListHelper extends EntryQueryHelper
{
    /**
     * 指定したスコープ（日付）文字長を取得する
     *
     * @param 'year'|'month'|'day'|'biz_year' $scope
     * @return integer
     */
    public function getArchiveScope(string $scope): int
    {
        switch ($scope) {
            case 'year':
                $substr = 4;
                break;
            case 'month':
                $substr = 7;
                break;
            case 'day':
                $substr = 10;
                break;
            case 'biz_year':
                $substr = 7;
                break;
        }
        return $substr;
    }

    /**
     * エントリーアーカイブリストのSQLを生成する
     *
     * @param 'asc' | 'desc' $order
     * @param integer $limit
     * @param int $scopeSubstr
     * @return SQL_Select
     */
    public function buildEntryArchiveListQuery(string $order, int $limit, int $scopeSubstr): SQL_Select
    {
        $sql = SQL::newSelect('entry');
        $sql->addLeftJoin('category', 'category_id', 'entry_category_id');
        $sql->addLeftJoin('blog', 'blog_id', 'entry_blog_id');

        if ($this->cid) {
            ACMS_Filter::categoryTree($sql, $this->cid, $this->categoryAxis);
        }
        ACMS_Filter::blogTree($sql, $this->bid, $this->blogAxis);
        ACMS_Filter::categoryStatus($sql);
        ACMS_Filter::blogStatus($sql);

        $sql->addSelect('entry_datetime');
        $sql->addSelect(SQL::newFunction('entry_datetime', ['SUBSTR', 0, $scopeSubstr]), 'entry_date');
        $sql->addSelect('entry_id', 'entry_amount', null, 'count');
        $sql->addGroup('entry_date');
        /** @var 'asc' | 'desc' $order */
        $order = $this->order;
        $sql->addOrder('entry_date', $order);

        ACMS_Filter::entrySession($sql);
        if ($this->start && $this->end) {
            ACMS_Filter::entrySpan($sql, $this->start, $this->end);
        }
        if ($this->tags) {
            ACMS_Filter::entryTag($sql, $this->tags);
        }
        if ($this->keyword) {
            ACMS_Filter::entryKeyword($sql, $this->keyword);
        }
        if ($this->Field instanceof Field_Search) { // @phpstan-ignore-line
            ACMS_Filter::entryField($sql, $this->Field);
        }
        $sql->addWhereOpr('entry_indexing', 'on');

        $sql->setLimit($limit);

        return $sql;
    }

    /**
     * 出力用のデータを構築する
     *
     * @param array $data
     * @param 'year' | 'month' | 'day' | 'biz_year' $scope
     * @return array
     */
    public function buildOutputData(array $data, string $scope): array
    {
        $outputData = [];
        foreach ($data as $row) {
            switch ($scope) {
                case 'year':
                    $entryDatetime = $row['entry_date'] . '-01-01 00:00:00';
                    $timestamp = strtotime($entryDatetime);
                    if ($timestamp === false) {
                        continue 2;
                    }
                    $y = date('Y', $timestamp);
                    $m = null;
                    $d = null;
                    break;
                case 'month':
                    $entryDatetime = $row['entry_date'] . '-01 00:00:00';
                    $timestamp = strtotime($entryDatetime);
                    if ($timestamp === false) {
                        continue 2;
                    }
                    $y = date('Y', $timestamp);
                    $m = date('m', $timestamp);
                    $d = null;
                    break;
                case 'day':
                    $entryDatetime = $row['entry_date'] . ' 00:00:00';
                    $timestamp = strtotime($entryDatetime);
                    if ($timestamp === false) {
                        continue 2;
                    }
                    $y = date('Y', $timestamp);
                    $m = date('m', $timestamp);
                    $d = date('d', $timestamp);
                    break;
                case 'biz_year':
                    $entryDatetime = $row['entry_date'] . '-01 00:00:00';
                    $timestamp = strtotime($entryDatetime);
                    if ($timestamp === false) {
                        continue 2;
                    }
                    $y = date('Y', $timestamp);
                    $m = date('m', $timestamp);
                    $d = null;
                    break;
            }
            $vars = [
                'amount' => $row['entry_amount'],
                'date' => $entryDatetime,
                'url' => acmsLink([ // @phpstan-ignore-line
                    'bid' => $this->bid,
                    'cid' => $this->cid,
                    'date' => [$y, $m, $d]
                ]),
            ];
            $outputData[] = $vars;
        }
        return $outputData;
    }
}
