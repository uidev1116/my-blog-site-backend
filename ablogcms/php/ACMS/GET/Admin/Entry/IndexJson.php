<?php

class ACMS_GET_Admin_Entry_IndexJson extends ACMS_GET_Admin_Entry_Index
{
    /**
     * @inheritDoc
     */
    protected function render(\Template $tpl, array $vars): string
    {
        $json = (string)json_encode($vars);
        if (jsonValidate($json)) {
            return $json;
        }
        return '{}';
    }
    /**
     * @inheritDoc
     */
    protected function filterByField(SQL_Select &$sql, array $params): void
    {
        if (isset($params['field']) && !$params['field']->isNull()) {
            $columns = [
                'datetime',
                'updated_datetime',
                'posted_datetime',
                'start_datetime',
                'end_datetime',
                'members_only',
                'indexing',
            ];
            $columnSearch = $params['field']->cloneWith($columns);
            foreach ($columns as $column) {
                $params['field']->delete($column);
            }
            ACMS_Filter::columnByField($sql, $columnSearch, 'entry_');
            ACMS_Filter::entryField($sql, $params['field']);
        }
    }

    /**
     * @inheritDoc
     */
    protected function buildEntry(
        Template $tpl,
        array $params,
        array $row,
        \Field $fields,
        array $tags,
        ?array $mainImage
    ): array {
        /** @var int $id */
        $id = $row['entry_id'];
        /** @var int|null $categoryId */
        $categoryId = $row['entry_category_id'];
        /** @var int $userId */
        $userId = $row['entry_user_id'];
        /** @var int $blogId */
        $blogId = $row['entry_blog_id'];

        $entry  = [
            'id' => $id,
            'code' => $row['entry_code'],
            'sort' => $this->getSort($row, $params),
            'status' => $row['entry_status'],
            'title' => addPrefixEntryTitle(
                $row['entry_title'],
                $row['entry_status'],
                $row['entry_start_datetime'],
                $row['entry_end_datetime'],
                $row['entry_approval']
            ),
            'link' => $row['entry_link'],
            'url' => acmsLink([
                'bid' => $blogId,
                'eid' => $id,
            ]),
            'datetime' => $row['entry_datetime'],
            'updated_datetime' => $row['entry_updated_datetime'],
            'posted_datetime' => $row['entry_posted_datetime'],
            'start_datetime' => $row['entry_start_datetime'],
            'end_datetime' => $row['entry_end_datetime'],
            'members_only' => $row['entry_members_only'] === 'on',
            'indexing' => $row['entry_indexing'] === 'on',
            'tags' => array_column($tags, 'tag_name'),
            'blog' => [
                'id' => $blogId,
                'name' => $row['blog_name'],
                'url' => acmsLink([
                    'admin' => ADMIN,
                    'bid' => BID,
                    'query' => [
                        '_bid' => $blogId !== BID ? $blogId : null,
                    ],
                ]),
            ],
            'user' => array_merge(
                [
                    'id' => $userId,
                    'name' => $row['user_name'],
                    'icon' => loadUserIcon($userId),
                ],
                // 現在のブログのユーザーであれば検索用のURLを追加
                BID === intval($row['user_blog_id']) ? [
                    'url' => acmsLink([
                        'admin' => ADMIN,
                        'bid' => BID,
                        'uid' => $userId,
                    ]),
                ] : []
            ),
        ];

        //-----------
        // Category
        if ($categoryId !== null && $categoryId > 0) {
            $entry += [
                'category' => [
                    'id' => $categoryId,
                    'name' => $row['category_name'],
                    'url' => acmsLink([
                        'admin' => ADMIN,
                        'cid' => $categoryId,
                    ]),
                ],
            ];
        } else {
            // 一覧でカテゴリーが未設定のエントリーは - で表示
            $entry += ['category' => null];
        }

        //-----------
        // Primary Image
        $clid = isset($row['entry_primary_image']) ? (string) $row['entry_primary_image'] : null;
        if ($mainImage) {
            $entryHelper = new \Acms\Modules\Get\Helpers\Entry\EntryHelper([]);
            $primaryImageInfo = $entryHelper->buildMainImage($clid, $id, $mainImage);

            if ($primaryImageInfo['path'] ?? null) {
                $entry['primary_image'] = $primaryImageInfo;
            } else {
                $entry['primary_image'] = null;
            }
        } else {
            $entry['primary_image'] = null;
        }

        //-----------
        // Approval
        if (enableApproval($blogId, $categoryId)) {
            $entry += [
                'approval' => $row['entry_approval'],
            ];
        }

        //-----------
        // Form
        /** @var int|null $formId */
        $formId = $row['entry_form_id'];
        /** @var string $formStatus */
        $formStatus = $row['entry_form_status'];
        if (in_array($formStatus, ['open', 'close'], true)) {
            $entry += [
                'form_status' => $formStatus,
                'form' => [
                    'id' => $formId,
                    'code' => $row['form_code'],
                    'name' => $row['form_name'],
                ],
            ];
        }

        //-----------
        // Lock User
        /** @var int|null $lockUserId */
        $lockUserId = $row['entry_lock_uid'];
        if ($lockUserId !== null && $lockUserId > 0) {
            if ($this->lockService->getExpiredDatetime() < strtotime($row['entry_lock_datetime'])) {
                $entry += [
                    'lockUser' => [
                        'id' => $lockUserId,
                        'name' => ACMS_RAM::userName($lockUserId),
                    ],
                ];
            }
        }

        //-------
        // action
        $entry += ['actions' => $this->buildEntryActions($row)];

        //-------
        // field
        $entry += $this->buildField($fields, $tpl, ['entry:loop']);

        return $entry;
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
        $params = [
            'blogId' => $params['blogId'],
            'categoryId' => $params['categoryId'],
            'userId' => $params['userId'],
            'keyword' => $params['keyword'],
            'order' => $params['order'],
            'limit' => $params['limit'],
            'page' => $params['page'],
            'status' => $params['status'],
            'session' => $params['session'],
            'blogAxis' => $params['blogAxis'],
            'categoryAxis' => $params['categoryAxis'],
        ];
        return ['params' => $params];
    }

    /**
     * @inheritDoc
     */
    protected function isSortable(array $params, string $sortType): bool
    {
        $order = $params['order'];
        $userId = $params['userId'];
        $categoryId = $params['categoryId'];
        $blogAxis = $params['blogAxis'];
        $categoryAxis = $params['categoryAxis'];

        if (!in_array($order, ['sort-asc', 'sort-desc'], true)) {
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
}
