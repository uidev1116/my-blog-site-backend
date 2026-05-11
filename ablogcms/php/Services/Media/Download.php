<?php

namespace Acms\Services\Media;

use DB;
use SQL;
use ACMS_Filter;
use Acms\Services\Facades\Auth;
use Acms\Services\Facades\BlockEditor;
use Acms\Services\Facades\Common;
use Acms\Services\Facades\Login;
use Acms\Services\Facades\PrivateStorage;
use Acms\Services\Facades\Session;
use Acms\Services\Facades\Storage;

class Download
{
    /**
     * @var int
     */
    protected $mid;

    /**
     * @var array{
     *   path: string,
     *   name: string,
     *   extension: string,
     *   status: string
     * }
     */
    protected $media;

    /**
     * Download constructor.
     * @param $media array
     */
    public function __construct($media)
    {
        @set_time_limit(0);

        $this->mid = intval($media['mid']);
        $this->media = $media;
    }

    /**
     * ファイルダウンロード
     *
     * @return never
     */
    public function download()
    {
        $path = MEDIA_STORAGE_DIR . $this->media['path'];
        $filename = $this->media['name'];
        $extension = strtolower($this->media['extension']);

        if (in_array($extension, configArray('media_inline_download_extension'), true)) {
            Common::download($path, $filename, $extension, false, PrivateStorage::getInstance());
        }
        Common::download($path, $filename, false, false, PrivateStorage::getInstance());
    }

    /**
     * 該当のメディアが存在するか確認
     *
     * @return boolean
     */
    public function exists(): bool
    {
        $path = MEDIA_STORAGE_DIR . $this->media['path'];
        return Storage::exists($path);
    }

    /**
     * 該当のメディアにアクセス権があるか確認
     *
     * @return bool
     */
    public function validate()
    {
        $status = $this->media['status'] ?: 'entry';

        if ($status === 'entry') {
            return $this->validateEntryType();
        } elseif ($status === 'close') {
            return $this->validateCloseType();
        } elseif ($status === 'secret') {
            return $this->validateSecretType();
        } elseif ($status === 'open') {
            return true;
        }
        return false;
    }

    /**
     * メディアステータスがログイン限定の場合のバリデート
     *
     * @return bool
     */
    protected function validateSecretType()
    {
        return Login::isLoggedIn();
    }

    /**
     * メディアステータスがエントリー依存の場合のバリデート
     *
     * @return bool
     */
    protected function validateEntryType()
    {
        if ($this->validateCloseType()) {
            return true;
        }
        $entryIds = $this->findEntriesUseMedia();
        if (count($entryIds) === 0) {
            if (config('media_disallow_download_if_unused') === 'on') {
                return false;
            }
            return true;
        }
        $sql = SQL::newSelect('entry');
        $sql->addSelect('entry_id');
        $sql->addLeftJoin('blog', 'blog_id', 'entry_blog_id');
        ACMS_Filter::entrySession($sql);
        ACMS_Filter::blogStatus($sql);
        $sql->addWhereIn('entry_id', $entryIds);
        $sql->setLimit(1);
        if (DB::query($sql->get(dsn()), 'row')) {
            return true;
        }
        return false;
    }

    /**
     * メディアステータスがCloseの場合のバリデート
     *
     * @return bool
     */
    protected function validateCloseType()
    {
        $session = Session::handle();
        $inPreviewLimit = $session->get('in-preview', REQUEST_TIME + (60 * 15));
        if ($inPreviewLimit && intval($inPreviewLimit) > REQUEST_TIME) {
            return true;
        }
        if (Login::isLoggedIn()) {
            /** @var int|null $sessionUserId */
            $sessionUserId = SUID;
            assert(is_int($sessionUserId)); // ログインしていることが保証されている
            if (Auth::isEditor($sessionUserId) || Auth::isAdministrator($sessionUserId) || Auth::isContributor($sessionUserId)) {
                return true;
            }
        }
        return false;
    }

    /**
     * 該当のメディアが使われているエントリーを検索
     *
     * @return array
     */
    protected function findEntriesUseMedia()
    {
        $entryIds = [];
        $entryIds = array_merge($entryIds, $this->findUnitsUseMedia());
        $entryIds = array_merge($entryIds, $this->findBlockEditorBlocksUseMedia());
        $entryIds = array_merge($entryIds, $this->findBlockEditorFieldsUseMedia());
        $entryIds = array_merge($entryIds, $this->findFieldsUseMedia());
        $entryIds = array_merge($entryIds, $this->findCustomUnitsUseMedia());

        return array_unique($entryIds);
    }

    /**
     * 該当のメディアが使われているユニットを検索
     *
     * @return array
     */
    protected function findUnitsUseMedia()
    {
        $sql = SQL::newSelect('column');
        $sql->addSelect('column_entry_id');
        $sql->addWhereOpr('column_type', 'media%', 'LIKE');
        $sql->addWhereOpr('column_field_1', $this->mid);

        return DB::query($sql->get(dsn()), 'list') ?: [];
    }

    /**
     * 該当のメディアが使われているブロックエディターユニットを検索
     *
     * @return int[]
     */
    protected function findBlockEditorBlocksUseMedia(): array
    {
        $sql = SQL::newSelect('column');
        $sql->addSelect('column_entry_id');
        $sql->addSelect('column_field_1');
        $sql->addWhereOpr('column_type', 'block-editor%', 'LIKE');
        // data-mid の前後に空白や改行が入る保存形式も拾えるようにする
        $sql->addWhereOpr('column_field_1', '%data-mid%', 'LIKE');

        /** @var array<int, array{column_entry_id: int|string, column_field_1: string}>|false $rows */
        $rows = DB::query($sql->get(dsn()), 'all');
        if (!$rows) {
            return [];
        }

        $entryIds = [];
        foreach ($rows as $row) {
            $mediaIds = BlockEditor::extractMediaId($row['column_field_1']);
            if (in_array($this->mid, $mediaIds, true)) {
                $entryIds[] = intval($row['column_entry_id']);
            }
        }

        return $entryIds;
    }

    /**
     * 該当のメディアが使われているブロックエディターのカスタムフィールドを検索
     *
     * @return int[]
     */
    protected function findBlockEditorFieldsUseMedia(): array
    {
        $sql = SQL::newSelect('field');
        $sql->addSelect('field_eid');
        $sql->addSelect('field_value');
        $sql->addWhereOpr('field_eid', null, '<>');
        $sql->addWhereOpr('field_type', 'block-editor');
        $sql->addWhereOpr('field_value', '%data-mid%', 'LIKE');

        /** @var array<int, array{field_eid: int|string, field_value: string}>|false $rows */
        $rows = DB::query($sql->get(dsn()), 'all');
        if (!$rows) {
            return [];
        }

        $entryIds = [];
        foreach ($rows as $row) {
            $mediaIds = BlockEditor::extractMediaId($row['field_value']);
            if (in_array($this->mid, $mediaIds, true)) {
                $entryIds[] = intval($row['field_eid']);
            }
        }

        return $entryIds;
    }

    /**
     * 該当のメディアが使われているフィールドを検索
     *
     * @return array
     */
    protected function findFieldsUseMedia()
    {
        $sql = SQL::newSelect('field');
        $sql->addSelect('field_eid');
        $sql->addWhereOpr('field_eid', null, '<>');
        $sql->addWhereOpr('field_type', 'media');
        $sql->addWhereOpr('field_value', $this->mid);

        return DB::query($sql->get(dsn()), 'list') ?: [];
    }

    /**
     * 該当のメディアが使われているカスタムユニットを検索
     *
     * @return array
     */
    protected function findCustomUnitsUseMedia()
    {
        $sql = SQL::newSelect('field');
        $sql->addSelect('field_unit_id');
        $sql->addWhereOpr('field_unit_id', null, '<>');
        $sql->addWhereOpr('field_type', 'media');
        $sql->addWhereOpr('field_value', $this->mid);
        if ($unitIds = DB::query($sql->get(dsn()), 'list')) {
            $sql = SQL::newSelect('column');
            $sql->addSelect('column_entry_id');
            $sql->addWhereIn('column_id', $unitIds);
            $sql->addWhereOpr('column_type', 'custom%', 'LIKE');
            $list = DB::query($sql->get(dsn()), 'list');
            return $list ? $list : [];
        }
        return [];
    }
}
