<?php

namespace Acms\Services\Unit\Models;

use Acms\Services\Unit\Contracts\Model;
use Acms\Services\Unit\Contracts\AlignableUnitInterface;
use Acms\Services\Unit\Contracts\AnkerUnitInterface;
use Acms\Services\Unit\Contracts\AttrableUnitInterface;
use Acms\Traits\Unit\AlignableUnitTrait;
use Acms\Traits\Unit\AnkerUnitTrait;
use Acms\Traits\Unit\AttrableUnitTrait;
use Acms\Traits\Unit\UnitMultiLangTrait;
use Template;
use ACMS_Corrector;

/**
 * @extends \Acms\Services\Unit\Contracts\Model<array<string, mixed>>
 */
class Text extends Model implements AlignableUnitInterface, AnkerUnitInterface, AttrableUnitInterface
{
    use AlignableUnitTrait;
    use AnkerUnitTrait;
    use AttrableUnitTrait;
    use UnitMultiLangTrait;

    /**
     * ユニットの独自データ
     * @var array<string, mixed>
     */
    private $attributes = [];

    /**
     * @inheritDoc
     */
    public function getAttributes()
    {
        return [
            'text_text' => $this->getField1(),
            'text_tag' => $this->getField2(),
            ...$this->attributes,
        ];
    }

    /**
     * @inheritDoc
     */
    public function setAttributes($attributes): void
    {
        $this->attributes = $attributes;
    }

    /**
     * ユニットタイプを取得
     *
     * @return string
     */
    public static function getUnitType(): string
    {
        return 'text';
    }

    /**
     * @inheritDoc
     */
    public static function getUnitLabel(): string
    {
        return gettext('テキスト');
    }

    /**
     * ユニットのデフォルト値をセット
     *
     * @param string $configKeyPrefix
     * @param int $configIndex
     * @return void
     */
    public function setDefault(string $configKeyPrefix, int $configIndex): void
    {
        $this->setField1(config("{$configKeyPrefix}field_1", '', $configIndex));
        $this->setField2(config("{$configKeyPrefix}field_2", '', $configIndex));
        $this->setField3('');
    }

    /**
     * @inheritDoc
     */
    public function extract(array $request): void
    {
        $id = $this->getId();
        if (is_null($id)) {
            throw new \LogicException('Unit ID must be set before calling extract');
        }
        if (!isset($request["text_tag_{$id}"])) {
            return;
        }
        $tag = is_array($request["text_tag_{$id}"]) ? $request["text_tag_{$id}"][0] : $request["text_tag_{$id}"];
        $tokens = preg_split('@(#|\.)@', $tag, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($tokens === false) {
            return;
        }
        $this->setField2(array_shift($tokens) ?? '');

        $idStr = '';
        $classStr = '';
        $attr = '';
        while ($mark = array_shift($tokens)) {
            $val = array_shift($tokens) ?: '';
            if ('#' === $mark) {
                $idStr = $val;
            } else {
                $classStr = $val;
            }
        }
        $attr .= $idStr !== '' ? " id=\"{$idStr}\"" : "";
        $attr .= $classStr !== '' ? " class=\"{$classStr}\"" : "";
        if ($attr !== '') {
            $this->setAttr($attr);
        }
        if (isset($request["text_extend_tag_{$id}"])) {
            $this->setField3($request["text_extend_tag_{$id}"] ?? '');
        }
        $text = $this->implodeUnitDataTrait($request["text_text_{$id}"] ?? '');
        $this->setField1($text);
    }

    /**
     * 保存できるユニットか判断
     *
     * @return bool
     */
    public function canSave(): bool
    {
        if ($this->getField1() === '') {
            return false;
        }
        return true;
    }

    /**
     * ユニット複製時の専用処理
     *
     * @return void
     */
    public function handleDuplicate(): void
    {
    }

    /**
     * ユニット削除時の専用処理
     *
     * @return void
     */
    public function handleRemove(): void
    {
    }

    /**
     * キーワード検索用のワードを取得
     *
     * @return string
     */
    public function getSearchText(): string
    {
        $text = $this->getField1();
        return $text;
    }

    /**
     * ユニットのサマリーテキストを取得
     *
     * @return string[]
     */
    public function getSummaryText(): array
    {
        $textAry = $this->explodeUnitDataTrait($this->getField1());
        $response = [];
        foreach ($textAry as $text) {
            if ($this->getField2() === 'table') {
                $corrector = new ACMS_Corrector();
                $text = $corrector->table($text);
            }
            $text = preg_replace('@\s+@u', ' ', strip_tags($text));
            $response[] = $text;
        }
        return $response;
    }

    /**
     * ユニットの描画
     *
     * @param Template $tpl
     * @param array $vars
     * @param string[] $rootBlock
     * @return void
     */
    public function render(Template $tpl, array $vars, array $rootBlock): void
    {
        if ($this->getField1() === '') {
            return;
        }
        $vars += [
            'text' => $this->getField1(),
            'extend_tag' => $this->getField3(),
        ];
        $this->formatMultiLangUnitDataTrait($vars['text'], $vars, 'text');

        $attr = $this->getAttr(); // テキストタグセレクトで登録したクラス属性
        if ($attr !== '') {
            $vars['attr'] = $attr;
            $vars['class'] = $attr; // legacy
        }
        $vars['extend_tag'] = $this->getField3();
        $vars['anker'] = $this->getAnker();
        $tpl->add(array_merge([$this->getField2(), 'unit#' . $this->getType()], $rootBlock), $vars);
        $tpl->add(array_merge(['unit#' . $this->getType()], $rootBlock), [
            'align' => $this->getAlign()->value,
        ]);
    }

    /**
     * 編集画面のユニット描画
     *
     * @param Template $tpl
     * @param array $vars
     * @param string[] $rootBlock
     * @return void
     */
    public function renderEdit(Template $tpl, array $vars, array $rootBlock): void
    {
        $suffix = '';
        $attr = $this->getAttr(); // テキストタグセレクトで登録したクラス属性
        $currentTag = $this->getField2();

        if (preg_match('@(?:id="([^"]+)"|class="([^"]+)")@', $attr, $match)) {
            if ($match[1] !== '') {
                $suffix .= '#' . $match[1];
            }
            if (isset($match[2])) {
                $suffix .= '.' . $match[2];
            }
        }
        foreach (configArray('column_text_tag') as $i => $tag) {
            $tagSelectVars = [
                'value' => $tag,
                'label' => config('column_text_tag_label', '', $i),
                'extend' => config('column_text_tag_extend_label', '', $i),
            ];
            if ($currentTag . $suffix === $tag) {
                $tagSelectVars['selected'] = config('attr_selected');
            }
            $tpl->add(array_merge(['textTag:loop', $this::getUnitType()], $rootBlock), $tagSelectVars);
        }
        $vars += [
            'extend_tag' => $this->getField3(),
            'selected_tag' => $currentTag,
        ];
        $this->formatMultiLangUnitDataTrait($this->getField1(), $vars, 'text');

        $tpl->add(array_merge([$this::getUnitType()], $rootBlock), $vars);
    }

    /**
     * レガシーなユニットデータを返却（互換性のため）
     *
     * @return array
     */
    protected function getLegacy(): array
    {
        return [
            'text' => $this->getField1(),
            'tag' => $this->getField2(),
            'extend_tag' => $this->getField3()
        ];
    }
}
