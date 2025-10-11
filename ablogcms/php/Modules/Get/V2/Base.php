<?php

namespace Acms\Modules\Get\V2;

use Acms\Services\Cache\Contracts\AdapterInterface as CacheAdapterInterface;
use Acms\Services\Common\HookFactory;
use Acms\Services\Facades\Cache;
use Acms\Services\Facades\Common;
use BadMethodCallException;
use Field;
use Field_Search;
use Field_Validation;

class Base
{
    use \Acms\Traits\Utilities\FieldTrait;

    /**
     * @var Field
     */
    protected $moduleContext;

    /**
     * @var Field
     */
    protected $Get;

    /**
     * @var Field_Validation
     */
    protected $Post;

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
    protected $scopes = []; // phpcs:ignore

    /**
     * 階層の設定
     * @var array<'bid' | 'cid', string>
     */
    protected $axis = [ // phpcs:ignore
        'bid' => 'self',
        'cid' => 'self',
    ];

    /**
     * @var string|null
     */
    protected $identifier = null;

    /**
     * @var int|null
     */
    protected $mid = null;

    /**
     * @var int|null
     */
    protected $mbid = null;

    /**
     * @var bool
     */
    protected $customFieldsEnabled = false;

    /**
     * @var int
     */
    protected $cacheLifetime = 0;

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
     * @var int|null
     */
    public $uid = null;

    /**
     * @var int[]
     */
    public $uids = [];

    /**
     * @var int<1, max>
     */
    public $page = 1;

    /**
     * @var int|null
     */
    public $limit = null;

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
     * @var Field_Search
     */
    public $Field;

    /**
     * @var string
     */
    public $order;

    /**
     * @var string
     */
    public $start;

    /**
     * @var string
     */
    public $end;

    /**
     * Constructor
     *
     * @param array{
     *   bid?: int,
     *   bids?: int[],
     *   cid?: int,
     *   cids?: int[],
     *   eid?: int,
     *   eids?: int[],
     *   uid?: int,
     *   uids?: int[],
     *   page?: int,
     *   limit?: int,
     *   keyword?: string,
     *   tag?: string,
     *   field?: string,
     *   order?: string,
     *   start?: string,
     *   end?: string,
     * } $context
     * @param array $scopes
     * @param array $axis
     * @param Field_Validation $Post
     * @param int $cacheLifetime
     * @param bool $customFieldsEnabled
     * @param null|int $mid
     * @param null|string $identifier
     * @return void
     */
    public function __construct(
        array $context, // モジュールとテンプレート指定のマージされたコンテキスト（モジュール優先）
        array $scopes, // スコープ設定
        array $axis, // 階層設定
        Field_Validation $Post, // POSTデータ
        int $cacheLifetime = 0, // キャッシュ時間
        bool $customFieldsEnabled = false, // カスタムフィールド有効・無効
        ?int $mid = null, // モジュールID
        ?int $mbid = null, // モジュールIDのブログID
        ?string $identifier = null // モジュールID識別子
    ) {
        $this->cacheLifetime = $cacheLifetime;
        $this->mid = $mid;
        $this->mbid = $mbid;
        $this->identifier = $identifier;
        $this->customFieldsEnabled = $customFieldsEnabled;
        $this->Post = new Field_Validation($Post, true);
        $this->Get = new Field(Field::singleton('get'));
        $this->moduleContext = new Field(Field::singleton('query'), true); // URLコンテキストをコピーして、モジュールコンテキストのベースを作成

        $this->buildModuleContext($context, $scopes); // URLコンテキストを組み立て
        $this->buildAxis($axis); // 階層設定を組み立て
    }

    /**
     * @return array|never
     * @throws BadMethodCallException
     */
    public function get(): array
    {
        throw new BadMethodCallException('Method get() is not implemented.');
    }

    /**
     * モジュールを実行
     *
     * @return array
     * @throws BadMethodCallException
     */
    public function fire(): array
    {
        Common::setV2Module(true); // V2モジュールフラグを有効化

        $response = $this->exec();
        if (isSessionAdministrator()) {
            $className = preg_replace('/.*?(V2\_.*)/', '$1', str_replace(['\\'], '_', get_class($this)));
            $className = preg_replace('@(?<=[a-zA-Z0-9])([A-Z])@', '-$1', $className);
            if ($className === null) {
                throw new BadMethodCallException('Invalid module class name.');
            }
            if ($className === '') {
                throw new BadMethodCallException('Invalid module class name.');
            }
            $config = 'config_' . strtolower($className);
            $bid = $this->mbid ?? BID;
            $url = acmsLink([
                'bid' => $bid,
                'admin' => $config,
                'query' => [
                    'mid' => $this->mid,
                    'setid' => SETID,
                ],
            ], false);
            $response['moduleInfo'] = [
                'bid' => $bid,
                'mid' => $this->mid,
                'url' => $url,
                'name' => $className,
                'identifier' => $this->identifier,
            ];
        }
        if (HOOK_ENABLE) {
            $Hook = HookFactory::singleton();
            $Hook->call('afterV2GetFire', [ &$response, $this]);
        }

        Common::setV2Module(false); // V2モジュールフラグを無効化

        return $response;
    }

    /**
     * モジュールIDのコンフィグをロード
     *
     * @return Field
     */
    protected function loadModuleConfig(): Field
    {
        $config = Field::singleton('config');
        if ($this->mid) {
            $config->overload(loadModuleConfig($this->mid, RID));
        }
        return $config;
    }

    /**
     * @return string
     */
    protected function blogAxis(): string
    {
        $axis = $this->axis['bid'];
        return empty($axis) ? 'self' : $axis;
    }

    /**
     * @return string
     */
    protected function categoryAxis(): string
    {
        $axis = $this->axis['cid'];
        return empty($axis) ? 'self' : $axis;
    }

    /**
     * モジュールを実行結果を返却（キャッシュ考慮）
     *
     * @return array
     * @throws BadMethodCallException
     */
    protected function exec(): array
    {
        $cacheEnabled = $this->cacheLifetime > 0 && $this->identifier;
        if ($cacheEnabled) {
            $cache = Cache::module();
            assert($cache instanceof CacheAdapterInterface);
            $className = get_class($this);
            $cacheKey = md5($className . $this->identifier);
            $cacheItem = $cache->getItem($cacheKey);
            if ($cacheItem->isHit()) {
                $data = $cacheItem->get();
                if (is_array($data)) {
                    return $data;
                }
            }
            $response = $this->get();
            $cacheItem->set($response);
            $cache->putItem($cacheItem, $this->cacheLifetime * 60);
            return $response;
        }
        return $this->get();
    }

    /**
     * モジュールコンテキストを組み立て
     *
     * @param array{
     *   bid?: int|int[],
     *   cid?: int|int[],
     *   eid?: int|int[],
     *   uid?: int|int[],
     *   page?: int,
     *   limit?: int,
     *   keyword?: string,
     *   tag?: string,
     *   field?: string,
     *   order?: string,
     *   start?: string,
     *   end?: string,
     * } $context
     * @param array $scopes
     * @return void
     */
    protected function buildModuleContext(array $context, array $scopes)
    {
        $urlContext = new Field(Field::singleton('query'), true); // URLコンテキストを取得（上書きされないようにコピーして取得）

        $this->moduleContext->set('bid', $context['bid'] ?? $urlContext->get('bid')); // BIDを決定

        foreach (['cid', 'eid', 'uid', 'page', 'limit', 'keyword', 'tag', 'field', 'order', 'start', 'end'] as $key) {
            $scope = $this->getScope($key, $scopes);
            $isGlobal = $scope === 'global';

            if ('field' === $key) {
                $field = $urlContext->getChild('field');
                if (!$isGlobal || $field->isNull()) {
                    $field = new Field_Search($context['field'] ?? '');
                }
                $this->moduleContext->addChild('field', $field);
            } elseif ('keyword' === $key) {
                $keyword = $urlContext->get('keyword');
                $querykeyword = $this->Get->get(KEYWORD_SEGMENT);
                if (!empty($querykeyword) && config('query_keyword') == 'on') {
                    $keyword = $this->Get->get(KEYWORD_SEGMENT);
                }
                if (!$isGlobal || empty($keyword)) {
                    $keyword = $context['keyword'] ?? null;
                }
                $this->moduleContext->set('keyword', $keyword);
            } elseif (
                $isGlobal && (
                    ($key === 'start' && $urlContext->get($key) === '1000-01-01 00:00:00') ||
                    ($key === 'end' && $urlContext->get($key) === '9999-12-31 23:59:59')
                )
            ) {
                if ($val = $context[$key] ?? null) {
                    if ($time = strtotime($val)) {
                        $this->moduleContext->set($key, date('Y-m-d H:i:s', $time));
                    }
                }
            } elseif (!$isGlobal || !$urlContext->get($key)) {
                if ($val = $context[$key] ?? null) {
                    $fixedVal = null;
                    if ('page' === $key && (1 > intval($val))) {
                        $fixedVal = 1;
                    } elseif (in_array($key, ['bid', 'cid', 'eid', 'uid'], true)) {
                        $fixedVal = is_array($val) ? implode(',', $val) : $val;
                    } else {
                        $fixedVal = $val;
                    }
                    $this->moduleContext->set($key, $fixedVal);
                } else {
                    $this->moduleContext->deleteField($key);
                }
            }
        }
        $this->setIntegerValue('bid');
        $this->setIntegerValue('cid');
        $this->setIntegerValue('eid');
        $this->setIntegerValue('uid');
        $this->setIntegerValue('limit');
        $this->keyword = $this->moduleContext->get('keyword');
        $this->start = $this->moduleContext->get('start');
        $this->end = $this->moduleContext->get('end');
        $this->page = (int) $this->moduleContext->get('page');
        if (intval($this->page) < 1) {
            $this->page = 1;
        }
        $this->order = $this->moduleContext->get('order');
        $this->tag = join('/', $this->moduleContext->getArray('tag'));
        $this->tags = $this->moduleContext->getArray('tag');
        /** @var Field_Search $field */
        $field = &$this->moduleContext->getChild('field');
        $this->Field = &$field;
        $this->field = $this->Field->serialize();
    }

    /**
     * 整数型の値を設定
     *
     * @param string $key
     * @return void
     */
    protected function setIntegerValue(string $key): void
    {
        $multiValueIdKeys = ['bid', 'cid', 'eid', 'uid'];
        if (!$this->moduleContext->isNull($key)) {
            if (in_array($key, $multiValueIdKeys, true)) {
                $val = $this->moduleContext->get($key);
                if (strpos($val, ',') !== false) {
                    $numbers = array_map(function ($item) {
                        $trimmed = trim($item); // 余分なスペースを削除
                        return is_numeric($trimmed) ? (int) $trimmed : null; // 数値なら変換、そうでなければnull
                    }, explode(',', $val));
                    $numbers = array_filter($numbers, function ($number) {
                        return $number !== null; // nullを除外
                    });
                    $this->moduleContext->set($key, $numbers);
                    $this->{"{$key}s"} = $this->moduleContext->getArray($key); // @phpstan-ignore-line
                    $this->{$key} = $numbers[0] ?? null; // @phpstan-ignore-line
                } else {
                    $this->{$key} = (int) $val ?? null; // @phpstan-ignore-line
                }
            } else {
                $this->{$key} = $this->moduleContext->get($key); // @phpstan-ignore-line
                if (is_numeric($this->{$key})) { // @phpstan-ignore-line
                    $this->{$key} = (int) $this->{$key}; // @phpstan-ignore-line
                }
            }
        }
    }

    /**
     * モジュールの階層設定を組み立て
     *
     * @param array $axis
     * @return void
     */
    protected function buildAxis(array $axis): void
    {
        foreach (['bid', 'cid'] as $key) {
            if (!array_key_exists($key, $axis)) {
                continue;
            }
            $this->axis[$key] = $axis[$key];
        }
    }

    /**
     * 指定したコンテキストのスコープを取得
     * モジュール設定とURLのコンテキストを考慮します
     *
     * @param string $key
     * @param array $scopes
     * @return string
     */
    protected function getScope(string $key, array $scopes): string
    {
        $scope = 'local';
        if (isset($scopes[$key]) && !empty($scopes[$key])) {
            // モジュール指定のスコープ
            $scope = $scopes[$key];
        } elseif (isset($this->scopes[$key]) && $this->scopes[$key]) {
            // phpのプロパティで設定されている、各モジュールのデフォルトスコープ
            $scope = $this->scopes[$key];
        }
        return $scope;
    }

    /**
     * モジュールフィールドを組み立て
     *
     * @return null|array
     */
    protected function buildModuleField(): ?array
    {
        if ($this->mid && $this->customFieldsEnabled) {
            return $this->buildFieldTrait(loadModuleField($this->mid));
        }
        return null;
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
            'limit' => $this->limit,
            'blogAxis' => $this->blogAxis(),
            'categoryAxis' => $this->categoryAxis(),
        ];
        return array_merge($base, $override);
    }
}
