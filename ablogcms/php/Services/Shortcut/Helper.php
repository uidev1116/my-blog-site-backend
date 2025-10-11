<?php

declare(strict_types=1);

namespace Acms\Services\Shortcut;

use Acms\Services\Facades\Application;

class Helper
{
    /**
     * ショートカットに登録できるリソースのタイプ一覧
     *
     * @var string[]
     */
    private $types = [];

    /**
     * @var Repository
     */
    protected $ShortcutRepository;

    /**
     * 初期化処理
     *
     * @param string[] $types ショートカットに登録できるリソースのタイプ一覧
     */
    public function __construct(array $types)
    {
        $this->types = $types;
        $this->ShortcutRepository = Application::make('shortcut.repository');
    }

    /**
     * @param string $admin
     * @param array{
     *  bid: int|null,
     *  cid: int|null,
     *  rid: int|null,
     *  mid: int|null,
     *  scid: int|null,
     *  setid: int|null
     * } $ids
     *
     * @return string
     */
    public function createShortcutKey(string $admin, array $ids): string
    {
        return array_reduce(
            array_keys($ids),
            function (string $string, string $key) use ($ids) {
                if (is_null($ids[$key])) {
                    return $string;
                }
                return $string .= $key . '_' . $ids[$key] . '_';
            },
            'shortcut_'
        ) . $admin;
    }

    /**
     * ショートカットURLの生成
     *
     * @param string $admin
     * @param array{
     *  bid: int|null,
     *  cid: int|null,
     *  rid: int|null,
     *  mid: int|null,
     *  scid: int|null,
     *  setid: int|null
     * } $ids
     *
     * @return string
     */
    public function createUrl(string $admin, array $ids): string
    {
        // Ver. 3.0以前のモジュールIDのショートカット機能対応のため残してあります。
        if (!is_null($ids['mid']) && sessionWithAdministration()) {
            $admin = 'module_edit';
        }
        $url = [
            'bid'   => BID,
            'admin' => $admin,
        ];

        $query = [];
        // acmsパスで指定できるID
        $acmsPathIds = ['bid', 'aid', 'uid', 'cid', 'eid'];
        foreach ($ids as $key => $id) {
            if (is_null($id)) {
                continue;
            }

            if (in_array($key, $acmsPathIds, true)) {
                $url[$key] = $id;
            } else {
                $query[$key] = $id;
            }
        }
        $url['query'] = $query;

        return acmsLink($url); // @phpstan-ignore argument.type
    }

    /**
     * ショートカットによる認可チェック
     *
     * @param string $admin
     * @param array{
     *  bid?: int|null,
     *  cid?: int|null,
     *  rid?: int|null,
     *  mid?: int|null,
     *  scid?: int|null,
     *  setid?: int|null
     * } $ids
     * @param int $blogId
     *
     * @return bool
     **/
    public function authorization(string $admin, array $ids, int $blogId = BID)
    {
        $admin = str_replace('/', '_', $admin);
        $baseIds = array_combine($this->types, array_fill(0, count($this->types), null));
        /** @var array{bid: int|null, cid: int|null, rid: int|null, mid: int|null, scid: int|null, setid: int|null} $ids */
        $ids = array_merge($baseIds, $ids);
        $key = $this->createShortcutKey($admin, $ids);
        $authorities = $this->getAuthorities();

        if ($this->ShortcutRepository->authorizationExists($key, $authorities, $blogId)) {
            return true;
        }

        return false;
    }

    /**
     * ログインユーザーの権限を取得
     *
     * @return string[]
     **/
    public function getAuthorities(): array
    {
        $authorities = [];
        if (sessionWithContribution()) {
            $authorities[] = 'contribution';
        }
        if (sessionWithCompilation()) {
            $authorities[] = 'compilation';
        }
        if (sessionWithAdministration()) {
            $authorities[] = 'administration';
        }

        return $authorities;
    }

    /**
     * クエリパラメーターからidsを作成
     *
     * @param \Field $getParameter
     * @return string[]
     **/
    public function createIdsFromGetParameter(\Field $getParameter): array
    {
        return array_reduce(
            $this->types,
            function (array $ids, string $key) use ($getParameter) {
                $id = $getParameter->get($key) ? intval($getParameter->get($key)) : null;
                return array_merge($ids, [$key => $id]);
            },
            []
        );
    }

    /**
     * ショートカットオブジェクトの作成
     *
     * @param array $resource
     * @return Entities\Shortcut
     **/
    public function createShortcut(array $resource): Entities\Shortcut
    {
        return new Entities\Shortcut(
            $this->createShortcutKey($resource['admin'], $resource['ids']),
            isset($resource['sort']) ? $resource['sort'] : null,
            isset($resource['name']) ? $resource['name'] : '',
            isset($resource['auth']) ? $resource['auth'] : '',
            isset($resource['action']) ? $resource['action'] : '',
            isset($resource['admin']) ? $resource['admin'] : '',
            isset($resource['ids']['bid']) ? $resource['ids']['bid'] : null,
            isset($resource['ids']['cid']) ? $resource['ids']['cid'] : null,
            isset($resource['ids']['rid']) ? $resource['ids']['rid'] : null,
            isset($resource['ids']['mid']) ? $resource['ids']['mid'] : null,
            isset($resource['ids']['setid']) ? $resource['ids']['setid'] : null,
            isset($resource['ids']['scid']) ? $resource['ids']['scid'] : null,
            isset($resource['blogId']) ? $resource['blogId'] : null
        );
    }
}
