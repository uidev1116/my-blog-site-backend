<?php

namespace Acms\Modules\Get\Helpers;

use SQL_Select;
use Field_Search;

class BaseHelper
{
    /**
     * @var SQL_Select
     */
    protected $countQuery;

    /**
     * @var mixed
     */
    protected $config;

    /**
     * @var int
     */
    protected $bid = null;

    /**
     * @var int[]
     */
    public $bids = [];

    /**
     * @var int|null
     */
    protected $cid = null;

    /**
     * @var int[]
     */
    public $cids = [];

    /**
     * @var int|null
     */
    protected $uid = null;

    /**
     * @var int[]
     */
    public $uids = [];

    /**
     * @var int|null
     */
    protected $eid = null;

    /**
     * @var int[]
     */
    public $eids = [];

    /**
     * @var Field_Search|null
     */
    protected $Field = null;

    /**
     * @var string|null
     */
    protected $keyword = null;

    /**
     * @var string[]
     */
    protected $tags = [];

    /**
     * @var string|null
     */
    protected $start = null;

    /**
     * @var string|null
     */
    protected $end = null;

    /**
     * @var int
     */
    protected $page = 1;

    /**
     * @var string
     */
    protected $order = 'desc';

    /**
     * @var string
     */
    protected $blogAxis = 'self';

    /**
     * @var string
     */
    protected $categoryAxis = 'self';

    /**
     * @var array
     */
    protected $sortFields = [];

    /**
     * コンストラクタ
     *
     * @param array $params
     */
    public function __construct(array $params)
    {
        $this->config = $params['config'] ?? [];
        $this->bid = $params['bid'] ?? null;
        $this->bids = $params['bids'] ?? [];
        $this->cid = $params['cid'] ?? null;
        $this->cids = $params['cids'] ?? [];
        $this->eid = $params['eid'] ?? null;
        $this->eids = $params['eids'] ?? [];
        $this->uid = $params['uid'] ?? null;
        $this->uids = $params['uids'] ?? [];
        $this->Field = $params['field'] ?? new Field_Search();
        $this->keyword = $params['keyword'] ?? null;
        $this->tags = $params['tags'] ?? [];
        $this->start = $params['start'] ?? null;
        $this->end = $params['end'] ?? null;
        $this->page = $params['page'] ?? 1;
        $this->order = $params['order'] ?? 'desc';
        $this->blogAxis = $params['blogAxis'] ?? 'self';
        $this->categoryAxis = $params['categoryAxis'] ?? 'self';
    }

    /**
     * プロパティの設定
     *
     * @param string $name
     * @param mixed $value
     * @return void
     */
    public function setProperty(string $name, $value): void
    {
        if (property_exists($this, $name)) {
            $this->{$name} = $value; // @phpstan-ignore-line
        }
    }
}
