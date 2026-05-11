<?php

use Acms\Services\Facades\Login;

class ACMS_POST_Search_Items extends ACMS_POST
{
    public $isCacheDelete = false;

    /**
     * @var array
     */
    public $_scope = [
        'keyword' => 'global',
    ];

    /**
     * @var int
     */
    protected $limit = 10;

    /**
     * @var string
     */
    protected $keyword = '';

    /**
     *
     */
    function post()
    {
        $json = [];
        $this->keyword = $this->Post->get('word');

        if (
            1
            && !empty($this->keyword)
            && Login::isLoggedIn()
        ) {
            $json[] = [
                'title' => gettext('ブログ'),
                'enTitle' => 'Blogs',
                'items' => $this->blogJSON(),
            ];
            $json[] = [
                'title' => gettext('エントリー'),
                'enTitle' => 'Entries',
                'items' => $this->entryJSON(),
            ];
            $json[] = [
                'title' => gettext('カテゴリー'),
                'enTitle' => 'Categories',
                'items' => $this->categoryJSON(),
            ];
            $json[] = [
                'title' => gettext('モジュール'),
                'enTitle' => 'Modules',
                'items' => $this->moduleJSON(),
            ];
        }
        Common::responseJson($json);
    }

    /**
     * @return array
     */
    protected function blogJSON()
    {
        $json = [];

        $SQL = SQL::newSelect('blog');
        ACMS_Filter::blogTree($SQL, SBID, 'descendant-or-self');
        ACMS_Filter::blogKeyword($SQL, $this->keyword);
        $SQL->setLimit($this->limit);
        $all = DB::query($SQL->get(dsn()), 'all');

        foreach ($all as $item) {
            $bid = $item['blog_id'];
            $json[] = [
                'bid' => $bid,
                'blogName' => ACMS_RAM::blogName($bid),
                'title' => $item['blog_name'],
                'subtitle' => $item['blog_code'],
                'url' => acmsLink([
                    'bid' => $bid,
                    'admin' => 'top',
                ]),
            ];
        }
        return $json;
    }

    /**
     * @return array
     */
    protected function entryJSON()
    {
        $json = [];

        $SQL = SQL::newSelect('entry');
        $SQL->addLeftJoin('blog', 'blog_id', 'entry_blog_id');
        ACMS_Filter::entrySession($SQL);
        ACMS_Filter::blogTree($SQL, SBID, 'descendant-or-self');
        ACMS_Filter::entryKeyword($SQL, $this->keyword);
        $SQL->setLimit($this->limit);
        $all = DB::query($SQL->get(dsn()), 'all');

        foreach ($all as $item) {
            $id = $item['entry_id'];
            $bid = $item['entry_blog_id'];
            $json[] = [
                'id' => $id,
                'bid' => $bid,
                'blogName' => ACMS_RAM::blogName($bid),
                'title' => $item['entry_title'],
                'subtitle' => $item['entry_code'],
                'url' => acmsLink([
                    'eid' => $id,
                    'bid' => $bid,
                    'admin' => 'entry_editor',
                ]),
            ];
        }
        return $json;
    }

    /**
     * @return array
     */
    protected function categoryJSON()
    {
        if (!sessionWithCompilation()) {
            return [];
        }
        $json = [];

        $SQL = SQL::newSelect('category');
        $SQL->addLeftJoin('blog', 'blog_id', 'category_blog_id');
        ACMS_Filter::blogTree($SQL, SBID, 'descendant-or-self');
        ACMS_Filter::categoryKeyword($SQL, $this->keyword);
        $SQL->setLimit($this->limit);
        $all = DB::query($SQL->get(dsn()), 'all');

        foreach ($all as $item) {
            $id = $item['category_id'];
            $bid = $item['category_blog_id'];
            $json[] = [
                'id' => $id,
                'bid' => $bid,
                'blogName' => ACMS_RAM::blogName($bid),
                'title' => $item['category_name'],
                'subtitle' => $item['category_code'],
                'url' => acmsLink([
                    'cid' => $id,
                    'bid' => $bid,
                    'admin' => 'category_edit',
                ]),
            ];
        }
        return $json;
    }

    /**
     * @return array
     */
    protected function moduleJSON()
    {
        if (!sessionWithAdministration()) {
            return [];
        }
        $json = [];
        $word = '%' . $this->keyword . '%';

        $SQL = SQL::newSelect('module');
        $SQL->addLeftJoin('blog', 'blog_id', 'module_blog_id');
        ACMS_Filter::blogTree($SQL, SBID, 'descendant-or-self');
        $WHERE = SQL::newWhere();
        $WHERE->addWhereOpr('module_identifier', $word, 'LIKE', 'OR');
        $WHERE->addWhereOpr('module_name', $word, 'LIKE', 'OR');
        $WHERE->addWhereOpr('module_label', $word, 'LIKE', 'OR');
        $WHERE->addWhereOpr('module_description', $word, 'LIKE', 'OR');
        $SQL->addWhere($WHERE);
        $SQL->setLimit($this->limit);
        $all = DB::query($SQL->get(dsn()), 'all');

        foreach ($all as $item) {
            $id = $item['module_id'];
            $bid = $item['module_blog_id'];
            $json[] = [
                'id' => $id,
                'bid' => $bid,
                'blogName' => ACMS_RAM::blogName($bid),
                'title' => $item['module_label'],
                'subtitle' => $item['module_identifier'],
                'url' => acmsLink([
                    'bid'   => $bid,
                    'admin' => 'module_edit',
                    'query' => [
                        'mid' => $id,
                    ],
                ]),
            ];
        }
        return $json;
    }
}
