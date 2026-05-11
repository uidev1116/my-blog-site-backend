<?php

use Acms\Services\Entry\Enums\EntryApprovalStatus;
use Acms\Services\Facades\Mailer;
use Acms\Services\Facades\Common;
use Acms\Services\Facades\Logger;
use Acms\Services\Facades\Entry;
use Acms\Services\Facades\Database as DB;

class ACMS_POST_Approval_Public extends ACMS_POST
{
    /**
     * 最終承認（公開）を実行する。
     *
     * @inheritdoc
     */
    public function post()
    {
        $approval = $this->extract('approval');

        if (!$this->canPublicApproval()) {
            return $this->Post;
        }
        /** @var int|null $entryId */
        $entryId = EID;
        /** @var int|null $categoryId */
        $categoryId = CID;
        /** @var int $blogId */
        $blogId = BID;
        /** @var int|null $sessionUserId */
        $sessionUserId = SUID;
        /** @var int $requestTime */
        $requestTime = REQUEST_TIME;

        if ($entryId === null) {
            return $this->Post;
        }

        if ($sessionUserId === null) {
            return $this->Post;
        }

        $revisionId = (int)$approval->get('rvid');
        if ($revisionId === 0) {
            return $this->Post;
        }
        $revision = $this->findRevision($revisionId, $entryId);
        if ($revision === null) {
            return $this->Post;
        }
        $approval->validate(new ACMS_Validator());
        if (!$this->Post->isValidAll()) {
            return $this->Post;
        }

        $type = 'series';
        $workflowCategoryId = null;
        if (editionIsEnterprise()) {
            $workflow = loadWorkflow($blogId, $categoryId);
            $type = $workflow->get('workflow_type');
            $cid = (int) $workflow->get('workflow_category_id');
            $workflowCategoryId = $cid === 0 ? null : $cid;
        }

        $to = $this->getApprovalRecipients($type, $revisionId, $entryId, $blogId, $workflowCategoryId, $sessionUserId);
        $this->sendNotificationMail($to, $approval, $revision, $entryId, $blogId, $categoryId, $sessionUserId);

        $this->applyRevision($revision, $revisionId, $entryId, $blogId, $requestTime);

        $comment = $approval->get('request_comment');
        $requestGroupId = ($type === 'parallel') ? null : (int) $approval->get('current_group');
        $approvalId = $this->insertApprovalRecord($comment, $requestGroupId, $revisionId, $entryId, $blogId, $sessionUserId, $requestTime);

        $this->updateRevisionStatus($type, $revisionId, $entryId, $blogId);

        $this->deleteApprovalNotifications($entryId, $blogId);

        AcmsLogger::info('「' . ACMS_RAM::entryTitle($entryId) . '（' . $revision['entry_rev_memo'] . '）」の最終承認をしました', [
            'apid' => $approvalId,
            'eid'  => $entryId,
            'rvid' => $revisionId,
            'comment' => $comment,
        ]);

        ACMS_RAM::entry($entryId, null);
        return $this->Post;
    }

    /**
     * 現在のセッションが最終承認（公開）を実行できる権限を持つか確認する。
     *
     * @return bool 権限がある場合 true
     */
    private function canPublicApproval(): bool
    {
        return sessionWithApprovalPublic();
    }

    /**
     * 指定されたリビジョンIDに対応するリビジョンデータを取得する。
     *
     * @param int $revisionId リビジョンID
     * @param int $entryId エントリーID
     * @return array<string, mixed>|null リビジョンデータ。存在しない場合は null
     */
    private function findRevision(int $revisionId, int $entryId): array|null
    {
        $revision = Entry::getRevision($entryId, $revisionId);
        if ($revision === false) {
            return null;
        }
        return $revision;
    }

    /**
     * 承認方式とエディションに応じて、承認通知メールの送信先アドレスを取得する。
     *
     * Enterprise エディションの場合:
     * - 並列承認: 承認者グループに所属する全ユーザー（自分を除く）のメールアドレス
     * - 直列承認: 承認依頼を行ったユーザーのメールアドレス
     *
     * Professional エディションの場合:
     * - 承認依頼レコードから依頼元ユーザーのメールアドレスを取得
     *
     * @param string $type 承認方式（'parallel' or 'series'）
     * @param int $revisionId リビジョンID
     * @param int $entryId エントリーID
     * @param int $blogId ブログID
     * @param int|null $workflowCategoryId ワークフローの有効カテゴリID（Enterprise の場合は呼び出し元で取得済みの値）
     * @param int $sessionUserId ログインユーザーID
     * @return string|string[] 送信先メールアドレス。単一の場合は文字列、複数の場合は配列。該当なしの場合は空文字
     */
    private function getApprovalRecipients(string $type, int $revisionId, int $entryId, int $blogId, ?int $workflowCategoryId, int $sessionUserId): string|array
    {
        if (editionIsEnterprise()) {
            // 並列承認
            if ($type === 'parallel') {
                $workflowUserSql = SQL::newSelect('workflow_usergroup', 'wug');
                $workflowUserSql->addLeftJoin('usergroup_user', 'usergroup_id', 'usergroup_id', 'ugu', 'wug');
                $workflowUserSql->addLeftJoin('user', 'user_id', 'user_id', 'u', 'ugu');
                $workflowUserSql->addSelect('user_mail');
                $workflowUserSql->addSelect('user_name');
                $workflowUserSql->addWhereOpr('user_status', 'open');
                $workflowUserSql->addWhereOpr('user_id', $sessionUserId, '<>', 'AND', 'u');
                $workflowUserSql->addWhereOpr('workflow_blog_id', $blogId);
                $workflowUserSql->addWhereOpr('workflow_category_id', $workflowCategoryId);

                $mailAddresses = [];
                foreach (DB::query($workflowUserSql->get(dsn()), 'all') as $row) {
                    $mailAddresses[] = $row['user_mail'];
                }
                return $mailAddresses;
            }

            // 直列承認
            $approvalSql = SQL::newSelect('approval');
            $approvalSql->addLeftJoin('user', 'approval_request_user_id', 'user_id');
            $approvalSql->addSelect('user_mail');
            $approvalSql->addSelect('user_name');
            $approvalSql->addWhereOpr('user_status', 'open');
            $approvalSql->addWhereOpr('approval_type', 'request');
            $approvalSql->addWhereOpr('approval_revision_id', $revisionId);
            $approvalSql->addWhereOpr('approval_entry_id', $entryId);

            $mailAddresses = [];
            foreach (DB::query($approvalSql->get(dsn()), 'all') as $row) {
                $mailAddresses[] = $row['user_mail'];
            }
            return $mailAddresses;
        }

        if (editionIsProfessional()) {
            $approvalUserSql = SQL::newSelect('approval');
            $approvalUserSql->addSelect('approval_request_user_id');
            $approvalUserSql->addWhereOpr('approval_revision_id', $revisionId);
            $approvalUserSql->addWhereOpr('approval_entry_id', $entryId);
            $approvalUserSql->addWhereOpr('approval_blog_id', $blogId);
            $approvalUserSql->addWhereOpr('approval_type', 'request');
            if ($userId = DB::query($approvalUserSql->get(dsn()), 'one')) {
                return ACMS_RAM::userMail($userId) ?? '';
            }
        }

        return '';
    }

    /**
     * 承認通知メールを送信する。
     *
     * 送信先アドレスまたはテンプレートが未設定の場合は何もしない。
     * フックが有効な場合は `approvalNotification` フックを呼び出し、
     * フック側で $send を false に設定することで送信をキャンセルできる。
     *
     * @param string|string[] $recipients 送信先メールアドレス（単一または配列）
     * @param \Field $approval 承認フォームのフィールドデータ
     * @param array<string, mixed> $revision リビジョンデータ（件名・本文のテンプレート変数に使用）
     * @param int $entryId エントリーID
     * @param int $blogId ブログID
     * @param int|null $categoryId カテゴリID
     * @param int $sessionUserId ログインユーザーID
     * @return void
     */
    private function sendNotificationMail(string|array $recipients, \Field $approval, array $revision, int $entryId, int $blogId, ?int $categoryId, int $sessionUserId): void
    {
        $subjectTpl = findTemplate(config('mail_approval_tpl_subject'));
        $bodyTpl = findTemplate(config('mail_approval_tpl_body'));
        if (
            $recipients === '' ||
            $recipients === [] ||
            $subjectTpl === false ||
            $subjectTpl === '' ||
            $bodyTpl === '' ||
            $bodyTpl === false
        ) {
            return;
        }

        $approval->setField('request_user', ACMS_RAM::userName($sessionUserId));
        $approval->setField('approval', 'public');
        $approval->setField('approval2', 'public');
        $approval->setField('approval3', 'public');
        $approval->setField('approval4', 'public');
        $approval->setField('entryTitle', $revision['entry_title']);
        $approval->setField('entryStatus', ACMS_RAM::entryStatus($entryId));
        $approval->setField('version', $revision['entry_rev_memo']);
        $approval->setField('revisionUrl', acmsLink([
            'protocol' => SSL_ENABLE ? 'https' : 'http',
            'bid' => $blogId,
            'cid' => $categoryId,
            'eid' => $entryId,
        ], false));

        $subject = Common::getMailTxt($subjectTpl, $approval);
        $body = Common::getMailTxt($bodyTpl, $approval);
        $to = is_array($recipients) ? implode(', ', $recipients) : $recipients;
        $from = getApprovalFrom($sessionUserId);
        $bcc = implode(', ', configArray('mail_approval_bcc'));

        $send = true;
        if (HOOK_ENABLE) {
            $data = [
                'type' => 'public',
                'from' => [$from],
                'to' => $recipients,
                'subject' => $subject,
                'bcc' => configArray('mail_approval_bcc'),
                'body' => $body,
                'data' => $approval,
            ];
            $Hook = ACMS_Hook::singleton();
            $Hook->call('approvalNotification', [$data, &$send]);
        }
        if ($send !== false) {
            try {
                $mailer = Mailer::init();
                $mailer = $mailer->setFrom($from)
                    ->setTo($to)
                    ->setBcc($bcc)
                    ->setSubject($subject)
                    ->setBody($body);

                if ($bodyHtmlTpl = findTemplate(config('mail_approval_tpl_body_html'))) {
                    $bodyHtml = Common::getMailTxt($bodyHtmlTpl, $approval);
                    $mailer = $mailer->setHtml($bodyHtml);
                }
                $mailer->send();
            } catch (Exception $e) {
                Logger::warning('最終承認の通知メールの送信に失敗しました', Common::exceptionArray($e));
            }
        }
    }

    /**
     * リビジョンの公開日時に応じて、予約公開または即時公開を実行する。
     *
     * 公開日時が現在時刻より未来の場合は予約公開として登録し、
     * 過去または現在の場合は即時にエントリーへ反映する。
     *
     * @param array<string, mixed> $revision リビジョンデータ
     * @param int $revisionId リビジョンID
     * @param int $entryId エントリーID
     * @param int $blogId ブログID
     * @param int $requestTime 現在時刻（UNIXタイムスタンプ）
     * @return void
     */
    private function applyRevision(array $revision, int $revisionId, int $entryId, int $blogId, int $requestTime): void
    {
        if (strtotime($revision['entry_start_datetime']) > $requestTime) {
            Entry::reserveRevision($revisionId, $entryId);
        } else {
            Entry::changeRevision($revisionId, $entryId, $blogId);
        }
    }

    /**
     * 最終承認（公開）の承認レコードを approval テーブルに保存する。
     *
     * @param string $comment 承認コメント
     * @param int|null $requestGroupId 承認依頼元のユーザーグループID（並列承認時は null）
     * @param int $revisionId リビジョンID
     * @param int $entryId エントリーID
     * @param int $blogId ブログID
     * @param int $sessionUserId ログインユーザーID
     * @param int $requestTime 現在時刻（UNIXタイムスタンプ）
     * @return int 採番された承認レコードID
     */
    private function insertApprovalRecord(string $comment, ?int $requestGroupId, int $revisionId, int $entryId, int $blogId, int $sessionUserId, int $requestTime): int
    {
        $approvalId = (int)DB::query(SQL::nextval('approval_id', dsn()), 'seq');

        $sql = SQL::newInsert('approval');
        $sql->addInsert('approval_id', $approvalId);
        $sql->addInsert('approval_type', 'public');
        $sql->addInsert('approval_datetime', date('Y-m-d H:i:s', $requestTime));
        $sql->addInsert('approval_comment', $comment);
        $sql->addInsert('approval_receive_usergroup_id', null);
        $sql->addInsert('approval_receive_user_id', null);
        $sql->addInsert('approval_request_usergroup_id', $requestGroupId);
        $sql->addInsert('approval_request_user_id', $sessionUserId);
        $sql->addInsert('approval_revision_id', $revisionId);
        $sql->addInsert('approval_entry_id', $entryId);
        $sql->addInsert('approval_blog_id', $blogId);
        DB::query($sql->get(dsn()), 'exec');

        return $approvalId;
    }

    /**
     * entry_rev テーブルのリビジョンステータスを「承認済み」に更新する。
     *
     * 並列承認の場合は、現在の承認ポイントにログインユーザー分のポイントを加算する。
     *
     * @param string $type 承認方式（'parallel' or 'series'）
     * @param int $revisionId リビジョンID
     * @param int $entryId エントリーID
     * @param int $blogId ブログID
     * @return void
     */
    private function updateRevisionStatus(string $type, int $revisionId, int $entryId, int $blogId): void
    {
        $sql = SQL::newUpdate('entry_rev');
        $sql->addUpdate('entry_rev_status', 'approved');
        // リビジョン自体の承認フロー状態をクリアする（承認フロー完了 = none）。
        $sql->addUpdate('entry_approval', EntryApprovalStatus::None->value);

        // 並列承認
        if ($type === 'parallel') {
            $pointSql = SQL::newSelect('entry_rev');
            $pointSql->addSelect('entry_approval_public_point');
            $pointSql->addWhereOpr('entry_id', $entryId);
            $pointSql->addWhereOpr('entry_rev_id', $revisionId);
            $currentPoint = DB::query($pointSql->get(dsn()), 'one');
            $point = approvalUserPoint($blogId);

            $sql->addUpdate('entry_approval_public_point', $currentPoint + $point);
        }
        $sql->addWhereOpr('entry_id', $entryId);
        $sql->addWhereOpr('entry_rev_id', $revisionId);
        DB::query($sql->get(dsn()), 'exec');
    }

    /**
     * approval_notification テーブルから対象エントリーの承認通知を削除する。
     *
     * @param int $entryId エントリーID
     * @param int $blogId ブログID
     * @return void
     */
    private function deleteApprovalNotifications(int $entryId, int $blogId): void
    {
        $sql = SQL::newDelete('approval_notification');
        $sql->addWhereOpr('notification_entry_id', $entryId);
        $sql->addWhereOpr('notification_blog_id', $blogId);
        DB::query($sql->get(dsn()), 'exec');
    }
}
