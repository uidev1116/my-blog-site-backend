<?php

use Acms\Services\Facades\Blog;
use Acms\Services\Facades\Category;
use Acms\Services\Facades\Entry;

class ACMS_GET_Admin_Entry_Index extends ACMS_GET
{
    public $_scope = [
        'field'     => 'global',
    ];

    /**
     * @var \Acms\Services\Entry\Lock
     */
    protected $lockService;

    public function get()
    {
        if (!$this->isExecutionAllowed()) {
            return die403();
        }

        $this->lockService = App::make('entry.lock');

        $tpl = new Template($this->tpl, new ACMS_Corrector());
        $vars = [];

        $params = $this->createParams();
        [
            'entries' => $entries,
            'amount' => $amount,
            'fields' => $fields,
            'tags' => $tags,
            'mainImage' => $mainImage,
        ] = $this->findEntries($params);
        $vars = array_merge(
            $vars,
            $this->buildParams($tpl, $params),
            $this->buildIgnoredFilters($tpl, $params, $entries, $fields, $tags, $mainImage),
            $this->buildSort($tpl, $params),
        );

        if ($this->canFilterByUser()) {
            $vars = array_merge($vars, $this->buildUserSelect($tpl));
        }

        if (count($entries) === 0) {
            $vars = array_merge(
                $vars,
                $this->buildNotFound($tpl),
            );
            return $this->render($tpl, $vars);
        }

        $vars = array_merge(
            $vars,
            ['entry' => $this->buildEntries($tpl, $params, $entries, $fields, $tags, $mainImage)],
            $this->buildPagination($tpl, $params['page'], $params['limit'], $amount),
            $this->buildBulkActions($tpl, $params, $entries, $fields, $tags, $mainImage),
        );

        return $this->render($tpl, $vars);
    }

    /**
     * 実行が許可されているかどうか
     * @return bool
     */
    protected function isExecutionAllowed(): bool
    {
        if (sessionWithContribution()) {
            return true;
        }
        return false;
    }

    /**
     * 表示に必要なパラメータを生成する
     * @return array{
     *  'status': 'open' | 'close' | 'draft' | 'trash' | null,
     *  'session': 'public' | 'expiration' | 'future' | null,
     *  'keyword': string,
     *  'field': \Field_Search,
     *  'blogId': int,
     *  'categoryId': int | null,
     *  'blogAxis': 'self' | 'descendant-or-self',
     *  'categoryAxis': 'self' | 'descendant-or-self',
     *  'userId': int | null,
     *  'order': string,
     *  'limit': int,
     *  'page': int,
     *  'sortFieldName': string,
     * }
     */
    protected function createParams(): array
    {
        $status = $this->defineStatus();
        $session = $this->defineSession();
        $keyword = $this->defineKeyword();
        $field = $this->defineField();
        $blogId = $this->defineBlogId();
        $blogAxis = $this->defineBlogAxis();
        $categoryId = $this->defineCategoryId();
        $categoryAxis = $this->defineCategoryAxis();
        $userId = $this->defineUserId();
        $order = $this->defineOrder();
        $limit = $this->defineLimit();
        $page = $this->definePage();
        $sortFieldName = $this->defineSortFieldName();
        return [
            'status' => $status,
            'session' => $session,
            'keyword' => $keyword,
            'field' => $field,
            'blogId' => $blogId,
            'blogAxis' => $blogAxis,
            'categoryId' => $categoryId,
            'categoryAxis' => $categoryAxis,
            'userId' => $userId,
            'order' => $order,
            'limit' => $limit,
            'page' => $page,
            'sortFieldName' => $sortFieldName,
        ];
    }

    /**
     * @return 'open' | 'close' | 'draft' | 'trash' | null
     */
    protected function defineStatus(): ?string
    {
        $status = $this->Get->get('status');
        if (in_array($status, ['open', 'close', 'draft', 'trash'], true)) {
            return $status;
        }
        return null;
    }

    /**
     * @return 'public' | 'expiration' | 'future' | null
     */
    protected function defineSession(): ?string
    {
        $session = $this->Get->get('session');
        if (in_array($session, ['public', 'expiration', 'future'], true)) {
            return $session;
        }
        return null;
    }

    /**
     * @return string
     */
    protected function defineKeyword(): string
    {
        return KEYWORD;
    }

    /**
     * @return \Field_Search
     */
    protected function defineField(): \Field_Search
    {
        return $this->Field;
    }

    /**
     * @return int
     */
    protected function defineBlogId(): int
    {
        return (int)$this->Get->get('_bid', BID);
    }

    /**
     * @return int|null
     */
    protected function defineCategoryId(): ?int
    {
        if (CID && CID > 0) { // @phpstan-ignore-line
            return CID;
        };


        if ($this->Get->get('_cid') === '0') {
            return 0;
        }

        return null;
    }

    /**
     * @return 'self' | 'descendant-or-self'
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
     * @return 'self' | 'descendant-or-self'
     */
    protected function defineCategoryAxis(): string
    {
        $categoryAxis = $this->Get->get('category_axis', 'self');
        if (in_array($categoryAxis, ['self', 'descendant-or-self'], true)) {
            return $categoryAxis;
        }
        return 'self';
    }

    /**
     * @return int|null
     */
    protected function defineUserId(): ?int
    {
        if ($this->canFilterByUser()) {
            return UID;
        }

        return SUID;
    }

    /**
     * @return string
     */
    protected function defineOrder(): string
    {
        return ORDER ? ORDER : $this->defaultOrder();
    }

    /**
     * @return int
     */
    protected function defineLimit(): int
    {
        return LIMIT ? LIMIT : $this->defaultLimit();
    }

    /**
     * @return int
     */
    protected function definePage(): int
    {
        return PAGE;
    }

    /**
     * @return string
     */
    protected function defineSortFieldName(): string
    {
        return $this->Get->get('sortFieldName');
    }

    /**
     * @param array $params
     * @return array{
     *  entries: array,
     *  amount: int,
     *  fields: array<int, \Field>,
     *  tags: array<int, array{tag_name: string, tag_sort: int, tag_entry_id: int, tag_blog_id: int}[]>,
     *  mainImage: array{
     *    unit: array<int, \Acms\Services\Unit\Contracts\Model>,
     *    media: array<int, array>,
     *    fieldMainImage?: array<int, array>,
     *    fieldMainImageKey?: string,
     *  }
     * }
     */
    protected function findEntries(array $params): array
    {
        $sql = $this->buildSql($params);
        /** @var array<int, array<string, mixed>> $entries */
        $entries = DB::query($sql['entries']->get(dsn()), 'all');
        $entryIds = [];
        foreach ($entries as $entry) {
            $entryIds[] = intval($entry['entry_id']);
        }
        $amount = intval(DB::query($sql['amount']->get(dsn()), 'one'));

        $eagerLoading = new \Acms\Traits\Utilities\EagerLoadingHelper();
        $fieldName = config('main_image_field_name', '');

        /**
         * @var array{
         *  unit: array<int, \Acms\Services\Unit\Contracts\Model>,
         *  media: array<int, array>,
         *  fieldMainImage?: array<int, array>,
         *  fieldMainImageKey?: string
         * } $mainImage
         */
        $mainImage = $eagerLoading->eagerLoadMainImagePublic($entries, 'field', $fieldName);

        /** @var array<int, \Field> $fields */
        $fields = $eagerLoading->eagerLoadFieldPublic($entryIds, 'eid');

        return [
            'entries' => $entries,
            'fields' => $fields,
            'amount' => $amount,
            'tags' => $this->eagerLoadTags($entries),
            'mainImage' => $mainImage,
        ];
    }

    /**
     * SQLを組み立てる
     * @param array{
     *  status?: string,
     * } $params
     * @return array{
     *   'entries': SQL_Select,
     *   'amount': SQL_Select
     * }
     */
    protected function buildSql(array $params): array
    {
        $entriesSql = SQL::newSelect('entry');
        $entriesSql->addLeftJoin('blog', 'blog_id', 'entry_blog_id');
        $entriesSql->addLeftJoin('category', 'category_id', 'entry_category_id');
        $entriesSql->addLeftJoin('user', 'user_id', 'entry_user_id');
        $entriesSql->addLeftJoin('form', 'form_id', 'entry_form_id');

        $this->filterSql($entriesSql, $params);
        $amountSql = $this->buildAmountSql($entriesSql);
        $this->limitSql($entriesSql, $params);
        $this->orderSql($entriesSql, $params);
        return [
            'entries' => $entriesSql,
            'amount' => $amountSql,
        ];
    }

    /**
     * エントリーの総数を取得するSQLを組み立てる
     * @param $sql SQL_Select
     * @return SQL_Select
     */
    protected function buildAmountSql(\SQL_Select $sql): SQL_Select
    {
        $amountSql = new SQL_Select($sql);
        $amountSql->setSelect(SQL::newFunction('entry_id', 'DISTINCT'), 'entry_amount', null, 'count');
        return $amountSql;
    }

    /**
     * @param SQL_Select $sql
     * @param array $params
     * @return void
     */
    protected function filterSql(SQL_Select &$sql, array $params): void
    {
        $this->filterByStatus($sql, $params);
        $this->filterBySession($sql, $params);
        $this->filterByKeyWord($sql, $params);
        $this->filterByField($sql, $params);
        $this->filterByBlog($sql, $params);
        $this->filterByCategory($sql, $params);
        $this->filterByUser($sql, $params);
    }

    /**
     * @param SQL_Select $sql
     * @param array{
     *  status?: string | null,
     * } $params
     * @return void
     */
    protected function filterByStatus(SQL_Select &$sql, array $params): void
    {
        $sql->addWhereOpr('entry_status', 'trash', '<>');
        if (isset($params['status'])) {
            $sql->addWhereOpr('entry_status', $params['status']);
        }
    }

    /**
     * @param SQL_Select $sql
     * @param array{
     *  session?: 'public' | 'expiration' | 'future' | null,
     * } $params
     * @return void
     */
    protected function filterBySession(SQL_Select &$sql, array $params): void
    {
        $session = $params['session'];
        if ($session === null) {
            return;
        }
        switch ($session) {
            case 'public':
                $sql->addWhereOpr('entry_start_datetime', date('Y-m-d H:i:s', REQUEST_TIME), '<=');
                $sql->addWhereOpr('entry_end_datetime', date('Y-m-d H:i:s', REQUEST_TIME), '>=');
                break;
            case 'expiration':
                $sql->addWhereOpr('entry_end_datetime', date('Y-m-d H:i:s', REQUEST_TIME), '<');
                break;
            case 'future':
                $sql->addWhereOpr('entry_start_datetime', date('Y-m-d H:i:s', REQUEST_TIME), '>=');
                break;
        }
    }

    /**
     * @param SQL_Select $sql
     * @param array{
     *  keyword?: string,
     * } $params
     * @return void
     */
    protected function filterByKeyWord(SQL_Select &$sql, array $params): void
    {
        if (isset($params['keyword']) && $params['keyword'] !== '') {
            ACMS_Filter::entryKeyword($sql, $params['keyword']);
        }
    }

    /**
     * @param SQL_Select $sql
     * @param array{
     *  field?: \Field_Search,
     * } $params
     * @return void
     */
    protected function filterByField(SQL_Select &$sql, array $params): void
    {
        if (isset($params['field']) && !$params['field']->isNull()) {
            ACMS_Filter::entryField($sql, $params['field']);
        }
    }

    /**
     * @param SQL_Select $sql
     * @param array{
     *  blogId?: int,
     *  blogAxis?: 'self' | 'descendant-or-self',
     * } $params
     * @return void
     */
    protected function filterByBlog(SQL_Select &$sql, array $params): void
    {
        /** @var int $blogId */
        $blogId = $params['blogId'] ? $params['blogId'] : BID;
        $axis = $params['blogAxis'];

        if ($axis === 'self' || !Blog::hasDescendantBlogs($blogId)) {
            // $axisがdescendant-or-selfでも子孫ブログを持っていない場合は自身のブログのみを検索
            $sql->addWhereOpr('entry_blog_id', $blogId);
        } else {
            ACMS_Filter::blogTree($sql, $blogId, $axis);
        }
    }


    /**
     * @param SQL_Select $sql
     * @param array{
     *  categoryId?: int | null,
     *  categoryAxis?: 'self' | 'descendant-or-self',
     * } $params
     * @return void
     */
    protected function filterByCategory(SQL_Select &$sql, array $params): void
    {
        $categoryId = $params['categoryId'];
        $axis = $params['categoryAxis'];

        if ($categoryId && $categoryId > 0) {
            if ($axis === 'self' || !Category::hasDescendantCategories($categoryId)) {
                // $axisがdescendant-or-selfでも子孫カテゴリーを持っていない場合は自身のカテゴリーのみを検索
                $sql->addWhereOpr('entry_category_id', $categoryId);
            } else {
                ACMS_Filter::categoryTree($sql, $categoryId, $axis);
            }
            ACMS_Filter::categoryStatus($sql);
        } elseif ($categoryId === 0) {
            $sql->addWhereOpr('entry_category_id', null);
        }
    }

    /**
     * @param SQL_Select $sql
     * @param array{
     *  userId?: int | null,
     * } $params
     * @return void
     */
    protected function filterByUser(SQL_Select &$sql, array $params): void
    {
        $userId = $params['userId'];
        if ($userId && $userId > 0) {
            $sql->addWhereOpr('entry_user_id', $userId);
        }
    }

    /**
     * @param SQL_Select $sql
     * @param array{
     *  limit?: int,
     *  page?: int,
     * } $params
     * @return void
     */
    protected function limitSql(SQL_Select &$sql, array $params): void
    {
        $limit = $params['limit'] ? $params['limit'] : $this->defaultLimit();
        $page = $params['page'] ? $params['page'] : 1;
        $sql->setLimit($limit, ($page - 1) * $limit);
    }

    /**
     * @param SQL_Select $sql
     * @param array{
     *  userId?: int | null,
     *  categoryId?: int | null,
     *  order?: string,
     *  sortFieldName?: string,
     * } $params
     * @return void
     */
    protected function orderSql(SQL_Select &$sql, array $params): void
    {
        $order = $params['order'] ? $params['order'] : $this->defaultOrder();
        $userId = $params['userId'];
        $categoryId = $params['categoryId'];
        $orderInfo = explode('-', $order);
        $sortFd = ACMS_Filter::entryOrder($sql, [$order, 'id-' . $orderInfo[1]], $userId, $categoryId, false, $params['sortFieldName']);
        if ($sortFd !== '') {
            $sql->addGroup($sortFd);
        }
        $sql->addGroup('entry_id');
    }

    /**
     * @return array<int, array{'tag_name': string, 'tag_sort': int, 'tag_entry_id': int, tag_blog_id: int}[]>
     */
    protected function eagerLoadTags(array $entries): array
    {
        /** @var int[] $entryIds */
        $entryIds = array_map(
            function ($entry) {
                return (int)$entry['entry_id'];
            },
            $entries
        );
        return Tpl::eagerLoadTag($entryIds);
    }

    /**
     * @param Template $tpl
     * @param array{
     *  'status': 'open' | 'close' | 'draft' | 'trash' | null,
     *  'session': 'public' | 'expiration' | 'future' | null,
     *  'keyword': string,
     *  'field': \Field_Search,
     *  'blogId': int,
     *  'categoryId': int | null,
     *  'blogAxis': 'self' | 'descendant-or-self',
     *  'categoryAxis': 'self' | 'descendant-or-self',
     *  'userId': int | null,
     *  'order': string,
     *  'limit': int,
     *  'page': int,
     * } $params
     * @param array $row
     * @param \Field $fields
     * @param array{ 'tag_name': string, 'tag_sort': int, 'tag_entry_id': int, tag_blog_id: int}[] $tags
     * @param array{
     *  unit: array<int, \Acms\Services\Unit\Contracts\Model>,
     *  media: array<int, array>,
     *  fieldMainImage?: array<int, array>,
     *  fieldMainImageKey?: string
     * } $mainImage
     * @return array
     */
    protected function buildEntry(
        Template $tpl,
        array $params,
        array $row,
        \Field $fields,
        array $tags,
        array $mainImage
    ): array {
        $eid = $row['entry_id'];
        $cid = $row['entry_category_id'];
        $uid = $row['entry_user_id'];
        $bid = $row['entry_blog_id'];

        $entry  = [
            'eid' => $eid,
            'bid' => $bid,
            'sort' => $this->getSort($row, $params),
            'sort#eid' => $eid,
            'datetime'  => $row['entry_datetime'],
            'status#' . $row['entry_status'] => (object)[],
            'updated_datetime' => $row['entry_updated_datetime'],
            'posted_datetime' => $row['entry_posted_datetime'],
            'title' => addPrefixEntryTitle(
                $row['entry_title'],
                $row['entry_status'],
                $row['entry_start_datetime'],
                $row['entry_end_datetime'],
                $row['entry_approval']
            ),
            'code' => $row['entry_code'],
            'blogName'  => ACMS_RAM::blogName($bid),
            'userName'  => ACMS_RAM::userName($uid),
            'userIcon'  => loadUserIcon($uid),
            'entryUrl'  => acmsLink([
                'bid'   => $bid,
                'eid'   => $eid,
                'query' => [],
            ]),
            'blogUrl'   => acmsLink([
                'admin' => ADMIN,
                'bid'   => BID,
                'query' => [
                    '_bid' => $bid !== BID ? $bid : null,
                ],
            ]),
            'editUrl'   => acmsLink([
                'admin' => 'entry_editor',
                'bid'   => $bid,
                'eid'   => $eid,
                'query' => [],
            ], false),
        ];
        if ($cid) {
            $entry += [
                'categoryName'  => ACMS_RAM::categoryName($cid),
                'categoryUrl'   => acmsLink([
                    'admin' => ADMIN,
                    'cid'   => $cid,
                ]),
            ];
        }

        // 現在のブログのユーザーであれば検索用のURLを追加
        if (BID === intval($row['user_blog_id'])) {
            $entry += [
                'userUrl'   => acmsLink([
                    'admin' => ADMIN,
                    'bid'   => BID,
                    'uid'   => $uid,
                ]),
            ];
        }

        //-----------
        // Lock User
        if ($lockUid = $row['entry_lock_uid']) {
            if ($this->lockService->getExpiredDatetime() < strtotime($row['entry_lock_datetime'])) {
                $entry['lockUser'] = ACMS_RAM::userName($lockUid);
            }
            if (intval($lockUid) === SUID) { // @phpstan-ignore-line
                $entry['selfLock'] = 'yes';
            }
        }

        //-------
        // action
        $entry += ['action' => $this->buildEntryActions($row)];

        //-------
        // field
        $entry += $this->buildField($fields, $tpl, 'entry:loop', 'entry');

        return $entry;
    }

    /**
     * エントリーに対する操作を取得する
     * @param array $row
     * @return array
     */
    protected function buildEntryActions(array $row): array
    {
        /** @var int $entryId */
        $entryId = $row['entry_id'];
        /** @var int $blogId */
        $blogId = $row['entry_blog_id'];

        $actions = [
            [
                'id' => 'edit',
                'url' => acmsLink([
                    'admin' => 'entry_editor',
                    'bid'   => $blogId,
                    'eid'   => $entryId,
                ], false),
            ]
        ];
        if (Entry::canViewApprovalHistory($entryId)) {
            $actions[] = [
                'id' => 'approval-history',
                'url' => acmsLink([
                    'admin' => 'entry_approval-history',
                    'eid' => $entryId,
                ]),
            ];
        }
        if (Entry::canDuplicate($entryId)) {
            $actions[] = ['id' => 'duplicate'];
        }
        if (Entry::canDelete($entryId)) {
            $actions[] = ['id' => 'trash'];
        }
        return $actions;
    }

    /**
     * @param array $row
     * @param array{
     *   categoryId: int | null,
     *   userId: int | null,
     *   blogAxis: 'self' | 'descendant-or-self',
     * } $params
     */
    protected function getSort(array $row, array $params): int
    {
        $type = $this->getSortType($params);
        if ($type === 'user') {
            return $row['entry_user_sort'];
        }
        if ($type === 'category') {
            return $row['entry_category_sort'];
        }
        return $row['entry_sort'];
    }

    /**
     * @param Template $tpl
     * @param array{
     *  'status': 'open' | 'close' | 'draft' | 'trash' | null,
     *  'session': 'public' | 'expiration' | 'future' | null,
     *  'keyword': string,
     *  'field': \Field_Search,
     *  'blogId': int,
     *  'categoryId': int | null,
     *  'blogAxis': 'self' | 'descendant-or-self',
     *  'categoryAxis': 'self' | 'descendant-or-self',
     *  'userId': int | null,
     *  'order': string,
     *  'limit': int,
     *  'page': int,
     * } $params
     * @param array $entries
     * @param array<int, \Field> $fields
     * @param array<int, array{'tag_name': string, 'tag_sort': int, 'tag_entry_id': int, tag_blog_id: int}[]> $tags
     * @param array{
     *  unit: array<int, \Acms\Services\Unit\Contracts\Model>,
     *  media: array<int, array>,
     *  fieldMainImage?: array<int, array>,
     *  fieldMainImageKey?: string,
     * } $mainImage
     * @return array
     */
    protected function buildEntries(
        Template $tpl,
        array $params,
        array $entries,
        array $fields,
        array $tags,
        array $mainImage
    ): array {
        return array_map(
            function ($entry) use ($tpl, $params, $fields, $tags, $mainImage) {
                $field = isset($fields[$entry['entry_id']]) ? $fields[$entry['entry_id']] : new Field();
                $entryTags = isset($tags[$entry['entry_id']]) ? $tags[$entry['entry_id']] : [];
                return $this->buildEntry($tpl, $params, $entry, $field, $entryTags, $mainImage);
            },
            $entries
        );
    }

    /**
     * @param Template $tpl
     * @return array
     */
    protected function buildNotFound(Template $tpl): array
    {
        return [
            'index#notFound' => (object)[],
        ];
    }

    /**
     * ユーザーで絞り込みが可能かどうか
     * @return bool
     */
    protected function canFilterByUser(): bool
    {
        if (roleAvailableUser()) {
            if (roleAuthorization('entry_edit_all')) {
                // 全エントリーの編集権限がある場合
                return true;
            }

            if (sessionWithCompilation()) {
                return true;
            }

            return false;
        }
        if (sessionWithCompilation()) {
            return true;
        }

        if (enableApproval(BID, CID) && config('approval_contributor_edit_auth') !== 'on') {
            // 承認機能が有効で、かつ投稿者が自分のエントリーのみ編集可能な設定が無効な場合（他のユーザーの記事を編集可能な場合）は、ユーザーで絞り込みが可能
            return true;
        }

        return false;
    }

    /**
     * @param Template $tpl
     * @return array
     */
    protected function buildUserSelect(Template $tpl): array
    {
        return [
            'userSelect#filter' => (object)[],
        ];
    }

    /**
     * @param Template $tpl
     * @param array{
     *  status: 'open' | 'close' | 'draft' | 'trash' | null,
     *  session: 'public' | 'expiration' | 'future' | null,
     *  keyword: string,
     *  order: string,
     *  limit: int,
     *  page: int,
     *  blogAxis: 'self' | 'descendant-or-self',
     *  categoryAxis: 'self' | 'descendant-or-self',
     *  userId: int | null,
     *  categoryId: int | null,
     *  blogId: int,
     *  field: \Field_Search,
     * } $params
     * @return array
     */
    protected function buildParams(Template $tpl, array $params): array
    {
        $vars = [];
        $vars['status:selected#' . $params['status']] = config('attr_selected');
        $vars['session:selected#' . $params['session']] = config('attr_selected');
        $vars['order:selected#' . $params['order']] = config('attr_selected');
        $vars['limit'] = $this->buildLimits($params['limit']);
        $vars['axis'] = [
            'axis:checked#' . $params['blogAxis'] => config('attr_checked')
        ];
        $vars['category_axis'] = [
            'category_axis:checked#' . $params['categoryAxis'] => config('attr_checked')
        ];
        return $vars;
    }

    /**
     * @param int $limit
     */
    protected function buildLimits(int $limit): array
    {
        return array_map(
            function ($num) use ($limit) {
                return [
                    'limit' => $num,
                    'selected' => $num === $limit ? config('attr_selected') : '',
                ];
            },
            $this->getLimitOptions()
        );
    }

    /**
     * @param array{
     *  status: string|null,
     *  keyword: string,
     *  userId: int | null,
     *  categoryId: int | null,
     *  blogAxis: 'self' | 'descendant-or-self',
     *  categoryAxis: 'self' | 'descendant-or-self',
     *  order: string,
     * } $params
     * @param 'entry' | 'user' | 'category' $sortType
     * @return bool
     */
    protected function isSortable(array $params, string $sortType): bool
    {
        $order = $params['order'];
        $keyword = $params['keyword'];
        $status = $params['status'];
        $userId = $params['userId'];
        $categoryId = $params['categoryId'];
        $blogAxis = $params['blogAxis'];
        $categoryAxis = $params['categoryAxis'];

        if (!in_array($order, ['sort-asc', 'sort-desc'], true)) {
            return false;
        }

        if ($keyword) {
            return false;
        }

        if ($status) {
            return false;
        }

        if ($blogAxis !== 'self') {
            return false;
        }

        if ($categoryAxis !== 'self') {
            return false;
        }

        if ($userId !== null && $categoryId !== null) {
            // ユーザーとカテゴリーで絞り込みがかかっている場合はソートできない
            return false;
        }

        if ($sortType === 'user' && $userId === null) {
            // ユーザー絞り込み時のソート順を変更するときはユーザーで絞り込みがかかっている必要がある
            return false;
        }

        if ($sortType === 'category' && $categoryId === null) {
            // カテゴリー絞り込み時のソート順を変更するときはカテゴリーで絞り込みがかかっている必要がある
            return false;
        }

        if ($sortType === 'entry' && ($userId !== null || $categoryId !== null)) {
            // エントリー絞り込み時のソート順を変更するときはユーザーとカテゴリーで絞り込みがかかっていない必要がある
            return false;
        }

        if (!Entry::canChangeOrder($sortType, BID)) {
            // ソート順を変更する権限がない場合はソートできない
            return false;
        }

        if ($sortType === 'user' && $userId !== SUID && !Entry::canChangeOrderByOtherUser(BID)) {
            // 権限がないのにユーザーで絞り込んだエントリーの表示順を変更しようとした場合はソートできない
            return false;
        }

        return true;
    }

    /**
     * @param Template $tpl
     * @param array{
     *  userId: int | null,
     *  categoryId: int | null,
     *  blogId: int,
     *  status: string|null,
     *  keyword: string,
     *  blogAxis: 'self' | 'descendant-or-self',
     *  categoryAxis: 'self' | 'descendant-or-self',
     *  order: string,
     * } $params
     * @return array
     */
    protected function buildSort(
        Template $tpl,
        array $params
    ): array {
        $vars = [];
        if (ADMIN !== 'entry_index') {
            // まとめて操作画面の場合はソート機能を無効化
            return array_merge($vars, [
                'enabled' => false,
                'type' => 'entry',
                'context' => null,
                'permissions' => [
                    'entry' => false,
                    'user' => false,
                    'category' => false,
                    'otherUser' => false,
                ],
            ]);
        }
        $sortType = $this->getSortType($params);
        $isSortable = $this->isSortable($params, $sortType);
        // sort#header
        if ($isSortable) {
            if ($sortType === 'user') {
                $vars = array_merge($vars, [
                    'sort#headerUser' => (object)[],
                    'postSortType' => 'ACMS_POST_Entry_Index_Sort_User',
                ]);
            } elseif ($sortType === 'category') {
                $vars = array_merge($vars, [
                    'sort#headerCategory' => (object)[],
                    'postSortType' => 'ACMS_POST_Entry_Index_Sort_Category',
                ]);
            } else {
                $vars = array_merge($vars, [
                    'sort#header' => (object)[],
                    'postSortType' => 'ACMS_POST_Entry_Index_Sort_Entry',
                ]);
            }
            $vars = array_merge($vars, [
                'sortable' => 'on',
            ]);
        } else {
            $vars = array_merge($vars, [
                'sortable' => 'off',
            ]);
        }

        // sort:action
        if (Entry::canChangeOrder($sortType, BID)) {
            $vars = array_merge($vars, [
                'sort:action#' . $sortType => (object)[],
            ]);
        }

        $sort = [
            'enabled' => $isSortable,
            'type' => $sortType,
            'context' => null,
            'permissions' => [
                'entry' => Entry::canChangeOrder('entry', BID),
                'user' => Entry::canChangeOrder('user', BID),
                'category' => Entry::canChangeOrder('category', BID),
                'otherUser' => Entry::canChangeOrderByOtherUser(BID),
            ],
        ];
        if ($sortType === 'category' && $params['categoryId'] !== null) {
            $category = ACMS_RAM::category($params['categoryId']);
            if ($category !== null) {
                $context = [];
                foreach ($category as $key => $value) {
                    $context[str_replace('category_', '', $key)] = $value;
                }
                $sort = array_merge($sort, [
                    'context' => $context,
                ]);
            }
        }

        if ($sortType === 'user' && $params['userId'] !== null) {
            $user = ACMS_RAM::user($params['userId']);
            if ($user !== null) {
                $context = [];
                foreach ($user as $key => $value) {
                    $context[str_replace('user_', '', $key)] = $value;
                }
                $sort = array_merge($sort, [
                    'context' => $context,
                ]);
            }
        }


        $vars = array_merge($vars, [
            'sort' => $sort
        ]);

        return $vars;
    }

    /**
     * 絞り込みできないフィールドを取得する
     * @param Template $tpl
     * @param array $params
     * @param array $entries
     * @param array<int, \Field> $fields
     * @param array<int, array{'tag_name': string, 'tag_sort': int, 'tag_entry_id': int, tag_blog_id: int}[]> $tags
     * @param array{
     *  unit: array<int, \Acms\Services\Unit\Contracts\Model>,
     *  media: array<int, array>,
     *  fieldMainImage?: array<int, array>,
     *  fieldMainImageKey?: string,
     * } $mainImage
     * @return array{ ignoredFilters: string[] }
     */
    protected function buildIgnoredFilters(
        Template $tpl,
        array $params,
        array $entries,
        array $fields,
        array $tags,
        array $mainImage
    ): array {
        $ignoredFilters = [];
        if (!$this->canFilterByUser()) {
            $ignoredFilters = array_merge(
                $ignoredFilters,
                ['user']
            );
        }
        return ['ignoredFilters' => $ignoredFilters];
    }

    /**
     * 一括操作の組み立て
     * @param Template $tpl
     * @param array $params
     * @param array $entries
     * @param array<int, \Field> $fields
     * @param array<int, array{'tag_name': string, 'tag_sort': int, 'tag_entry_id': int, tag_blog_id: int}[]> $tags
     * @param array{
     *  unit: array<int, \Acms\Services\Unit\Contracts\Model>,
     *  media: array<int, array>,
     *  fieldMainImage?: array<int, array>,
     *  fieldMainImageKey?: string,
     * } $mainImage
     * @return array
     */
    protected function buildBulkActions(
        Template $tpl,
        array $params,
        array $entries,
        array $fields,
        array $tags,
        array $mainImage
    ): array {
        $vars = [];

        $bulkActions = [];
        if (Entry::canChangeOrder($this->getSortType($params), BID)) {
            $bulkActions[] = 'order';
        }
        if (Entry::canBulkStatusChange(BID, CID)) {
            $bulkActions[] = 'status';
        }
        if (Entry::canBulkUserChange(BID, CID)) {
            $bulkActions[] = 'user';
        }
        if (Entry::canBulkCategoryChange(BID, CID)) {
            $bulkActions[] = 'category';
        }
        if (Entry::canBulkBlogChange(BID)) {
            $bulkActions[] = 'blog';
        }
        if (Entry::canExport(BID)) {
            $bulkActions[] = 'export';
        }
        if (Entry::canBulkDuplicate(BID)) {
            $bulkActions[] = 'duplicate';
        }

        if (Entry::canBulkDelete(BID, CID)) {
            $bulkActions[] = 'trash';
        }

        return array_merge(
            $vars,
            ['bulkActions' => $bulkActions, 'bulkAction' => $bulkActions],
        );
    }

    /**
     * @param array $params
     * @return 'user' | 'category' | 'entry'
     */
    protected function getSortType(array $params): string
    {
        if ($params['userId'] !== null) {
            return 'user';
        }
        if ($params['categoryId'] !== null) {
            return 'category';
        }
        return 'entry';
    }

    /**
     * ページネーションを組み立てる
     * @param Template $tpl
     * @param int $page
     * @param int $limit
     * @param int $amount
     * @return array
     */
    protected function buildPagination(Template $tpl, int $page, int $limit, int $amount): array
    {
        return $this->buildPager(
            $page,
            $limit,
            $amount,
            (int)config('admin_pager_delta'),
            config('admin_pager_cur_attr'),
            $tpl,
            [],
            ['admin' => ADMIN]
        );
    }

    /**
     * 表示件数の選択肢を取得する
     */
    protected function getLimitOptions(): array
    {
        return configArray('admin_limit_option');
    }

    /**
     * 表示件数のデフォルト値を取得する
     * @return int
     */
    protected function defaultLimit(): int
    {
        $limitOptions = $this->getLimitOptions();
        return intval($limitOptions[config('admin_limit_default')]);
    }

    /**
     * デフォルトの並び順を取得する
     * @return string
     */
    protected function defaultOrder(): string
    {
        return 'datetime-desc';
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
}
