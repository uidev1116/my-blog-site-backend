<?php

declare(strict_types=1);

namespace Acms\Services\Shortcut;

use Acms\Services\Facades\Database as DB;
use SQL;

class Repository
{
    /**
     * ショートカットに登録できるリソースのタイプ一覧
     *
     * @var string[]
     */
    private $types = [];

    /**
     * 初期化処理
     *
     * @param string[] $types ショートカットに登録できるリソースのタイプ一覧
     */
    public function __construct(array $types)
    {
        $this->types = $types;
    }

    /**
     * ショートカットの取得
     *
     * @param string $shortcutKey
     * @param int $blogId
     *
     * @return Entities\Shortcut|null
     */
    public function findOneByKey(string $shortcutKey, int $blogId = BID): ?Entities\Shortcut
    {
        $sql = SQL::newSelect('dashboard');
        $sql->addWhereOpr('dashboard_key', $shortcutKey . '%', 'LIKE');
        $sql->addWhereOpr('dashboard_blog_id', $blogId);
        $rows = DB::query($sql->get(dsn()), 'all');

        if (empty($rows)) {
            return null;
        }

        $shortcut = $this->fixRows($rows)[0];
        return new Entities\Shortcut(
            $shortcut['key'],
            (int)$shortcut['sort'],
            $shortcut['name'],
            $shortcut['auth'],
            $shortcut['action'],
            $shortcut['admin'],
            $shortcut['bid'],
            $shortcut['cid'],
            $shortcut['rid'],
            $shortcut['mid'],
            $shortcut['setid'],
            $shortcut['scid'],
            (int)$shortcut['blogId']
        );
    }

    /**
     * 指定したブログのShortcutを配列で取得
     *
     * @param int $blogId
     * @return Entities\Shortcut[]
     */
    public function findAll(int $blogId = BID): array
    {
        $sql = SQL::newSelect('dashboard');
        $sql->addWhereOpr('dashboard_key', 'shortcut_%', 'LIKE');
        $sql->addWhereOpr('dashboard_blog_id', $blogId);
        $sql->setOrder('dashboard_sort');
        $rows = DB::query($sql->get(dsn()), 'all');

        $data = $this->fixRows($rows);

        return array_map(
            function (array $shortcut) {
                return new Entities\Shortcut(
                    $shortcut['key'],
                    (int)$shortcut['sort'],
                    $shortcut['name'],
                    $shortcut['auth'],
                    $shortcut['action'],
                    $shortcut['admin'],
                    $shortcut['bid'],
                    $shortcut['cid'],
                    $shortcut['rid'],
                    $shortcut['mid'],
                    $shortcut['setid'],
                    $shortcut['scid'],
                    (int)$shortcut['blogId']
                );
            },
            $data
        );
    }

    /**
     * 指定したブログのShortcutを権限で絞り込んで取得
     *
     * @param array $authorities
     * @param int $blogId
     * @return Entities\Shortcut[]
     */
    public function findByAuthorities(array $authorities = [], int $blogId = BID): array
    {
        return array_values(
            array_filter(
                $this->findAll($blogId),
                function (Entities\Shortcut $Shortcut) use ($authorities) {
                    return in_array($Shortcut->getAuth(), $authorities, true);
                }
            )
        );
    }

    /**
     * ショートカットの削除
     *
     * @param Entities\Shortcut $Shortcut
     * @return void
     */
    public function delete(Entities\Shortcut $Shortcut)
    {
        $data = $Shortcut->getDataBaseKeyValues();
        $targetBlogId = $Shortcut->getBlogId();

        $sql = SQL::newDelete('dashboard');
        $sql->addWhereIn('dashboard_key', array_keys($data));
        $sql->addWhereOpr('dashboard_blog_id', $targetBlogId);
        DB::query($sql->get(dsn()), 'exec');
    }

    /**
     * ショートカットの保存
     *
     * @param Entities\Shortcut $Shortcut
     * @return void
     */
    public function save(Entities\Shortcut $Shortcut)
    {
        $this->delete($Shortcut);

        $data = $Shortcut->getDataBaseKeyValues();
        $blogId = $Shortcut->getBlogId();
        $sort = $Shortcut->getSort();

        $sql = SQL::newBulkInsert('dashboard');
        foreach ($data as $key => $value) {
            $sql->addInsert([
                'dashboard_key' => $key,
                'dashboard_value' => $value,
                'dashboard_sort' => $sort,
                'dashboard_blog_id' => $blogId,
            ]);
        }
        if ($sql->hasData()) {
            DB::query($sql->get(dsn()), 'exec');
        }
    }

    /**
     * 指定したブログにおける次のソート順を取得する
     *
     * @param int $blogId
     * @return int
     */
    public function nextSort(int $blogId = BID): int
    {
        $sql = SQL::newSelect('dashboard');
        $sql->setSelect('dashboard_sort');
        $sql->addWhereOpr('dashboard_key', 'shortcut_%', 'LIKE');
        $sql->addWhereOpr('dashboard_blog_id', $blogId);
        $sql->setOrder('dashboard_sort', 'DESC');
        $sql->setLimit(1);
        return intval(DB::query($sql->get(dsn()), 'one')) + 1;
    }

    /**
     * 指定したブログにおけるショートカットの存在チェック
     *
     * @param string $key
     * @param array $authorities
     * @param int $blogId
     * @return bool
     */
    public function authorizationExists(string $key, array $authorities = [], int $blogId = BID): bool
    {
        $sql = SQL::newSelect('dashboard');
        $sql->setSelect('dashboard_key');
        $sql->addWhereOpr('dashboard_key', $key . '_auth');
        $sql->addWhereIn('dashboard_value', $authorities);
        $sql->addWhereOpr('dashboard_blog_id', $blogId);

        return !!DB::query($sql->get(dsn()), 'one');
    }

    /**
     * ショートカットキーの解析
     *
     * @param string $key
     * @return array{
     *  bid?: int|null,
     *  cid?: int|null,
     *  rid?: int|null,
     *  mid?: int|null,
     *  scid?: int|null,
     *  setid?: int|null,
     *  admin: string,
     *  key: string,
     *  type: string
     * }
     */
    protected function parseShortcutKey(string $key): array
    {
        $regex = '/^shortcut_((?:(?:' . join('|', $this->types) . ')_(?:\d+)_)*)(.+)_([^_]+)$/';
        preg_match($regex, $key, $match);
        $key = 'shortcut_' . $match[1] . $match[2];
        $idsStr = $match[1];
        $admin = $match[2];
        $type = $match[3];
        $ids = array_combine($this->types, array_fill(0, count($this->types), null));
        $resource = explode('_', $idsStr);
        foreach ($resource as $i => $node) {
            if (ctype_digit(strval($node))) {
                $ids[$resource[$i - 1]] = intval($node);
            }
        }
        return [
            'bid' => $ids['bid'] ?? null,
            'cid' => $ids['cid'] ?? null,
            'rid' => $ids['rid'] ?? null,
            'mid' => $ids['mid'] ?? null,
            'scid' => $ids['scid'] ?? null,
            'setid' => $ids['setid'] ?? null,
            'key' => $key,
            'admin' => $admin,
            'type' => $type,
        ];
    }

    /**
     * レコードの整形
     *
     * @param array $rows
     * @return array
     **/
    protected function fixRows(array $rows): array
    {
        return array_reduce(
            $rows,
            function (array $shortcuts, array $row) {
                [
                    'key' => $key,
                    'admin' => $admin,
                    'type' => $type,
                    'bid' => $bid,
                    'cid' => $cid,
                    'rid' => $rid,
                    'mid' => $mid,
                    'setid' => $setid,
                    'scid' => $scid,
                ] = $this->parseShortcutKey($row['dashboard_key']);

                if (!in_array($key, array_column($shortcuts, 'key'), true)) {
                    $shortcuts[] = [
                        'key' => $key,
                        'sort' => intval($row['dashboard_sort']),
                        'admin' => $admin,
                        $type => $row['dashboard_value'],
                        'bid' => $bid,
                        'cid' => $cid,
                        'rid' => $rid,
                        'mid' => $mid,
                        'setid' => $setid,
                        'scid' => $scid,
                        'blogId' => intval($row['dashboard_blog_id'])
                    ];
                    return $shortcuts;
                }

                return array_map(
                    function (array $shortcut) use ($key, $type, $row) {
                        return $shortcut['key'] === $key
                            ? array_merge($shortcut, [$type => $row['dashboard_value']])
                            : $shortcut;
                    },
                    $shortcuts
                );
            },
            []
        );
    }
}
