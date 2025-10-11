<?php

namespace Acms\Modules\Get\Helpers;

use Acms\Modules\Get\Helpers\BaseHelper;
use Acms\Services\Facades\Database;
use ACMS_Filter;
use SQL;
use SQL_Select;
use Field_Search;

class TagHelper extends BaseHelper
{
    /**
     * タグクラウドのタグを取得
     *
     * @param int|string $bid
     * @param int|string|null $cid
     * @param array $all
     * @param string | bool $urlContext
     * @param boolean $linkCategoryContext
     * @return array
     */
    public function getTagCloudTags($bid, $cid, array $all, $urlContext, bool $linkCategoryContext): array
    {
        $tags = [];
        $amounts = [];
        foreach ($all as $row) {
            $tag = $row['tag_name'];
            $tags[$tag] = $row['tag_amount'];
            $amounts[] = $row['tag_amount'];
        }
        $min = $amounts ? min($amounts) : 0;
        $max = $amounts ? max($amounts) : 0;

        $c = ($max !== $min) ? (24 / (sqrt($max) - sqrt($min))) : 1;
        $x = ceil(sqrt($min) * $c);

        $urlContext = is_string($urlContext) ? $urlContext : '';
        $context = $this->getBaseUrlContext($bid, $cid, $urlContext, $linkCategoryContext);
        $response = [];

        foreach ($tags as $tag => $amount) {
            $context['tag'] = $tag;
            $response[] = [
                'level' => ceil(sqrt($amount) * $c) - $x + 1,
                'url' => acmsLink($context),
                'path' => acmsPath($context),
                'amount' => $amount,
                'name' => $tag,
            ];
        }
        return $response;
    }

    /**
     * タグフィルターの選択中のタグを取得
     *
     * @param int|string $bid
     * @param int|string|null $cid
     * @param string[] $tags
     * @param string $urlContext
     * @param boolean $linkCategoryContext
     * @param int $selectedLimit
     * @return array
     */
    public function getSelectedTags($bid, $cid, array $tags, string $urlContext, bool $linkCategoryContext, int $selectedLimit): array
    {
        $cnt = count($tags);
        if ($cnt === 0) {
            return [];
        }
        $selectedTags = [];
        $context = $this->getBaseUrlContext($bid, $cid, $urlContext, $linkCategoryContext);
        $context2 = $context;
        if ($cnt > $selectedLimit) {
            $cnt = $selectedLimit;
        }
        $stack = [];
        for ($i = 0; $i < $cnt; $i++) {
            $stack[] = $tags[$i];
        }

        $tempTags = [];
        for ($i = 0; $i < $cnt; $i++) {
            $tag = $tags[$i];
            $tempTags[] = $tag;

            // 現在選択中のタグの中から該当の$tagを除いたものを表示
            $rejects = $stack;
            unset($rejects[array_search($tag, $tempTags, true)]);

            $context['tag'] = [$tag];
            $context2['tag'] = array_merge($rejects); // indexを振り直し（unsetで空いた分）

            $selectedTags[] = [
                'name' => $tag,
                'url' => acmsLink($context, false),
                'path' => acmsPath($context),
                'omitUrl' => acmsLink($context2, false),
            ];
        }
        return $selectedTags;
    }

    /**
     * タグフィルターのタグ選択肢を取得
     *
     * @param array $tagData
     * @return array
     */
    public function getChoiceTags(array $tagData): array
    {
        $choiceTags = [];
        while ($row = array_shift($tagData)) {
                $tag = $row['tag_name'];
                $tags = $this->tags;
                $tags[] = $tag;
                $context = [
                    'tag' => $tags,
                ];
                $choiceTags[] = [
                    'name' => $row['tag_name'],
                    'url' => acmsLink($context),
                    'path' => acmsPath($context),
                ];
        }
        return $choiceTags;
    }

    /**
     * ベースURLコンテキストを取得
     *
     * @param int|string $bid
     * @param int|string|null $cid
     * @param string $ctx
     * @param boolean $includeCategoryContext
     * @return array
     */
    public function getBaseUrlContext($bid, $cid, $ctx, $includeCategoryContext = false): array
    {
        $context = [
            'bid' => BID,
        ];
        if (!$ctx) {
            if (is_int($bid)) {
                $context['bid'] = $bid;
            } else {
                $context['bid'] = BID;
            }
            if ($includeCategoryContext) {
                if ($cid && is_int($cid)) {
                    $context['cid'] = $cid;
                } elseif (CID) { // @phpstan-ignore-line
                    $context['cid'] = CID;
                }
            }
        } else {
            $arg = parseAcmsPath($ctx);
            foreach ($arg->listFields() as $key) {
                if ($val = $arg->get($key)) {
                    $context[$key] = $val;
                }
            }
        }
        return $context;
    }

    /**
     * タグフィルターのSQLを生成する
     *
     * @param string[] $tags
     * @param string $order
     * @param int $threshold
     * @param int $limit
     * @return SQL_Select
     */
    public function buildTagFilterQuery(array $tags, string $order, int $threshold, int $limit): SQL_Select
    {
        $sql = SQL::newSelect('tag', 'tag0');
        $sql->addSelect('tag_name', null, 'tag0');
        $sql->addSelect('tag_name', 'tag_amount', 'tag0', 'count');
        foreach ($tags as $i => $tag) {
            $sql->addLeftJoin('tag', 'tag_entry_id', 'tag_entry_id', 'tag' . ($i + 1), 'tag' . $i);
            $sql->addWhereOpr('tag_name', $tag, '=', 'AND', 'tag' . ($i + 1));
        }
        foreach ($tags as $tag) {
            $sql->addWhereOpr('tag_name', $tag, '<>', 'AND', 'tag0');
        }
        $multiId = false;
        $entrySub = $this->filterEntryQuery(null, $this->Field);
        $categorySub = $this->filterCategoryQuery($multiId, $entrySub);
        if ($categorySub) {
            $entrySub->addWhereIn('entry_category_id', Database::subQuery($categorySub));
        }
        $blogSub = $this->filterBlogQuery($multiId);
        $sql->addWhereIn('tag_blog_id', Database::subQuery($blogSub), 'AND', 'tag0');
        $sql->addWhereIn('tag_entry_id', Database::subQuery($entrySub), 'AND', 'tag0');

        ACMS_Filter::tagOrder($sql, $order);
        $sql->addGroup('tag_name', 'tag0');
        if (1 < ($tagThreshold = $threshold)) {
            $sql->addHaving(SQL::newOpr('tag_amount', $tagThreshold, '>='));
        }
        $sql->setLimit($limit);

        return $sql;
    }

    /**
     * タグクラウドのSQLを生成する
     *
     * @param string $order
     * @param integer $threshold
     * @param integer $limit
     * @return SQL_Select
     */
    public function buildTagCloudQuery(string $order, int $threshold, int $limit): SQL_Select
    {
        $sql = SQL::newSelect('tag');
        $sql->addSelect('tag_name');
        $sql->addSelect('tag_name', 'tag_amount', null, 'count');

        $multiId = false;
        $entrySub = $this->filterEntryQuery($this->eid, $this->Field, $this->start, $this->end);
        $categorySub = $this->filterCategoryQuery($multiId, $entrySub);
        if ($categorySub) {
            $entrySub->addWhereIn('entry_category_id', Database::subQuery($categorySub));
        }
        $blogSub = $this->filterBlogQuery($multiId);
        $sql->addWhereIn('tag_blog_id', Database::subQuery($blogSub));
        $sql->addWhereIn('tag_entry_id', Database::subQuery($entrySub));

        $sql->addGroup('tag_name');
        if (1 < ($tagThreshold = $threshold)) {
            $sql->addHaving(SQL::newOpr('tag_amount', $tagThreshold, '>='));
        }
        $sql->setLimit($limit);
        ACMS_Filter::tagOrder($sql, $order);

        return $sql;
    }

    /**
     * エントリーの絞り込み
     *
     * @param int|string|null $eid
     * @param Field_Search|null $field
     * @param string|null $start
     * @param string|null $end
     * @return SQL_Select
     */
    protected function filterEntryQuery($eid, ?Field_Search $field, ?string $start = null, ?string $end = null): SQL_Select
    {
        $entrySub = SQL::newSelect('entry');
        $entrySub->setSelect('entry_id');
        $entrySub->addLeftJoin('category', 'entry_category_id', 'category_id');
        ACMS_Filter::entrySession($entrySub);
        if ($start && $end) {
            ACMS_Filter::entrySpan($entrySub, $start, $end);
        }
        if ($field) {
            ACMS_Filter::entryField($entrySub, $field);
        }
        if ($eid) {
            $entrySub->addWhereOpr('entry_id', $eid);
        }
        return $entrySub;
    }

    /**
     * ブログを絞り込み
     *
     * @param boolean $multiId
     * @return SQL_Select
     */
    protected function filterBlogQuery(bool $multiId): SQL_Select
    {
        $blogSub = SQL::newSelect('blog');
        $blogSub->setSelect('blog_id');
        if ($this->bids) {
            $blogSub->addWhereIn('blog_id', $this->bids);
        } else {
            if ($multiId) {
                ACMS_Filter::blogTree($blogSub, $this->bid, 'descendant-or-self');
            } else {
                ACMS_Filter::blogTree($blogSub, $this->bid, $this->blogAxis);
            }
        }
        ACMS_Filter::blogStatus($blogSub);

        return $blogSub;
    }

    /**
     * カテゴリーを絞り込み
     *
     * @param boolean $multiId
     * @param SQL_Select|null $entrySub
     * @return SQL_Select|null
     */
    protected function filterCategoryQuery(bool &$multiId, ?SQL_Select $entrySub): ?SQL_Select
    {
        $categorySub = null;
        if ($this->cids) {
            $categorySub = SQL::newSelect('category');
            $categorySub->setSelect('category_id');
            $categorySub->addWhereIn('category_id', $this->cids);
            $multiId = true;
        } elseif ($this->cid) {
            $categorySub = SQL::newSelect('category');
            $categorySub->setSelect('category_id');
            ACMS_Filter::categoryTree($categorySub, $this->cid, $this->categoryAxis);
        } elseif ($entrySub !== null) {
            ACMS_Filter::categoryStatus($entrySub);
        }
        return $categorySub;
    }
}
