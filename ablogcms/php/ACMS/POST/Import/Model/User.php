<?php

use Acms\Traits\CsvImport\UpdateByFieldKey;

/**
 * ユーザーCSVインポート用モデルクラス
 *
 * ユーザーのCSVインポート処理を実装するクラス
 */
class ACMS_POST_Import_Model_User extends ACMS_POST_Import_Model
{
    use UpdateByFieldKey;

    /** @var array<string, mixed> ユーザーデータ */
    protected array $user = [];

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
        $this->nextId = intval(DB::query(SQL::nextval('user_id', dsn()), 'seq'));
    }

    /**
     * バリデーション処理
     *
     * save() メソッドで実行される完全なバリデーション
     * 必須フィールドの存在確認、データフォーマットの検証、重複チェックを実行する
     *
     * @return void
     * @throws RuntimeException バリデーションエラー時
     */
    /**
     * フィールドIDカラム名を返す
     *
     * @return string
     */
    protected function getFieldIdColumn(): string
    {
        return 'field_uid';
    }

    /**
     * 保存処理
     *
     * @return void
     */
    public function save(): void
    {
        $this->validate();
        $this->updateKey();
        $this->build();
        $this->duplicateCheck();

        if ($this->isUpdate) {
            $this->update();
        } else {
            $this->insert();
        }
    }

    protected function validate(): void
    {
        // 1. 基本的なフォーマットチェック
        $this->validateBasicFormat();

        // 2. 文字数制限チェック
        $this->validateFieldLengths();

        // 3. ユーザーコードのバリデーション
        $this->validateUserCode();
    }

    /**
     * 基本的なフォーマットチェック
     *
     * validate() 内で実行される基本的なフォーマットチェック
     *
     * @return void
     * @throws RuntimeException フォーマットが不正な場合
     */
    private function validateBasicFormat(): void
    {
        foreach ($this->data as $key => $value) {
            switch ($key) {
                case 'user_id':
                    // 新規作成の場合は空文字列が許可される
                    if ($value !== '' && !is_numeric($value)) {
                        throw new \RuntimeException('数値でない値が設定されています（' . $key . '）。入力された値: ' . $value);
                    }
                    break;
                case 'user_sort':
                    if ($value !== '' && !is_numeric($value)) {
                        throw new \RuntimeException('数値でない値が設定されています（' . $key . '）。入力された値: ' . $value);
                    }
                    break;
                case 'user_status':
                    if (!in_array($value, ['open', 'close', 'withdrawal', 'pseudo'], true)) {
                        throw new \RuntimeException('不正な値が設定されています（' . $key . '）。open, close, withdrawal, pseudo のいずれかを指定してください。入力された値: ' . $value);
                    }
                    break;
                case 'user_auth':
                    if (!in_array($value, ['administrator', 'editor', 'contributor', 'subscriber'], true)) {
                        throw new \RuntimeException('不正な値が設定されています（' . $key . '）。administrator, editor, contributor, subscriber のいずれかを指定してください。入力された値: ' . $value);
                    }
                    break;
                case 'user_login_expire':
                    // 空文字列の場合はスキップ（オプショナルフィールド）
                    if ($value !== '' && !preg_match('@^\d{4}-\d{2}-\d{2}$@', $value)) {
                        throw new \RuntimeException('日付のフォーマットが間違っています（' . $key . '）。YYYY-MM-DD 形式で指定してください。入力された値: ' . $value);
                    }
                    break;
                case 'user_login_datetime':
                case 'user_updated_datetime':
                case 'user_generated_datetime':
                    // 空文字列の場合はスキップ（オプショナルフィールド）
                    if ($value !== '' && !preg_match('@^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$@', $value)) {
                        throw new \RuntimeException('日時のフォーマットが間違っています（' . $key . '）。YYYY-MM-DD HH:MM:SS 形式で指定してください。入力された値: ' . $value);
                    }
                    break;
                case 'user_indexing':
                case 'user_mail_magazine':
                case 'user_mail_mobile_magazine':
                case 'user_login_anywhere':
                case 'user_global_auth':
                case 'user_login_terminal_restriction':
                    // 空文字列の場合はスキップ（デフォルト値が使用される）
                    if ($value !== '' && !in_array($value, ['on', 'off'], true)) {
                        throw new \RuntimeException('on または off 以外の値が設定されています（' . $key . '）。入力された値: ' . $value);
                    }
                    break;
            }
        }
    }

    /**
     * 文字数制限チェック
     *
     * @return void
     * @throws RuntimeException 文字数制限を超えている場合
     */
    private function validateFieldLengths(): void
    {
        // user_name: varchar(255)
        if (isset($this->data['user_name']) && $this->data['user_name'] !== '') {
            $length = mb_strlen($this->data['user_name'], 'UTF-8');
            if ($length > 255) {
                throw new \RuntimeException('ユーザー名が長すぎます（user_name）。最大255文字まで入力できます。現在: ' . $length . '文字。入力された値: ' . $this->data['user_name']);
            }
        }

        // user_code: varchar(64)
        if (isset($this->data['user_code']) && $this->data['user_code'] !== '') {
            $length = mb_strlen($this->data['user_code'], 'UTF-8');
            if ($length > 64) {
                throw new \RuntimeException('ユーザーコードが長すぎます（user_code）。最大64文字まで入力できます。現在: ' . $length . '文字。入力された値: ' . $this->data['user_code']);
            }
        }

        // user_mail: varchar(255)
        if (isset($this->data['user_mail']) && $this->data['user_mail'] !== '') {
            $length = mb_strlen($this->data['user_mail'], 'UTF-8');
            if ($length > 255) {
                throw new \RuntimeException('メールアドレスが長すぎます（user_mail）。最大255文字まで入力できます。現在: ' . $length . '文字。入力された値: ' . $this->data['user_mail']);
            }
        }

        // user_url: varchar(255)
        if (isset($this->data['user_url']) && $this->data['user_url'] !== '') {
            $length = mb_strlen($this->data['user_url'], 'UTF-8');
            if ($length > 255) {
                throw new \RuntimeException('URLが長すぎます（user_url）。最大255文字まで入力できます。現在: ' . $length . '文字。入力された値: ' . $this->data['user_url']);
            }
        }
    }

    /**
     * ユーザーコードのバリデーション
     *
     * @return void
     * @throws RuntimeException ユーザーコードが不正な場合
     */
    private function validateUserCode(): void
    {
        if (!isset($this->data['user_code']) || $this->data['user_code'] === '') {
            return; // ユーザーコードが指定されていない場合はスキップ（自動生成される）
        }

        $code = $this->data['user_code'];

        // 形式チェック
        if (!isValidCode($code)) {
            throw new \RuntimeException('ユーザーコードの形式が正しくありません（user_code）。改行、タブ、制御文字、引用符を含むことはできません。入力された値: ' . $code);
        }

        // 予約語チェック
        if (isReserved($code, false)) {
            throw new \RuntimeException('ユーザーコードに予約語が使用されています（user_code: ' . $code . '）。別のコードを指定してください。');
        }
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
        $userCode = (string) ($this->user['user_code'] ?? '');
        $userMail = (string) ($this->user['user_mail'] ?? '');

        if ($userMail === '') {
            throw new RuntimeException('メールアドレスが設定されていません。（user_mail）');
        }

        $where = SQL::newWhere();
        if ($userCode !== '') {
            $where->addWhereOpr('user_code', $userCode, '=', 'OR');
        }
        $where->addWhereOpr('user_mail', $userMail, '=', 'OR');

        $sql = SQL::newSelect('user');
        $sql->addWhere($where, 'AND');
        // 更新時は自分自身を重複対象から除外する（UpdateByFieldKey でも csvId が設定される）
        if ($this->isUpdate && $this->csvId !== null) {
            $sql->addWhereOpr('user_id', $this->csvId, '<>');
        }

        if (DB::query($sql->get(dsn()), 'row')) {
            $duplicateInfo = [];
            if ($userCode !== '') {
                $duplicateInfo[] = 'user_code: ' . $userCode;
            }
            $duplicateInfo[] = 'user_mail: ' . $userMail;
            throw new RuntimeException('既に存在するユーザーが含まれています。 重複した値: ' . implode(', ', $duplicateInfo));
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

        $sql = SQL::newInsert('user');
        foreach ($this->user as $key => $val) {
            $sql->addInsert($key, $val);
        }
        DB::query($sql->get(dsn()), 'exec');
    }

    /**
     * ユーザーフィールドを挿入
     *
     * @return void
     */
    protected function insertUserField(): void
    {
        $uid = $this->nextId;

        if (count($this->fields) > 0) {
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
        $uid = $this->csvId;
        $sql = SQL::newUpdate('user');
        foreach ($this->user as $key => $val) {
            $sql->addUpdate($key, $val);
        }
        $sql->addWhereOpr('user_id', $uid);
        $sql->addWhereOpr('user_blog_id', BID);
        DB::query($sql->get(dsn()), 'exec');
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

        if (count($this->fields) > 0) {
            $fkey = [];
            $deleteSql = SQL::newDelete('field');
            $deleteSql->addWhereOpr('field_uid', $uid);
            foreach ($this->fields as $dval) {
                foreach ($dval as $key => $val) {
                    if ($key === 'field_key') {
                        $fkey[] = $val;
                    }
                }
            }
            $deleteSql->addWhereIn('field_key', $fkey);
            DB::query($deleteSql->get(dsn()), 'exec');
            Common::deleteFieldCache('uid', $uid);

            $insertSql = SQL::newBulkInsert('field');
            foreach ($this->fields as $fval) {
                $fval['field_uid'] = $uid;
                $fval['field_blog_id'] = ACMS_RAM::userBlog($uid);
                $insertSql->addInsert($fval);
            }
            if ($insertSql->hasData()) {
                DB::query($insertSql->get(dsn()), 'exec');
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

        if ($this->isUpdate && $this->csvId !== null) {
            $field['field_uid'] = $this->csvId;
        }

        foreach ($this->data as $key => $value) {
            if ($key === 'user_id' && $this->isUpdate) {
                $this->user['user_id'] = $this->csvId;
            }
            if (array_key_exists($key, $this->user)) {
                $this->buildUser($key, $value);
            } else {
                $this->buildField($field, $key, $value);
            }
        }
        // パスワードが空の場合は、更新時に元のパスワードを維持するために unset
        // （新規作成時は userBase() でデフォルトパスワードが設定されているのでここには到達しない）
        if (!isset($this->user['user_pass']) || $this->user['user_pass'] === '') {
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
        $sql = SQL::newSelect('user');
        $sql->setSelect('user_sort');
        $sql->setOrder('user_sort', 'DESC');
        $sql->addWhereOpr('user_blog_id', BID);
        $sort = intval(DB::query($sql->get(dsn()), 'one')) + 1;

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
                if ($value !== '') {
                    $this->user[$key] = $value;
                }
                break;
            case 'user_pass':
                if ($value !== '') {
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
            // 新規作成時のデフォルトパスワードは予測不能なランダム値とする（user_id 由来の推測可能値は使わない）
            $base['user_pass'] = acmsUserPasswordHash(Common::genPass(16));
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
