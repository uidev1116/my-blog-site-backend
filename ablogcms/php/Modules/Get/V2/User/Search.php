<?php

namespace Acms\Modules\Get\V2\User;

use Acms\Modules\Get\V2\Base;
use Acms\Modules\Get\Helpers\User\UserHelper;
use Acms\Services\Facades\Database;
use ACMS_RAM;
use SQL_Select;

class Search extends Base
{
    use \Acms\Traits\Modules\ConfigTrait;

    /**
     * @inheritDoc
     */
    protected $scopes = [ // phpcs:ignore
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
        $config = $this->loadModuleConfig();
        return [
            'indexing' => $config->get('user_search_indexing'),
            'auth' => configArray('user_search_auth'),
            'status' => configArray('user_search_status'),
            'mail_magazine' => configArray('user_search_mail_magazine'),
            'order' => $config->get('user_search_order'),
            'limit' => $this->limit ?? (int) $config->get('user_search_limit'),
            'pager_delta' => $config->get('user_search_pager_delta'),
            'entry_list_enable' => $config->get('user_search_entry_list_enable'),
            'entry_list_order' => $config->get('user_search_entry_list_order'),
            'entry_list_limit' => $config->get('user_search_entry_list_limit'),
            'geolocation_on' => $config->get('user_search_geolocation_on')
        ];
    }

    /**
     * @inheritDoc
     */
    public function get(): array
    {
        try {
            if (!$this->setConfigTrait()) {
                throw new \RuntimeException('Failed to set config.');
            }
            $vars = [];
            // 起動
            $this->boot();
            // SQL組み立て
            $sql = $this->buildQuery();
            // ユーザー取得
            $users = $this->getUsers($sql);
            // カスタム処理
            $vars = $this->preBuild($vars, $users);
            // ユーザー一覧組み立て
            $vars['items'] = $this->build($users);
            $vars['pagination'] = $this->buildPagination();
            $vars['moduleFields'] = $this->buildModuleField();

            return $vars;
        } catch (\Exception $e) {
            return [];
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
     * @param array $vars
     * @param array $users
     * @return array
     */
    protected function preBuild(array $vars, array $users): array
    {
        return $vars;
    }

    /**
     * ユーザー一覧の組み立て
     *
     * @param array $users
     * @return array
     */
    protected function build(array $users): array
    {
        return $this->userHelper->buildUserIndex($users);
    }

    /**
     * ページネーションの組み立て
     *
     * @return array|null
     */
    protected function buildPagination(): ?array
    {
        if ($this->uid) {
            return null;
        }
        $countQuery = $this->userHelper->getCountQuery();
        return $this->userHelper->buildPagination($countQuery);
    }

    /**
     * ユーザーの取得
     *
     * @param SQL_Select $sql
     * @return array
     */
    protected function getUsers(SQL_Select $sql): array
    {
        $users = Database::query($sql->get(dsn()), 'all');
        foreach ($users as $user) {
            $uid = (int) $user['user_id'];
            ACMS_RAM::user($uid, $user);
        }
        return $users;
    }
}
