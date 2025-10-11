<?php

use Acms\Services\Facades\Module;

/**
 * @phpstan-type FindModulesParams array{
 *  'keyword': string | null,
 *  'status': 'open' | 'close' | null,
 *  'scope': 'local' | 'global' | null,
 *  'layoutUse': '1' | '0' | null,
 *  'blogId': int,
 *  'blogAxis': 'self' | 'descendant-or-self',
 *  'order': array{
 *    'field': string,
 *    'direction': 'asc' | 'desc',
 *  },
 * }
 * @phpstan-type ModuleRow array{
 *  'module_id': int,
 *  'module_name': string,
 *  'module_identifier': string,
 *  'module_label': string,
 *  'module_description': string,
 *  'module_status': 'open' | 'close',
 *  'module_scope': 'local' | 'global',
 *  'module_custom_field': '1' | '0',
 *  'module_layout_use': '1' | '0',
 *  'module_api_use': '1' | '0',
 *  'module_blog_id': int,
 *  'blog_id': int,
 *  'blog_code': string,
 *  'blog_name': string,
 * }
 */
class ACMS_GET_Admin_Module_Index extends ACMS_GET
{
    public function get()
    {
        $tpl = new Template($this->tpl, new ACMS_Corrector());
        if (!Module::canUpdate(BID)) {
            die403();
        }

        $vars = [];

        $params = $this->createParams();
        $modules = $this->findModules($params);

        if (count($modules) === 0) {
            $vars = array_merge(
                $vars,
                $this->buildNotFound(),
            );
            return $this->render($tpl, $vars);
        }

        $vars = array_merge(
            $vars,
            ['modules' => $this->buildModules($modules)],
            ['bulkActions' => $this->buildBulkActions($modules)],
        );

        return $this->render($tpl, $vars);
    }

    /**
     * 表示に必要なパラメータを生成する
     * @return FindModulesParams
     */
    protected function createParams(): array
    {
        $keyword = $this->defineKeyword();
        $status = $this->defineStatus();
        $scope = $this->defineScope();
        $layoutUse = $this->defineLayoutUse();
        $blogId = $this->defineBlogId();
        $blogAxis = $this->defineBlogAxis();
        $order = $this->defineOrder();
        return [
            'keyword' => $keyword,
            'status' => $status,
            'scope' => $scope,
            'layoutUse' => $layoutUse,
            'blogId' => $blogId,
            'blogAxis' => $blogAxis,
            'order' => $order,
        ];
    }

    /**
     * @return FindModulesParams['keyword']
     */
    protected function defineKeyword()
    {
        $keyword = $this->Get->get('keyword');
        if ($keyword !== '') {
            return $keyword;
        }
        return null;
    }

    /**
     * @return FindModulesParams['status']
     */
    protected function defineStatus()
    {
        $status = $this->Get->get('status');
        if (in_array($status, ['open', 'close'], true)) {
            return $status;
        }
        return null;
    }

    /**
     * @return FindModulesParams['scope']
     */
    protected function defineScope()
    {
        $scope = $this->Get->get('scope');
        if (in_array($scope, ['local', 'global'], true)) {
            return $scope;
        }
        return null;
    }

    /**
     * @return FindModulesParams['layoutUse']
     */
    protected function defineLayoutUse()
    {
        $layoutUse = $this->Get->get('layout_use');
        if (in_array($layoutUse, ['1', '0'], true)) {
            return $layoutUse;
        }
        return null;
    }

    /**
     * ブログIDを返す
     * @return FindModulesParams['blogId']
     */
    protected function defineBlogId(): int
    {
        return BID;
    }

    /**
     * ブログ軸を返す
     * @return FindModulesParams['blogAxis']
     */
    protected function defineBlogAxis(): string
    {
        $axis = $this->Get->get('axis', 'self');
        if (in_array($axis, ['self', 'descendant-or-self'], true)) {
            return $axis;
        }
        return 'self';
    }

    /**
     * @return FindModulesParams['order']
     */
    protected function defineOrder(): array
    {
        /** @var string $order */
        $order = ORDER;
        $order = explode('-', $order);
        $defaultOrder = $this->defaultOrder();
        $field = $order[0] !== '' ? $order[0] : $defaultOrder['field'];
        $direction = (isset($order[1]) && in_array($order[1], ['asc', 'desc'], true)) ? $order[1] : $defaultOrder['direction'];
        return [
            'field' => $field,
            'direction' => $direction,
        ];
    }


    /**
     * @param FindModulesParams $params
     * @return ModuleRow[]
     */
    protected function findModules(array $params): array
    {
        $sql = $this->buildSql($params);
        $q = $sql->get(dsn());
        /** @var ModuleRow[] $modules */
        $modules = DB::query($q, 'all');

        return $modules;
    }


    /**
     * SQLを組み立てる
     * @param FindModulesParams $params
     * @return \SQL_Select
     */
    protected function buildSql(array $params): \SQL_Select
    {
        $sql = SQL::newSelect('module');
        $sql->addLeftJoin('blog', 'blog_id', 'module_blog_id');

        $this->filterSql($sql, $params);
        $this->orderSql($sql, $params);
        return $sql;
    }

    /**
     * @param \SQL_Select $sql
     * @param FindModulesParams $params
     * @return void
     * @param-out \SQL_Select $sql
     */
    protected function filterSql(\SQL_Select &$sql, array $params): void
    {
        $this->filterByKeyword($sql, $params);
        $this->filterByStatus($sql, $params);
        $this->filterByScope($sql, $params);
        $this->filterByLayoutUse($sql, $params);
        $this->filterByBlog($sql, $params);
        $sql->addWhereOpr('module_label', 'crm-module-indexing-hidden', '<>'); // 互換性のため残しておく
    }

    /**
     * @param \SQL_Select $sql
     * @param FindModulesParams $params
     * @return void
     * @param-out \SQL_Select $sql
     */
    protected function filterByKeyword(\SQL_Select &$sql, array $params): void
    {
        if ($params['keyword'] !== null) {
            ACMS_Filter::moduleKeyword($sql, $params['keyword']);
        }
    }

    /**
     * @param \SQL_Select $sql
     * @param FindModulesParams $params
     * @return void
     * @param-out \SQL_Select $sql
     */
    protected function filterByStatus(\SQL_Select &$sql, array $params): void
    {
        if ($params['status'] !== null) {
            $sql->addWhereOpr('module_status', $params['status']);
        }
    }

    /**
     * @param \SQL_Select $sql
     * @param FindModulesParams $params
     * @return void
     * @param-out \SQL_Select $sql
     */
    protected function filterByScope(\SQL_Select &$sql, array $params): void
    {
        if ($params['scope'] !== null) {
            $sql->addWhereOpr('module_scope', $params['scope']);
        }
    }

    /**
     * @param \SQL_Select $sql
     * @param FindModulesParams $params
     * @return void
     * @param-out \SQL_Select $sql
     */
    protected function filterByLayoutUse(\SQL_Select &$sql, array $params): void
    {
        if ($params['layoutUse'] !== null) {
            $sql->addWhereOpr('module_layout_use', $params['layoutUse']);
        }
    }

    /**
     * @param \SQL_Select $sql
     * @param FindModulesParams $params
     * @return void
     * @param-out \SQL_Select $sql
     */
    protected function filterByBlog(\SQL_Select &$sql, array $params): void
    {
        $where = SQL::newWhere();
        ACMS_Filter::blogTree($where, BID, $params['blogAxis']);

        // 祖先の場合はグローバルを考慮
        $left = ACMS_RAM::blogLeft(BID);
        $right = ACMS_RAM::blogRight(BID);
        $globalWhere = SQL::newWhere();
        $globalWhere->addWhereOpr('blog_left', $left, '<', 'AND');
        $globalWhere->addWhereOpr('blog_right', $right, '>', 'AND');
        $globalWhere->addWhereOpr('module_scope', 'global', '=', 'AND');
        $where->addWhere($globalWhere, 'OR');
        $sql->addWhere($where);
    }

    /**
     * @param \SQL_Select $sql
     * @param FindModulesParams $params
     * @return void
     * @param-out \SQL_Select $sql
     */
    protected function orderSql(\SQL_Select &$sql, array $params): void
    {
        $sql->addOrder('module_' . $params['order']['field'], $params['order']['direction']);
        $sql->addOrder('module_id', $params['order']['direction']);
    }

    /**
     * @return array
     */
    protected function buildNotFound(): array
    {
        return [
            'index#notFound' => (object)[],
        ];
    }

    /**
     * @param ModuleRow[] $rows
     * @return array
     */
    protected function buildModules(array $rows): array
    {
        return array_map(
            function (array $row) {
                return $this->buildModule($row);
            },
            $rows
        );
    }

    /**
     * @param ModuleRow $row
     * @return array
     */
    protected function buildModule(
        array $row
    ): array {
        $module = [];
        foreach ($row as $key => $value) {
            if (strpos($key, 'module_') === 0) {
                $module[str_replace('module_', '', $key)] = $value;
            }
        }
        $module = array_merge(
            $module,
            [
                'custom_field' => $module['custom_field'] === '1', // boolean に変換
                'layout_use' => $module['layout_use'] === 1, // boolean に変換
                'api_use' => $module['api_use'] === 'on', // boolean に変換
                'blog' => [
                    'id' => $row['blog_id'],
                    'name' => $row['blog_name'],
                    'code' => $row['blog_code'],
                ],
            ]
        );

        // blog
        if ($row['module_blog_id'] < BID) {
            // 祖先ブログの場合はブログ情報を公開しない
            unset($module['blog']);
        }

        // action
        $module = array_merge(
            $module,
            [
                'actions' => $this->buildActions($row),
            ]
        );

        return $module;
    }

    /**
     * モジュールに対する操作を取得する
     * @param ModuleRow $row
     * @return array
     */
    protected function buildActions(array $row): array
    {
        $actions = [];
        if (Module::canUpdate($row['module_blog_id'])) {
            $actions[] = [
                'id' => 'edit',
            ];
        }
        return $actions;
    }

    /**
     * 一括操作の組み立て
     * @param ModuleRow[] $rows
     * @return string[]
     */
    protected function buildBulkActions(array $rows): array
    {
        $bulkActions = [];
        if (Module::canBulkStatusChange(BID)) {
            $bulkActions[] = 'status';
        }
        if (Module::canBulkBlogChange(BID)) {
            $bulkActions[] = 'blog';
        }
        if (Module::canBulkExport(BID)) {
            $bulkActions[] = 'export';
        }
        if (Module::canBulkDelete(BID)) {
            $bulkActions[] = 'delete';
        }

        return $bulkActions;
    }

    /**
     * render
     * @param \Template $tpl
     * @param array $vars
     * @return string
     */
    protected function render(\Template $tpl, array $vars): string
    {
        return $tpl->render($vars);
    }

    /**
     * デフォルトの並び順を取得する
     * @return FindModulesParams['order']
     */
    protected function defaultOrder(): array
    {
        return [
            'field' => 'name',
            'direction' => 'asc',
        ];
    }
}
