<?php

namespace Acms\Services\Unit\Contracts;

use Template;
use Acms\Services\Unit\Constants\UnitAlign;
use Acms\Services\Unit\Constants\UnitStatus;
use Acms\Services\Unit\Contracts\AlignableUnitInterface;

/**
 * @template T of array<string, mixed>
 */
abstract class Model
{
    use \Acms\Traits\Unit\UnitModelTrait;
    use \Acms\Traits\Unit\UnitConfigTrait;

    /**
     * ユニットID
     * @var non-empty-string|null
     */
    private $id;

    /**
     * ユニットの親ID
     * @var non-empty-string|null
     */
    private $parentId;

    /**
     * エントリーID
     * @var int|null
     */
    private $entryId;

    /**
     * エントリーのリビジョンID
     * @var int|null
     */
    private $revId;

    /**
     * ブログID
     * @var int|null
     */
    private $blogId;

    /**
     * sort
     * @var positive-int
     */
    private $sort = 1;



    /**
     * タイプ
     * @var string
     */
    private $type = '';

    /**
     * ユニットグループ
     * @deprecated ユニットグループは非推奨です。
     * @var string
     */
    private $group = '';

    /**
     * ステータス
     * @var UnitStatus
     */
    private $status = UnitStatus::OPEN;

    /**
     * フィールド1
     * @var string
     */
    private $field1 = '';

    /**
     * フィールド2
     * @var string
     */
    private $field2 = '';

    /**
     * フィールド3
     * @var string
     */
    private $field3 = '';

    /**
     * フィールド4
     * @var string
     */
    private $field4 = '';

    /**
     * フィールド5
     * @var string
     */
    private $field5 = '';

    /**
     * フィールド6
     * @var string
     */
    private $field6 = '';

    /**
     * フィールド7
     * @var string
     */
    private $field7 = '';

    /**
     * フィールド8
     * @var string
     */
    private $field8 = '';

    /**
     * コンストラクター
     */
    public function __construct()
    {
    }

    /**
     * ユニットタイプを取得
     *
     * @return string
     */
    abstract public static function getUnitType(): string;

    /**
     * ユニットラベルを取得
     *
     * @return string
     */
    abstract public static function getUnitLabel(): string;

    /**
     * ユニットのデフォルト値をセット
     *
     * @param string $configKeyPrefix
     * @param int $configIndex
     * @return void
     */
    abstract public function setDefault(string $configKeyPrefix, int $configIndex): void;

    /**
     * リクエストデータからユニット独自データを抽出
     *
     * @param array $request
     * @return void
     */
    abstract public function extract(array $request): void;

    /**
     * 保存できるユニットか判断
     *
     * @return bool
     */
    abstract public function canSave(): bool;

    /**
     * ユニット複製時の専用処理
     *
     * @return void
     */
    abstract public function handleDuplicate(): void;

    /**
     * ユニット削除時の専用処理
     *
     * @return void
     */
    abstract public function handleRemove(): void;

    /**
     * キーワード検索用のワードを取得
     *
     * @return string
     */
    abstract public function getSearchText(): string;

    /**
     * ユニットのサマリーテキストを取得
     *
     * @return string[]
     */
    abstract public function getSummaryText(): array;

    /**
     * ユニット描画
     *
     * @param Template $tpl
     * @param array $vars
     * @param string[] $rootBlock
     * @return void
     */
    abstract public function render(Template $tpl, array $vars, array $rootBlock): void;

    /**
     * 編集画面のユニット描画
     *
     * @param Template $tpl
     * @param array $vars
     * @param string[] $rootBlock
     * @return void
     */
    abstract public function renderEdit(Template $tpl, array $vars, array $rootBlock): void;

    /**
     * レガシーなユニットデータを返却（互換性のため）
     *
     * @return array
     */
    abstract protected function getLegacy(): array;


    /**
     * ユニットの独自データを取得
     * ユニットの独自データをHTMLから抽出する場合はHTML文字列を返却する
     *
     * @return T
     */
    abstract public function getAttributes();

    /**
     * ユニットの独自データを設定
     * ユニットの独自データをHTMLから抽出する場合はHTML文字列を設定する
     *
     * @param T $attributes
     * @return void
     */
    abstract public function setAttributes($attributes): void;

    /**
     * id getter
     *
     * @return non-empty-string|null
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * id setter
     *
     * @param non-empty-string $id
     * @return void
     */
    public function setId(string $id): void
    {
        if ($this->id) {
            throw new \InvalidArgumentException('id is already set');
        }
        $this->id = $id;
    }

    /**
     * parent id getter
     *
     * @return non-empty-string|null
     */
    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    /**
     * parent id setter
     *
     * @param non-empty-string|null $parentId
     * @return void
     */
    public function setParentId(?string $parentId): void
    {
        $this->parentId = $parentId;
    }

    /**
     * entry id getter
     *
     * @return int|null
     */
    public function getEntryId(): ?int
    {
        return $this->entryId;
    }

    /**
     * entry id setter
     *
     * @param int $eid
     * @return void
     */
    public function setEntryId(int $eid): void
    {
        $this->entryId = $eid;
    }

    /**
     * revision id getter
     *
     * @return int|null
     */
    public function getRevId(): ?int
    {
        return $this->revId;
    }

    /**
     * revision id setter
     *
     * @param int|null $revId
     * @return void
     */
    public function setRevId(?int $revId): void
    {
        $this->revId = $revId;
    }

    /**
     * blog id getter
     *
     * @return int|null
     */
    public function getBlogId(): ?int
    {
        return $this->blogId;
    }

    /**
     * blog id setter
     *
     * @param int $bid
     * @return void
     */
    public function setBlogId(int $bid): void
    {
        $this->blogId = $bid;
    }

    /**
     * sort getter
     *
     * @return positive-int
     */
    public function getSort(): int
    {
        return $this->sort;
    }

    /**
     * sort setter
     *
     * @param positive-int $sort
     * @return void
     */
    public function setSort(int $sort): void
    {
        $this->sort = $sort;
    }



    /**
     * type getter
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * type setter
     *
     * @param string $type
     * @return void
     */
    public function setType(string $type): void
    {
        $this->type = $type;
    }

    /**
     * group getter
     *
     * @deprecated ユニットグループは非推奨です。
     * @return string
     */
    public function getGroup(): string
    {
        if (config('unit_group') !== 'on') {
            return '';
        }
        if ($this instanceof ParentUnit) {
            // 親になるユニットは、ユニットグループを設定できない
            return '';
        }
        return $this->group;
    }

    /**
     * group setter
     *
     * @deprecated ユニットグループは非推奨です。
     * @param string $group
     * @return void
     */
    public function setGroup(string $group): void
    {
        if (config('unit_group') !== 'on') {
            return;
        }
        if ($this instanceof ParentUnit) {
            // 親になるユニットは、ユニットグループを設定できない
            return;
        }
        $this->group = $group;
    }

    /**
     * status getter
     *
     * @return UnitStatus
     */
    public function getStatus(): UnitStatus
    {
        return $this->status;
    }

    /**
     * status setter
     *
     * @param UnitStatus $status
     * @return void
     */
    public function setStatus(UnitStatus $status): void
    {
        $this->status = $status;
    }

    /**
     * 非表示ユニットかどうか
     *
     * @return bool
     */
    public function isHidden(): bool
    {
        return $this->status === UnitStatus::CLOSE;
    }

    /**
     * filed1 getter
     *
     * @return string
     */
    public function getField1(): string
    {
        return $this->field1;
    }

    /**
     * field1 setter
     *
     * @param string $field
     * @return void
     */
    public function setField1(string $field): void
    {
        $this->field1 = $field;
    }

    /**
     * field2 getter
     *
     * @return string
     */
    public function getField2(): string
    {
        return $this->field2;
    }

    /**
     * field2 setter
     *
     * @param string $field
     * @return void
     */
    public function setField2(string $field): void
    {
        $this->field2 = $field;
    }

    /**
     * field3 getter
     *
     * @return string
     */
    public function getField3(): string
    {
        return $this->field3;
    }

    /**
     * field3 setter
     *
     * @param string $field
     * @return void
     */
    public function setField3(string $field): void
    {
        $this->field3 = $field;
    }

    /**
     * field4 getter
     *
     * @return string
     */
    public function getField4(): string
    {
        return $this->field4;
    }

    /**
     * field4 setter
     *
     * @param string $field
     * @return void
     */
    public function setField4(string $field): void
    {
        $this->field4 = $field;
    }

    /**
     * field5 getter
     *
     * @return string
     */
    public function getField5(): string
    {
        return $this->field5;
    }

    /**
     * field5 setter
     *
     * @param string $field
     * @return void
     */
    public function setField5(string $field): void
    {
        $this->field5 = $field;
    }

    /**
     * field6 getter
     *
     * @return string
     */
    public function getField6(): string
    {
        return $this->field6;
    }

    /**
     * field6 setter
     *
     * @param string $field
     * @return void
     */
    public function setField6(string $field): void
    {
        $this->field6 = $field;
    }

    /**
     * field7 getter
     *
     * @return string
     */
    public function getField7(): string
    {
        return $this->field7;
    }

    /**
     * field7 setter
     *
     * @param string $field
     * @return void
     */
    public function setField7(string $field): void
    {
        $this->field7 = $field;
    }

    /**
     * field8 getter
     *
     * @return string
     */
    public function getField8(): string
    {
        return $this->field8;
    }

    /**
     * field8 setter
     *
     * @param string $field
     * @return void
     */
    public function setField8(string $field): void
    {
        $this->field8 = $field;
    }

    /**
     * unit name getter
     *
     * @return string
     */
    public function getName(): string
    {
        $name = $this->getUnitNameTrait($this->getType());
        if ($name !== '') {
            return $name;
        }
        return static::getUnitLabel();
    }

    /**
     * 追加時の新規ユニットモデルを作成
     *
     * @param string $addType
     * @param int $configIndex
     * @return void
     */
    public function create(string $addType, int $configIndex): void
    {
        if (config('unit_group') === 'on') {
            $this->setGroup(config('column_def_add_' . $addType . '_group', '', $configIndex));
        }
        if ($this instanceof AttrableUnitInterface) {
            $this->setAttr(config('column_def_add_' . $addType . '_attr', '', $configIndex));
        }
        if ($this instanceof AlignableUnitInterface) {
            $align = config('column_def_add_' . $addType . '_align', '', $configIndex);
            $this->setAlign(UnitAlign::tryFrom($align) ?? UnitAlign::CENTER);
        }
        if ($this instanceof SizeableUnitInterface) {
            $size = config('column_def_add_' . $addType . '_size', '', $configIndex);
            $this->setSize($size);
        }
        $this->setDefault($this->getUnitDefaultConfigKeyPrefix('add', $addType), $configIndex);
    }

    /**
     * 初期表示時の新規ユニットモデルを作成
     *
     * @param int $configIndex
     * @return void
     */
    public function createDefault(int $configIndex): void
    {
        $sort = $configIndex + 1;
        assert($sort > 0);
        $this->setSort($sort);
        if (config('unit_group') === 'on') {
            $this->setGroup(config('column_def_insert_group', '', $configIndex));
        }
        if ($this instanceof AlignableUnitInterface) {
            $align = config('column_def_insert_align', '', $configIndex);
            $this->setAlign(UnitAlign::tryFrom($align) ?? UnitAlign::CENTER);
        }
        if ($this instanceof AttrableUnitInterface) {
            $this->setAttr(config('column_def_insert_attr', '', $configIndex));
        }
        if ($this instanceof SizeableUnitInterface) {
            $size = config('column_def_insert_size', '', $configIndex);
            $this->setSize($size);
        }
        $this->setDefault($this->getUnitDefaultConfigKeyPrefix('insert', static::getUnitType()), $configIndex);
    }

    /**
     * ユニットをロード
     *
     * @param array $record
     * @return void
     */
    public function load(array $record)
    {
        $id = (string) $record['column_id'];
        if ($id === '') {
            throw new \InvalidArgumentException('column_id is required');
        }
        $this->id = $id;
        $revId = isset($record['column_rev_id']) ? (int) $record['column_rev_id'] : null;
        if (is_int($revId) && $revId < 1) {
            throw new \InvalidArgumentException('column_rev_id must be greater than 0');
        }
        $this->revId = $revId;
        $entryId = (int) $record['column_entry_id'];
        if ($entryId < 1) {
            throw new \InvalidArgumentException('column_entry_id must be greater than 0');
        }
        $this->entryId = $entryId;
        $blogId = (int) $record['column_blog_id'];
        if ($blogId < 1) {
            throw new \InvalidArgumentException('column_blog_id must be greater than 0');
        }
        $this->blogId = $blogId;

        $sort = (int) $record['column_sort'];
        if ($sort < 1) {
            throw new \InvalidArgumentException('column_sort must be greater than 0');
        }
        $this->sort = $sort;

        $this->parentId = $record['column_parent_id'];
        $this->type = $record['column_type'];
        if ($this instanceof AlignableUnitInterface) {
            $this->setAlign(UnitAlign::tryFrom($record['column_align']) ?? UnitAlign::CENTER);
        }
        if ($this instanceof \Acms\Services\Unit\Contracts\AttrableUnitInterface) {
            $this->setAttr($record['column_attr']);
        }
        if ($this instanceof \Acms\Services\Unit\Contracts\AnkerUnitInterface) {
            $this->setAnker($record['column_anker']);
        }
        $this->group = $record['column_group'];
        if ($this instanceof \Acms\Services\Unit\Contracts\SizeableUnitInterface) {
            $this->setSize($record['column_size']);
        }
        $this->field1 = $record['column_field_1'];
        $this->field2 = $record['column_field_2'];
        $this->field3 = $record['column_field_3'];
        $this->field4 = $record['column_field_4'];
        $this->field5 = $record['column_field_5'];
        $this->field6 = $record['column_field_6'];
        $this->field7 = $record['column_field_7'];
        $this->field8 = $record['column_field_8'];
        $this->status = UnitStatus::tryFrom($record['column_status'] ?? 'open') ?? UnitStatus::OPEN;
        $this->onLoad($record);

        if ($this instanceof \Acms\Services\Unit\Contracts\ProcessExtender) {
            $this->extendOnLoad(); // ロード時に拡張処理を行う
        }
    }

    /**
     * ユニットロード時に拡張処理を行う
     *
     * @param array $record
     * @return void
     */
    public function onLoad(array $record): void
    {
    }

    /**
     * ユニットを保存してユニットIDを返却
     *
     * @param int $eid
     * @param int $bid
     * @param int|null $rvid
     * @return void
     */
    public function save(int $eid, int $bid, ?int $rvid): void
    {
        if (!enableRevision()) {
            // リビジョン機能が有効でない場合はリビジョンIDをnullに設定
            $rvid = null;
        }
        $unitId = $this->getId();
        if (is_null($unitId)) {
            throw new \LogicException('unit id is required to save');
        }
        // データセット
        $this->setEntryId($eid);
        $this->setBlogId($bid);
        $this->setRevId($rvid);

        // ユニットを保存
        $this->insertDataTrait($this, $rvid !== null && $rvid > 0);

        if ($this instanceof \Acms\Services\Unit\Contracts\ProcessExtender) {
            $this->extendOnSave(); // 保存時時に拡張処理を行う
        }
    }

    /**
     * レガシーなユニットデータを取得（互換性のため）
     * レガシーな方法なため新しく使用はしないでください。
     *
     * @return array
     */
    public function getLegacyData(): array
    {
        $data = [
            'clid' => $this->getId(),
            'type' => $this->getType(),
            'sort' => $this->getSort(),
        ];
        if (config('unit_group') === 'on') {
            $data['group'] = $this->getGroup();
        }
        if ($this instanceof \Acms\Services\Unit\Contracts\SizeableUnitInterface) {
            $data['size'] = $this->getSize();
        }
        if ($this instanceof \Acms\Services\Unit\Contracts\AttrableUnitInterface) {
            $data['attr'] = $this->getAttr();
        }
        if ($this instanceof \Acms\Services\Unit\Contracts\AlignableUnitInterface) {
            $data['align'] = $this->getAlign()->value;
        }
        if ($this instanceof \Acms\Services\Unit\Contracts\AnkerUnitInterface) {
            $data['anker'] = $this->getAnker();
        }
        $data += $this->getLegacy();

        return $data;
    }

    /**
     * ユニットのデフォルト値のコンフィグキープレフィックスを取得
     *
     * @param 'add'|'init'|'insert' $mode
     * @param string $addType
     * @return string
     */
    protected function getUnitDefaultConfigKeyPrefix(string $mode, string $addType): string
    {
        if ($mode === 'add') {
            return "column_def_add_{$addType}_";
        }
        return 'column_def_insert_';
    }

    /**
     * ユニットのデータを保存する前に拡張処理を行う
     *
     * @param \SQL_Insert $sql
     * @param bool $isRevision
     * @return void
     * @param-out \SQL_Insert $sql
     */
    public function extendInsertQuery(\SQL_Insert &$sql, bool $isRevision): void
    {
    }

    public function __clone()
    {
        if ($this->id !== null) {
            $newId = $this->generateNewIdTrait();
            $this->id = $newId;
        }
    }
}
