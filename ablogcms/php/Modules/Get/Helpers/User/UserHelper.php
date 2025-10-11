<?php

namespace Acms\Modules\Get\Helpers\User;

use Acms\Modules\Get\Helpers\BaseHelper;
use Acms\Services\Facades\Common;
use Acms\Services\Facades\Database;
use ACMS_RAM;
use ACMS_Filter;
use SQL;
use SQL_Select;

class UserHelper extends BaseHelper
{
    use \Acms\Traits\Utilities\FieldTrait;
    use \Acms\Traits\Utilities\EagerLoadingTrait;
    use \Acms\Traits\Utilities\PaginationTrait;

    /**
     * ユーザーへのアクセス権限を確認する
     *
     * @param int $uid
     * @return boolean
     */
    public function canAccessUser(int $uid): bool
    {
        if (!$uid) {
            return false;
        }
        $status = ACMS_RAM::userStatus((int) $this->uid);

        if ($status === 'open') {
            return true;
        }
        if (sessionWithAdministration() && 'close' === $status) {
            return true;
        }
        return false;
    }

    /**
     * ユーザー数取得用のSQLを返す
     *
     * @return SQL_Select
     */
    public function getCountQuery(): SQL_Select
    {
        return $this->countQuery;
    }

    /**
     * ユーザープロフィールデータを取得する
     *
     * @param array $users
     * @return array
     */
    public function getUserProfileData(array $users): array
    {
        $data = [];
        foreach ($users as $row) {
            $uid = (int) $row['user_id'];
            $vars = [];
            foreach ($row as $key => $val) {
                if ($key === 'user_mail_magazine' || $key === 'user_mail_mobile_magazine') {
                    $val = $val === 'on' ? 'on' : 'off';
                }
                $vars[substr($key, strlen('user_'))] = $val;
            }
            $icon = loadUserIcon($uid);
            $largeIcon = loadUserLargeIcon($uid);
            $originalIcon = loadUserOriginalIcon($uid);
            $vars['icon'] = $icon ? Common::resolveUrl($icon, ARCHIVES_DIR) : null;
            $vars['largeIcon'] = $largeIcon ? Common::resolveUrl($largeIcon, ARCHIVES_DIR) : null;
            $vars['origIcon'] = $originalIcon ? Common::resolveUrl($originalIcon, ARCHIVES_DIR) : null;
            if (isset($row['latitude'])) {
                $vars['geo_lat'] = $row['latitude'];
            }
            if (isset($row['longitude'])) {
                $vars['geo_lng'] = $row['longitude'];
            }
            $data[] = $vars;
        }
        return $data;
    }

    /**
     * ユーザー情報を取得するSQLを生成する
     *
     * @return SQL_Select
     */
    public function buildUserIndexQuery(): SQL_Select
    {
        $sql = SQL::newSelect('user', 'user');
        $sql->addLeftJoin('blog', 'blog_id', 'user_blog_id', 'blog', 'user');

        if ($this->config['geolocation_on'] === 'on') {
            $sql->addLeftJoin('geo', 'geo_uid', 'user_id', 'geo', 'user');
            $sql->addSelect('user.*');
            $sql->addSelect('geo_geometry', 'longitude', null, 'ST_X');
            $sql->addSelect('geo_geometry', 'latitude', null, 'ST_Y');
        }

        $this->filterQuery($sql);
        if ($uid = intval($this->uid)) {
            $sql->addWhereOpr('user_id', $uid);
        }
        $this->setCountQuery($sql); // limitする前のクエリから全件取得のクエリを準備しておく
        $this->orderQuery($sql);
        $this->limitQuery($sql);

        return $sql;
    }

    /**
     * 絞り込みクエリ組み立て
     *
     * @param SQL_Select $sql
     * @return void
     */
    protected function filterQuery(SQL_Select $sql): void
    {
        $sql->addWhereOpr('user_pass', '', '<>');

        // blog axis
        ACMS_Filter::blogTree($sql, $this->bid, $this->blogAxis);
        ACMS_Filter::blogStatus($sql);
        // field
        if ($this->Field) {
            ACMS_Filter::userField($sql, $this->Field);
        }
        // keyword
        if ($this->keyword) {
            ACMS_Filter::userKeyword($sql, $this->keyword);
        }
        // indexing
        if ($this->config['indexing'] === 'on') {
            $sql->addWhereOpr('user_indexing', 'on');
        }
        // auth
        if ($this->config['auth']) {
            $sql->addWhereIn('user_auth', $this->config['auth']);
        }
        // status 2013/02/08
        if ($this->config['status']) {
            $statusWhere = SQL::newWhere();
            foreach ($this->config['status'] as $status) {
                if ($status === 'open') {
                    $openStatusWhere = SQL::newWhere();
                    $openStatusWhere->addWhereOpr('user_login_expire', date('Y-m-d', REQUEST_TIME), '>=', 'AND');
                    $openStatusWhere->addWhereOpr('user_status', 'open', '=', 'AND');
                    $statusWhere->addWhere($openStatusWhere, 'OR');
                } elseif ($status === 'close') {
                    $closeStatusWhere = SQL::newWhere();
                    $closeStatusWhere->addWhereOpr('user_login_expire', date('Y-m-d', REQUEST_TIME), '<', 'OR');
                    $closeStatusWhere->addWhereOpr('user_status', 'close', '=', 'OR');
                    $statusWhere->addWhere($closeStatusWhere, 'OR');
                } else {
                    $otherStatusWhere = SQL::newWhere();
                    $otherStatusWhere->addWhereOpr('user_status', $status, '=', 'OR');
                    $statusWhere->addWhere($otherStatusWhere, 'OR');
                }
            }
            $sql->addWhere($statusWhere, 'AND');
        }
        // mail_magazine 2013/02/08
        if (is_array($this->config['mail_magazine']) && count($this->config['mail_magazine']) > 0) {
            foreach ($this->config['mail_magazine'] as $val_mailmagazine) {
                switch ($val_mailmagazine) {
                    case 'pc':
                        $sql->addWhereOpr('user_mail_magazine', 'on');
                        $sql->addWhereOpr('user_mail', '', '<>');
                        break;
                    case 'mobile':
                        $sql->addWhereOpr('user_mail_mobile_magazine', 'on');
                        $sql->addWhereOpr('user_mail_mobile', '', '<>');
                        break;
                }
            }
        }
    }

    /**
     * ユーザー数取得sqlの準備
     *
     * @param SQL_Select $sql
     * @return void
     */
    protected function setCountQuery(SQL_Select $sql): void
    {
        $this->countQuery = new SQL_Select(clone $sql);
        $this->countQuery->setSelect(SQL::newFunction('user_id', 'DISTINCT'), 'user_amount', null, 'COUNT');
    }

    /**
     * orderクエリ組み立て
     *
     * @param SQL_Select $sql
     * @return void
     */
    protected function orderQuery(SQL_Select $sql): void
    {
        if ($this->uid) {
            ACMS_Filter::userOrder($sql, $this->config['order']);
            $sql->setGroup('user_id');
        }
    }

    /**
     * limitクエリ組み立て
     *
     * @param SQL_Select $sql
     * @return void
     */
    protected function limitQuery(SQL_Select $sql): void
    {
        if ($this->uid) {
            $sql->setLimit(1);
        } else {
            $limit = $this->config['limit'];
            $from = ($this->page - 1) * $limit;
            $sql->setLimit($limit, $from);
        }
    }

    /**
     * ユーザーインデックスを組み立てる
     *
     * @param array $users
     * @return array
     */
    public function buildUserIndex(array $users): array
    {
        $items = [];
        $includeEntryData = $this->config['entry_list_enable'] === 'on';
        $userIds = array_map(function ($user) {
            return (int) $user['user_id'];
        }, $users);
        $eagerLoadingField = $this->eagerLoadFieldTrait($userIds, 'uid');

        foreach ($users as $row) {
            $vars = [
                'uid' => (int) $row['user_id'],
                'code' => $row['user_code'] ?? null,
                'name' => $row['user_name'] ?? null,
                'mail' => $row['user_mail'] ?? null,
                'mail_magaginze' => $row['user_mail_magazine'] ?? null,
                'url' => $row['user_url'] ?? null,
                'auth' => $row['user_auth'] ?? null,
                'locale' => $row['user_locale'] ?? null,
                'indexing' => $row['user_indexing'] ?? null,
                'expiresAt' => $row['user_login_expire'] ?? null,
                'updatedAt' => $row['user_updated_datetime'] ?? null,
                'blog_id' => $row['user_blog_id'],
            ];
            $uid = (int) $row['user_id'];
            $icon = loadUserIcon($uid);
            $largeIcon = loadUserLargeIcon($uid);
            $originalIcon = loadUserOriginalIcon($uid);
            $vars['icon'] = $icon ? Common::resolveUrl($icon, ARCHIVES_DIR) : null;
            $vars['largeIcon'] = $largeIcon ? Common::resolveUrl($largeIcon, ARCHIVES_DIR) : null;
            $vars['origIcon'] = $originalIcon ? Common::resolveUrl($originalIcon, ARCHIVES_DIR) : null;
            $vars['fields'] = isset($eagerLoadingField[$uid]) ? $this->buildFieldTrait($eagerLoadingField[$uid]) : null;
            $vars['entries'] = $includeEntryData ? $this->buildUserEntry($uid) : [];
            $vars['geo'] = [
                'lat' => $row['latitude'] ?? null,
                'lng' => $row['longitude'] ?? null,
                'zoom' => $row['geo_zoom'] ?? null,
                'distance' => $row['distance'] ?? null,
            ];
            $items[] = $vars;
        }
        return $items;
    }

    /**
     * ユーザーのエントリーを取得する
     *
     * @param int $uid
     * @return array
     */
    public function buildUserEntry(int $uid): array
    {
        $sql = SQL::newSelect('entry');
        $sql->addWhereOpr('entry_user_id', $uid);
        $sql->addLeftJoin('blog', 'blog_id', 'entry_blog_id');
        ACMS_Filter::entrySession($sql);
        if ($this->start && $this->end) {
            ACMS_Filter::entrySpan($sql, $this->start, $this->end);
        }
        if ($this->bid) {
            ACMS_Filter::blogTree($sql, $this->bid, $this->blogAxis);
            ACMS_Filter::blogStatus($sql);
        }
        ACMS_Filter::entryOrder($sql, $this->config['entry_list_order'], $uid);
        $sql->setLimit($this->config['entry_list_limit'], 0);
        $sql->setGroup('entry_id');
        $q = $sql->get(dsn());
        $entries = Database::query($q, 'all');
        $entryData = [];
        foreach ($entries as $i => $entry) {
            $vars = [];
            $link = $entry['entry_link'];
            if ($link !== '#') {
                $vars['url'] = $link ? $link : acmsLink([
                    'bid' => $entry['entry_blog_id'],
                    'cid' => $entry['entry_category_id'],
                    'eid' => $entry['entry_id'],
                ]);
            } else {
                $vars['url'] = null;
            }
            $vars['title'] = addPrefixEntryTitle(
                $entry['entry_title'],
                $entry['entry_status'],
                $entry['entry_start_datetime'],
                $entry['entry_end_datetime'],
                $entry['entry_approval']
            );
            $entryData[] = $vars;
        }
        return $entryData;
    }

    /**
     * ページネーションを組み立て
     *
     * @param SQL_Select $countQuery
     * @return null|array
     */
    public function buildPagination(SQL_Select $countQuery): ?array
    {
        $q = $countQuery->get(dsn());
        $itemsAmount = (int) Database::query($q, 'one');
        return $this->buildPaginationTrait(
            $this->page,
            $itemsAmount,
            $this->config['limit'],
            $this->config['pager_delta']
        );
    }
}
