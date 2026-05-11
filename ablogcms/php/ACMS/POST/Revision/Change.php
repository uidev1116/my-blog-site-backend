<?php

use Acms\Services\Facades\Entry;

class ACMS_POST_Revision_Change extends ACMS_POST
{
    public function post()
    {
        /** @var int|null $entryId */
        $entryId = EID;
        /** @var int $blogId */
        $blogId = BID;
        /** @var int $requestTime */
        $requestTime = REQUEST_TIME;

        if ($entryId === null) {
            return $this->Post;
        }

        try {
            $revisionId = (int) $this->Post->get('revision');
            if ($revisionId === 0) {
                throw new \RuntimeException('バージョン番号が指定されていません');
            }

            if (!Entry::canChangeEntryRevision($entryId, $revisionId)) {
                throw new \RuntimeException('権限がありません');
            }

            $revision = $this->loadRevision($revisionId, $entryId);
            if ($revision === null) {
                throw new \RuntimeException('バージョンが見つかりません');
            }
            $this->applyRevision($revision, $revisionId, $entryId, $blogId, $requestTime);

            AcmsLogger::info('「' . ACMS_RAM::entryTitle($entryId) . '（' . $revision['entry_rev_memo'] . '）」を公開バージョンに切り替えました', [
                'eid' => $entryId,
                'rvid' => $revisionId,
            ]);

            $this->redirect(acmsLink([
                'bid' => $blogId,
                'eid' => $entryId,
            ]));
        } catch (\Exception $e) {
            AcmsLogger::info('公開バージョンへの切り替えができませんでした。' . $e->getMessage(), Common::exceptionArray($e));
            return $this->Post;
        }
    }

    /**
     * 指定されたリビジョンIDに対応するリビジョンデータを取得する。
     *
     * @param int $revisionId リビジョンID
     * @param int $entryId エントリーID
     * @return array<string, mixed>|null リビジョンが存在しない場合は null
     */
    private function loadRevision(int $revisionId, int $entryId): array|null
    {
        $revision = Entry::getRevision($entryId, $revisionId);
        if ($revision === false) {
            return null;
        }
        return $revision;
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
            return;
        }
        Entry::changeRevision($revisionId, $entryId, $blogId);
    }
}
