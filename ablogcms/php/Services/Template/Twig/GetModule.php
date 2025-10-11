<?php

namespace Acms\Services\Template\Twig;

use Acms\Services\Facades\Application;
use ACMS_Filter;
use ACMS_Namespace;
use ACMS_RAM;
use DB;
use Exception;
use RuntimeException;
use SQL;
use Timer;

class GetModule
{
    /**
     * キャッシュ
     *
     * @var array
     */
    protected $cache = [];

    /**
     * twigテンプレートから「module」関数で呼び出し
     *
     * @param string $name
     * @param string $identifier|null
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
     * } $ctx
     * @return array
     * @throws RuntimeException
     * @throws Exception
     */
    public function moduleFunction(string $name, ?string $identifier = null, array $ctx = []): array
    {
        $timer1 = null;
        $timer2 = null;
        $cacheKey = $this->generateCacheKey($name, $identifier, $ctx);

        // キャッシュが存在すればそれを返す
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        // timer start
        if (isBenchMarkMode()) {
            global $query_result_count;
            $sql_count = $query_result_count;
            $timer1 = new Timer();
            $timer1->start();
        }

        $namespace = ACMS_Namespace::singleton();
        $moduleClass = $namespace->getModuleClass('Get', $name);

        if (empty($moduleClass)) {
            return [];
        }
        $app = Application::getInstance();
        $post = $app->getPostParameter();
        $mid = null;
        $mbid = null;
        $moduleContext = [];
        $scopes = [];
        $axis = [];
        $customFieldsEnabled = false;
        $cacheLifetime = 0;

        // モジュール取得
        $module = $this->getModuleId($name, $identifier, BID);

        if ($module) {
            if (!$this->validate($module)) {
                throw new RuntimeException('403 forbidden.');
            }
            $mid = intval($module['module_id']);
            $mbid = intval($module['module_blog_id']);
            $customFieldsEnabled = intval($module['module_custom_field']) === 1;
            $cacheLifetime = intval($module['module_cache']);
            $axis['bid'] = $module['module_bid_axis'];
            $axis['cid'] = $module['module_cid_axis'];
            $moduleContext = $this->getContextByModule($module);
            $scopes = $this->getScopes($module);
        }
        // モジュールのコンテキストとテンプレートのコンテキストをマージ（モジュールのコンテキスト優先）
        $moduleContext = array_merge($ctx, $moduleContext);

        // timer start
        if (isBenchMarkMode()) {
            $timer2 = new Timer();
            $timer2->start();
        }

        /**
         * モジュール実行
         * @var \Acms\Modules\Get\V2\Base $getModule
         */
        $getModule = new $moduleClass($moduleContext, $scopes, $axis, $post, $cacheLifetime, $customFieldsEnabled, $mid, $mbid, $identifier);
        $res = $getModule->fire();

        // timer end
        if (isBenchMarkMode()) {
            global $bench;
            $timer2->end();
            $bench['module'][] = [
                'module' => $name,
                'identifier' => $identifier,
                'sql_count' => ($query_result_count - $sql_count),
                'run_time' => $timer2->time,
                'sort_key' => $timer2->time,
            ];
        }

        // timer end
        if (isBenchMarkMode()) {
            global $bench_boot;
            $timer1->end();
            $bench_boot += $timer1->time;
        }
        // モジュールのデータを保存
        if (isDebugMode()) {
            $dataHolder = Application::make('template.twig.data');
            $dataHolder->addModuleData($name, $res, $identifier);
        }
        // キャッシュに保存
        $this->cache[$cacheKey] = $res;

        return $res;
    }

    /**
     * モジュールIDを取得
     *
     * @param string $name
     * @param string|null $identifier
     * @param int $bid
     * @return null|array
     */
    protected function getModuleId(string $name, ?string $identifier, int $bid): ?array
    {
        if (empty($identifier)) {
            return null;
        }
        $sql = SQL::newSelect('module');
        $sql->addLeftJoin('blog', 'blog_id', 'module_blog_id');
        ACMS_Filter::blogTree($sql, $bid, 'ancestor-or-self');
        $sql->addWhereOpr('module_name', $name);
        $sql->addWhereOpr('module_identifier', $identifier);
        $sql->addWhereOpr('module_status', 'open');
        $Where = SQL::newWhere();
        $Where->addWhereOpr('module_blog_id', $bid, '=', 'OR');
        $Where->addWhereOpr('module_scope', 'global', '=', 'OR');
        $sql->addWhere($Where);

        $q = $sql->get(dsn());
        $row = DB::query($q, 'row');

        if (empty($row)) {
            return null;
        }
        return $row;
    }

    /**
     * アクセスできない情報を参照しようとしているのであれば403を返す
     *
     * @param array $module
     * @return bool
     * @throws RuntimeException
     */
    protected function validate(array $module): bool
    {
        $eid = is_numeric($module['module_eid']) ? intval($module['module_eid']) : 0;
        $mbid = intval($module['module_blog_id']);
        $bid = !empty($eid) ? ACMS_RAM::entryBlog($eid) : $mbid;
        if (checkModuleEntry($eid, $bid, $mbid)) {
            return false;
        }
        return true;
    }

    /**
     * モジュールのスコープを取得
     *
     * @param array $module
     * @return array
     */
    protected function getScopes(array $module): array
    {
        $scopes['uid'] = $module['module_uid_scope'];
        $scopes['cid'] = $module['module_cid_scope'];
        $scopes['eid'] = $module['module_eid_scope'];
        $scopes['keyword'] = $module['module_keyword_scope'];
        $scopes['tag'] = $module['module_tag_scope'];
        $scopes['field'] = $module['module_field_scope'];
        $scopes['start'] = $module['module_start_scope'];
        $scopes['end'] = $module['module_end_scope'];
        $scopes['page'] = $module['module_page_scope'];
        $scopes['order'] = $module['module_order_scope'];

        return $scopes;
    }

    /**
     * モジュールIDのコンテキスト設定を取得
     *
     * @param array $module
     * @return array
     * @throws Exception
     */
    protected function getContextByModule(array $module): array
    {
        if (!isset($module['module_id'])) {
            return [[], []];
        }
        $contextTypes = [
            'int' => ['bid', 'cid', 'eid', 'uid', 'page'],
            'string' => ['keyword', 'tag', 'field', 'order', 'start', 'end'],
        ];
        $moduleContext = [];

        // 各タイプのコンテキストを処理
        foreach ($contextTypes as $type => $keys) {
            foreach ($keys as $key) {
                // グローバル変数の設定（nullを空文字に変換する処理も兼ねる）
                $val = $module['module_' . $key] = setGlobalVars($module['module_' . $key]);
                // 値がnullまたは空文字の場合はスキップ
                if (is_null($val) || $val === '') { // @phpstan-ignore-line
                    continue;
                }
                // 型が'int'の場合の処理
                if ($type === 'int') {
                    // 値にカンマが含まれている場合は配列に追加
                    if (strpos($val, ',') !== false) {
                        $numbers = array_map(function ($item) {
                            $trimmed = trim($item); // 余分なスペースを削除
                            return is_numeric($trimmed) ? (int) $trimmed : null; // 数値なら変換、そうでなければnull
                        }, explode(',', $val));
                        $numbers = array_filter($numbers, function ($number) {
                            return $number !== null; // nullを除外
                        });
                        $moduleContext[$key] = $numbers;
                    } else {
                        if (strval($val) !== strval(intval($val))) {
                            continue; // 値が整数として表現できない場合はスキップ
                        }
                        $moduleContext[$key] = (int) $val;
                    }
                } else {
                    $moduleContext[$key] = $val;
                }
            }
        }
        return $moduleContext;
    }

    /**
     * URLコンテキストをパスに組み立て
     *
     * @param array|null $context
     * @return string
     */
    protected function buildUrlContext(?array $context): string
    {
        if (!$context) {
            return '';
        }
        $urlContext = '';
        foreach ($context as $key => $val) {
            if ($key === 'datetimeRange') {
                continue;
            }
            if (is_array($val)) {
                $val = implode('/', $val);
            }
            $urlContext .= '/' . $key . '/' . $val;
        }
        if (isset($context['datetimeRange'])) {
            $urlContext .= $context['datetimeRange'];
        }
        return $urlContext;
    }

    /**
     * キャッシュキーを生成
     *
     * @param string $name
     * @param string|null $identifier
     * @param array $ctx
     * @return string
     */
    protected function generateCacheKey(string $name, ?string $identifier = null, array $ctx = []): string
    {
        ksort($ctx);
        $encoded = json_encode($ctx);
        if ($encoded === false) {
            throw new \RuntimeException('Failed to encode context to JSON: ' . json_last_error_msg());
        }
        $ctxKey = md5($encoded);

        return $name . ($identifier ? '-' . $identifier : '') . '-' . $ctxKey;
    }
}
