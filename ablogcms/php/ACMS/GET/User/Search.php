<?php

use Acms\Modules\Get\Helpers\User\UserHelper;
use Acms\Services\Facades\Template as TemplateHelper;
use Acms\Services\Facades\Database;

class ACMS_GET_User_Search extends ACMS_GET
{
    use \Acms\Traits\Modules\ConfigTrait;

    public $_scope = [
        'uid' => 'global',
        'field' => 'global',
        'page' => 'global',
    ];

    /**
     * @var \Acms\Modules\Get\Helpers\User\UserHelper
     */
    protected $userHelper;

    /**
     * @inheritDoc
     */
    protected function initConfig(): array
    {
        return [
            'indexing' => config('user_search_indexing'),
            'auth' => configArray('user_search_auth'),
            'status' => configArray('user_search_status'),
            'mail_magazine' => configArray('user_search_mail_magazine'),
            'order' => config('user_search_order'),
            'limit' => intval(config('user_search_limit')),
            'parent_loop_class' => config('user_search_parent_loop_class'),
            'loop_class' => config('user_search_loop_class'),
            'pager_delta' => config('user_search_pager_delta'),
            'pager_cur_attr' => config('user_search_pager_cur_attr'),
            'entry_list_enable' => config('user_search_entry_list_enable'),
            'entry_list_order' => config('user_search_entry_list_order'),
            'entry_list_limit' => config('user_search_entry_list_limit'),
            'geolocation_on' => config('user_search_geolocation_on')
        ];
    }

    function get()
    {
        try {
            if (!$this->setConfigTrait()) {
                throw new \RuntimeException('Failed to set config.');
            }
            $tpl = new Template($this->tpl, new ACMS_Corrector());
            TemplateHelper::buildModuleField($tpl);

            // 起動
            $this->boot();
            // SQL組み立て
            $sql = $this->buildQuery();
            // ユーザー取得
            $users = $this->getUsers($sql);
            // カスタム処理
            [$isRunnable, $renderTpl] = $this->preBuild($tpl);
            if (!$isRunnable) {
                if ($renderTpl) {
                    return $tpl->get();
                } else {
                    return '';
                }
            }
            // NotFound処理
            if ($this->buildNotFound($tpl, $users)) {
                return $tpl->get();
            }
            $this->build($tpl, $users);

            $vars = $this->buildPagination($tpl);
            $vars = array_merge($vars, $this->getRootVars());
            $tpl->add(null, $vars);

            return $tpl->get();
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * 起動処理
     *
     * @return void
     */
    protected function boot(): void
    {
        $this->userHelper = new UserHelper($this->getBaseParams([
            'config' => $this->config,
        ]));
    }

    /**
     * クエリの組み立て
     *
     * @return SQL_Select
     */
    protected function buildQuery(): SQL_Select
    {
        return $this->userHelper->buildUserIndexQuery();
    }

    /**
     * ビルド前のカスタム処理
     *
     * @param Template $tpl
     * @return array{0: bool, 1: bool} 0: 処理を続けるかどうか, 1: ここまでの処理をレンダリングするかどうか
     */
    protected function preBuild(Template $tpl): array
    {
        return [true, true];
    }

    /**
     * ページネーションの組み立て
     *
     * @param Template $tpl
     * @return array
     */
    protected function buildPagination(Template $tpl): array
    {
        if ($this->uid) {
            return [];
        }
        $countQuery = $this->userHelper->getCountQuery();
        $itemsAmount = (int) Database::query($countQuery->get(dsn()), 'one');

        return TemplateHelper::buildPager(
            $this->page,
            $this->config['limit'],
            $itemsAmount,
            $this->config['pager_delta'],
            $this->config['pager_cur_attr'],
            $tpl
        );
    }

    /**
     * ユーザーの取得
     *
     * @param SQL_Select $sql
     * @return array
     */
    protected function getUsers(SQL_Select $sql): array
    {
        $q = $sql->get(dsn());
        $users = Database::query($q, 'all');
        foreach ($users as $user) {
            $uid = (int) $user['user_id'];
            ACMS_RAM::user($uid, $user);
        }
        return $users;
    }

    /**
     * NotFound時のテンプレート組み立て
     *
     * @param Template $tpl
     * @return bool
     */
    protected function buildNotFound(Template $tpl, array $users): bool
    {
        if ($users) {
            return false;
        }
        $tpl->add('notFound');
        return true;
    }

    /**
     * ユーザー一覧の組み立て
     *
     * @param array $users
     * @return void
     */
    protected function build(Template $tpl, array $users): void
    {
        // entry list config
        $entry_list_enable = $this->config['entry_list_enable'] === 'on';
        $loop_class = $this->config['loop_class'];

        //-----------
        // user:loop
        foreach ($users as $i => $row) {
            $vars = TemplateHelper::buildField(loadUserField(intval($row['user_id'])), $tpl);
            $vars += [
                'i' => $i,
                'id' => (int) $row['user_id'],
                'code' => $row['user_code'] ?? null,
                'name' => $row['user_name'] ?? null,
                'mail' => $row['user_mail'] ?? null,
                'mail_magaginze' => $row['user_mail_magazine'] ?? null,
                'url' => $row['user_url'] ?? null,
                'auth' => $row['user_auth'] ?? null,
                'locale' => $row['user_locale'] ?? null,
                'indexing' => $row['user_indexing'] ?? null,
                'login_expire' => $row['user_login_expire'] ?? null,
                'updated_datetime' => $row['user_updated_datetime'] ?? null,
                'blog_id' => (int) $row['user_blog_id'],
            ];
            $id = intval($row['user_id']);
            $vars['icon'] = loadUserIcon($id);
            if ($large = loadUserLargeIcon($id)) {
                $vars['largeIcon'] = $large;
            }
            if ($orig = loadUserOriginalIcon($id)) {
                $vars['origIcon'] = $orig;
            }
            if ($entry_list_enable) {
                $this->loadUserEntry($tpl, $id, ['user:loop']);
            }
            $vars['user:loop.class'] = $loop_class;
            if (!empty($i)) {
                $tpl->add(array_merge(['user:glue', 'user:loop']));
            }
            if (isset($row['distance'])) {
                $vars['geo_distance'] = $row['distance'];
            }
            if (isset($row['latitude'])) {
                $vars['geo_lat'] = $row['latitude'];
            }
            if (isset($row['longitude'])) {
                $vars['geo_lng'] = $row['longitude'];
            }
            if (isset($row['geo_zoom'])) {
                $vars['geo_zoom'] = $row['geo_zoom'];
            }
            $tpl->add('user:loop', $vars);
        }
    }

    /**
     * ユーザーのエントリーをロード
     *
     * @param Template $tpl
     * @param int $uid
     * @param array $block
     * @return void
     */
    protected function loadUserEntry(Template $tpl, int $uid, array $block = []): void
    {
        $sql = SQL::newSelect('entry');
        $sql->addWhereOpr('entry_user_id', $uid);
        $sql->addLeftJoin('blog', 'blog_id', 'entry_blog_id');
        ACMS_Filter::entrySession($sql);
        ACMS_Filter::entrySpan($sql, $this->start, $this->end);

        if (!empty($this->bid)) {
            ACMS_Filter::blogTree($sql, $this->bid, $this->blogAxis());
            ACMS_Filter::blogStatus($sql);
        }
        ACMS_Filter::entryOrder($sql, $this->config['entry_list_order'], $uid);
        $sql->setLimit($this->config['entry_list_limit'], 0);
        $sql->setGroup('entry_id');
        $q = $sql->get(dsn());
        $entries = Database::query($q, 'all');
        foreach ($entries as $i => $entry) {
            $link = $entry['entry_link'];
            $vars = [];
            $url = acmsLink([
                'bid' => $entry['entry_blog_id'],
                'cid' => $entry['entry_category_id'],
                'eid' => $entry['entry_id'],
            ]);
            if (!empty($i)) {
                $tpl->add(array_merge(['glue', 'entry:loop']));
            }
            if (!empty($i)) {
                $tpl->add(array_merge(['entry:glue', 'entry:loop']));
            }

            if ($link != '#') {
                $vars += [
                    'url' => !empty($link) ? $link : $url,
                ];
                $tpl->add(array_merge(['url#rear', 'entry:loop'], $block));
            }
            $vars['title'] = addPrefixEntryTitle(
                $entry['entry_title'],
                $entry['entry_status'],
                $entry['entry_start_datetime'],
                $entry['entry_end_datetime'],
                $entry['entry_approval']
            );
            $tpl->add(array_merge(['entry:loop'], $block), $vars);
        }
    }

    /**
     * ルート変数を取得
     *
     * @return array<string, mixed>
     */
    public function getRootVars(): array
    {
        return [
            'parent.loop.class' => $this->config['parent_loop_class'] ?? '',
        ];
    }
}
