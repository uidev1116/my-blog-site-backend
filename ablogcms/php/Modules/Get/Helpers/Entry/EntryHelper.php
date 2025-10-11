<?php

namespace Acms\Modules\Get\Helpers\Entry;

use Acms\Modules\Get\Helpers\BaseHelper;
use Acms\Services\Facades\Database;
use Acms\Services\Facades\Media;
use Acms\Services\Facades\Common;
use Acms\Services\Facades\PublicStorage;
use Acms\Services\Facades\Template as TemplateHelper;
use ACMS_Corrector;
use ACMS_Filter;
use ACMS_RAM;
use SQL;
use SQL_Select;
use Field_Search;
use Field;
use Template;

class EntryHelper extends BaseHelper
{
    use \Acms\Traits\Utilities\FieldTrait;
    use \Acms\Traits\Utilities\EagerLoadingTrait;
    use \Acms\Traits\Utilities\PaginationTrait;

    /**
     * エントリーへのアクセス権限を確認する
     *
     * @param int $eid
     * @return boolean
     */
    public function canAccessEntry(int $eid): bool
    {
        if (!$eid) {
            return false;
        }
        $entry = ACMS_RAM::entry($eid);
        if (!$entry) {
            return false;
        }
        if ($entry['entry_status'] === 'trash') {
            return false;
        }
        $isPublicStatus =
            $entry['entry_status'] === 'open' &&
            $entry['entry_approval'] !== 'pre_approval' &&
            requestTime() >= strtotime($entry['entry_start_datetime']) &&
            requestTime() <= strtotime($entry['entry_end_datetime']);

        if (timemachineMode()) {
            // タイムマシンモード時
            return $isPublicStatus;
        } elseif (sessionWithCompilation()) {
            // 編集者以上の場合
            return true;
        } elseif (sessionWithContribution()) {
            // 投稿者の場合
            if ($isPublicStatus || SUID === (int) $entry['entry_user_id']) { // @phpstan-ignore-line
                return true;
            }
        }
        // 読者 or ログアウト状態
        return $isPublicStatus;
    }

    /**
     * ルート変数の取得
     *
     * @return array
     */
    public function getRootVars(): array
    {
        $blogName = ACMS_RAM::blogName($this->bid);
        $vars = [
            'indexUrl' => acmsLink([
                'bid' => $this->bid,
                'cid' => is_int($this->cid) ? $this->cid : null,
            ]),
            'indexBlogName' => $blogName,
            'blogName' => $blogName,
            'blogCode' => ACMS_RAM::blogCode($this->bid),
            'blogUrl' => acmsLink([
                'bid' => $this->bid,
            ]),
        ];
        if ($this->cid) {
            $categoryName = ACMS_RAM::categoryName($this->cid);
            $vars['indexCategoryName'] = $categoryName;
            $vars['categoryName'] = $categoryName;
            $vars['categoryCode'] = ACMS_RAM::categoryCode($this->cid);
            $vars['categoryUrl'] = acmsLink([
                'bid' => $this->bid,
                'cid' => $this->cid,
            ]);
        }
        return $vars;
    }

    /**
     * Eager loads for the given entries.
     * @param array $entries
     * @param array{
     *   includeMainImage?: bool,
     *   mainImageTarget?: 'unit'|'field',
     *   mainImageFieldName?: string,
     *   includeFulltext?: bool,
     *   includeTags?: bool,
     *   includeEntryFields?: bool,
     *   includeUserFields?: bool,
     *   includeBlogFields?: bool,
     *   includeCategoryFields?: bool,
     *   includeSubCategories?: bool,
     *   includeRelatedEntries?: bool} $config
     * @param int|null $rvid
     * @return array
     */
    public function eagerLoad(array $entries, array $config, ?int $rvid = null): array
    {
        $eagerLoadingData = [];
        $entryIds = array_reduce($entries, function ($carry, $entry) {
            $carry[] = intval($entry['entry_id']);
            return $carry;
        }, []);

        // メイン画像のEagerLoading
        if ($config['includeMainImage'] ?? false) {
            $target = $config['mainImageTarget'] ?? 'unit';
            $fieldName = $config['mainImageFieldName'] ?? '';
            $fieldName = $fieldName ? $fieldName : config('main_image_field_name', '');
            $eagerLoadingData['mainImage'] = $this->eagerLoadMainImageTrait($entries, $target, $fieldName, $rvid);
        }
        // フルテキストのEagerLoading
        if ($config['includeFulltext'] ?? false) {
            $eagerLoadingData['fullText'] = $this->eagerLoadFullTextTrait($entryIds);
        }
        // タグのEagerLoading
        if ($config['includeTags'] ?? false) {
            $eagerLoadingData['tag'] = $this->eagerLoadTagTrait($entryIds, $rvid);
        }
        // エントリーフィールドのEagerLoading
        if ($config['includeEntryFields'] ?? false) {
            $eagerLoadingData['entryField'] = $this->eagerLoadFieldTrait($entryIds, 'eid', $rvid);
        }
        // ユーザーフィールドのEagerLoading
        if ($config['includeUserFields'] ?? false) {
            $userIds = array_reduce($entries, function ($carry, $entry) {
                $carry[] = intval($entry['entry_user_id']);
                return $carry;
            }, []);
            $eagerLoadingData['userField'] = $this->eagerLoadFieldTrait($userIds, 'uid');
        }
        // ブログフィールドのEagerLoading
        if ($config['includeBlogFields'] ?? false) {
            $blogIds = array_reduce($entries, function ($carry, $entry) {
                $carry[] = intval($entry['entry_blog_id']);
                return $carry;
            }, []);
            $eagerLoadingData['blogField'] = $this->eagerLoadFieldTrait($blogIds, 'bid');
        }
        // カテゴリーフィールドのEagerLoading
        if ($config['includeCategoryFields'] ?? false) {
            $categoryIds = array_reduce($entries, function ($carry, $entry) {
                if ($entry['entry_category_id']) {
                    $carry[] = intval($entry['entry_category_id']);
                }
                return $carry;
            }, []);
            $eagerLoadingData['categoryField'] = $this->eagerLoadFieldTrait($categoryIds, 'cid');
        }
        // サブカテゴリーのEagerLoading
        if ($config['includeSubCategories'] ?? false) {
            $eagerLoadingData['subCategory'] = $this->eagerLoadSubCategoriesTrait($entryIds, $rvid);
        }
        // 関連エントリーのEagerLoading
        if ($config['includeRelatedEntries'] ?? false) {
            $eagerLoadingData['relatedEntry'] = $this->eagerLoadRelatedEntryTrait($entryIds);
        }
        return $eagerLoadingData;
    }

    /**
     * 関連エントリーの取得
     * @param int $eid
     * @param null|string $type
     * @return array
     */
    public function getRelationalEntryIds(int $eid, ?string $type): array
    {
        if (!($this->config['relatedEntryMode'] ?? false)) {
            return [];
        }
        if (!$eid) {
            return [];
        }
        if ($type) {
            return loadRelatedEntries($eid, null, $type);
        }
        return loadRelatedEntries($eid);
    }

    /**
     * エントリ情報の組み立て
     *
     * @param array $row
     * @param array{
     *  includeBlog: ?bool,
     *  includeUser: ?bool,
     *  includeCategory: ?bool,
     *  fulltextWidth: ?int,
     *  fulltextMarker: ?string,
     *  newItemPeriod: ?int} $config
     * @param array $extraVars
     * @param array $eagerLoadingData
     * @return array
     */
    public function buildEntry(array $row, array $config, array $extraVars = [], array $eagerLoadingData = []): array
    {
        $data = [];
        if (!$row || !isset($row['entry_id'])) {
            return $data;
        }
        $bid = isset($row['entry_blog_id']) ? (int) $row['entry_blog_id'] : BID;
        $uid = isset($row['entry_user_id']) ? (int) $row['entry_user_id'] : null;
        $cid = isset($row['entry_category_id']) ? (int) $row['entry_category_id'] : null;
        $eid = (int) $row['entry_id'];
        $clid = isset($row['entry_primary_image']) ? (string) $row['entry_primary_image'] : null;
        // 基本情報
        $data = [
            'status' => $row['entry_status'],
            'title' => $this->buildEntryTitle($row),
            'url' => $this->buildEntryLink($row['entry_link'], $eid, $bid, $cid),
            'permalink' => acmsLink([
                'bid' => $bid,
                'cid' => $cid,
                'eid' => $eid,
            ], false),
            'ecd' => $row['entry_code'],
            'eid' => $eid,
            'uid' => $uid,
            'bid' => $bid,
            'cid' => $cid,
            'sort' => (int) $row['entry_sort'],
            'csort' => (int) $row['entry_category_sort'],
            'usort' => (int) $row['entry_user_sort'],
        ];
        // new
        $data['isNew'] = (requestTime() <= strtotime($row['entry_datetime']) + intval($config['newItemPeriod'] ?? 0));
        // members only
        $data['isMembersOnly'] = ($row['entry_members_only'] ?? 'off') === 'on';
        // image
        if (isset($eagerLoadingData['mainImage'])) {
            $data['mainImage'] = $this->buildMainImage($clid, $eid, $eagerLoadingData['mainImage']);
        }
        // fulltext
        $data['summary'] = isset($eagerLoadingData['fullText']) ? $this->buildFulltext($eid, ($config['fulltextWidth'] ?? 200), $config['fulltextMarker'] ?? '...', $eagerLoadingData['fullText']) : null;
        // date
        $data['datetime'] = $row['entry_datetime'];
        $data['updatedAt'] = $row['entry_updated_datetime'];
        $data['createdAt'] = $row['entry_posted_datetime'];
        $data['publishStartAt'] = $row['entry_start_datetime'];
        $data['publishEndAt'] = $row['entry_end_datetime'];
        // tag
        $data['tags'] = isset($eagerLoadingData['tag'][$eid]) ? $this->buildTag($eagerLoadingData['tag'][$eid]) : null;
        // geo
        $data['geo'] = $this->buildGeo($row);
        // entry field
        $data['fields'] = isset($eagerLoadingData['entryField'][$eid]) ? $this->buildFieldTrait($eagerLoadingData['entryField'][$eid]) : null;
        // blog info
        $data['blog'] = null;
        if ($config['includeBlog'] ?? false) {
            $data['blog'] = $this->buildBlog($bid, $row);
            $data['blog']['fields'] = isset($eagerLoadingData['blogField'][$bid]) ? $this->buildFieldTrait($eagerLoadingData['blogField'][$bid]) : null;
        }
        // user info
        $data['user'] = null;
        if ($uid && ($config['includeUser'] ?? false)) {
            $data['user'] = $this->buildUser($uid);
            $data['user']['fields'] = isset($eagerLoadingData['userField'][$uid]) ? $this->buildFieldTrait($eagerLoadingData['userField'][$uid]) : null;
        }
        // category info
        $data['category'] = null;
        if ($cid && ($config['includeCategory'] ?? false)) {
            $data['category'] = [];
            $data['category']['items'] = $this->buildCategory($cid, $bid);
            $data['category']['fields'] = isset($eagerLoadingData['categoryField'][$cid]) ? $this->buildFieldTrait($eagerLoadingData['categoryField'][$cid]) : null;
        }
        // sub category
        $data['subCategories'] = isset($eagerLoadingData['subCategory'][$eid]) ? $this->buildSubCategory($eagerLoadingData['subCategory'][$eid]) : null;
        // attachment vars
        foreach ($extraVars as $key => $val) {
            $data += [$key => $row[$val]];
        }
        // related entry
        $data['relatedEntries'] = isset($eagerLoadingData['relatedEntry'][$eid]) ? $this->buildRelatedEntries($eagerLoadingData['relatedEntry'][$eid]) : null;
        return $data;
    }

    /**
     * ユニットリストの組み立て
     *
     * @param array $unit
     * @param array $config
     * @param array $eagerLoadingData
     * @param array $mediaEagerLoading
     * @param \Acms\Services\Unit\Repository $unitRepository
     * @param string $tplString
     * @return array|null
     */
    public function buildUnitList(array $unit, array $config, array $eagerLoadingData, array $mediaEagerLoading, \Acms\Services\Unit\Repository $unitRepository, string $tplString): ?array
    {
        $data = [
            'unit' => [],
        ];
        $model = $unitRepository->loadModel($unit);
        if (!$model) {
            return null;
        }
        $tpl = null;
        if ($model instanceof \Acms\Services\Unit\Contracts\EagerLoadingMedia) {
            $model->setEagerLoadedMedia($mediaEagerLoading);
        }
        if ($model instanceof \Acms\Services\Unit\Contracts\UnitListModule) {
            $tpl = new Template($tplString, new ACMS_Corrector());
            $data += $model->renderUnitListModule($tpl);
            $tpl->add('unit:loop');
        }
        if ($tpl && ($model instanceof \Acms\Services\Unit\Models\Custom)) {
            $unitHtml = buildIF($tpl->get());
            $unitHtml = removeComments($unitHtml);
            $unitHtml = removeBlank($unitHtml);
            if (isApiBuildOrV2Module()) {
                $unitHtml = Common::convertRelativeUrlsToAbsolute($unitHtml, BASE_URL);
            }
            $unit['column_field_6'] = $unitHtml;
        }
        foreach ($unit as $key => $val) {
            if (str_starts_with($key, 'column_')) {
                $data['unit'][preg_replace('/column_/', '', $key)] = $val;
            }
        }
        $eid = (int) $unit['entry_id'];
        $cid = (int) $unit['entry_category_id'];
        $bid = (int) $unit['entry_blog_id'];
        $uid = (int) $unit['entry_user_id'];

        // entry field
        $data['entry'] = null;
        if ($config['includeEntry'] ?? false) {
            $data['entry'] = [
                'eid' => $eid,
                'title' => $unit['entry_title'],
                'code' => $unit['entry_code'],
                'status' => $unit['entry_status'],
                'link' => $unit['entry_link'],
                'datetime' => $unit['entry_datetime'],
                'url' => acmsLink([
                    'bid' => $bid,
                    'cid' => $cid,
                    'eid' => $eid,
                ], false),
            ];
            $data['entry']['fields'] = isset($eagerLoadingData['entryField'][$eid]) ? $this->buildFieldTrait($eagerLoadingData['entryField'][$eid]) : null;
        }
        // blog info
        $data['blog'] = null;
        if ($config['includeBlog'] ?? false) {
            $data['blog'] = $this->buildBlog($bid, $unit);
            $data['blog']['fields'] = isset($eagerLoadingData['blogField'][$bid]) ? $this->buildFieldTrait($eagerLoadingData['blogField'][$bid]) : null;
        }
        // user info
        $data['user'] = null;
        if ($config['includeUser'] ?? false) {
            $data['user'] = $this->buildUser($uid);
            $data['user']['fields'] = isset($eagerLoadingData['userField'][$uid]) ? $this->buildFieldTrait($eagerLoadingData['userField'][$uid]) : null;
        }
        // category info
        $data['category'] = null;
        if ($cid && ($config['includeCategory'] ?? false)) {
            $data['category'] = [];
            $data['category']['items'] = $this->buildCategory($cid, $bid);
            $data['category']['fields'] = isset($eagerLoadingData['categoryField'][$cid]) ? $this->buildFieldTrait($eagerLoadingData['categoryField'][$cid]) : null;
        }
        return $data;
    }

    /**
     * エントリータイトルを組み立て
     * @param array $row
     * @return string
     */
    public function buildEntryTitle(array $row): string
    {
        $title = addPrefixEntryTitle(
            $row['entry_title'],
            $row['entry_status'],
            $row['entry_start_datetime'],
            $row['entry_end_datetime'],
            $row['entry_approval']
        );
        return IS_LICENSED ? $title : '[test]' . $title;
    }

    /**
     * NotFound時のテンプレート組み立て
     *
     * @return void
     */
    public function notFoundStatus(): void
    {
        httpStatusCode('404 Not Found');
    }

    /**
     * シンプルページャーを組み立て
     *
     * @param int $page
     * @param bool $nextPage
     * @return array|null
     */
    public function buildSimplePager(int $page, bool $nextPage): ?array
    {
        if ($this->config['simplePagerEnabled'] ?? false) {
            return $this->buildPagerTrait($page, $nextPage);
        }
        return null;
    }

    /**
     * ページネーションを組み立て
     *
     * @param SQL_Select $amount
     * @return null|array
     */
    public function buildPagination(SQL_Select $amount): ?array
    {
        $order = $this->config['order'][0] ?? null;
        if (($this->config['paginationEnabled'] ?? false) && $order !== 'random') {
            $q = $amount->get(dsn());
            $total = (int) Database::query($q, 'one') - (int) $this->config['offset'];
            return $this->buildPaginationTrait(
                $this->page,
                $total,
                $this->config['limit'],
                $this->config['paginationDelta']
            );
        }
        return null;
    }

    /**
     * 前後リンクを組み立て
     *
     * @param int $eid
     * @param string $order
     * @param bool $ignoreCategory
     * @param Field_Search | Field $field
     * @return null|array
     */
    public function buildSerialNavi(int $eid, string $order, bool $ignoreCategory = false, $field = null): ?array
    {
        $sql = SQL::newSelect('entry');
        $sql->addLeftJoin('category', 'category_id', 'entry_category_id');
        $sql->setLimit(1);
        $sql->addWhereOpr('entry_link', ''); // リンク先URLが設定されているエントリーはリンクに含まないようにする
        $sql->addWhereOpr('entry_blog_id', $this->bid);
        if ($ignoreCategory === false && $this->cid) {
            ACMS_Filter::categoryTree($sql, $this->cid, $this->categoryAxis);
        }
        ACMS_Filter::entrySession($sql);
        if ($this->start && $this->end) {
            ACMS_Filter::entrySpan($sql, $this->start, $this->end);
        }
        if ($this->tags) {
            ACMS_Filter::entryTag($sql, $this->tags);
        }
        if ($this->keyword) {
            ACMS_Filter::entryKeyword($sql, $this->keyword);
        }
        if ($this->Field && !$this->Field->isNull()) {
            ACMS_Filter::entryField($sql, $this->Field);
        }
        $sql->addWhereOpr('entry_indexing', 'on');
        $aryOrder1 = explode('-', $order);
        $sortFieldName = $aryOrder1[0];
        $sortOrder = isset($aryOrder1[1]) ? $aryOrder1[1] : 'desc';

        if ('random' !== $sortFieldName) {
            switch ($sortFieldName) {
                case 'datetime':
                    $field = 'entry_datetime';
                    $value = ACMS_RAM::entryDatetime($eid);
                    break;
                case 'updated_datetime':
                    $field = 'entry_updated_datetime';
                    $value = ACMS_RAM::entryUpdatedDatetime($eid);
                    break;
                case 'posted_datetime':
                    $field = 'entry_posted_datetime';
                    $value = ACMS_RAM::entryPostedDatetime($eid);
                    break;
                case 'code':
                    $field = 'entry_code';
                    $value = ACMS_RAM::entryCode($eid);
                    break;
                case 'sort':
                    if ($this->uid) {
                        $field = 'entry_user_sort';
                        $value = ACMS_RAM::entryUserSort($eid);
                    } elseif ($this->cid) {
                        $field = 'entry_category_sort';
                        $value = ACMS_RAM::entryCategorySort($eid);
                    } else {
                        $field = 'entry_sort';
                        $value = ACMS_RAM::entrySort($eid);
                    }
                    break;
                case 'field':
                case 'intfield':
                    $entryField = loadEntryField($eid);
                    $fieldList = $entryField->listFields();
                    $entryFieldKey = $fieldList[0] ?? null;
                    if ($entryFieldKey) {
                        $field = $sortFieldName === 'field' ? 'strfield_sort' : 'intfield_sort';
                        $value = $entryField->get($entryFieldKey);
                    } else {
                        $field = 'entry_id';
                        $value = $eid;
                    }
                    break;
                case 'id':
                default:
                    $field = 'entry_id';
                    $value = $eid;
            }
            return [
                'prevLink' => $this->buildPrevLink($sortFieldName, $sortOrder, $sql, $field, (string) $value),
                'nextLink' => $this->buildNextLink($sortFieldName, $sortOrder, $sql, $field, (string) $value),
            ];
        }
        return null;
    }

    /**
     * メイン画像を組み立て
     *
     * @param string|null $pimageId
     * @param array $eagerLoadingData
     * @return null|array
     */
    public function buildMainImage(?string $pimageId, int $eid, array $eagerLoadingData): ?array
    {
        // カスタムフィールドのメイン画像
        if (isset($eagerLoadingData['fieldMainImage'][$eid]) && $eagerLoadingData['fieldMainImageKey']) {
            $key = $eagerLoadingData['fieldMainImageKey'];
            $media = $eagerLoadingData['fieldMainImage'][$eid];
            $mid = $media['media_id'];
            $mediaList = [];
            $mediaList[$mid] = $media;
            $mediaField = new Field();
            $mediaField->set("{$key}@media", $media['media_id']);
            Media::injectMediaField($mediaField, $mediaList, [$key]);
            $field = $this->buildFieldTrait($mediaField);
            $data = $field[$key]['value'] ?? null;
            return $data ? $this->buildMainImageVars($data) : null;
        }

        // ユニットのメイン画像
        if ($pimageId !== null && $pimageId !== '' && isset($eagerLoadingData['unit'][$pimageId])) {
            /** @var \Acms\Services\Unit\Contracts\Model $unit */
            $unit = $eagerLoadingData['unit'][$pimageId];
            if ($unit->isHidden()) {
                return null;
            }

            // メディアユニットの場合
            if ($unit instanceof \Acms\Services\Unit\Models\Media) {
                $mediaId = $unit->getMediaIds()[0] ?? null;
                if (!$mediaId) {
                    return null;
                }
                $key = 'hogekey';
                $mediaField = new Field();
                $mediaField->set("{$key}@media", $mediaId);
                Media::injectMediaField($mediaField, $eagerLoadingData['media'], [$key]);
                $field = $this->buildFieldTrait($mediaField);
                $data = $field[$key]['value'] ?? null;
                return $data ? $this->buildMainImageVars($data) : null;
            }

            // 画像ユニットの場合
            if ($unit instanceof \Acms\Services\Unit\Contracts\ImageUnit) {
                $path = $unit->getPaths()[0] ?? null;
                if (!$path) {
                    return null;
                }
                $alt = $unit->getAlts()[0] ?? null;
                $caption = $unit->getCaptions()[0] ?? null;
                [$width, $height] = is_array($size = PublicStorage::getImageSize(ARCHIVES_DIR . $path)) ? $size : [null, null];
                $data = [
                    'type' => 'image',
                    'name' => PublicStorage::mbBasename($path),
                    'extension' => pathinfo($path, PATHINFO_EXTENSION),
                    'path' => Common::resolveUrl($path, ARCHIVES_DIR),
                    'thumbnail' => Common::resolveUrl('/' . DIR_OFFSET . ARCHIVES_DIR . otherSizeImagePath($path, 'tiny')),
                    'width' => $width,
                    'height' => $height,
                    'ratio' => $width && $height ? round($width / $height, 2) : null,
                    'alt' => $alt,
                    'caption' => $caption,
                ];
                return $this->buildMainImageVars($data);
            }
        }
        return null;
    }

    /**
     * リンクを組み立て
     * @param string $link
     * @param int $eid
     * @param int $bid
     * @param ?int $cid
     * @return null|string
     */
    public function buildEntryLink(string $link, int $eid, int $bid, ?int $cid): ?string
    {
        $url = acmsLink([
            'bid' => $bid,
            'cid' => $cid,
            'eid' => $eid,
        ]);
        if (!$url) {
            $url = null;
        }
        if ($link !== '#') {
            return $link ? $link : $url;
        }
        return null;
    }

    /**
     * 位置情報を組み立て
     * @param array $row
     * @return null|array
     */
    public function buildGeo(array $row): ?array
    {
        if (isset($row['latitude']) && isset($row['longitude'])) {
            return [
                'lat' => $row['latitude'],
                'lng' => $row['longitude'],
                'zoom' => $row['geo_zoom'] ?? null,
                'distance' => $row['distance'] ?? null,
            ];
        }
        return null;
    }

    /**
     * ブログ情報を組み立て
     * @param int $bid
     * @param array $row
     * @return array
     */
    public function buildBlog(int $bid, array $row): array
    {
        return [
            'bid' => $bid,
            'name' => $row['blog_name'],
            'code' => $row['blog_code'],
            'url' => acmsLink([
                'bid' => $bid,
            ]),
        ];
    }

    /**
     * ユーザー情報を組み立て
     * @param int $uid
     * @return array
     */
    public function buildUser(int $uid): array
    {
        $icon = loadUserIcon($uid);
        $largeIcon = loadUserLargeIcon($uid);
        $originalIcon = loadUserOriginalIcon($uid);

        return [
            'uid' => $uid,
            'name' => ACMS_RAM::userName($uid),
            'code' => ACMS_RAM::userCode($uid),
            'status' => ACMS_RAM::userStatus($uid),
            'email' => ACMS_RAM::userMail($uid),
            'emailMobile' => ACMS_RAM::userMailMobile($uid),
            'url' => ACMS_RAM::userUrl($uid),
            'icon' => $icon ? Common::resolveUrl($icon, ARCHIVES_DIR) : null,
            'largeIcon' => $largeIcon ? Common::resolveUrl($largeIcon, ARCHIVES_DIR) : null,
            'originalIcon' => $originalIcon ? Common::resolveUrl($originalIcon, ARCHIVES_DIR) : null,
        ];
    }

    /**
     * カテゴリ情報を組み立て
     * @param int $cid
     * @param int $bid
     * @return array
     */
    public function buildCategory(int $cid, int $bid): array
    {
        $sql = SQL::newSelect('category');
        $sql->addSelect('category_id');
        $sql->addSelect('category_name');
        $sql->addSelect('category_code');
        $sql->addWhereOpr('category_indexing', 'on');
        ACMS_Filter::categoryTree($sql, $cid, 'ancestor-or-self');
        $sql->addOrder('category_left', 'DESC');
        $q = $sql->get(dsn());
        $items = Database::query($q, 'all');

        switch ($this->config['categoryOrder'] ?? '') {
            case 'child_order':
                break;
            case 'parent_order':
                $items = array_reverse($items);
                break;
            case 'current_order':
                $items = [array_shift($items)];
                break;
            default:
                break;
        }
        return array_reduce($items, function (array $carry, array $item) use ($bid) {
            $cid = (int) $item['category_id'];
            $carry[] = [
                'cid' => $cid,
                'name' => $item['category_name'],
                'code' => $item['category_code'],
                'url' => acmsLink([
                    'bid' => $bid,
                    'cid' => $cid,
                ]),
            ];
            return $carry;
        }, []);
    }

    /**
     * 関連エントリを組み立て
     * @param array $relatedEntries
     * @return array
     */
    public function buildRelatedEntries(array $relatedEntries): array
    {
        $buitData = [];
        foreach ($relatedEntries as $type => $entries) {
            $buitData[$type] = array_reduce($entries, function ($carry, $entry) {
                $field = $entry['field'];
                $data = [
                    'bid' => $entry['bid'],
                    'cid' => $entry['cid'],
                    'uid' => $entry['uid'],
                    'eid' => $entry['eid'],
                    'title' => $entry['title'],
                    'url' => $entry['url'],
                    'categoryName' => ACMS_RAM::categoryName($entry['cid']),
                ];
                if ($field instanceof Field) {
                    $data['fields'] = $this->buildFieldTrait($field);
                }
                $carry[] = $data;
                return $carry;
            }, []);
        }
        return $buitData;
    }

    /**
     * フルテキストの組み立て
     * @param int $eid
     * @param int $width
     * @param string $marker
     * @param array $eagerLoadedFulltext
     * @return null|string
     */
    public function buildFulltext(int $eid, int $width, string $marker, array $eagerLoadedFulltext): ?string
    {
        $fulltextVars = [];
        $fulltextVars = TemplateHelper::buildSummaryFulltext($fulltextVars, $eid, $eagerLoadedFulltext);
        $summary = $fulltextVars['summary'] ?? null;
        if ($summary && $width) {
            return mb_strimwidth($summary, 0, $width, $marker, 'UTF-8');
        }
        return $summary;
    }

    /**
     * サブカテゴリーを組み立て
     * @param array $subCategories
     * @return array
     */
    public function buildSubCategory(array $subCategories): array
    {
        return array_reduce($subCategories, function ($carry, $category) {
            $carry[] = [
                'cid' => (int) $category['category_id'],
                'name' => $category['category_name'],
                'code' => $category['category_code'],
                'url' => acmsLink([
                    'cid' => $category['category_id'],
                ]),
            ];
            return $carry;
        }, []);
    }

    /**
     * タグを組み立て
     * @param array $tags
     * @return array
     */
    public function buildTag(array $tags): array
    {
        return array_reduce($tags, function ($carry, $tag) {
            $carry[] = [
                'name' => $tag['tag_name'],
                'url' => acmsLink([
                    'bid' => $tag['tag_blog_id'],
                    'tag' => $tag['tag_name'],
                ]),
            ];
            return $carry;
        }, []);
    }

    /**
     * メイン画像の変数を組み立て
     * @param array $data
     * @return array
     */
    public function buildMainImageVars(array $data): array
    {
        return [
            'type' => $data['type'] ?? null,
            'media' => $data['media'] ?? null,
            'name' => $data['name'] ?? null,
            'extension' => $data['extension'] ?? null,
            'fileSize' => $data['fileSize'] ?? null,
            'imageSize' => $data['imageSize'] ?? null,
            'path' => $data['path'] ?? null,
            'thumbnail' => $data['thumbnail'] ?? null,
            'width' => $data['width'] ?? null,
            'height' => $data['height'] ?? null,
            'alt' => $data['alt'] ?? null,
            'caption' => $data['caption'] ?? null,
            'focalX' => $data['focalX'] ?? null,
            'focalY' => $data['focalY'] ?? null,
            'ratio' => $data['ratio'] ?? null,
        ];
    }

    /**
     * メイン画像をIDから組み立て
     *
     * @param array $entry
     * @return null|array
     */
    public function buildMainImageFromEntryData(array $entry): ?array
    {
        if (!isset($entry['entry_id'])) {
            return null;
        }
        $mainImageData = $this->eagerLoad([$entry], [
            'includeMainImage' => true,
            'mainImageTarget' => $this->config['mainImageTarget'] ?? 'field',
            'mainImageFieldName' => $this->config['mainImageFieldName'] ?? '',
        ]);
        $mainImageVars = null;
        if (isset($mainImageData['mainImage'])) {
            $eid = (int) $entry['entry_id'];
            $clid = isset($entry['entry_primary_image']) ? (string) $entry['entry_primary_image'] : null;
            $mainImageVars = $this->buildMainImage($clid, $eid, $mainImageData['mainImage']);
        }
        return $mainImageVars;
    }

    /**
     * 一個前のリンクを組み立て
     *
     * @param string $sortFieldName
     * @param string $sortOrder
     * @param SQL_Select $baseSql
     * @param string $field
     * @param string $value
     * @return null|array
     */
    public function buildPrevLink(string $sortFieldName, string $sortOrder, SQL_Select $baseSql, string $field, string $value): ?array
    {
        if (!$this->eid) {
            return null;
        }
        $sql = new SQL_Select($baseSql);
        $where1 = SQL::newWhere();
        $where1->addWhereOpr($field, $value, '=');
        $where1->addWhereOpr('entry_id', $this->eid, '<');
        $where2 = SQL::newWhere();
        $where2->addWhere($where1);
        if ($sortOrder === 'desc') {
            $where2->addWhereOpr($field, $value, '<', 'OR');
        } else {
            $where2->addWhereOpr($field, $value, '>', 'OR');
        }
        $sql->addWhere($where2);
        if ($sortOrder === 'desc') {
            ACMS_Filter::entryOrder($sql, [$sortFieldName . '-desc', 'id-desc'], $this->uid, $this->cid);
        } else {
            ACMS_Filter::entryOrder($sql, [$sortFieldName . '-asc', 'id-asc'], $this->uid, $this->cid);
        }
        ACMS_Filter::entrySession($sql);
        $q = $sql->get(dsn());
        if ($row = Database::query($q, 'row')) {
            $eid = (int) $row['entry_id'];
            $mainImageVars = $this->buildMainImageFromEntryData($row);
            return [
                'name' => addPrefixEntryTitle(
                    $row['entry_title'],
                    $row['entry_status'],
                    $row['entry_start_datetime'],
                    $row['entry_end_datetime'],
                    $row['entry_approval']
                ),
                'url' => acmsLink([
                    '_inherit' => true,
                    'eid' => $eid,
                ]),
                'eid' => $eid,
                'mainImage' => $mainImageVars,
            ];
        }
        return null;
    }

    /**
     * 次のリンクを組み立て
     *
     * @param string $sortFieldName
     * @param string $sortOrder
     * @param SQL_Select $baseSql
     * @param string $field
     * @param string $value
     * @return null|array
     */
    public function buildNextLink(string $sortFieldName, string $sortOrder, SQL_Select $baseSql, string $field, string $value): ?array
    {
        $sql = new SQL_Select($baseSql);
        $where1 = SQL::newWhere();
        $where1->addWhereOpr($field, $value, '=');
        $where1->addWhereOpr('entry_id', $this->eid, '>');
        $where2 = SQL::newWhere();
        $where2->addWhere($where1);
        if ($sortOrder === 'desc') {
            $where2->addWhereOpr($field, $value, '>', 'OR');
        } else {
            $where2->addWhereOpr($field, $value, '<', 'OR');
        }
        $sql->addWhere($where2);
        if ($sortOrder === 'desc') {
            ACMS_Filter::entryOrder($sql, [$sortFieldName . '-asc', 'id-asc'], $this->uid, $this->cid);
        } else {
            ACMS_Filter::entryOrder($sql, [$sortFieldName . '-desc', 'id-desc'], $this->uid, $this->cid);
        }
        ACMS_Filter::entrySession($sql);
        $q = $sql->get(dsn());
        if ($row = Database::query($q, 'row')) {
            $eid = (int) $row['entry_id'];
            $mainImageVars = $this->buildMainImageFromEntryData($row);
            return [
                'name' => addPrefixEntryTitle(
                    $row['entry_title'],
                    $row['entry_status'],
                    $row['entry_start_datetime'],
                    $row['entry_end_datetime'],
                    $row['entry_approval']
                ),
                'url' => acmsLink([
                    '_inherit' => true,
                    'eid' => $eid,
                ]),
                'eid' => $eid,
                'mainImage' => $mainImageVars,
            ];
        }
        return null;
    }
}
