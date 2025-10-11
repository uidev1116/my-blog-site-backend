<?php

use Acms\Services\Facades\PublicStorage;
use Acms\Services\Facades\Logger;

class ACMS_POST_Form extends ACMS_POST
{
    public $isCacheDelete  = false;

    /**
     * 残った添付ファイルの削除
     * 送信まで行かず確認画面でフォームをキャンセルした場合に添付ファイルが残る為
     *
     * @param int $lifetime
     */
    public static function clearAttachedFile($lifetime = 1800)
    {
        $tempDirPath = ARCHIVES_DIR . config('mail_attachment_temp_dir', 'temp/');
        if (!PublicStorage::isDirectory($tempDirPath)) {
            return;
        }
        $fileList = PublicStorage::getFileList($tempDirPath);
        foreach ($fileList as $path) {
            if (!PublicStorage::isReadable($path)) {
                continue;
            }
            if (PublicStorage::isDirectory($path)) {
                continue;
            }
            $mtime = PublicStorage::lastModified($path);
            if (REQUEST_TIME - $lifetime > $mtime) {
                PublicStorage::remove($path);
                Logger::debug('添付ファイルの一時ファイルを削除しました', [
                    'path' => $path,
                ]);
                if (HOOK_ENABLE) {
                    $Hook = ACMS_Hook::singleton();
                    $Hook->call('mediaDelete', $path);
                }
            }
        }
    }

    /**
     * フォームのロード
     *
     * @param string $code
     * @return array{
     *  id: int,
     *  bid: int,
     *  code: string,
     *  name: string,
     *  scope: string,
     *  log: '1' | '0',
     *  data: \Field
     * }|false
     */
    public function loadForm($code)
    {
        if (empty($code)) {
            return false;
        }

        $sql = SQL::newSelect('form');
        $sql->addWhereOpr('form_code', $code);
        $sql->addLeftJoin('blog', 'blog_id', 'form_blog_id');
        ACMS_Filter::blogTree($sql, BID, 'ancestor-or-self');

        $where = SQL::newWhere();
        $where->addWhereOpr('form_blog_id', BID, '=', 'OR');
        $where->addWhereOpr('form_scope', 'global', '=', 'OR');
        $sql->addWhere($where);
        $row = DB::query($sql->get(dsn()), 'row');

        if (empty($row)) {
            return false;
        }

        return $this->toFormArray($row);
    }

    /**
     * IDによるフォームの探索
     *
     * @param int $id
     * @return array{
     *  id: int,
     *  bid: int,
     *  code: string,
     *  name: string,
     *  scope: string,
     *  log: '1' | '0',
     *  data: \Field
     * }|null
     */
    public function findFormById(int $id): ?array
    {
        $sql = SQL::newSelect('form');
        $sql->addWhereOpr('form_id', $id);
        $sql->addLeftJoin('blog', 'blog_id', 'form_blog_id');
        $row = DB::query($sql->get(dsn()), 'row');

        if (empty($row)) {
            return null;
        }

        return $this->toFormArray($row);
    }

    /**
     * フォームの配列を作成
     *
     * @param array $row
     * @return array{
     *  id: int,
     *  bid: int,
     *  code: string,
     *  name: string,
     *  scope: string,
     *  log: '1' | '0',
     *  data: \Field
     * }
     */
    public function toFormArray(array $row): array
    {
        return [
            'id'    => (int)$row['form_id'],
            'bid'   => (int)$row['form_blog_id'],
            'code'  => $row['form_code'],
            'name'  => $row['form_name'],
            'scope' => $row['form_scope'],
            'log'   => strval($row['form_log']),
            'data'  => acmsDangerUnserialize($row['form_data']),
        ];
    }

    /**
     * フォームオプション（サーバーサイドのバリデーション）を組み込み
     *
     * @param Field $Option
     * @return array
     */
    function buildOptions($Option)
    {
        $dup = []; // メールアドレスの重複オプション

        $field = new Field_Validation();
        if ($takeover = $this->Post->get('field:takeover')) {
            $takeoverField = acmsUnserialize($takeover);
            if ($takeoverField instanceof Field) {
                $field->overload($takeoverField);
            }
            $this->Post->delete('field:takeover');
        }
        $field->overload($this->Post->dig('field'));

        foreach ($Option->getArray('field') as $i => $fd) {
            if (empty($fd)) {
                continue;
            }
            if (!($method = $Option->get('method', '', $i))) {
                continue;
            }
            $value  = $Option->get('value', '', $i);
            if ('converter' == $method) {
                $this->Post->set($fd . ':converter', $value);
            } elseif ('duplication' == $method) {
                $dup[] = $fd;
                $dup[] = $field->get($fd);
            } else {
                $field->setMethod($fd, $method, $value);
            }
        }
        $this->Post->addChild('field', $field);

        return $dup;
    }

    /**
     * フォームオプション（サーバーサイドのバリデーション）を組み込み
     *
     * @param Field $Field
     * @param string $fd
     * @return array|false
     */
    function getAttachedFilePath($Field, $fd)
    {
        if (
            1
            and preg_match('@^(.+)\@path$@', $fd, $match)
            and $Field->isExists($match[1])
            and $Field->isExists($match[1] . '@baseName')
        ) {
            $fd_file    = $match[1];
            $filename   = $Field->get($fd_file . '@baseName');
            $path       = $Field->get($fd_file . '@path');
            $original_name = $Field->get($fd_file . '@originalName');

            if ($download_name = $Field->get($fd_file . '@downloadName')) {
                $original_name = $download_name;
            }
            if (strlen($filename) === 0) {
                $filename = PublicStorage::mbBasename($path);
            }
            $realpath = ARCHIVES_DIR . $path;
            $temppath = ARCHIVES_DIR . config('mail_attachment_temp_dir') . $filename;

            if (PublicStorage::exists($realpath) && PublicStorage::isFile($realpath)) {
                return [
                    'realpath'  => $realpath,
                    'temppath'  => $temppath,
                    'fieldpath' => config('mail_attachment_temp_dir') . $filename,
                    'fdname'    => $fd_file,
                    'original_name' => $original_name,
                ];
            }
        }

        return false;
    }

    /**
     * フォームIDの重複チェック
     *
     * @param string $code
     * @param string $fmid
     * @param string $scope
     * @return boolean
     */
    function double($code, $fmid = null, $scope = 'local')
    {
        if (empty($code)) {
            return true;
        }
        $DB     = DB::singleton(dsn());
        $SQL    = SQL::newSelect('form');
        $SQL->setSelect('form_id');
        $SQL->addLeftJoin('blog', 'blog_id', 'form_blog_id');

        // local
        if ($scope === 'local') {
            ACMS_Filter::blogTree($SQL, BID, 'ancestor-or-self');
            $Where  = SQL::newWhere();
            $Where->addWhereOpr('form_blog_id', BID, '=', 'OR');
            $Where->addWhereOpr('form_scope', 'global', '=', 'OR');
            $SQL->addWhere($Where);
        // global
        } else {
            ACMS_Filter::blogTree($SQL, BID, 'ancestor-or-descendant-or-self');
        }

        $SQL->addWhereOpr('form_code', $code);
        if (is_int($fmid)) {
            $SQL->addWhereOpr('form_id', $fmid, '<>');
        }
        $SQL->setLimit(1);

        return !$DB->query($SQL->get(dsn()), 'one');
    }

    /**
     * メールアドレスの重複チェック
     *
     * @param int $id
     * @param string $mail
     * @return boolean
     */
    function mailToDouble($id, $mail)
    {
        $DB     = DB::singleton(dsn());
        $mail   = preg_quote($mail);
        $regex  = "^{$mail}$|[^a-z]{$mail}\s?[>]?[^a-z]";

        $SQL    = SQL::newSelect('log_form');
        $SQL->addSelect('log_form_mail_to');
        $SQL->addWhereOpr('log_form_mail_to', $regex, 'REGEXP');
        $SQL->addWhereOpr('log_form_form_id', $id);
        $SQL->addWhereOpr('log_form_blog_id', BID);

        return !($DB->query($SQL->get(dsn()), 'one'));
    }
}
