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

        $this->validate();
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
     *
     * @return void
     */
    public function save(): void
    {
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
     * サブクラスでオーバーライドして実装可能
     *
     * @return void
     */
    protected function validate(): void
    {
    }

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
}
