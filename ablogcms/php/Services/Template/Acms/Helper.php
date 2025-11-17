<?php

namespace Acms\Services\Template\Acms;

use Acms\Services\Facades\Application;
use Acms\Services\Facades\Media;
use Acms\Services\Facades\RichEditor;
use Acms\Services\Facades\BlockEditor;
use Acms\Services\Facades\Database as DB;
use Acms\Services\Facades\PublicStorage;
use ACMS_Filter;
use ACMS_RAM;
use Field;
use Field_Validation;
use SQL;

class Helper
{
    use \Acms\Traits\Utilities\EagerLoadingTrait;
    use \Acms\Traits\Unit\UnitMultiLangTrait;

    /**
     * テキストインプットの組み立て
     *
     * @param array $data
     * @param \Acms\Services\View\Contracts\ViewInterface $Tpl
     * @param string[]|string $block
     *
     * @return array
     */
    protected function buildInputTextValue($data, $Tpl, $block = [])
    {
        if (!is_array($block)) {
            $block = [$block];
        }
        $vars = [];
        foreach ($data as $key => $val) {
            if (is_array($val)) {
                foreach ($val as $i => $v) {
                    if (empty($i)) {
                        $vars[$key] = $v;
                        if (!isApiBuild()) {
                            $Tpl->add(array_merge([$key], $block), [$key => $v]);
                        }
                    }

                    $sfx = '[' . $i . ']';
                    if ($v !== '') {
                        $vars[$key . $sfx] = $v;
                    }
                    if (!isApiBuild()) {
                        if (!empty($i)) {
                            $Tpl->add(array_merge(['glue', $key . ':loop'], $block));
                            $Tpl->add(array_merge([$key . ':glue', $key . ':loop'], $block));
                        }
                    }
                    $Tpl->add(array_merge([$key . ':loop'], $block), !empty($v) ? [$key => $v] : []);
                }
            } else {
                //--------
                // legacy?
                $vars[$key] = $val;
            }
        }
        return $vars;
    }

    /**
     * チェックボックスインプットの組み立て
     *
     * @param array $data
     * @param \Acms\Services\View\Contracts\ViewInterface & $Tpl
     * @param string[]|string $block
     *
     * @return array
     */
    protected function buildInputCheckboxChecked($data, $Tpl, $block = [])
    {
        if (!is_array($block)) {
            $block = [$block];
        }
        $vars = [];
        foreach ($data as $key => $vals) {
            if (!is_array($vals)) {
                $vals = [$vals];
            }
            foreach ($vals as $i => $val) {
                if (!is_array($val)) {
                    foreach (
                        [
                            $key . ':checked#' . $val,
                            $key . '[' . $i . ']' . ':checked#' . $val,
                        ] as $name
                    ) {
                        $vars[$name] = config('attr_checked');
                        $Tpl->add(array_merge([$name], $block));
                    }
                }
            }
            $vars[$key . '[]'] = $vals; // フィールド名[] で配列形式でも取得できるようにする
        }
        return $vars;
    }

    /**
     * セレクトボックスインプットの組み立て
     *
     * @param array $data
     * @param \Acms\Services\View\Contracts\ViewInterface & $Tpl
     * @param string[]|string $block
     *
     * @return array
     */
    protected function buildSelectSelected($data, $Tpl, $block = [])
    {
        if (!is_array($block)) {
            $block = [$block];
        }
        $vars = [];
        foreach ($data as $key => $vals) {
            if (!is_array($vals)) {
                $vals = [$vals];
            }
            foreach ($vals as $i => $val) {
                if (!is_array($val)) {
                    foreach (
                        [
                            $key . ':selected#' . $val,
                            $key . '[' . $i . ']' . ':selected#' . $val,
                        ] as $name
                    ) {
                        $vars[$name] = config('attr_selected');
                        $Tpl->add(array_merge([$name], $block));
                    }
                }
            }
            $vars[$key . '[]'] = $vals; // フィールド名[] で配列形式でも取得できるようにする
        }
        return $vars;
    }

    /**
     * モジュールフィールドの組み立て
     *
     * @param \Acms\Services\View\Contracts\ViewInterface $Tpl
     * @param int|null $mid
     * @param bool $show
     *
     * @return void
     */
    public function buildModuleField($Tpl, $mid = null, $show = false)
    {
        if ($mid && $show) {
            $vars = $this->buildField(loadModuleField($mid), $Tpl, 'moduleField');
            $Tpl->add('moduleField', $vars);
        }
    }

    /**
     * 日付の組み立て
     *
     * @param int|string $datetime
     * @param \Acms\Services\View\Contracts\ViewInterface $Tpl
     * @param string[]|string $block
     * @param string $prefix
     *
     * @return array<string, string|false>
     */
    public function buildDate($datetime, $Tpl, $block = [], $prefix = 'date#')
    {
        if (!is_numeric($datetime)) {
            $timestamp = strtotime($datetime);
            if ($timestamp === false) {
                throw new \RuntimeException('Invalid datetime: ' . $datetime);
            }
            $datetime = $timestamp;
        }
        $block = empty($block) ? [] : (is_array($block) ? $block : [$block]);
        /** @var int $datetime */
        $w = date('w', $datetime);
        $weekPrefix = $prefix === 'date#' ? 'week#'
        : str_replace('date', 'week', $prefix);
        $Tpl->add(array_merge([$weekPrefix . $w], $block));

        $formats = [
            'd', 'D', 'j', 'l', 'N', 'S', 'w', 'z',
            'W',
            'F', 'm', 'M', 'n', 't',
            'L', 'o', 'Y', 'y',
            'a', 'A', 'B', 'g', 'G', 'h', 'H', 'i', 's', 'u',
            'e',
            'I', 'O', 'P', 'T', 'Z',
            'c', 'r', 'U',
        ];
        $vars = [];

        //--------
        // format
        $combined = implode('__', $formats);
        $formatted = explode('__', date($combined, $datetime));
        foreach ($formatted as $p => $val) {
            $c = $formats[$p];
            $vars[$prefix . $c] = $val;
        }
        $vars[$prefix] = date('Y-m-d H:i:s', $datetime);

        $vars[$prefix . 'week'] = config('week_label', '', intval($w));
        return $vars;
    }

    public function injectMediaField($Field, $force = false)
    {
        if (!$force && (!defined('ACMS_POST') || !ACMS_POST)) {
            return;
        }
        $mediaIds = [];
        $mediaList = [];
        $useMediaField = [];
        foreach ($Field->listFields() as $fd) {
            if (strpos($fd, '@media') !== false) {
                $useMediaField[] = substr($fd, 0, -6);
                foreach ($Field->getArray($fd) as $mid) {
                    $mediaIds[] = intval($mid);
                }
            }
        }
        if (!empty($mediaIds)) {
            $DB = DB::singleton(dsn());
            $SQL = SQL::newSelect('media');
            $SQL->addWhereIn('media_id', $mediaIds);
            $q = $SQL->get(dsn());
            $statement = $DB->query($q, 'exec');
            while ($media = $DB->next($statement)) {
                $mid = intval($media['media_id']);
                $mediaList[$mid] = $media;
            }
        }
        Media::injectMediaField($Field, $mediaList, $useMediaField);
    }

    public function injectRichEditorField($Field, $force = true)
    {
        if (!$force && !ACMS_POST) {
            return;
        }
        foreach ($Field->listFields() as $fd) {
            if (strpos($fd, '@html') !== false) {
                $values = $Field->getArray($fd);
                $fix = [];
                foreach ($values as $value) {
                    $fix[] = RichEditor::fix($value);
                }
                $Field->setField($fd, $fix);
            }
        }
    }

    /**
     * ブロックエディタフィールドの注入
     *
     * @param Field $Field
     * @param boolean $resizeImage
     * @return void
     */
    public function injectBlockEditorField(Field $Field, bool $resizeImage = true): void
    {
        foreach ($Field->listFields() as $fd) {
            if ($Field->getMeta($fd, 'type') === 'block-editor') {
                $values = $Field->getArray($fd);
                $fix = [];
                foreach ($values as $value) {
                    $fix[] = BlockEditor::fix($value, $resizeImage);
                }
                $Field->setField($fd, $fix);
            }
        }
    }

    /**
     * カスタムフィールドの組み立て
     *
     * @param \Field $Field
     * @param \Acms\Services\View\Contracts\ViewInterface $Tpl
     * @param string[]|string $block
     * @param string|null $scp
     * @param array $loop_vars
     *
     * @return array
     */
    public function buildField($Field, $Tpl, $block = [], $scp = null, $loop_vars = [])
    {
        $block = !empty($block) ? (is_array($block) ? $block : [$block]) : [];
        $vars = [];
        $this->injectMediaField($Field);
        $this->injectRichEditorField($Field);
        $this->injectBlockEditorField($Field, (!defined('ADMIN') || !ADMIN));
        $fds = $Field instanceof \Field_Validation  ? $Field->listFields(true) : $Field->listFields();

        //-------
        // group
        $mapGroup = [];
        foreach ($Field->listFields() as $fd) {
            if (preg_match('/^@(.*)$/', $fd, $match)) {
                $groupName = $match[1];
                $mapGroup[$groupName] = $Field->getArray($fd);
            }
        }
        foreach ($mapGroup as $groupName => $aryFd) {
            $data = [];
            for ($i = 0; true; $i++) {
                $row = [];
                $isExists = false;
                $hasValidator = false;
                foreach ($aryFd as $fd) {
                    $isExists |= $Field->isExists($fd, $i);
                    $row[$fd] = $Field->get($fd, '', $i);
                    if ($Field->isExists($fd . '@media', $i)) {
                        foreach (['name', 'fileSize', 'caption', 'link', 'alt', 'text', 'path', 'thumbnail', 'imageSize', 'type', 'extension', 'width', 'height', 'ratio'] as $exMedia) {
                            $fdMedia = $fd . "@$exMedia";
                            $row[$fdMedia] = $Field->get($fdMedia, '', $i);
                        }
                    }
                    if ($Field->isExists($fd . '@html', $i)) {
                        $row[$fd . '@html'] = $Field->get($fd . '@html', '', $i);
                    }
                    if ($Field->isExists($fd . '@title', $i)) {
                        $row[$fd . '@title'] = $Field->get($fd . '@title', '', $i);
                    }
                    if (!$hasValidator && $Field instanceof \Field_Validation) {
                        $validator = $Field->getMethods($fd);
                        $hasValidator |= !empty($validator);
                    }
                }
                if (!$isExists) {
                    break;
                }
                // 空の行を削除するかどうか
                if (!$hasValidator) {
                    if (!join('', $row)) {
                        continue;
                    }
                }
                $data[] = $row;
            }

            $vars[$groupName] = [];
            foreach ($data as $i => $row) {
                $_vars = $loop_vars;
                $loopblock = array_merge([$groupName . ':loop'], $block);

                //-----------
                // validator
                if (!isApiBuild()) {
                    if ($Field instanceof \Field_Validation) {
                        foreach ($row as $fd => $kipple) {
                            foreach ($Field->getMethods($fd) as $method) {
                                if (!$val = intval($Field->isValid($fd, $method, $i))) {
                                    foreach (['validator', 'v'] as $v) {
                                        $key = $fd . ':' . $v . '#' . $method;
                                        $_vars[$key] = $val;
                                        $Tpl->add(array_merge([$key], $loopblock), [$key => $val]);
                                    }
                                }
                            }
                        }
                    }
                }
                //-------
                // value
                foreach ($row as $key => $value) {
                    if ($value !== '') {
                        $_vars[$key] = $value;
                        if (!isApiBuild()) {
                            $_vars[$key . ':checked#' . $value] = config('attr_checked');
                            $_vars[$key . ':selected#' . $value] = config('attr_selected');
                        }
                    }
                    if (!isApiBuild() && !empty($i)) {
                        $Tpl->add(array_merge([$key . ':glue'], $loopblock));
                    }
                }
                //---
                // n
                if (!isApiBuild()) {
                    $_vars['i'] = $i;
                    if (!empty($i)) {
                        $Tpl->add(array_merge(['glue'], $loopblock));
                        $Tpl->add(array_merge([$groupName . ':glue'], $loopblock));
                    }
                }
                $vars[$groupName][] = $_vars;
                $Tpl->add($loopblock, $_vars);
            }
        }

        $data = [];
        foreach ($fds as $fd) {
            if (!$aryVal = $Field->getArray($fd)) {
                if (!isApiBuild()) {
                    $Tpl->add(array_merge([$fd . ':null'], $block));
                }
            }
            $data[$fd] = $aryVal;
            if ($Field instanceof \Field_Search) {
                if (!isApiBuild()) {
                    $data[$fd . '@connector'] = $Field->getConnector($fd, null);
                    $data[$fd . '@operator'] = $Field->getOperator($fd, null);
                }
            }
            if (!($Field instanceof \Field_Validation)) {
                continue;
            }
            if (!$val = intval($Field->isValid($fd))) {
                foreach (['validator', 'v'] as $v) {
                    $key = $fd . ':' . $v;
                    $vars[$key] = $val;
                    if (!isApiBuild()) {
                        $Tpl->add(array_merge([$key], $block), [$key => $val]);
                    }
                }

                $aryMethod = $Field->getMethods($fd);
                foreach ($aryMethod as $method) {
                    if (!$val = intval($Field->isValid($fd, $method))) {
                        foreach (['validator', 'v'] as $v) {
                            $key = $fd . ':' . $v . '#' . $method;
                            $vars[$key] = $val;
                            if (!isApiBuild()) {
                                $Tpl->add(array_merge([$key], $block), [$key => $val]);
                            }
                        }

                        $cnt = count($Field->getArray($fd));
                        for ($i = 0; $i < $cnt; $i++) {
                            if (!$val = intval($Field->isValid($fd, $method, $i))) {
                                foreach (['validator', 'v'] as $v) {
                                    $key = $fd . '[' . $i . ']' . ':' . $v . '#' . $method;
                                    $vars[$key] = $val;
                                    if (!isApiBuild()) {
                                        $Tpl->add(array_merge([$key], $block), [$key => $val]);
                                    }
                                }
                            } else {
                                continue;
                            }
                        }
                    } else {
                        continue;
                    }
                }
            } else {
                continue;
            }
        }

        //-------
        // touch
        if (!isApiBuild()) {
            foreach ($data as $fd => $vals) {
                if (!is_array($vals)) { // @phpstan-ignore-line
                    $vals = [$vals];
                }
                foreach ($vals as $i => $val) {
                    if (empty($i)) {
                        if (!is_array($val)) {
                            $Tpl->add(array_merge([$fd . ':touch#' . $val], $block));
                        }
                    }
                    if (!is_array($val)) {
                        $Tpl->add(array_merge([$fd . '[' . $i . ']' . ':touch#' . $val], $block));
                    }
                }
            }
        }

        $vars += $this->buildInputTextValue($data, $Tpl, $block);
        if (!isApiBuild()) {
            $vars += $this->buildInputCheckboxChecked($data, $Tpl, $block);
            $vars += $this->buildSelectSelected($data, $Tpl, $block);
            if (!is_null($scp)) {
                $vars[(!empty($scp) ? $scp . ':' : '') . 'takeover'] = acmsSerialize($Field);
            }
        }
        foreach ($Field->listChildren() as $child) {
            $vars += $this->buildField($Field->getChild($child), $Tpl, $block, $child);
        }

        return $vars;
    }

    /**
     * ページャーの組み立て
     *
     * @param int $page ページ数
     * @param int $limit 1ページの件数
     * @param int $amount 総数
     * @param int $delta 前後ページ数
     * @param string $curAttr
     * @param \Acms\Services\View\Contracts\ViewInterface $Tpl
     * @param string[]|string $block
     * @param array $Q
     *
     * @return array
     */
    public function buildPager($page, $limit, $amount, $delta, $curAttr, $Tpl, $block = [], $Q = [])
    {
        $vars = [];
        $block = is_array($block) ? $block : [$block];
        if (!ADMIN) {
            $Q['query'] = [];
        }
        if (KEYWORD) {
            $Q['keyword'] = KEYWORD;
        }

        $from = ($page - 1) * $limit;
        $to = $from + $limit; // - 1;
        if ($amount < $to) {
            $to = $amount;
        }
        $vars += [
            'itemsAmount' => $amount,
            'itemsFrom' => $from + 1,
            'itemsTo' => $to,
        ];
        $delta = intval($delta);
        $lastPage = ceil($amount / $limit);
        $fromPage = 1 > ($page - $delta) ? 1 : ($page - $delta);
        $toPage = $lastPage < ($page + $delta) ? $lastPage : ($page + $delta);

        if ($lastPage > 1) {
            for ($curPage = $fromPage; $curPage <= $toPage; $curPage++) {
                $_vars = ['page' => $curPage];
                if ($curPage != $toPage) {
                    $Tpl->add(array_merge(['glue', 'page:loop'], $block));
                }
                if (PAGE == $curPage) {
                    $_vars['pageCurAttr'] = $curAttr;
                } else {
                    $Tpl->add(array_merge(['link#front', 'page:loop'], $block), [
                        'url' => acmsLink($Q + [
                            'page' => $curPage,
                        ]),
                    ]);
                    $Tpl->add(array_merge(['link#rear', 'page:loop'], $block));
                }
                $Tpl->add(array_merge(['page:loop'], $block), $_vars);
            }
        }

        if ($toPage != $lastPage) {
            $vars += [
                'lastPageUrl' => acmsLink($Q + [
                    'page' => $lastPage,
                ]),
                'lastPage' => $lastPage,
            ];
        }

        if (1 < $fromPage) {
            $vars += [
                'firstPageUrl' => acmsLink($Q + [
                    'page' => 1,
                ]),
                'firstPage' => 1,
            ];
        }

        if (1 < $page) {
            $Tpl->add(array_merge(['backLink'], $block), [
                'url' => acmsLink($Q + [
                    'page' => ($page > 2) ? $page - 1 : false,
                ]),
                'backNum' => $limit,
                'backPage' => $page - 1,
            ]);
        }
        if ($page != $lastPage) {
            $forwardNum = $amount - ($from + $limit);
            if ($limit < $forwardNum) {
                $forwardNum = $limit;
            }
            $Tpl->add(array_merge(['forwardLink'], $block), [
                'url' => acmsLink($Q + [
                    'page' => $page + 1,
                ]),
                'forwardNum' => $forwardNum,
                'forwardPage' => $page + 1,
            ]);
        }

        if ($page - $delta > 2) {
            $Tpl->add(array_merge(['omitBeforePage'], $block));
        }
        if ($lastPage - $page - $delta > 1) {
            $Tpl->add(array_merge(['omitAfterPage'], $block));
        }

        return $vars;
    }

    /**
     * フルテキストのEagerLoading
     * ToDo: deplicated mehod Ver. 3.2
     *
     * @deprecated
     * @param int[] $entryIds
     * @return array<int<1, max>, \Acms\Services\Unit\UnitCollection>
     */
    public function eagerLoadFullText($entryIds)
    {
        return $this->eagerLoadFullTextTrait($entryIds);
    }

    /**
     * フルテキストの組み立て
     *
     * @param array $vars
     * @param int $eid
     * @param array<int<1, max>, \Acms\Services\Unit\UnitCollection> $eagerLoadingData
     *
     * @return array
     */
    public function buildSummaryFulltext($vars, $eid, $eagerLoadingData)
    {
        if (isset($eagerLoadingData[$eid])) {
            /** @var \Acms\Services\Unit\Rendering\Front $unitRenderingService */
            $unitRenderingService = Application::make('unit-rendering-front');
            $textData = $unitRenderingService->renderSummaryText($eagerLoadingData[$eid]);
            $this->formatMultiLangUnitDataTrait($textData, $vars, 'summary');
        }
        return $vars;
    }

    /**
     * タグのEagerLoading
     * ToDo: deplicated mehod Ver. 3.2
     *
     * @param int[] $eidArray
     * @return array
     */
    public function eagerLoadTag($eidArray)
    {
        return $this->eagerLoadTagTrait($eidArray);
    }

    /**
     * 関連記事のEagerLoading
     * ToDo: deplicated mehod Ver. 3.2
     *
     * @param $eidArray array
     * @return array
     */
    public function eagerLoadRelatedEntry($eidArray)
    {
        return $this->eagerLoadRelatedEntryTrait($eidArray);
    }

    /**
     * タグの組み立て
     *
     * @param \Acms\Services\View\Contracts\ViewInterface $tpl
     * @param int $eid
     * @param array $eagerLoadingData
     * @param string[] $blocks
     *
     * @return void
     */
    public function buildTag($tpl, $eid, $eagerLoadingData, $blocks = [])
    {
        if (isset($eagerLoadingData[$eid]) && is_array($eagerLoadingData[$eid])) {
            $length = count($eagerLoadingData[$eid]);
            foreach ($eagerLoadingData[$eid] as $i => $tag) {
                if ($length > ($i + 1)) {
                    $tpl->add(array_merge(['tagGlue', 'tag:loop'], $blocks));
                }
                $tpl->add(array_merge(['tag:loop'], $blocks), [
                    'name' => $tag['tag_name'],
                    'url' => acmsLink([
                        'bid' => $tag['tag_blog_id'],
                        'tag' => $tag['tag_name'],
                    ]),
                ]);
            }
        }
    }

    /**
     * メインイメージのEagerLoading
     * ToDo: deplicated mehod Ver. 3.2
     *
     * @param $entries
     * @param $target ?'unit'|'field'
     * @param $fieldName ?string
     * @return ($target is 'field' ? array{
     *   unit: array<string, \Acms\Services\Unit\Contracts\Model>,
     *   media: array<int, array>,
     *   fieldMainImage: array<int, array>
     * } : array{
     *   unit: array<string, \Acms\Services\Unit\Contracts\Model>,
     *   media: array<int, array>,
     * })
     */
    function eagerLoadMainImage($entries, $target = 'unit', $fieldName = '')
    {
        return $this->eagerLoadMainImageTrait($entries, $target, $fieldName);
    }

    /**
     * 画像の組み立て
     *
     * @param \Acms\Services\View\Contracts\ViewInterface $Tpl
     * @param int $entryId
     * @param string $pimageId
     * @param array $config
     * @param array{unit: array<string, \Acms\Services\Unit\Contracts\Model>, media: array<int, array<string, mixed>>, fieldMainImage?: array<int, array<string, mixed>>} $eagerLoadingData
     *
     * @return array
     */
    public function buildImage($Tpl, $entryId, $pimageId, $config, $eagerLoadingData)
    {
        $pathAry = [];
        $vars = [];
        $squareSize = config('image_size_square');
        $unitType = 'image';
        $alt = '';
        $caption = '';
        $unit = null;
        $fieldImage = null;

        if (isset($eagerLoadingData['fieldMainImage'][$entryId])) {
            $media = $eagerLoadingData['fieldMainImage'][$entryId];
            $pathAry[] = $media['media_path'];
            $fieldImage = $media;
            $unitType = 'media';
        }
        if (empty($pathAry) && $pimageId && isset($eagerLoadingData['unit'][$pimageId])) {
            $unit = $eagerLoadingData['unit'][$pimageId];
            if ($unit instanceof \Acms\Services\Unit\Contracts\EagerLoadingMedia) {
                $unit->setEagerLoadedMedia($eagerLoadingData['media']);
            }
            $unitType = $unit::getUnitType();
            if ($unit instanceof \Acms\Services\Unit\Contracts\ImageUnit) {
                $pathAry = $unit->getPaths();
                $alt = $unit->getAlts();
                $caption = $unit->getCaptions();
            }
        } else {
            $path = null;
        }
        if (empty($pathAry) || ($unit !== null && $unit->isHidden())) {
            $Tpl->add('noimage', [
                'noImgX' => $config['imageX'],
                'noImgY' => $config['imageY'],
            ]);
            return [
                'x' => $config['imageX'],
                'y' => $config['imageY'],
            ];
        }
        foreach ($pathAry as $i => $path) {
            if ($i === 0) {
                $fx = '';
            } else {
                $fx = $i + 1;
            }

            $vars['focalX' . $fx] = 0;
            $vars['focalY' . $fx] = 0;
            $mediaSize = null;
            $query = '';

            if ($fieldImage) {
                $query = Media::cacheBusting($fieldImage['media_update_date']);
                $focalPoint = $fieldImage['media_field_5'];
                $mediaSize = $fieldImage['media_image_size'];
                if (strpos($focalPoint, ',') !== false) {
                    list($focalX, $focalY) = explode(',', $focalPoint);
                    if ($focalX && $focalY) {
                        $vars['focalX' . $fx] = (((float) $focalX / 50) - 1);
                        $vars['focalY' . $fx] = ((((float) $focalY / 50) - 1) * -1);
                    }
                }
            }
            if (!$mediaSize && $unit instanceof \Acms\Services\Unit\Models\Media) {
                $mediaId = $unit->getMediaIds()[$i];
                if (isset($unit->getEagerLoadedMedia()[$mediaId])) {
                    if ($media = $unit->getEagerLoadedMedia()[$mediaId]) {
                        $query = Media::cacheBusting($media['media_update_date']);
                        $focalPoint = $media['media_field_5'];
                        $mediaSize = $media['media_image_size'];
                        if (strpos($focalPoint, ',') !== false) {
                            list($focalX, $focalY) = explode(',', $focalPoint);
                            if ($focalX && $focalY) {
                                $vars['focalX' . $fx] = (((float) $focalX / 50) - 1);
                                $vars['focalY' . $fx] = ((((float) $focalY / 50) - 1) * -1);
                            }
                        }
                    }
                } else {
                    continue;
                }
            }
            $storageDir = $unitType === 'image' ? ARCHIVES_DIR : MEDIA_LIBRARY_DIR;
            $filename = $path;
            $path = $storageDir . $path;

            $x = 0;
            $y = 0;
            if ($mediaSize) {
                list($tempX, $tempY) = explode('x', $mediaSize);
                $x = intval(trim($tempX));
                $y = intval(trim($tempY));
            } elseif (PublicStorage::isReadable($path)) {
                list($x, $y) = PublicStorage::getImageSize($path);
            }
            if ($x > 0 && $y > 0) {
                if (max($config['imageX'], $config['imageY']) > max($x, $y)) {
                    if ($_path = preg_replace('@(.*?)([^/]+)$@', '$1large-$2', $path)) {
                        if ($xy = PublicStorage::getImageSize($_path)) {
                            $path = $_path;
                            $x = $xy[0];
                            $y = $xy[1];
                        }
                    }
                }
                $vars += [
                    'path' . $fx => Media::urlencode($path) . $query,
                ];
                if ('on' == $config['imageTrim']) {
                    if ($x > $config['imageX'] and $y > $config['imageY']) {
                        if (($x / $config['imageX']) < ($y / $config['imageY'])) {
                            $imgX = $config['imageX'];
                            if ($config['imageX'] > 0 && ($x / $config['imageX']) > 0) {
                                $imgY = round($y / ($x / $config['imageX']));
                            } else {
                                $imgY = 0;
                            }
                        } else {
                            $imgY = $config['imageY'];
                            if ($config['imageY'] > 0 && ($y / $config['imageY']) > 0) {
                                $imgX = round($x / ($y / $config['imageY']));
                            } else {
                                $imgX = 0;
                            }
                        }
                    } else {
                        if ($x < $config['imageX']) {
                            $imgX = $config['imageX'];
                            if ($config['imageX'] > 0 && $x > 0) {
                                $imgY = round($y * ($config['imageX'] / $x));
                            } else {
                                $imgY = 0;
                            }
                        } elseif ($y < $config['imageY']) {
                            $imgY = $config['imageY'];
                            if ($config['imageY'] > 0 && $y > 0) {
                                $imgX = round($x * ($config['imageY'] / $y));
                            } else {
                                $imgX = 0;
                            }
                        } else {
                            if (($config['imageX'] - $x) > ($config['imageY'] - $y)) {
                                $imgX = $config['imageX'];
                                if ($config['imageX'] > 0 && $x > 0) {
                                    $imgY = round($y * ($config['imageX'] / $x));
                                } else {
                                    $imgY = 0;
                                }
                            } else {
                                $imgY = $config['imageY'];
                                if ($config['imageY'] > 0 && $y > 0) {
                                    $imgX = round($x * ($config['imageY'] / $y));
                                } else {
                                    $imgX = 0;
                                }
                            }
                        }
                    }
                    $config['imageCenter'] = 'on';
                } else {
                    if ($x > $config['imageX']) {
                        if ($y > $config['imageY']) {
                            if (($x - $config['imageX']) < ($y - $config['imageY'])) {
                                $imgY = $config['imageY'];
                                if ($config['imageY'] > 0 && ($y / $config['imageY']) > 0) {
                                    $imgX = round($x / ($y / $config['imageY']));
                                } else {
                                    $imgX = 0;
                                }
                            } else {
                                $imgX = $config['imageX'];
                                if ($config['imageX'] > 0 && ($x / $config['imageX']) > 0) {
                                    $imgY = round($y / ($x / $config['imageX']));
                                } else {
                                    $imgY = 0;
                                }
                            }
                        } else {
                            $imgX = $config['imageX'];
                            $imgY = round($y / ($x / $config['imageX']));
                        }
                    } elseif ($y > $config['imageY']) {
                        $imgY = $config['imageY'];
                        $imgX = round($x / ($y / $config['imageY']));
                    } else {
                        if ('on' == $config['imageZoom']) {
                            if (($config['imageX'] - $x) > ($config['imageY'] - $y)) {
                                $imgY = $config['imageY'];
                                $imgX = round($x * ($config['imageY'] / $y));
                            } else {
                                $imgX = $config['imageX'];
                                $imgY = round($y * ($config['imageX'] / $x));
                            }
                        } else {
                            $imgX = $x;
                            $imgY = $y;
                        }
                    }
                }
                //-------
                // align
                if ('on' == $config['imageCenter']) {
                    if ($imgX > $config['imageX']) {
                        $left = round((-1 * ($imgX - $config['imageX'])) / 2);
                    } else {
                        $left = round(($config['imageX'] - $imgX) / 2);
                    }
                    if ($imgY > $config['imageY']) {
                        $top = round((-1 * ($imgY - $config['imageY'])) / 2);
                    } else {
                        $top = round(($config['imageY'] - $imgY) / 2);
                    }
                } else {
                    $left = 0;
                    $top = 0;
                }

                $vars += [
                    'imgX' . $fx  => $imgX,
                    'imgY' . $fx  => $imgY,
                    'left' . $fx  => $left,
                    'top' . $fx   => $top,
                    'alt' . $fx   => $alt,
                    'caption' . $fx => $caption,
                    'utid' . $fx => $pimageId,
                ];
                //------
                // tiny
                $tiny = $storageDir . preg_replace('@(.*?)([^/]+)$@', '$1tiny-$2', $filename);
                if ($mediaSize) {
                } elseif ($xy = PublicStorage::getImageSize($tiny)) {
                    $vars += [
                        'tinyPath' . $fx => $tiny . $query,
                        'tinyX' . $fx => $xy[0],
                        'tinyY' . $fx => $xy[1],
                    ];
                }
                //--------
                // square
                $square = $storageDir . preg_replace('@(.*?)([^/]+)$@', '$1square-$2', $filename);
                if (PublicStorage::isFile($square)) {
                    $vars += [
                        'squarePath' . $fx => $square . $query,
                        'squareX' . $fx => $squareSize,
                        'squareY' . $fx => $squareSize,
                    ];
                }
                //--------
                // large
                $large = $storageDir . preg_replace('@(.*?)([^/]+)$@', '$1large-$2', $filename);
                if ($mediaSize) {
                } elseif ($xy = PublicStorage::getImageSize($large)) {
                    $vars += [
                        'largePath' . $fx => $large . $query,
                        'largeX' . $fx => $xy[0],
                        'largeY' . $fx => $xy[1],
                    ];
                }
            } else {
                $Tpl->add('noimage', [
                    'noImgX' => $config['imageX'],
                    'noImgY' => $config['imageY'],
                ]);
            }
            $vars += [
                'x' . $fx => $config['imageX'],
                'y' . $fx => $config['imageY'],
            ];
        }
        return $vars;
    }

    /**
     * 関連記事を組み立て
     *
     * @param \Acms\Services\View\Contracts\ViewInterface $Tpl
     * @param int $eid
     * @param array<int, array<string, array<array>>> $eagerLoadingData
     * @param string[]|string $block
     */
    public function buildRelatedEntriesList($Tpl, $eid, $eagerLoadingData, $block = [])
    {
        $block = !empty($block) ? (is_array($block) ? $block : [$block]) : [];

        if (!isset($eagerLoadingData[$eid])) {
            $Tpl->add($block);
            return;
        }
        $eagerLoading = $eagerLoadingData[$eid];

        foreach ($eagerLoading as $type => $data) {
            $typeBlock = 'relatedEntry.' . $type;
            $loopBlock = array_merge([$typeBlock . ':loop', $typeBlock], $block);
            foreach ($data as $entry) {
                $field = $entry['field'];
                $vars = [
                    'bid' => $entry['bid'],
                    'cid' => $entry['cid'],
                    'uid' => $entry['uid'],
                    'eid' => $entry['eid'],
                    'title' => $entry['title'],
                    'url' => $entry['url'],
                    'categoryName' => ACMS_RAM::categoryName($entry['cid']),
                ];
                if ($field && method_exists($field, 'listFields')) {
                    $vars += $this->buildField($field, $Tpl, $loopBlock);
                }
                $Tpl->add($loopBlock, $vars);
            }
            $Tpl->add(array_merge([$typeBlock], $block));
        }
    }

    /**
     * 関連記事の組み立て
     *
     * @param \Acms\Services\View\Contracts\ViewInterface $Tpl
     * @param int[] $eids
     * @param string[]|string $block
     * @param string $start
     * @param string $end
     * @param string $relatedBlock
     * @param string|null $thumbnailField
     *
     * @return void
     */
    public function buildRelatedEntries($Tpl, $eids, $block, $start, $end, $relatedBlock = 'related:loop', $thumbnailField = '')
    {
        $block = !empty($block) ? (is_array($block) ? $block : [$block]) : [];
        $loopblock = array_merge([$relatedBlock], $block);

        $DB = DB::singleton(dsn());
        $SQL = SQL::newSelect('entry');
        $SQL->addWhereIn('entry_id', $eids);
        ACMS_Filter::entrySpan($SQL, $start, $end);
        ACMS_Filter::entrySession($SQL);
        $SQL->setFieldOrder('entry_id', $eids);
        $all = $DB->query($SQL->get(dsn()), 'all');

        $thumbnailType = $thumbnailField ? 'field' : 'unit';
        $mainImages = $this->eagerLoadMainImage($all, $thumbnailType, $thumbnailField);
        $eagerLoadField = eagerLoadField($eids, 'eid');
        $config = [
            'imageX' => 100,
            'imageY' => 100,
            'imageTrim' => 'off',
            'imageCenter' => 'off',
            'imageZoom' => 'off',
        ];

        foreach ($all as $i => $row) {
            if ($i > 0) {
                $Tpl->add(array_merge(['related:glue'], $loopblock));
            }
            $bid = intval($row['entry_blog_id']);
            $cid    = intval($row['entry_category_id']);
            $eid    = intval($row['entry_id']);
            $vars   = [
                'related.eid'           => $eid,
                'related.bid'           => $bid,
                'related.cid'           => $cid,
                'related.categoryName'  => ACMS_RAM::categoryName($cid),
                'related.permalink' => acmsLink([
                    'bid' => $bid,
                    'cid' => $cid,
                    'eid' => $eid,
                ], false),
            ];
            $images = $this->buildImage($Tpl, $eid, $row['entry_primary_image'] ?? '', $config, $mainImages);
            foreach ($images as $key => $val) {
                $vars['related.' . $key] = $val;
            }
            $title = addPrefixEntryTitle(
                $row['entry_title'],
                $row['entry_status'],
                $row['entry_start_datetime'],
                $row['entry_end_datetime'],
                $row['entry_approval']
            );
            $vars['related.title'] = $title;
            $link = $row['entry_link'];
            $url = acmsLink([
                'bid' => $bid,
                'cid' => $cid,
                'eid' => $eid,
            ]);
            if ($link != '#') {
                $vars['related.url'] = !empty($link) ? $link : $url;
            }
            if (isset($eagerLoadField[$eid])) {
                $vars += $this->buildField($eagerLoadField[$eid], $Tpl, array_merge(['relatedAdminField', $relatedBlock], $block));
            }
            $Tpl->add($loopblock, $vars);
        }
    }

    /**
     * サマリーの組み立て
     *
     * @param \Acms\Services\View\Contracts\ViewInterface $Tpl
     * @param array $row
     * @param int $count
     * @param int $gluePoint
     * @param array $config
     * @param array $extraVars
     * @param int $page
     * @param array $eagerLoadingData
     *
     * @return void
     */
    function buildSummary($Tpl, $row, $count, $gluePoint, $config, $extraVars = [], $page = 1, $eagerLoadingData = [])
    {
        if ($row && isset($row['entry_id'])) {
            if (!IS_LICENSED) {
                $row['entry_title'] = '[test]' . $row['entry_title'];
            }

            $bid = intval($row['entry_blog_id']);
            $uid = intval($row['entry_user_id']);
            $cid = intval($row['entry_category_id']);
            $eid = intval($row['entry_id']);
            $clid = strval($row['entry_primary_image']);
            $sort = intval($row['entry_sort']);
            $csort = intval($row['entry_category_sort']);
            $usort = intval($row['entry_user_sort']);

            $ecd = $row['entry_code'];
            $link = $row['entry_link'];
            $status = $row['entry_status'];
            $permalink = acmsLink([
                'bid' => $bid,
                'cid' => $cid,
                'eid' => $eid,
            ], false);
            $url = acmsLink([
                'bid' => $bid,
                'cid' => $cid,
                'eid' => $eid,
            ]);
            $title = addPrefixEntryTitle(
                $row['entry_title'],
                $status,
                $row['entry_start_datetime'],
                $row['entry_end_datetime'],
                $row['entry_approval']
            );

            if ($count % 2 == 0) {
                $oddOrEven = 'even';
            } else {
                $oddOrEven = 'odd';
            }

            $blogName = '';
            $blogCode = '';
            $blogUrl = '';

            $categoryName = '';
            $categoryCode = '';
            $categoryUrl = '';

            $vars = [
                'permalink' => $permalink,
                'title' => $title,
                'eid' => $eid,
                'ecd' => $ecd,
                'uid' => $uid,
                'bid' => $bid,
                'sort' => $sort,
                'csort' => $csort,
                'usort' => $usort,
                'iNum' => $count,
                'sNum' => (($page - 1) * $config['limit']) + $count,
                'oddOrEven' => $oddOrEven,
                'status' => $status,
                'geo_distance' => isset($row['distance']) ? $row['distance'] : '',
                'geo_zoom' => isset($row['geo_zoom']) ? $row['geo_zoom'] : '',
                'geo_lat' => isset($row['latitude']) ? $row['latitude'] : '',
                'geo_lng' => isset($row['longitude']) ? $row['longitude'] : '',
                'entry:loop.class' => isset($config['loop_class']) ? $config['loop_class'] : '',
            ];

            if ($link != '#') {
                $vars += [
                    'url' => !empty($link) ? $link : $url,
                ];
                $Tpl->add(['url#rear', 'entry:loop']);
            }

            if (!isset($config['blogInfoOn']) or $config['blogInfoOn'] === 'on') {
                $blogName = $row['blog_name'];
                $blogCode = $row['blog_code'];
                $blogUrl = acmsLink([
                    'bid' => $bid,
                ]);
                $vars += [
                    'blogName' => $blogName,
                    'blogCode' => $blogCode,
                    'blogUrl' => $blogUrl,
                ];
            }

            if (!empty($cid) and (!isset($config['categoryInfoOn']) or $config['categoryInfoOn'] === 'on')) {
                $categoryName = $row['category_name'];
                $categoryCode = $row['category_code'];
                $categoryUrl = acmsLink([
                    'bid' => $bid,
                    'cid' => $cid,
                ]);

                $vars += [
                    'categoryName' => $categoryName,
                    'categoryCode' => $categoryCode,
                    'categoryUrl' => $categoryUrl,
                    'cid' => $cid,
                ];
            }

            //----------------------
            // attachment vars
            foreach ($extraVars as $key => $val) {
                $vars += [$key => $row[$val]];
            }

            //-----
            // new
            if (requestTime() <= strtotime($row['entry_datetime']) + intval($config['newtime'])) {
                $Tpl->add(['new', 'entry:loop']);
            }

            //--------------
            // members only
            if (isset($row['entry_members_only']) && $row['entry_members_only'] === 'on') {
                $Tpl->add(['membersOnly', 'entry:loop']);
            }

            //-------
            // image
            if (isset($eagerLoadingData['mainImage'])) {
                $vars += $this->buildImage($Tpl, $eid, $clid, $config, $eagerLoadingData['mainImage']);
            }

            //---------------
            // related entry
            if (isset($eagerLoadingData['relatedEntry'])) {
                $this->buildRelatedEntriesList($Tpl, $eid, $eagerLoadingData['relatedEntry'], ['relatedEntry', 'entry:loop']);
            } else {
                $Tpl->add(['relatedEntry', 'entry:loop']);
            }

            //----------
            // fulltext
            if (isset($eagerLoadingData['fullText'])) {
                $vars = $this->buildSummaryFulltext($vars, $eid, $eagerLoadingData['fullText']);
                if (
                    isset($vars['summary']) &&
                    isset($config['fulltextWidth']) &&
                    !empty($config['fulltextWidth'])
                ) {
                    $width = intval($config['fulltextWidth']);
                    $marker = isset($config['fulltextMarker']) ? $config['fulltextMarker'] : '';
                    $vars['summary'] = mb_strimwidth($vars['summary'], 0, $width, $marker, 'UTF-8');
                }
            }

            //------
            // date
            if (!isset($config['dateOn']) || $config['dateOn'] !== 'off') {
                $vars += $this->buildDate($row['entry_datetime'], $Tpl, 'entry:loop');
            }
            if (!isset($config['detailDateOn']) or $config['detailDateOn'] === 'on') {
                $vars += $this->buildDate($row['entry_updated_datetime'], $Tpl, 'entry:loop', 'udate#');
                $vars += $this->buildDate($row['entry_posted_datetime'], $Tpl, 'entry:loop', 'pdate#');
                $vars += $this->buildDate($row['entry_start_datetime'], $Tpl, 'entry:loop', 'sdate#');
                $vars += $this->buildDate($row['entry_end_datetime'], $Tpl, 'entry:loop', 'edate#');
            }

            //-------------
            // entry field
            if (isset($eagerLoadingData['entryField'][$eid])) {
                $vars += $this->buildField($eagerLoadingData['entryField'][$eid], $Tpl, ['entry:loop']);
            }

            //-------------
            // user field
            if (isset($config['userInfoOn']) && $config['userInfoOn'] === 'on') {
                if ($config['userFieldOn'] === 'on' && isset($eagerLoadingData['userField'][$uid])) {
                    $Field = $eagerLoadingData['userField'][$uid];
                } else {
                    $Field = new Field();
                }
                $Field->setField('fieldUserName', ACMS_RAM::userName($uid));
                $Field->setField('fieldUserCode', ACMS_RAM::userCode($uid));
                $Field->setField('fieldUserStatus', ACMS_RAM::userStatus($uid));
                $Field->setField('fieldUserMail', ACMS_RAM::userMail($uid));
                $Field->setField('fieldUserMailMobile', ACMS_RAM::userMailMobile($uid));
                $Field->setField('fieldUserUrl', ACMS_RAM::userUrl($uid));
                $Field->setField('fieldUserIcon', loadUserIcon($uid));
                if ($large = loadUserLargeIcon($uid)) {
                    $Field->setField('fieldUserLargeIcon', $large);
                }
                if ($orig = loadUserOriginalIcon($uid)) {
                    $Field->setField('fieldUserOrigIcon', $orig);
                }
                $Tpl->add(['userField', 'entry:loop'], $this->buildField($Field, $Tpl, ['userField', 'entry:loop']));
            }

            //------------
            // blog field
            if (isset($config['blogInfoOn']) && $config['blogInfoOn'] === 'on') {
                if ($config['blogFieldOn'] === 'on' && isset($eagerLoadingData['blogField'][$bid])) {
                    $Field = $eagerLoadingData['blogField'][$bid];
                } else {
                    $Field = new Field();
                }
                $Field->setField('fieldBlogName', $blogName);
                $Field->setField('fieldBlogCode', $blogCode);
                $Field->setField('fieldBlogUrl', $blogUrl);
                $Tpl->add(['blogField', 'entry:loop'], $this->buildField($Field, $Tpl, ['blogField', 'entry:loop']));
            }

            //----------------
            // category field
            if (!empty($cid) && isset($config['categoryInfoOn']) && $config['categoryInfoOn'] === 'on') {
                if ($config['categoryFieldOn'] === 'on' && isset($eagerLoadingData['categoryField'][$cid])) {
                    $Field = $eagerLoadingData['categoryField'][$cid];
                } else {
                    $Field = new Field();
                }
                $Field->setField('fieldCategoryName', $categoryName);
                $Field->setField('fieldCategoryCode', $categoryCode);
                $Field->setField('fieldCategoryUrl', $categoryUrl);
                $Field->setField('fieldCategoryId', $cid);
                $Tpl->add(['categoryField', 'entry:loop'], $this->buildField($Field, $Tpl, ['categoryField', 'entry:loop']));
            }

            //--------------
            // sub category
            if (isset($eagerLoadingData['subCategory'])) {
                if (isset($eagerLoadingData['subCategory'][$eid])) {
                    $subCategories = $eagerLoadingData['subCategory'][$eid];
                    foreach ($subCategories as $i => $category) {
                        if ($i !== count($subCategories) - 1) {
                            $Tpl->add(['glue', 'sub_category:loop', 'entry:loop']);
                        }
                        $Tpl->add(['sub_category:loop', 'entry:loop'], [
                            'name' => $category['category_name'],
                            'code' => $category['category_code'],
                            'url' => acmsLink([
                                'cid' => $category['category_id'],
                            ]),
                        ]);
                    }
                }
            }

            //-----
            // tag
            if (isset($eagerLoadingData['tag'])) {
                $this->buildTag($Tpl, $eid, $eagerLoadingData['tag'], ['entry:loop']);
            }

            //------
            // glue
            $addend = ($count === $gluePoint);
            if (!$addend) {
                $Tpl->add(array_merge(['glue', 'entry:loop']));
            }
            $Tpl->add('entry:loop', $vars);

            if ($addend) {
                $Tpl->add('unit:loop');
            } elseif ($count != 0 && $config['unit'] > 0) {
                if (!($count % $config['unit'])) {
                    $Tpl->add('unit:loop');
                }
            }
        }
    }

    /**
     * 編集ページの動的フォームユニットを組み立て
     *
     * @param array $data
     * @param \Acms\Services\View\Contracts\ViewInterface $Tpl
     * @param string[]|string $rootBlock
     *
     * @return bool
     */
    public function buildAdminFormColumn($data, $Tpl, $rootBlock = [])
    {
        $rootBlock = empty($rootBlock) ? [] :
        (is_array($rootBlock) ? $rootBlock : [$rootBlock])
        ;
        $id = $data['id'];
        $type = $data['type'];

        //----------------
        // text, textarea
        if (in_array($type, ['text', 'textarea'], true)) {
            //-------------------------
            // radio, select, checkbox
        } elseif (in_array($type, ['radio', 'select', 'checkbox'], true)) {
            if (isset($data['values']) && $values = acmsDangerUnserialize($data['values'])) {
                if (is_array($values)) {
                    foreach ($values as $val) {
                        if (!empty($val)) {
                            $Tpl->add(array_merge([$type . '_value:loop'], $rootBlock), [
                                'value' => $val,
                                'id' => $id,
                            ]);
                        }
                    }
                }
            }
        } else {
            return false;
        }

        $data = array_merge([
            'type' => '',
            'label' => '',
            'caption' => '',
            'validator' => [],
            'validator-value' => [],
            'validator-message' => [],
        ], $data);

        //---------------
        // label caption
        $Tpl->add(array_merge([$type], $rootBlock), [
            'label' => $data['label'],
            'caption' => $data['caption'],
            'id' => $id,
        ]);
        //------------
        // validator
        if (isset($data['validatorSet'])) {
            $validatorSet = acmsDangerUnserialize($data['validatorSet']);
            if (is_array($validatorSet)) {
                $validator = $validatorSet['validator'] ?? [];
                $validator_val = $validatorSet['validator-value'] ?? [];
                $validator_mess = $validatorSet['validator-message'] ?? [];
            } else {
                $validator = [];
                $validator_val = [];
                $validator_mess = [];
            }
        } else {
            $validator = $data['validator'];
            $validator_val = $data['validator-value'];
            $validator_mess = $data['validator-message'];
        }

        foreach ($validator as $j => $val) {
            if (!empty($val)) {
                $Tpl->add(array_merge(['option:loop'], $rootBlock), [
                    'validator' => $val,
                    'validator:selected#' . $val => config('attr_selected'),
                    'validator-value' => $validator_val[$j],
                    'validator-message' => $validator_mess[$j],
                    'id' => $id,
                    'unique' => 'data-' . ($j + 1),
                ]);
            }
        }
        return true;
    }

    /**
     * レイアウトモジュールの1モジュールを組み立て
     *
     * @param string $moduleName
     * @param string $moduleID
     * @param string $moduleTpl
     * @param bool $onlyLayout
     *
     * @return string
     */
    public function spreadModule($moduleName, $moduleID, $moduleTpl, $onlyLayout = false)
    {
        $acmsTplEngine = Application::make('template.acms.engine');
        $acmsTplResolver = Application::make('template.acms.resolver');
        assert($acmsTplEngine instanceof \Acms\Services\Template\Acms\Engine);
        assert($acmsTplResolver instanceof \Acms\Services\Template\Acms\Resolver);

        $tpl = 'include/module/template/' . $moduleName . '.html'; // 標準テンプレート
        if ($moduleTpl) {
            $tpl = 'include/module/template/' . $moduleName . '/' . $moduleTpl; // 選択テンプレート
        } else {
            $modShort = preg_replace('/' . config('module_identifier_duplicate_suffix') . '.*/', '', $moduleID);
            $def = 'include/module/template/' . $moduleName . '/' . $modShort . '.html'; // 固定テンプレート
            if (findTemplate($def)) {
                $tpl = $def;
            }
        }

        if ($path = findTemplate($tpl)) {
            $rootTpl = $acmsTplResolver->resolvePath($tpl, config('theme'), '/');
            $mTpl = $acmsTplEngine->spreadTemplate('<!--#include file="' . $rootTpl . '" vars=""-->', config('theme'), BID, false, false);
            if ($mTpl) {
                $mTpl = setGlobalVars($mTpl);
                $opt = ' id="' . $moduleID . '"';

                if (
                    LAYOUT_EDIT &&
                    !LAYOUT_PREVIEW &&
                    preg_match('/<!--[\t 　]*BEGIN[\t 　]+layout\#display[^>]*?-->/i', $mTpl)
                ) {
                    \ACMS_GET_Layout::formatBlock($mTpl, 'dummy');
                } else {
                    \ACMS_GET_Layout::formatBlock($mTpl, 'display');
                    $post = Field_Validation::singleton('post');
                    if ($onlyLayout) {
                        if ($moduleName === 'Entry_Body') {
                            $mTpl = (string)preg_replace('/<!--[\t 　]*BEGIN_MODULE[\t 　]+Entry_Body[^>]*?-->/', '<!-- BEGIN_MODULE Entry_Body' . $opt . ' -->', $mTpl);
                            $mTpl = $acmsTplEngine->render($mTpl, $post);
                        } else {
                            $mTpl = (string)preg_replace(
                                '/<!--[\t 　]*(BEGIN|END)_MODULE+[\t 　]+([^\t 　]+)([^>]*?)[\t 　]*-->/',
                                '',
                                $mTpl
                            );
                            $mTpl = '<!-- BEGIN_MODULE ' . $moduleName . $opt . ' -->' . $mTpl . '<!-- END_MODULE ' . $moduleName . ' -->';
                        }
                    } elseif ($moduleName === 'Entry_Body') {
                        $mTpl = (string)preg_replace('/<!--[\t 　]*BEGIN_MODULE[\t 　]+Entry_Body[^>]*?-->/', '<!-- BEGIN_MODULE Entry_Body' . $opt . ' -->', $mTpl);
                        $mTpl = $acmsTplEngine->render($mTpl, $post);
                    } else {
                        $mTpl = (string)preg_replace(
                            '/<!--[\t 　]*(BEGIN|END)_MODULE+[\t 　]+([^\t 　]+)([^>]*?)[\t 　]*-->/',
                            '',
                            $mTpl
                        );
                        $sql = SQL::newSelect('module');
                        $sql->addWhereOpr('module_identifier', $moduleID);
                        $sql->addWhereOpr('module_name', $moduleName);

                        $eagerLoadModule[$moduleName][$moduleID] = DB::query($sql->get(dsn()), 'row');

                        $mTpl = preg_replace(['/@begin_acms_vars@[^@]+@end_acms_vars@/'], [''], $mTpl) ?? ''; // 未解決の @begin_acms_vars@xxx@end_acms_vars@ を削除
                        $mTpl = boot($moduleName, $mTpl, $opt, $post, Field::singleton('config'), $eagerLoadModule);
                        $mTpl = verbatim($mTpl, false); // @verbatim ブロックが残ってしまう問題対策
                    }
                }
                if (isDebugMode()) {
                    $mTpl = $acmsTplEngine->includeCommentBegin($path) . $mTpl . $acmsTplEngine->includeCommentEnd($path);
                }
                return $mTpl;
            }
        }
        return '';
    }
}
