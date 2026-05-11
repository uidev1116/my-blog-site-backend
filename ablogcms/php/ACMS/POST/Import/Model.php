<?php

/**
 * CSVインポート用のモデル基底クラス
 *
 * エントリーやユーザーなどのCSVインポート処理の共通ロジックを提供する抽象クラス
 */
abstract class ACMS_POST_Import_Model extends ACMS_POST_Import
{
    /** @var array<string, string> CSVデータの連想配列（ラベル => 値） */
    protected array $data = [];

    /** @var array<int, string> CSVのラベル（カラム名）配列 */
    protected array $labels = [];

    /** @var int|null CSVから取得したID（更新時に使用） */
    protected ?int $csvId = null;

    /** @var int 次に発行されるID（新規作成時に使用） */
    protected int $nextId = 0;

    /** @var bool 更新フラグ（true: 更新, false: 新規作成） */
    protected bool $isUpdate = false;

    /** @var string IDラベル名（サブクラスで設定、例: 'entry_id', 'user_id'） */
    protected string $idLabel = '';

    /** @var array<int, array<string, mixed>> 組み立て済みのカスタムフィールド配列 */
    protected array $fields = [];

    /**
     * コンストラクタ
     *
     * CSVデータとラベルを受け取り、データを初期化する
     *
     * @param array<int, string> $csv CSV行データ
     * @param array<int, string> $labels CSVラベル配列
     * @throws RuntimeException CSV項目が足りない場合
     */
    public function __construct(array $csv, array $labels)
    {
        $this->isUpdate = false;
        $this->labels = $labels;
        $data = $this->normalize($csv);

        foreach ($labels as $i => $label) {
            if (!isset($data[$i])) {
                throw new RuntimeException('CSVの項目が足りません。');
            }
            if ($label === $this->idLabel && $data[$i] !== '' && is_numeric($data[$i])) {
                $this->csvId = intval($data[$i]);
            }
            $this->data[$label] = $data[$i];
        }

        if ($this->exist()) {
            $this->isUpdate = true;
        } else {
            $this->nextId();
        }
    }

    /**
     * 存在チェック
     *
     * CSVから取得したIDが存在し、更新可能な状態かを確認する
     *
     * @return bool 存在し更新可能な場合true
     */
    abstract protected function exist(): bool;

    /**
     * 次発行されるIDを設定
     *
     * 新規作成時に使用するIDを設定する
     *
     * @return void
     */
    abstract protected function nextId(): void;

    /**
     * データの組み立て
     *
     * CSVデータから実際のデータ構造を組み立てる
     *
     * @return void
     */
    abstract protected function build(): void;

    /**
     * データの挿入
     *
     * 新規データをデータベースに挿入する
     *
     * @return void
     */
    abstract protected function insert(): void;

    /**
     * データの更新
     *
     * 既存データをデータベースで更新する
     *
     * @return void
     */
    abstract protected function update(): void;

    /**
     * 保存処理
     *
     * データを組み立て、更新または挿入を実行する
     * バリデーションは save() で実行（setTargetCid() や setTargetBid() の後に実行される）
     *
     * @return void
     */
    public function save(): void
    {
        // 完全なバリデーションを実行（参照整合性チェックなど）
        $this->validate();

        $this->build();

        if ($this->isUpdate) {
            $this->update();
        } else {
            $this->insert();
        }
    }

    /**
     * バリデーション
     *
     * save() メソッドで実行される完全なバリデーション
     * サブクラスでオーバーライドして実装可能
     *
     * @return void
     */
    abstract protected function validate(): void;

    /**
     * データの正規化
     *
     * データを正規化する
     *
     * @param array<int, string> $data データ
     * @return array<int, string> 正規化されたデータ
     */
    private function normalize(array $data): array
    {
        $normalized = array_map(function ($value) {
            $newValue = preg_replace('/^str-data\_/', '', $value);
            if ($newValue !== null) {
                return $newValue;
            }
            return $value;
        }, $data);
        return $normalized;
    }

    /**
     * カスタムフィールドの組み立て
     *
     * CSV のラベル（field_key）から添字とサフィックスを抽出し、
     * field_type を決定したうえで $this->fields に積む。
     *
     * - `[n]` 形式の添字を解釈（1 以上必須）
     * - 先頭の `*`（カスタムフィールドキー指定）を除去
     * - 末尾の `@html|@media|@title|@block-editor` を field_type として抽出
     * - `@block-editor` は通常フォーム保存と挙動を揃え field_key からも取り除く
     *
     * @param array<string, mixed> $field フィールドベースデータ
     * @param string $key フィールドキー（CSVラベル）
     * @param string $value フィールド値
     * @return void
     */
    protected function buildField(array $field, string $key, string $value): void
    {
        $sort = 1;
        if (preg_match('@\[\d+\]$@', $key, $matchs)) {
            $sort = intval(preg_replace('@\[|\]@', '', $matchs[0]));
            $key = (string) preg_replace('@\[\d+\]$@', '', $key);
        }
        if ($sort < 1) {
            throw new RuntimeException(
                'フィールドの添字は1以上を指定してください（' . $key . '）。入力された値: ' . $sort
            );
        }
        $key = ltrim($key, '*');
        if (!$key) {
            return;
        }
        $fieldTypeValue = null;
        if (preg_match('/@(html|media|title|block-editor)$/', $key, $matches)) {
            $fieldTypeValue = $matches[1];
        }
        // ブロックエディターはフィールド本体の type 属性として扱うため、
        // 通常フォーム保存時と同じく field_key からサフィックスを除去する
        if ($fieldTypeValue === 'block-editor') {
            $key = (string) preg_replace('/@block-editor$/', '', $key);
        }
        $field['field_key'] = $key;
        $field['field_type'] = $fieldTypeValue;
        $field['field_value'] = $value;
        $field['field_sort'] = $sort;

        $this->fields[] = $field;
    }
}
