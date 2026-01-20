<?php

/**
 * ユーザーCSVインポート用モデルクラス
 *
 * ユーザーのCSVインポート処理を実装するクラス
 */
class ACMS_POST_Import_Model_User extends ACMS_POST_Import_Model
{
    /** @var array<string, mixed> ユーザーデータ */
    protected array $user = [];

    /** @var array<int, array<string, mixed>> フィールドデータ配列 */
    protected array $fields = [];

    /** @var string IDラベル名 */
    protected string $idLabel = 'user_id';

    /**
     * ユーザーの存在チェック
     *
     * CSVから取得したIDが存在し、更新可能な状態かを確認する
     *
     * @return bool 存在し更新可能な場合true
     */
    protected function exist(): bool
    {
        if (is_null($this->csvId)) {
            return false;
        }
        $userBlogId = ACMS_RAM::userBlog($this->csvId);
        $userName = ACMS_RAM::userName($this->csvId);
        if ($userBlogId !== BID) {
            // 実行ブログに存在しないユーザーは更新できない
            return false;
        }
        if ($userName === null || $userName === '') {
            return false;
        }
        return true;
    }

    /**
     * 次発行されるユーザーIDを設定
     *
     * @return void
     */
    protected function nextId(): void
    {
        $DB = DB::singleton(dsn());
        $this->nextId = intval($DB->query(SQL::nextval('user_id', dsn()), 'seq'));
    }

    /**
     * バリデーション処理
     *
     * 必須フィールドの存在確認、データフォーマットの検証、重複チェックを実行する
     *
     * @return void
     * @throws RuntimeException バリデーションエラー時
     */
    protected function validate(): void
    {
        if (array_search('user_code', $this->labels, true) === false) {
            throw new RuntimeException('コード (user_code) フィールドがありません。');
        }
        if (array_search('user_mail', $this->labels, true) === false) {
            throw new RuntimeException('メールアドレス (user_mail) フィールドがありません。');
        }
        if (array_search('user_pass', $this->labels, true) === false) {
            throw new RuntimeException('パスワード (user_pass) フィールドがありません。');
        }

        foreach ($this->data as $key => $value) {
            switch ($key) {
                case 'user_id':
                case 'user_sort':
                    if (!is_numeric($value)) {
                        throw new \RuntimeException('数値でない値が設定されています（' . $key . '）');
                    }
                    break;
                case 'user_mail':
                    if (empty($value)) {
                        throw new \RuntimeException('必須入力項目に空の値がセットされています。（' . $key . '）');
                    }
                    break;
                case 'user_status':
                    if (!in_array($value, ['open', 'close', 'withdrawal', 'pseudo'], true)) {
                        throw new \RuntimeException('不正な値が設定されています（' . $key . '）');
                    }
                    break;
                case 'user_auth':
                    if (!in_array($value, ['administrator', 'editor', 'contributor', 'subscriber'], true)) {
                        throw new \RuntimeException('不正な値が設定されています（' . $key . '）');
                    }
                    break;
                case 'user_login_expire':
                    if (!preg_match('@^\d{4}-\d{2}-\d{2}$@', $value)) {
                        throw new \RuntimeException('日付のフォーマットが間違っています（' . $key . '）');
                    }
                    break;
                case 'user_login_datetime':
                case 'user_updated_datetime':
                case 'user_generated_datetime':
                    if (!preg_match('@^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$@', $value)) {
                        throw new \RuntimeException('日時のフォーマットが間違っています（' . $key . '）');
                    }
                    break;
                case 'user_indexing':
                case 'user_mail_magazine':
                case 'user_mail_mobile_magazine':
                case 'user_login_anywhere':
                case 'user_global_auth':
                case 'user_login_terminal_restriction':
                    if (!in_array($value, ['on', 'off'], true)) {
                        throw new \RuntimeException('on または off 以外の値が設定されています（' . $key . '）');
                    }
                    break;
            }
        }
        $this->duplicateCheck();
    }

    /**
     * 重複チェック
     *
     * ユーザーコードまたはメールアドレスが既に存在するかを確認する
     *
     * @return void
     * @throws RuntimeException 重複するユーザーが見つかった場合
     */
    protected function duplicateCheck(): void
    {
        $DB = DB::singleton(dsn());

        $WHERE = SQL::newWhere();
        if (!!$this->data['user_code']) {
            $WHERE->addWhereOpr('user_code', $this->data['user_code'], '=', 'OR');
        }
        $WHERE->addWhereOpr('user_mail', $this->data['user_mail'], '=', 'OR');

        $SQL = SQL::newSelect('user');
        $SQL->addWhere($WHERE, 'AND');
        $q = $SQL->get(dsn());

        if ($row = $DB->query($q, 'row')) {
            if (array_search('user_id', $this->labels, true) === false || $row['user_id'] != $this->data['user_id']) {
                throw new RuntimeException('既に存在するユーザーが含まれています。');
            }
        }
    }

    /**
     * ユーザーデータの挿入
     *
     * ユーザー本体とフィールドを挿入する
     *
     * @return void
     */
    protected function insert(): void
    {
        $this->insertUser();
        $this->insertUserField();

        Common::saveFulltext('uid', $this->nextId, Common::loadUserFulltext($this->nextId));
    }

    /**
     * ユーザーデータの更新
     *
     * ユーザー本体とフィールドを更新する
     *
     * @return void
     */
    protected function update(): void
    {
        if ($this->csvId === null) {
            throw new RuntimeException('更新対象のユーザーIDが設定されていません。');
        }
        $uid = $this->csvId;
        $this->updateUser();
        $this->updateUserField();

        Common::saveFulltext('uid', $uid, Common::loadUserFulltext($uid));
    }

    /**
     * ユーザー本体を挿入
     *
     * @return void
     */
    protected function insertUser(): void
    {
        $DB = DB::singleton(dsn());

        $SQL = SQL::newInsert('user');
        foreach ($this->user as $key => $val) {
            $SQL->addInsert($key, $val);
        }
        $DB->query($SQL->get(dsn()), 'exec');
    }

    /**
     * ユーザーフィールドを挿入
     *
     * @return void
     */
    protected function insertUserField(): void
    {
        $uid = $this->nextId;

        if (!empty($this->fields)) {
            Common::deleteField('uid', $uid);

            $sql = SQL::newBulkInsert('field');
            foreach ($this->fields as $fval) {
                $fval['field_uid'] = $uid;
                $fval['field_blog_id'] = ACMS_RAM::userBlog($uid);
                $sql->addInsert($fval);
            }
            if ($sql->hasData()) {
                DB::query($sql->get(dsn()), 'exec');
            }
        }
    }

    /**
     * ユーザー本体を更新
     *
     * @return void
     */
    protected function updateUser(): void
    {
        if ($this->csvId === null) {
            return;
        }
        $DB = DB::singleton(dsn());
        $uid = $this->csvId;
        $SQL = SQL::newUpdate('user');
        foreach ($this->user as $key => $val) {
            $SQL->addUpdate($key, $val);
        }
        $SQL->addWhereOpr('user_id', $uid);
        $SQL->addWhereOpr('user_blog_id', BID);
        $DB->query($SQL->get(dsn()), 'exec');
        ACMS_RAM::user($uid, null);
    }

    /**
     * ユーザーフィールドを更新
     *
     * @return void
     */
    protected function updateUserField(): void
    {
        if ($this->csvId === null) {
            return;
        }
        $uid = $this->csvId;

        if (!empty($this->fields)) {
            $fkey = [];
            $SQL    = SQL::newDelete('field');
            $SQL->addWhereOpr('field_uid', $uid);
            foreach ($this->fields as $dval) {
                foreach ($dval as $key => $val) {
                    if ($key === 'field_key') {
                        $fkey[] = $val;
                    }
                }
            }
            $SQL->addWhereIn('field_key', $fkey);
            DB::query($SQL->get(dsn()), 'exec');
            Common::deleteFieldCache('uid', $uid);

            $sql = SQL::newBulkInsert('field');
            foreach ($this->fields as $fval) {
                $fval['field_uid'] = $uid;
                $fval['field_blog_id'] = ACMS_RAM::userBlog($uid);
                $sql->addInsert($fval);
            }
            if ($sql->hasData()) {
                DB::query($sql->get(dsn()), 'exec');
            }
        }
    }

    /**
     * ユーザーデータの組み立て
     *
     * CSVデータからユーザーとフィールドのデータを組み立てる
     *
     * @return void
     */
    protected function build(): void
    {
        $this->user = $this->userBase();
        $field = $this->fieldBase();

        foreach ($this->data as $key => $value) {
            if ($key === 'user_id' && $this->isUpdate) {
                $this->user['user_id'] = $this->csvId;
                $field['field_uid'] = $this->csvId;
            }
            if (array_key_exists($key, $this->user)) {
                $this->buildUser($key, $value);
            } else {
                $this->buildField($field, $key, $value);
            }
        }
        // パスワードが空の場合は、アップデートしないように修正
        if (empty($this->user['user_pass'])) {
            unset($this->user['user_pass']);
            unset($this->user['user_pass_generation']);
        }
        // アップデートの場合は余分なベース情報を削除
        if ($this->isUpdate) {
            foreach ($this->user as $key => $value) {
                if (!isset($this->data[$key])) {
                    unset($this->user[$key]);
                }
            }
        }
    }

    /**
     * 次発行されるユーザーのソート番号を取得
     *
     * @return int ソート番号
     */
    protected function nextSortId(): int
    {
        $DB = DB::singleton(dsn());

        $SQL = SQL::newSelect('user');
        $SQL->setSelect('user_sort');
        $SQL->setOrder('user_sort', 'DESC');
        $SQL->addWhereOpr('user_blog_id', BID);
        $sort = intval($DB->query($SQL->get(dsn()), 'one')) + 1;

        return $sort;
    }

    /**
     * ユーザーフィールドの組み立て
     *
     * @param string $key フィールドキー
     * @param string $value フィールド値
     * @return void
     */
    protected function buildUser(string $key, string $value): void
    {
        switch ($key) {
            case 'user_updated_datetime':
            case 'user_generated_datetime':
                if (preg_match('@^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$@', $value)) {
                    $this->user[$key] = $value;
                }
                break;
            case 'user_expire':
                if (preg_match('@^\d{4}-\d{2}-\d{2}$@', $value)) {
                    $this->user[$key] = $value;
                }
                break;
            case 'user_code':
            case 'user_mail':
                if (!empty($value)) {
                    $this->user[$key] = $value;
                }
                break;
            case 'user_pass':
                if (!empty($value)) {
                    $this->user['user_pass'] = acmsUserPasswordHash($value);
                    $this->user['user_pass_generation'] = PASSWORD_ALGORITHM_GENERATION;
                }
                break;
            case 'user_id':
            case 'user_blog_id':
            case 'user_path_reset':
            case 'user_sort':
                break;
            default:
                $this->user[$key] = $value;
        }
    }

    /**
     * ユーザーフィールドの組み立て
     *
     * @param array<string, mixed> $field フィールドベースデータ
     * @param string $key フィールドキー
     * @param string $value フィールド値
     * @return void
     */
    protected function buildField(array $field, string $key, string $value): void
    {
        $sort = 1;
        if (preg_match('@\[\d+\]$@', $key, $matchs)) {
            $sort = intval(preg_replace('@\[|\]@', '', $matchs[0]));
            $key = preg_replace('@\[\d+\]$@', '', $key);
        }
        if (!$key) {
            return;
        }
        $fieldTypeValue = null;
        if (preg_match('/@(html|media|title)$/', $key, $matches)) {
            $fieldTypeValue = $matches[1];
        }
        $field['field_key'] = $key;
        $field['field_type'] = $fieldTypeValue;
        $field['field_value'] = $value;
        $field['field_sort'] = $sort;

        $this->fields[] = $field;
    }

    /**
     * ユーザーベースデータを取得
     *
     * @return array<string, mixed> ユーザーベースデータ
     */
    protected function userBase(): array
    {
        $base = [
            'user_id'               => $this->nextId,
            'user_code'             => 'user-' . $this->nextId,
            'user_status'           => 'open',
            'user_sort'             => $this->nextSortId(),
            'user_name'             => 'user-' . $this->nextId,
            'user_mail'             => 'user-' . $this->nextId . '@example.com',
            'user_mail_magazine'    => 'off',
            'user_mail_mobile'      => '',
            'user_mail_mobile_magazine' => 'off',
            'user_pass'             => '',
            'user_pass_generation'  => PASSWORD_ALGORITHM_GENERATION,
            'user_url'              => '',
            'user_auth'             => 'subscriber',
            'user_locale'           => '',
            'user_indexing'         => 'on',
            'user_login_anywhere'   => 'off',
            'user_login_expire'     => '9999-12-31',
            'user_login_datetime'   => null,
            'user_updated_datetime' => date('Y-m-d H:i:s', REQUEST_TIME),
            'user_generated_datetime'   => date('Y-m-d H:i:s', REQUEST_TIME),
            'user_blog_id'          => BID,
        ];

        if (!$this->isUpdate) {
            $base['user_pass'] = acmsUserPasswordHash('user-' . $this->nextId);
            $base['user_pass_generation'] = PASSWORD_ALGORITHM_GENERATION;
        }

        return $base;
    }

    /**
     * フィールドベースデータを取得
     *
     * @return array<string, mixed> フィールドベースデータ
     */
    protected function fieldBase(): array
    {
        return [
            'field_key'     => null,
            'field_value'   => null,
            'field_sort'    => 1,
            'field_search'  => 'on',
            'field_uid'     => $this->nextId,
            'field_blog_id' => BID,
        ];
    }
}
