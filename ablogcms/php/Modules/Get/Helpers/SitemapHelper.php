<?php

namespace Acms\Modules\Get\Helpers;

use Acms\Modules\Get\Helpers\BaseHelper;
use Acms\Services\Facades\Database;
use Acms\Services\Facades\Config;
use ACMS_Filter;
use Field_Search;
use SQL;

class SitemapHelper extends BaseHelper
{
    /**
     * サイトマップのアイテムを取得する
     *
     * @param boolean $blogIndexing
     * @param string $blogOrder
     * @param Field_Search $blogFieldSearch
     * @param boolean $categoryIndexing
     * @param string $categoryOrder
     * @param Field_Search $categoryFieldSearch
     * @param boolean $entryIndexing
     * @param string $entryOrder
     * @param integer $entryLimit
     * @param Field_Search $entryFieldSearch
     * @return array<array{
     *  loc: string,
     *  lastmod: string|null,
     * }>
     */
    public function getSitemap(
        bool $blogIndexing,
        string $blogOrder,
        Field_Search $blogFieldSearch,
        bool $categoryIndexing,
        string $categoryOrder,
        Field_Search $categoryFieldSearch,
        bool $entryIndexing,
        string $entryOrder,
        int $entryLimit,
        Field_Search $entryFieldSearch
    ): array {
        $items = [];
        $exceptBlogIds = $this->getExceptBlogIds($this->bid);
        $blogData = $this->getSitemapBlogs(
            $exceptBlogIds,
            $blogIndexing,
            $blogOrder,
            $blogFieldSearch
        );

        foreach ($blogData as $blog) {
            $bid = (int) $blog['blog_id'];
            $url = acmsLink([
                'bid' => $bid,
            ], false);
            if (!$url) {
                continue;
            }
            $items["key-{$bid}"] = [
                'loc' => $url,
                'lastmod' => $this->getLastModifiedEntry($bid, null, $entryIndexing),
            ];
            $categoryData = $this->getSitemapCategories(
                $bid,
                $categoryIndexing,
                $categoryOrder,
                $categoryFieldSearch
            );

            $cidAry = [];
            foreach ($categoryData as $category) {
                $cid = (int) $category['category_id'];
                $cidAry[] = $cid;
                $url = acmsLink([
                    'bid' => $bid,
                    'cid' => $cid,
                ], false);
                if (!$url) {
                    continue;
                }
                $items["key-{$bid}-{$cid}"] = [
                    'loc' => $url,
                    'lastmod' => $this->getLastModifiedEntry($bid, $cid, $entryIndexing),
                ];
            }
            $entryData = $this->getSitemapEntries(
                $bid,
                $cidAry,
                $entryIndexing,
                $entryOrder,
                $entryLimit,
                $entryFieldSearch,
            );
            foreach ($entryData as $entry) {
                $eid = (int) $entry['entry_id'];
                $cid = (int) $entry['entry_category_id'];
                $key = "key-{$bid}-{$cid}";
                if ($entry['entry_code'] !== '') {
                    $key = "{$key}-{$eid}";
                }
                $url = acmsLink([
                    'bid' => $bid,
                    'cid' => $cid,
                    'eid' => $eid,
                ], false);
                if (!$url) {
                    continue;
                }
                $items[$key] = [
                    'loc' => $url,
                    'lastmod' => $this->formatLastModified($entry['entry_updated_datetime']),
                ];
            }
        }
        return array_values($items);
    }

    /**
     * 除外するブログIDを取得する
     *
     * @param integer $targetBid
     * @return integer[]
     */
    protected function getExceptBlogIds(int $targetBid): array
    {
        $sql = SQL::newSelect('blog');
        $sql->addSelect('blog_id');
        ACMS_Filter::blogTree($sql, $targetBid, $this->blogAxis);
        $q = $sql->get(dsn());
        $blogArray  = Database::query($q, 'all');

        return array_values(array_filter(array_column($blogArray, 'blog_id'), function ($bid) {
            return Config::loadBlogConfigSet($bid)->get('feed_output_disable') === 'on';
        }));
    }

    /**
     * サイトマップに出力するブログを取得する
     *
     * @param integer[] $exceptBlog
     * @param bool $indexing
     * @param string $order
     * @param Field_Search $fieldSearch
     * @return array
     */
    protected function getSitemapBlogs(array $exceptBlog, bool $indexing, string $order, Field_Search $fieldSearch): array
    {
        $sql = SQL::newSelect('blog');
        $sql->setSelect('blog_id');
        ACMS_Filter::blogStatus($sql);
        ACMS_Filter::blogTree($sql, $this->bid, $this->blogAxis);
        ACMS_Filter::blogField($sql, $fieldSearch);
        if ($indexing) {
            $sql->addWhereOpr('blog_indexing', 'on');
        }
        $sql->addWhereNotIn('blog_id', $exceptBlog); // config（feed_output_disable）で指定されたブログを除外
        ACMS_Filter::blogOrder($sql, $order);
        $q = $sql->get(dsn());
        return Database::query($q, 'all');
    }

    /**
     * サイトマップに出力するカテゴリーを取得する
     *
     * @param integer $bid
     * @param Field_Search $fieldSearch
     * @return array
     */
    protected function getSitemapCategories(int $bid, bool $indexing, string $order, Field_Search $fieldSearch): array
    {
        $sql = SQL::newSelect('category');
        $sql->setSelect('category_id');
        $sql->addLeftJoin('blog', 'blog_id', 'category_blog_id');

        ACMS_Filter::blogTree($sql, $bid, 'ancestor-or-self');
        ACMS_Filter::categoryStatus($sql);
        ACMS_Filter::categoryField($sql, $fieldSearch);
        $where = SQL::newWhere();
        $where->addWhereOpr('category_blog_id', $bid, '=', 'OR');
        $where->addWhereOpr('category_scope', 'global', '=', 'OR');
        $sql->addWhere($where);
        if ($indexing) {
            $sql->addWhereOpr('category_indexing', 'on');
        }
        list($sort) = explode('-', $order);
        if ($sort === 'amount') {
            $sql->addLeftJoin('entry', 'entry_category_id', 'category_id');
            $where = SQL::newWhere();
            ACMS_Filter::entrySession($where);
            $case = SQL::newCase();
            $case->add($where, 1);
            $case->setElse('NULL');
            $sql->addSelect($case, 'category_entry_amount', null, 'count');
            $sql->setGroup('category_id');
        }
        ACMS_Filter::categoryOrder($sql, $order);
        $q = $sql->get(dsn());

        return Database::query($q, 'all');
    }

    /**
     * サイトマップに出力するカテゴリーを取得する
     *
     * @param integer $bid
     * @param Field_Search $fieldSearch
     * @return array
     */
    protected function getSitemapEntries(int $bid, array $cidAry, bool $indexing, string $order, int $limit, Field_Search $fieldSearch): array
    {
        $sql = SQL::newSelect('entry');
        $sql->addSelect('entry_id');
        $sql->addSelect('entry_code');
        $sql->addSelect('entry_category_id');
        $sql->addSelect('entry_updated_datetime');
        ACMS_Filter::entrySession($sql);
        ACMS_Filter::entryField($sql, $fieldSearch);
        $sql->addWhereOpr('entry_blog_id', $bid);
        if ($cidAry) {
            $sql->addWhereIn('entry_category_id', $cidAry);
        }
        if ($indexing) {
            $sql->addWhereOpr('entry_indexing', 'on');
        }
        ACMS_Filter::entryOrder($sql, $order);
        $sql->setLimit($limit);
        $q = $sql->get(dsn());

        return Database::query($q, 'all');
    }

    /**
     * 指定したブログの最終更新日時（エントリー更新日）を取得する
     *
     * @param integer|null $bid
     * @param integer|null $cid
     * @return string|null
     */
    protected function getLastModifiedEntry(?int $bid, ?int $cid, bool $indexing): ?string
    {
        $sql = SQL::newSelect('entry');
        $sql->addSelect('entry_id');
        $sql->addSelect('entry_category_id');
        $sql->addSelect('entry_updated_datetime');
        if ($bid) {
            $sql->addLeftJoin('blog', 'blog_id', 'entry_blog_id');
            ACMS_Filter::blogTree($sql, $bid, $this->blogAxis);
        }
        if ($bid && $cid) {
            $sql->addLeftJoin('category', 'category_id', 'entry_category_id');
            ACMS_Filter::categoryTree($sql, $cid, $this->categoryAxis);
            $sql->addWhereOpr('entry_blog_id', $bid);
        }
        ACMS_Filter::entrySession($sql);
        if ($indexing) {
            $sql->addWhereOpr('entry_indexing', 'on');
        }
        $sql->setOrder('entry_updated_datetime', 'desc');
        $sql->setLimit(1);
        $q = $sql->get(dsn());
        if ($entry = Database::query($q, 'row')) {
            return $this->formatLastModified($entry['entry_updated_datetime']);
        }
        return null;
    }

    /**
     * 指定した日時をサイトマップのlastmod形式に変換する
     *
     * @param string $datetime
     * @return string
     */
    protected function formatLastModified(string $datetime): string
    {
        $t = strtotime($datetime);
        if ($t === false) {
            return '';
        }
        return date('Y-m-d', $t) . 'T' . date('H:i:s', $t) . preg_replace('@(?=\d{2,2}$)@', ':', date('O', $t));
    }
}
