<?php

class ACMS_GET
{
    /**
     * @var string
     */
    public $tpl = '';

    /**
     * @var int
     */
    public $bid = null;

    /**
     * @var int[]
     */
    public $bids = [];

    /**
     * @var int|null
     */
    public $uid = null;

    /**
     * @var int[]
     */
    public $uids = [];

    /**
     * @var int|null
     */
    public $cid = null;

    /**
     * @var int[]
     */
    public $cids = [];

    /**
     * @var int|null
     */
    public $eid = null;

    /**
     * @var int[]
     */
    public $eids = [];

    /**
     * @var string
     */
    public $keyword;

    /**
     * @var string
     */
    public $tag;

    /**
     * @var string[]
     */
    public $tags = [];

    /**
     * @var string
     */
    public $field;

    /**
     * @var \Field_Search
     */
    public $Field;

    /**
     * @var string
     */
    public $start;

    /**
     * @var string
     */
    public $end;

    /**
     * @var int<1, max>
     */
    public $page = 1;

    /**
     * @var string
     */
    public $order;

    /**
     * @deprecated 未使用のプロパティ
     * @var null
     */
    public $alt = null;

    /**
     * @deprecated 未使用のプロパティ
     * @var null
     */
    public $action = null;

    /**
     * @deprecated 未使用のプロパティ
     * @var null
     */
    public $squareSize = null;

    /**
     * @var Field
     */
    public $Q;

    /**
     * @var Field
     */
    public $Get;

    /**
     * @var Field_Validation
     */
    public $Post;

    /**
     * スコープの設定
     * @var array{
     *     uid?: 'local' | 'global',
     *     cid?: 'local' | 'global',
     *     eid?: 'local' | 'global',
     *     keyword?: 'local' | 'global',
     *     tag?: 'local' | 'global',
     *     field?: 'local' | 'global',
     *     date?: 'local' | 'global',
     *     start?: 'local' | 'global',
     *     end?: 'local' | 'global',
     *     page?: 'local' | 'global',
     *     order?: 'local' | 'global'
     * }
     */
    public $_scope = []; // phpcs:ignore

    /**
     * 階層の設定
     * @var array<'bid' | 'cid', string>
     */
    public $_axis = [ // phpcs:ignore
        'bid' => 'self',
        'cid' => 'self',
    ];

    /**
     * @var int|null
     */
    public $mid = null;

    /**
     * @var int|null
     */
    public $mbid = null;

    /**
     * @var string|null
     */
    public $identifier = null;

    /**
     * @var bool
     */
    public $showField = false;

    /**
     * @var int
     */
    public $cache = 0;

    /**
     * @param string $tpl
     * @param string $acms
     * @param array $scope
     * @param array $axis
     * @param \Field_Validation $Post
     * @param int|null $mid
     * @param int|null $mbid
     * @param string|null $identifier
     * @param array|null $aryMultiAcms
     * @param bool $showField
     */
    public function __construct(
        $tpl,
        $acms,
        $scope,
        $axis,
        $Post,
        $mid = null,
        $mbid = null,
        $identifier = null,
        $aryMultiAcms = null,
        $showField = false
    ) {
        $this->Post = new Field_Validation($Post, true);
        $this->Get = new Field(Field::singleton('get'));
        $this->Q = new Field(Field::singleton('query'), true);

        $this->tpl = $tpl;
        $this->identifier = $identifier;
        $this->showField = $showField;

        //-------
        // scope
        $Arg = parseAcmsPath($acms);
        $this->cache = isset($scope['cache']) ? intval($scope['cache']) : 0;

        $this->Q->set('bid', $Arg->get('bid', $this->Q->get('bid')));
        foreach (
            [
                'cid', 'eid', 'uid', 'keyword', 'tag', 'field', 'start', 'end', 'page', 'order',
            ] as $key
        ) {
            $isGlobal = ('global' == (!empty($scope[$key]) ? $scope[$key] : (!empty($this->_scope[$key]) ? $this->_scope[$key] : 'local')));
            if ('field' == $key) {
                $Field = $this->Q->getChild('field');
                if (!$isGlobal or $Field->isNull()) {
                    $this->Q->addChild('field', $Arg->getChild('field'));
                }
            } elseif (!$isGlobal or !$this->Q->get($key)) {
                $val = $Arg->getArray($key);
                if (('page' == $key) and (1 > $val[0])) {
                    $val[0] = 1;
                }
                $this->Q->set($key, array_shift($val));
                foreach ($val as $argV) {
                    $this->Q->add($key, $argV);
                }
            } elseif (
                $isGlobal && (0
                    || ($key == 'start' && $this->Q->get($key) == '1000-01-01 00:00:00')
                    || ($key == 'end' && $this->Q->get($key) == '9999-12-31 23:59:59')
                )
            ) {
                $val = $Arg->getArray($key);
                $this->Q->set($key, array_shift($val));
                foreach ($val as $argV) {
                    $this->Q->add($key, $argV);
                }
            }
        }

        if (!$this->Q->isNull('bid')) {
            $this->bid = intval($this->Q->get('bid'));
        }
        if (!$this->Q->isNull('cid')) {
            $this->cid = intval($this->Q->get('cid'));
        }
        if (!$this->Q->isNull('eid')) {
            $this->eid = intval($this->Q->get('eid'));
        }
        if (!$this->Q->isNull('uid')) {
            $this->uid = intval($this->Q->get('uid'));
        }
        if (is_array($aryMultiAcms)) {
            foreach ($aryMultiAcms as $k => $v) {
                $isGlobal_ = ('global' == (!empty($scope[$k]) ? $scope[$k] : (!empty($this->_scope[$k]) ? $this->_scope[$k] : 'local')));
                if (!$isGlobal_ || !$this->Q->get($k)) {
                    if (strpos($v, ',') !== false) {
                        $numbers = array_map(function ($item) {
                            $trimmed = trim($item); // 余分なスペースを削除
                            return is_numeric($trimmed) ? (int) $trimmed : null; // 数値なら変換、そうでなければnull
                        }, explode(',', $v));
                        $numbers = array_filter($numbers, function ($number) {
                            return $number !== null; // nullを除外
                        });
                        $this->Q->set($k, $numbers);
                        $this->{"{$k}s"} = $this->Q->getArray($k); // @phpstan-ignore-line
                        $this->{$k} = $numbers[0] ?? null; // @phpstan-ignore-line
                    } else {
                        $this->{$k} = (int) $v ?? null; // @phpstan-ignore-line
                    }
                }
            }
        }

        $keyword = $this->Q->get('keyword');
        $qkeyword = $this->Get->get(KEYWORD_SEGMENT);
        if (
            !empty($qkeyword) &&
            config('query_keyword') == 'on' &&
            ('global' == (!empty($scope['keyword']) ? $scope['keyword'] : (!empty($this->_scope['keyword']) ? $this->_scope['keyword'] : 'local')))
        ) {
            $keyword = $this->Get->get(KEYWORD_SEGMENT);
        }

        $this->keyword = $keyword;
        $this->start = $this->Q->get('start');
        $this->end = $this->Q->get('end');
        $this->page = intval($this->Q->get('page'));
        $this->order = $this->Q->get('order');

        $this->tag = join('/', $this->Q->getArray('tag'));
        $this->tags = $this->Q->getArray('tag');
        /** @var Field_Search $field */
        $field = &$this->Q->getChild('field');
        $this->Field = &$field;
        $this->field = $this->Field->serialize();

        //------
        // axis
        foreach (['bid', 'cid'] as $key) {
            if (!array_key_exists($key, $axis)) {
                continue;
            }
            $this->_axis[$key] = $axis[$key];
        }

        $this->mid = $mid;
        $this->mbid = $mbid;
    }

    /**
     * @return string
     */
    public function blogAxis()
    {
        $axis = $this->_axis['bid'];
        return empty($axis) ? 'self' : $axis;
    }

    /**
     * @return string
     */
    public function categoryAxis()
    {
        $axis = $this->_axis['cid'];
        return empty($axis) ? 'self' : $axis;
    }

    /**
     * @return string
     */
    public function fire()
    {
        //----------------
        // module link
        $className = str_replace(['ACMS_GET_', 'ACMS_User_GET_'], '', get_class($this));
        $config = 'config_' . strtolower((string) preg_replace('@(?<=[a-zA-Z0-9])([A-Z])@', '-$1', $className));
        $bid = !empty($this->mbid) ? $this->mbid : BID;

        $url = acmsLink([
            'bid' => $bid,
            'admin' => $config,
            'query' => [
                'mid' => $this->mid,
                'setid' => SETID,
            ],
        ], false);

        $this->tpl = str_replace([
            '{admin_module_bid}',
            '{admin_module_mid}',
            '{admin_module_url}',
            '{admin_module_name}',
            '{admin_module_identifier}',
        ], [
            $bid,
            $this->mid,
            $url,
            $className,
            $this->identifier,
        ], $this->tpl);

        if (isSessionAdministrator()) {
            $this->tpl = (string)preg_replace('@<!--[\t 　]*(BEGIN|END)[\t 　]module_setting[\t 　]-->@', '', $this->tpl);
        }

        //----------------
        // execute & hook
        if (HOOK_ENABLE) {
            $Hook = ACMS_Hook::singleton();
            $Hook->call('beforeGetFire', [ &$this->tpl, $this]);
            $rendered = $this->cache();
            $Hook->call('afterGetFire', [ &$rendered, $this]);
        } else {
            $rendered = $this->cache();
        }
        return $rendered;
    }

    /**
     * @return string
     */
    protected function cache()
    {
        $cacheOn = $this->cache > 0 && $this->identifier;
        if ($cacheOn) {
            $cache = Cache::module();
            assert($cache instanceof \Acms\Services\Cache\Contracts\AdapterInterface);
            $className = str_replace(['ACMS_GET_', 'ACMS_User_GET_'], '', get_class($this));
            $cacheKey = md5($className . $this->identifier);
            $cacheItem = $cache->getItem($cacheKey);
            if ($cacheItem->isHit()) {
                return (string) $cacheItem->get();
            }
        }
        $rendered = $this->get();
        if ($cacheOn) {
            $cacheItem->set($rendered);
            $cache->putItem($cacheItem, $this->cache * 60);
        }
        return $rendered;
    }

    /**
     * @return string|never
     */
    public function get()
    {
        throw new \BadMethodCallException('Method get() is not implemented.');
    }

    /**
     * ToDo: deplicated mehod Ver. 2.7.0
     * 互換性のためpublic宣言
     * @deprecated
     * @param Template &$Tpl
     * @return void
     */
    public function buildModuleField(&$Tpl)
    {
        Tpl::buildModuleField($Tpl, $this->mid, $this->showField);
    }

    /**
     * ToDo: deplicated mehod Ver. 2.7.0
     * 互換性のためpublic宣言
     * @deprecated
     * @param int|string $datetime
     * @param Template &$Tpl
     * @param string[]|string $block
     * @param string $prefix
     *
     * @return array
     */
    public function buildDate($datetime, &$Tpl, $block = [], $prefix = 'date#')
    {
        return Tpl::buildDate($datetime, $Tpl, $block, $prefix);
    }

    /**
     * ToDo: deplicated mehod Ver. 2.7.0
     * 互換性のためpublic宣言
     * @deprecated
     * @param \Field $Field
     * @param Template &$Tpl
     * @param string[]|string $block
     * @param string|null $scp
     * @param array $root_vars
     *
     * @return array
     */
    public function buildField($Field, &$Tpl, $block = [], $scp = null, $root_vars = [])
    {
        return Tpl::buildField($Field, $Tpl, $block, $scp, $root_vars);
    }

    /**
     * ToDo: deplicated mehod Ver. 2.7.0
     * 互換性のためpublic宣言
     * @deprecated
     * @param int $page ページ数
     * @param int $limit 1ページの件数
     * @param int $amount 総数
     * @param int $delta 前後ページ数
     * @param string $curAttr
     * @param Template &$Tpl
     * @param string[]|string $block
     * @param array $Q
     *
     * @return array
     */
    public function buildPager($page, $limit, $amount, $delta, $curAttr, &$Tpl, $block = [], $Q = [])
    {
        return Tpl::buildPager($page, $limit, $amount, $delta, $curAttr, $Tpl, $block, $Q);
    }

    /**
     * ToDo: deplicated mehod Ver. 2.7.0
     * 互換性のためpublic宣言
     * @deprecated
     * @param array &$vars
     * @param int $eid
     * @param array<int<1, max>, \Acms\Services\Unit\UnitCollection> $eagerLoadingData
     *
     * @return void
     */
    public function buildSummaryFulltext(&$vars, $eid, $eagerLoadingData)
    {
        $vars = Tpl::buildSummaryFulltext($vars, $eid, $eagerLoadingData);
    }

    /**
     * ToDo: deplicated mehod Ver. 2.7.0
     * 互換性のためpublic宣言
     * @deprecated
     * @param Template $Tpl
     * @param int $eid
     *
     * @return void
     */
    public function buildTag(&$Tpl, $eid)
    {
        Tpl::buildTag($Tpl, $eid);
    }

    /**
     * ToDo: deplicated mehod Ver. 2.7.0
     * 互換性のためpublic宣言
     * @deprecated
     * @param Template $Tpl
     * @param string $pimageId
     * @param int $entryId
     * @param array $config
     * @param array{unit: array<string, \Acms\Services\Unit\Contracts\Model>, media: array<int, array<string, mixed>>, fieldMainImage?: array<int, array<string, mixed>>} $eagerLoadingData
     *
     * @return array
     */
    public function buildImage(&$Tpl, $entryId, $pimageId, $config, $eagerLoadingData)
    {
        return Tpl::buildImage($Tpl, $entryId, $pimageId, $config, $eagerLoadingData);
    }

    /**
     * ToDo: deplicated mehod Ver. 2.7.0
     * 互換性のためpublic宣言
     * @deprecated
     * @param Template $Tpl
     * @param int[] $eids
     * @param string[]|string $block
     * @param string $relatedBlock
     * @param string|null $thumbnailField
     *
     * @return void
     */
    public function buildRelatedEntries(&$Tpl, $eids = [], $block = [], $relatedBlock = 'related:loop', $thumbnailField = '')
    {
        Tpl::buildRelatedEntries($Tpl, $eids, $block, $this->start, $this->end, $relatedBlock, $thumbnailField);
    }

    /**
     * ToDo: deplicated mehod Ver. 2.7.0
     * 互換性のためpublic宣言
     * @deprecated
     * @param Template $Tpl
     * @param array $row
     * @param int $count
     * @param int $gluePoint
     * @param array $config
     * @param array $extraVars
     * @param array $eagerLoadingData
     *
     * @return void
     */
    public function buildSummary(&$Tpl, $row, $count, $gluePoint, $config, $extraVars = [], $eagerLoadingData = [])
    {
        Tpl::buildSummary($Tpl, $row, $count, $gluePoint, $config, $extraVars, $this->page, $eagerLoadingData);
    }

    /**
     * モジュールの基本パラメータを取得
     *
     * @param array $override
     * @return array
     */
    protected function getBaseParams(array $override = []): array
    {
        $base = [
            'bid' => $this->bid,
            'bids' => $this->bids,
            'eid' => $this->eid,
            'eids' => $this->eids,
            'cid' => $this->cid,
            'cids' => $this->cids,
            'uid' => $this->uid,
            'uids' => $this->uids,
            'field' => $this->Field,
            'keyword' => $this->keyword,
            'tag' => $this->tag,
            'tags' => $this->tags,
            'start' => $this->start,
            'end' => $this->end,
            'page' => $this->page,
            'order' => $this->order,
            'blogAxis' => $this->blogAxis(),
            'categoryAxis' => $this->categoryAxis(),
        ];
        return array_merge($base, $override);
    }
}
