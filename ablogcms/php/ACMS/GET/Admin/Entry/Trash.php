<?php

class ACMS_GET_Admin_Entry_Trash extends ACMS_GET_Admin_Entry_Index
{
    /**
     * @inheritDoc
     */
    protected function isExecutionAllowed(): bool
    {
        if ('entry_trash' !== ADMIN) {
            return false;
        }
        if (!sessionWithContribution()) {
            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    protected function defineStatus(): ?string
    {
        return null; // ゴミ箱のエントリーはステータスでの絞り込みはしない
    }

    /**
     * @inheritDoc
     */
    protected function defineSession(): ?string
    {
        return null; // 掲載期間での絞り込みはしない
    }

    /**
     * @inheritDoc
     */
    protected function filterByStatus(SQL_Select &$sql, array $params): void
    {
        $sql->addWhereOpr('entry_status', 'trash'); // ゴミ箱のエントリーのみを取得する
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
        array $mainImage
    ): array {
        /** @var int<1, max> $eid */
        $eid = $row['entry_id'];
        /** @var int<1, max>|null $cid */
        $cid = $row['entry_category_id'];
        /** @var int<1, max> $uid */
        $uid = $row['entry_user_id'];
        /** @var int<1, max> $bid */
        $bid = $row['entry_blog_id'];
        /** @var int<1, max>|null $delUid */
        $delUid = $row['entry_delete_uid'];

        $entry  = [
            'eid' => $eid,
            'bid' => $bid,
            'sort' => $this->getSort($row, $params),
            'sort#eid' => $eid,
            'datetime'  => $row['entry_datetime'],
            'status#' . $row['entry_status'] => (object)[],
            'del_datetime' => $row['entry_updated_datetime'],
            'title' => $row['entry_title'],
            'code' => $row['entry_code'],
            'blogName'  => ACMS_RAM::blogName($bid),
            'userName'  => ACMS_RAM::userName($uid),
            'userIcon'  => loadUserIcon($uid),
            'entryUrl'  => acmsLink([
                'eid'   => $eid,
            ]),
            'blogUrl'   => acmsLink([
                'admin' => ADMIN,
                'bid'   => BID,
                'query' => [
                    '_bid' => $bid !== BID ? $bid : null,
                ],
            ]),
            'userUrl'   => acmsLink([
                'admin' => ADMIN,
                'uid'   => $uid,
            ]),
            'editUrl'   => acmsLink([
                'admin' => 'entry_editor',
                'bid'   => $bid,
                'eid'   => $eid,
            ], false),
        ];

        if ($delUid !== null) {
            $entry += [
                'delUserName' => ACMS_RAM::userName($delUid),
                'delUserIcon' => loadUserIcon($delUid),
                'delUserUrl' => acmsLink([
                    'admin' => ADMIN,
                    'uid' => $delUid,
                ]),
            ];
        }
        if ($cid !== null) {
            $entry += [
                'categoryName'  => ACMS_RAM::categoryName($cid),
                'categoryUrl'   => acmsLink([
                    'admin' => ADMIN,
                    'cid'   => $cid,
                ]),
            ];
        }

        $entry += ['action' => $this->buildEntryActions($row)];

        //-------
        // field
        $entry += $this->buildField($fields, $tpl, 'entry:loop', 'entry');

        return $entry;
    }

    /**
     * @inheritDoc
     */
    protected function buildEntryActions(array $row): array
    {
        /** @var int $entryId */
        $entryId = $row['entry_id'];
        $actions = [[]];
        if (Entry::canTrashRestore($entryId)) {
            $actions[] = ['id' => 'restore'];
        }
        if (Entry::canViewApprovalHistory($entryId)) {
            $actions[] = [
                'id' => 'approval-history',
                'url' => acmsLink([
                    'admin' => 'entry_approval-history',
                    'eid' => $entryId,
                ]),
            ];
        }
        return $actions;
    }

    /**
     * @inheritDoc
     */
    protected function isSortable(array $params, string $sortType): bool
    {
        return false; // ゴミ箱のエントリーは並び替えができない
    }

    /**
     * @inheritDoc
     */
    protected function buildBulkActions(
        Template $tpl,
        array $params,
        array $entries,
        array $field,
        array $tag,
        array $primaryImage
    ): array {
        $bulkActions = [];
        if (Entry::canBulkDelete(BID, CID)) {
            $bulkActions[] = 'delete';
        }
        if (Entry::canBulkTrashRestore(BID, CID)) {
            // ゴミ箱のエントリーを一括で復元できる場合
            $bulkActions[] = 'restore';
        }
        return ['bulkActions' => $bulkActions, 'bulkAction' => $bulkActions];
    }

    /**
     * @inheritDoc
     */
    protected function defaultOrder(): string
    {
        return 'updated_datetime-desc'; // ゴミ箱のエントリーは更新日時の降順で表示する
    }
}
