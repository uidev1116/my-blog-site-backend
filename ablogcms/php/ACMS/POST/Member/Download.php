<?php

use Acms\Services\Facades\LocalStorage;
use Acms\Services\Facades\Common;

class ACMS_POST_Member_Download extends ACMS_POST
{
    /**
     * CSVの文字コード
     *
     * @var string
     */
    protected $charset = 'UTF-8';

    /**
     * Main
     */
    function post()
    {
        try {
            $this->validate();
            $sql = $this->buildQuery();
            $this->charset = $this->Post->get('charset', 'UTF-8');
            $destDir = MEDIA_STORAGE_DIR . 'user_tmp/';
            $fileName = 'members' . date('_Ymd_His') . '.csv';
            $this->createCsv($sql, $destDir, $destDir . $fileName);

            AcmsLogger::info('読者ユーザーのCSVをダウンロードしました');

            Common::download($destDir . $fileName, $fileName, false, true);
        } catch (Exception $e) {
            $this->addError($e->getMessage());
            AcmsLogger::notice($e->getMessage());
        }
        return $this->Post;
    }

    /**
     * バリデート
     *
     * @return void
     * @throws RuntimeException
     */
    protected function validate(): void
    {
        if (!sessionWithAdministration()) {
            throw new \RuntimeException('権限がないため、CSVダウンロードできませんでした。');
        }
    }

    /**
     * SQLを組み立て
     *
     * @return SQL_Select
     */
    protected function buildQuery(): SQL_Select
    {
        $targetBid = $this->Get->get('_bid', BID);

        $sql = SQL::newSelect('user');
        $sql->addWhereOpr('user_pass', '', '<>');
        $sql->addLeftJoin('blog', 'blog_id', 'user_blog_id');
        ACMS_Filter::blogTree($sql, $targetBid, 'self');
        ACMS_Filter::blogStatus($sql);

        $this->filterKeyword($sql);
        $this->filterField($sql);
        $this->filterAuth($sql);
        $this->filterStatus($sql);

        $sql->setGroup('user_id');
        $sql->setOrder('user_id', 'ASC');

        return $sql;
    }

    /**
     * キーワードで絞り込み
     *
     * @param SQL_Select $sql
     * @return void
     */
    protected function filterKeyword(SQL_Select $sql): void
    {
        if (!!KEYWORD) {
            $sql->addLeftJoin('fulltext', 'fulltext_uid', 'user_id');
            $keywords = preg_split(REGEX_SEPARATER, KEYWORD, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($keywords as $keyword) {
                $sql->addWhereOpr('fulltext_value', '%' . $keyword . '%', 'LIKE');
            }
        }
    }

    /**
     * 権限で絞り込み
     *
     * @param SQL_Select $sql
     * @return void
     */
    protected function filterAuth(SQL_Select $sql): void
    {
        if (config('subscribe_auth') === 'contributor') {
            $sql->addWhereIn('user_auth', ['subscriber', 'contributor']);
        } else {
            $sql->addWhereOpr('user_auth', 'subscriber');
        }
    }

    /**
     * ステータスで絞り込み
     *
     * @param SQL_Select $sql
     * @return void
     */
    protected function filterStatus(SQL_Select $sql): void
    {
        $status = $this->Get->get('status');
        if (!empty($status)) {
            $sql->addWhereOpr('user_status', $status);
        }
    }

    /**
     * カスタムフィールドで絞り込み
     *
     * @param SQL_Select $sql
     * @return void
     */
    protected function filterField(SQL_Select $sql): void
    {
        $field = $this->extract('field');
        ACMS_Filter::userField($sql, $field);
    }

    /**
     * CSVを出力
     *
     * @param SQL_Select $sql
     * @param string $destDir
     * @param string $destPath
     * @return void
     */
    protected function createCsv(SQL_Select $sql, string $destDir, string $destPath): void
    {
        $csvField = $this->extract('csv');
        $userColumns = $csvField->getArray('csv_column_user');
        $fieldColumns = $csvField->getArray('csv_column_field');

        ignore_user_abort(true);
        set_time_limit(0);

        LocalStorage::makeDirectory($destDir);
        $fp = fopen($destPath, 'w');

        if (!$fp) {
            throw new \RuntimeException('CSVファイルの作成に失敗しました。');
        }

        $this->createCsvHeader($fp, $userColumns, $fieldColumns);

        $q = $sql->get(dsn());
        $statement = DB::query($q, 'exec');
        while ($user = DB::next($statement)) {
            $this->createCsvData($fp, $user, $userColumns, $fieldColumns);
        }
        fclose($fp);
    }

    /**
     * CSVのヘッダーを出力
     *
     * @param resource $fp
     * @param array $userColumns
     * @param array $fieldColumns
     * @return void
     */
    protected function createCsvHeader($fp, array $userColumns, array $fieldColumns): void
    {
        $columns = array_merge($userColumns, $fieldColumns);
        $this->putCsv($fp, $columns);
    }

    /**
     * CSVのデータを出力
     *
     * @param resource $fp
     * @param array $user
     * @param array $userColumns
     * @param array $fieldColumns
     * @return void
     */
    protected function createCsvData($fp, array $user, array $userColumns, array $fieldColumns)
    {
        $data = [];
        $uid = intval($user['user_id']);
        $field = loadUserField($uid);
        foreach ($userColumns as $key) {
            if (isset($user['user_' . $key])) {
                $data[] = $user['user_' . $key];
            } else {
                $data[] = '';
            }
        }
        foreach ($fieldColumns as $key) {
            $data[] = implode('|', $field->getArray($key));
        }
        $this->putCsv($fp, $data);
    }

    /**
     * 文字コードを変換してCSVデータ1行を出力
     *
     * @param resource $fp
     * @param array $data
     * @return void
     */
    protected function putCsv($fp, array $data)
    {
        if ($this->charset === 'UTF-8') {
            acmsFputcsv($fp, $data, ",", "\"", "\\", "\n");
        } else {
            $temp = [];
            foreach ($data as $row) {
                $temp[] = mb_convert_encoding($row, $this->charset, 'UTF-8');
            }
            acmsFputcsv($fp, $temp, ",", "\"", "\\", "\r\n");
        }
    }
}
