<?php

namespace Acms\Services\Unit\Models;

use Acms\Services\Unit\Contracts\Model;
use Acms\Services\Unit\Contracts\AlignableUnitInterface;
use Acms\Services\Unit\Contracts\AnkerUnitInterface;
use Acms\Services\Facades\Cache;
use Acms\Traits\Unit\AlignableUnitTrait;
use Acms\Traits\Unit\AnkerUnitTrait;
use Acms\Traits\Unit\UnitMultiLangTrait;
use ACMS_Hook;
use Template;

/**
 * @extends \Acms\Services\Unit\Contracts\Model<array<string, mixed>>
 */
class Embed extends Model implements AlignableUnitInterface, AnkerUnitInterface
{
    use AlignableUnitTrait;
    use AnkerUnitTrait;
    use UnitMultiLangTrait;

    /**
     * ユニットの独自データ
     * @var array<string, mixed>
     */
    private $attributes = [];

    /**
     * ユニットタイプを取得
     *
     * @return string
     */
    public static function getUnitType(): string
    {
        // 互換性のため、quoteとしているが、実際はembedユニット
        return 'quote';
    }

    /**
     * ユニットラベルを取得
     *
     * @return string
     */
    public static function getUnitLabel(): string
    {
        return gettext('埋め込み');
    }

    /**
     * @inheritDoc
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * @inheritDoc
     */
    public function setAttributes($attributes): void
    {
        $this->attributes = $attributes;
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
        $this->setField3(config("{$configKeyPrefix}field_3", '', $configIndex));
        $this->setField4(config("{$configKeyPrefix}field_4", '', $configIndex));
        $this->setField5(config("{$configKeyPrefix}field_5", '', $configIndex));
        $this->setField6(config("{$configKeyPrefix}field_6", '', $configIndex));
        $this->setField7(config("{$configKeyPrefix}field_7", '', $configIndex));
        $this->setField8(config("{$configKeyPrefix}field_8", '', $configIndex));
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
        $quoteUrl = $this->implodeUnitDataTrait($request["quote_url_{$id}"] ?? '');
        $this->setField6($quoteUrl);

        $urlAry = $this->explodeUnitDataTrait($quoteUrl);
        $field1Ary = [];
        $field2Ary = [];
        $field3Ary = [];
        $field4Ary = [];
        $field5Ary = [];
        $field7Ary = [];
        $field8Ary = [];

        foreach ($urlAry as $url) {
            if (preg_match(REGEX_VALID_URL, $url)) {
                $cache = Cache::field();
                $cacheKey = md5($url);
                $cacheItem = $cache->getItem($cacheKey);
                if ($cacheItem->isHit()) {
                    $field = $cacheItem->get();
                    $field1Ary[] = $field['field1'] ?? '';
                    $field2Ary[] = $field['field2'] ?? '';
                    $field3Ary[] = $field['field3'] ?? '';
                    $field4Ary[] = $field['field4'] ?? '';
                    $field5Ary[] = $field['field5'] ?? '';
                    $field7Ary[] = $field['field7'] ?? '';
                    $field8Ary[] = $field['field8'] ?? '';
                } else {
                    $html = '';
                    $field1 = '';
                    $field2 = '';
                    $field3 = '';
                    $field4 = '';
                    $field8 = '';
                    if (HOOK_ENABLE) {
                        $Hook = ACMS_Hook::singleton();
                        $Hook->call('extendsQuoteUnit', [$url, &$html]);
                    }
                    if (is_string($html) && $html !== '') { // @phpstan-ignore-line
                        $field7Ary[] = $html;
                    } else {
                        try {
                            $embed = new \Embed\Embed();
                            $graph = $embed->get($url);
                            if ($graph) { // @phpstan-ignore-line
                                $field1 = $graph->providerName;
                                $field2 = $graph->authorName;
                                $field3 = $graph->title;
                                $field4 = $graph->description;
                                $field8 = $graph->image;
                            }
                        } catch (\Exception $e) {
                        }
                        $field1Ary[] = $field1;
                        $field2Ary[] = $field2;
                        $field3Ary[] = $field3;
                        $field4Ary[] = $field4;
                        $field8Ary[] = $field8;
                    }
                    $cache->put($cacheKey, [
                        'field1' => $field1,
                        'field2' => $field2,
                        'field3' => $field3,
                        'field4' => $field4,
                        'field8' => $field8,
                        'field7' => $html,
                    ]);
                }
            }
        }

        $this->setField1($this->implodeUnitDataTrait($field1Ary));
        $this->setField2($this->implodeUnitDataTrait($field2Ary));
        $this->setField3($this->implodeUnitDataTrait($field3Ary));
        $this->setField4($this->implodeUnitDataTrait($field4Ary));
        $this->setField5($this->implodeUnitDataTrait($field5Ary));
        $this->setField7($this->implodeUnitDataTrait($field7Ary));
        $this->setField8($this->implodeUnitDataTrait($field8Ary));
    }

    /**
     * 保存できるユニットか判断
     *
     * @return bool
     */
    public function canSave(): bool
    {
        if ($this->getField6() === '') {
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
        return '';
    }

    /**
     * ユニットのサマリーテキストを取得
     *
     * @return string[]
     */
    public function getSummaryText(): array
    {
        return [];
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
        if ($this->getField6() === '') {
            return;
        }
        $url = $this->getField6();
        $vars += [
            'quote_url' => $url,
        ];
        $this->formatMultiLangUnitDataTrait($this->getField6(), $vars, 'quote_url');

        if ($html = $this->getField7()) {
            $vars['quote_html'] = $html;
            $this->formatMultiLangUnitDataTrait($html, $vars, 'quote_html');
        }
        if ($siteName = $this->getField1()) {
            $vars['quote_site_name'] = $siteName;
            $this->formatMultiLangUnitDataTrait($siteName, $vars, 'quote_site_name');
        }
        if ($author = $this->getField2()) {
            $vars['quote_author'] = $author;
            $this->formatMultiLangUnitDataTrait($author, $vars, 'quote_author');
        }
        if ($title = $this->getField3()) {
            $vars['quote_title'] = $title;
            $this->formatMultiLangUnitDataTrait($title, $vars, 'quote_title');
        }
        if ($description = $this->getField4()) {
            $vars['quote_description'] = $description;
            $this->formatMultiLangUnitDataTrait($description, $vars, 'quote_description');
        }
        $image = $this->getField8() ? $this->getField8() : $this->getField5();
        if ($image) {
            $vars['quote_image'] = $image;
            $this->formatMultiLangUnitDataTrait($image, $vars, 'quote_image');
        }
        $vars['align'] = $this->getAlign()->value;
        $vars['anker'] = $this->getAnker();
        $tpl->add(['unit#' . $this->getType()], $vars);
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
        $vars += [
            'quote_url' => $this->getField6(),
            'html' => $this->getField7(),
            'site_name' => $this->getField1(),
            'author' => $this->getField2(),
            'title' => $this->getField3(),
            'description' => $this->getField4(),
            'image' => $this->getField8() ? $this->getField8() : $this->getField5(),
        ];
        $this->formatMultiLangUnitDataTrait($vars['quote_url'], $vars, 'quote_url');
        $this->formatMultiLangUnitDataTrait($vars['html'], $vars, 'html');
        $this->formatMultiLangUnitDataTrait($vars['site_name'], $vars, 'site_name');
        $this->formatMultiLangUnitDataTrait($vars['author'], $vars, 'author');
        $this->formatMultiLangUnitDataTrait($vars['title'], $vars, 'title');
        $this->formatMultiLangUnitDataTrait($vars['description'], $vars, 'description');
        $this->formatMultiLangUnitDataTrait($vars['image'], $vars, 'image');

        $tpl->add(array_merge([static::getUnitType()], $rootBlock), $vars);
    }

    /**
     * レガシーなユニットデータを返却（互換性のため）
     *
     * @return array
     */
    protected function getLegacy(): array
    {
        return [
            'quote_url' => $this->getField6(),
            'html' => $this->getField7(),
            'site_name' => $this->getField1(),
            'author' => $this->getField2(),
            'title' => $this->getField3(),
            'description' => $this->getField4(),
            'image' => $this->getField8() ? $this->getField8() : $this->getField5(),
        ];
    }
}
