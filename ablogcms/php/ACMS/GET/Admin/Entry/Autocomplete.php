<?php

use Acms\Services\Facades\Database as DB;
use Acms\Services\Facades\Common;

class ACMS_GET_Admin_Entry_Autocomplete extends ACMS_GET_Entry_Summary
{
    public $_axis = [
        'bid' => 'descendant-or-self',
        'cid' => 'descendant-or-self',
    ];

    public $_scope = [
        'keyword' => 'global',
    ];

    /**
     * @inheritDoc
     */
    public function initConfig(): array
    {
        return [
            'order' => ['datetime-desc'],
            'orderFieldName' => '',
            'noNarrowDownSort' => false,
            'limit' => 40,
            'offset' => 0,
            'unit' => 1,
            'loopClass' => '',
            'newItemPeriod' => 0,
            'displayIndexingOnly' => true,
            'displayMembersOnly' => false,
            'displaySubcategoryEntries' => false,
            'displaySecretEntry' => false,
            'dateOn' => false,
            'detailDateOn' => false,
            'notfoundBlock' => false,
            'notfoundStatus404' => false,
            'fulltextEnabled' => false,
            'fulltextWidth' => 0,
            'fulltextMarker' => '',
            'includeTags' => false,
            'hiddenCurrentEntry' => false,
            'hiddenPrivateEntry' => false,
            'includeRelatedEntries' => false,
            // 画像系
            'includeMainImage' => true,
            'mainImageTarget' => 'unit',
            'mainImageFieldName' => '',
            'displayNoImageEntry' => true,
            'imageX' => 200,
            'imageY' => 200,
            'imageTrim' => false,
            'imageZoom' => false,
            'imageCenter' => false,
            // ページネーション
            'simplePagerEnabled' => false,
            'paginationEnabled' => false,
            'paginationDelta' => 0,
            'paginationCurrentAttr' => '',
            // フィールド・情報
            'includeEntryFields' => true,
            'includeCategory' => false,
            'includeCategoryFields' => false,
            'includeUser' =>  false,
            'includeUserFields' =>  false,
            'includeBlog' =>  false,
            'includeBlogFields' => false,
            // 表示モード
            'relatedEntryMode' => false,
            'relatedEntryType' => '',
        ];
    }

    public function get()
    {
        if (!$this->setConfigTrait()) {
            return '';
        }
        if ($thumbnailField = $this->Get->get('thumbnail')) {
            $this->config['mainImageTarget'] = 'field';
            $this->config['mainImageFieldName'] = $thumbnailField;
        }

        $tpl = new Template($this->tpl, new ACMS_Corrector());
        $this->boot();

        $sql = $this->buildCustomQuery();
        $this->entries = DB::query($sql->get(dsn()), 'all');
        $this->buildEntries($tpl);

        $json = preg_replace(
            '/[\x00-\x08\x10\x0B\x0C\x0E-\x19\x7F]' .
            '|[\x00-\x7F][\x80-\xBF]+' .
            '|([\xC0\xC1]|[\xF0-\xFF])[\x80-\xBF]*' .
            '|[\xC2-\xDF]((?![\x80-\xBF])|[\x80-\xBF]{2,})' .
            '|[\xE0-\xEF](([\x80-\xBF](?![\x80-\xBF]))|(?![\x80-\xBF]{2})|[\x80-\xBF]{3,})/S',
            '?',
            $tpl->get()
        );
        $json = buildIF($json);

        Common::setSafeHeadersWithoutCache(200, 'application/json');
        echo ($json);
        die();
    }

    /**
     * sqlの組み立て
     * @return SQL_Select
     */
    protected function buildCustomQuery(): SQL_Select
    {
        $sql = SQL::newSelect('entry');
        $sql->addLeftJoin('blog', 'blog_id', 'entry_blog_id');
        $sql->addLeftJoin('category', 'category_id', 'entry_category_id');
        ACMS_Filter::entrySpan($sql, $this->start, $this->end);
        ACMS_Filter::entrySession($sql, null, false);
        $multi = $this->entryQueryHelper->categoryFilterQuery($sql, false);
        $multi = $this->entryQueryHelper->entryFilterQuery($sql) || $multi;
        $this->blogFilterQuery($sql, $multi);
        $this->entryQueryHelper->keywordFilterQuery($sql);
        $this->entryQueryHelper->otherFilterQuery($sql);
        $this->entryQueryHelper->limitQuery($sql);
        $this->entryQueryHelper->orderQuery($sql, []);

        return $sql;
    }

    /**
     * ブログの絞り込み
     *
     * @param SQL_Select $sql
     * @param bool $multi
     * @return void
     */
    protected function blogFilterQuery(SQL_Select $sql, bool $multi): void
    {
        if ($this->bid && !$this->bids && $this->blogAxis() === 'self') {
            $sql->addWhereOpr('entry_blog_id', $this->bid);
        } elseif ($this->bid) {
            $blogSubQuery = SQL::newSelect('blog');
            $blogSubQuery->setSelect('blog_id');
            if ($this->bids) {
                $blogSubQuery->addWhereIn('blog_id', $this->bids);
            } else {
                if ($multi) {
                    ACMS_Filter::blogTree($blogSubQuery, $this->bid, 'descendant-or-self');
                } else {
                    ACMS_Filter::blogTree($blogSubQuery, $this->bid, $this->blogAxis());
                }
            }
            if ($blogIds = DB::query($blogSubQuery->get(dsn()), 'list')) {
                $sql->addWhereIn('entry_blog_id', $blogIds);
            }
        }
    }
}
