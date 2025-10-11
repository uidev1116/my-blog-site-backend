<?php

use Acms\Services\Facades\Common;
use Acms\Services\Facades\Database as DB;

class ACMS_GET_Admin_User_Assist extends ACMS_GET_Admin
{
    /** @inheritDoc */
    public $_scope = [
        'uid' => 'global',
        'keyword' => 'global',
    ];

    /**
     * 検索可能なカラム
     * @var string[]
     */
    protected $filterableColumns = [
        'user_code',
        'user_name',
    ];

    /** @inheritDoc */
    public function get()
    {
        if (!sessionWithContribution()) {
            return Common::responseJson([]);
        }
        $order = 'sort-desc';
        $order2 = config('user_select_global_order');
        if ($order2 !== '') {
            $order = $order2;
        }
        $limit = (int)config('user_select_limit', 999);
        $query = $this->buildQuery($order, $limit);
        $list = $this->buildList($query);
        return Common::responseJson($list);
    }

    /**
     * クエリを組み立て
     * @param string $order ソート順
     * @param int $limit 取得制限数
     * @return SQL_Select
     */
    protected function buildQuery(string $order, int $limit = 999): SQL_Select
    {
        $sql = SQL::newSelect('user');
        ACMS_Filter::userStatus($sql);
        // 擬似的なユーザーと退会ユーザーはエントリーを持てないため除外
        $sql->addWhereNotIn('user_status', ['pseudo', 'withdrawal']);
        $sql->addWhereIn('user_auth', ['administrator', 'editor', 'contributor']);

        // 現在のブログを内包するブログ（現在のブログおよびその親ブログ）に所属するユーザー
        $sql->addLeftJoin('blog', 'blog_id', 'user_blog_id');
        $sql->addWhereOpr('blog_left', ACMS_RAM::blogLeft(BID), '<=');
        $sql->addWhereOpr('blog_right', ACMS_RAM::blogRight(BID), '>=');

        if (!$this->canShowOtherUser()) {
            $sql->addWhereOpr('user_id', SUID);
        }

        if ($this->keyword !== '') {
            $columns = array_map(function ($column) {
                return "`{$column}`";
            }, $this->filterableColumns);

            $sql->addWhereOpr(
                SQL::newField(implode(',', $columns), null, false),
                '%' . addcslashes($this->keyword, '%_\\') . '%',
                'LIKE',
                'AND',
                null,
                'CONCAT'
            );
        }

        ACMS_Filter::userOrder($sql, $order);
        $sql->setLimit($limit);

        return $sql;
    }

    /**
     * ユーザーリストを構築
     * @param SQL_Select $sql
     * @return array<int, array{label: string, value: string}> ユーザー選択肢の配列
     */
    protected function buildList(SQL_Select $sql): array
    {
        /** @var array<int, array{label: string, value: string}> */
        $list = [];
        $query = $sql->get(dsn());
        $statement = DB::query($query, 'exec');

        if (!!($row = DB::next($statement))) {
            do {
                $list[] = [
                    'label' => $row['user_name'],
                    'value' => strval($row['user_id']),
                ];
            } while (!!($row = DB::next($statement)));
        }

        $currentUid = (int)$this->Get->get('currentUid');
        if ($currentUid > 0) {
            if (array_search(strval($currentUid), array_column($list, 'value'), true) === false) {
                $userName = ACMS_RAM::userName($currentUid);
                if ($userName) {
                    $list[] = [
                        'label' => $userName,
                        'value' => strval($currentUid),
                    ];
                }
            }
        }
        return $list;
    }

    /**
     * 自分以外のユーザーを表示できるか
     * @return bool
     */
    protected function canShowOtherUser(): bool
    {
        if (roleAvailableUser()) {
            if (roleAuthorization('entry_edit_all', BID)) {
                // 全エントリーの編集権限がある場合は自分以外のユーザーを表示できる
                return true;
            }

            if (sessionWithCompilation(BID)) {
                // 編集者以上の場合は自分以外のユーザーを表示できる
                return true;
            }

            return false;
        }

        if (sessionWithCompilation(BID)) {
            // 編集者以上の場合は自分以外のユーザーを表示できる
            return true;
        }

        if (enableApproval(BID, CID) && config('approval_contributor_edit_auth') !== 'on') {
            // 承認機能が有効で、かつ投稿者が自分のエントリーのみ編集可能な設定が無効な場合（他のユーザーの記事を編集可能な場合）は、自分以外のユーザーを表示できる
            return true;
        }

        return false;
    }
}
