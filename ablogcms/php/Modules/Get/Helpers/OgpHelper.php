<?php

namespace Acms\Modules\Get\Helpers;

use Acms\Modules\Get\Helpers\BaseHelper;
use Acms\Services\Facades\Media;
use Acms\Services\Facades\Application;
use Acms\Services\Facades\PublicStorage;
use Acms\Services\Facades\Common;
use Acms\Services\Facades\Template as TemplateHelper;
use Acms\Services\Facades\Database as DB;
use ACMS_Filter;
use ACMS_RAM;
use SQL;

class OgpHelper extends BaseHelper
{
    /**
     * 連結文字列
     *
     * @var string
     */
    protected $glue = ' | ';

    /**
     * 連結文字列を設定
     *
     * @param string $glue
     */
    public function setGlue(string $glue): void
    {
        $this->glue = $glue;
    }

    /**
     * タイプを取得
     *
     * @return string
     */
    public function getType()
    {
        if (RBID === BID && VIEW === 'top') { // @phpstan-ignore-line
            return 'website';
        }
        return 'article';
    }

    /**
     * og:title を取得します
     *
     * @return string
     */
    public function getTitle()
    {
        $titleConfig = $this->config->get('ogp_title_order', 'entry,page,tag,keyword,date,admin,404,category,blog,rootBlog');
        $title = '';
        if ($parts = preg_split(REGEXP_SEPARATER, $titleConfig, -1, PREG_SPLIT_NO_EMPTY)) {
            $parts = array_unique($parts);
            foreach ($parts as $part) {
                $method = 'get' . ucwords($part) . 'Title';
                if (is_callable([$this, $method])) {
                    if ($val = call_user_func([$this, $method])) { // @phpstan-ignore-line
                        $title .= ($val . $this->glue);
                    }
                }
            }
        }
        return rtrim($title, $this->glue);
    }

    /**
     * og:image を取得
     * 優先度: EntryField -> EntryMainImage -> CategoryField -> BlogField
     *
     * @return false|array{
     *   type: 'image' | 'media',
     *   width: int,
     *   height: int,
     *   path: string
     * }
     */
    public function getImage()
    {
        if ($image = $this->getEntryImage()) {
            return $image;
        }
        if ($image = $this->getCategoryImage()) {
            return $image;
        }
        if ($image = $this->getBlogImage(BID)) {
            return $image;
        }
        if ($image = $this->getBlogImage(RBID)) { // @phpstan-ignore-line
            return $image;
        }
        return false;
    }

    /**
     * og:description を取得
     * 優先度: EntryField -> EntrySummary -> CategoryField -> BlogField
     *
     * @return bool|string
     */
    public function getDescription()
    {
        $hide_summary = EID && $this->config->get('ogp_description_hide_summary') === 'true'; // @phpstan-ignore-line
        if ($description = $this->getEntryDescription($hide_summary)) {
            return $description;
        }
        if ($description = $this->getCategoryDescription()) {
            return $description;
        }
        if ($description = $this->getBlogDescription(BID)) {
            return $description;
        }
        if ($description = $this->getBlogDescription(RBID)) { // @phpstan-ignore-line
            return $description;
        }
        return false;
    }

    /**
     * keywords を取得
     * 優先度: EntryField -> CategoryField -> BlogField
     *
     * @return bool|string
     */
    public function getKeywords()
    {
        if ($keyword = $this->getEntryKeywords()) {
            return $keyword;
        }
        if ($keyword = $this->getCategoryKeywords()) {
            return $keyword;
        }
        if ($keyword = $this->getBlogKeywords(BID)) {
            return $keyword;
        }
        if ($keyword = $this->getBlogKeywords(RBID)) { // @phpstan-ignore-line
            return $keyword;
        }
        return false;
    }

    protected function getSize($str, $type = 'width')
    {
        if (preg_match('/[^x]+x[^x]+/', $str)) {
            list($x, $y) = explode('x', $str);
            if ($type === 'width') {
                return intval(trim($x));
            }
            return intval(trim($y));
        }
        return '';
    }

    /**
     * @return false|array{
     *   type: 'image' | 'media',
     *   width: int,
     *   height: int,
     *   path: string
     * }
     */
    protected function getEntryImage()
    {
        if (!EID) { // @phpstan-ignore-line
            return false;
        }
        $field_name = $this->config->get('ogp_image_entry_field_name', null); // @phpstan-ignore-line
        if (!empty($field_name)) {
            $field = loadEntryField(EID);
            if ($mediaId = $field->get($field_name . '@media')) {
                if ($media = Media::getMedia($mediaId)) {
                    if ($media['type'] === 'image' || $media['type'] === 'svg') {
                        return [
                            'type' => 'media',
                            'width' => $this->getSize($media['size'], 'width'),
                            'height' => $this->getSize($media['size'], 'height'),
                            'path' => $media['path'],
                        ];
                    }
                }
            }
            if ($image = $field->get($field_name . '@path')) {
                list($width, $height) = PublicStorage::getImageSize(ARCHIVES_DIR . $image);
                return [
                    'type' => 'image',
                    'width' => $width,
                    'height' => $height,
                    'path' => $image,
                ];
            }
        }
        if ($this->config->get('ogp_image_unit_image_not_use') === 'true') {
            return false;
        }
        if ($primary_img_id = ACMS_RAM::entryPrimaryImage(EID)) {
            if ($unit = ACMS_RAM::unit($primary_img_id)) {
                $unitRepository = Application::make('unit-repository');
                assert($unitRepository instanceof \Acms\Services\Unit\Repository);

                /** @var \Acms\Services\Unit\Contracts\Model $unitModel */
                $unitModel = $unitRepository->loadModel($unit);
                if (empty($unitModel)) {
                    return false;
                }
                if ($unitModel->isHidden()) {
                    return false;
                }
                if ($unitModel instanceof \Acms\Services\Unit\Contracts\EagerLoadingMedia) {
                    $collection = new \Acms\Services\Unit\UnitCollection([$unitModel]);
                    $unitModel->setEagerLoadedMedia(Media::mediaEagerLoadFromUnit($collection));
                }
                if ($unitModel instanceof \Acms\Services\Unit\Contracts\ImageUnit) {
                    $path = $unitModel->getPaths()[0] ?? '';

                    if ($unitModel instanceof \Acms\Services\Unit\Models\Media) {
                        $mediaId = $unitModel->getMediaIds()[0];
                        if (isset($unitModel->getEagerLoadedMedia()[$mediaId])) {
                            $media = $unitModel->getEagerLoadedMedia()[$mediaId];
                            if (in_array($media['media_type'], ['image', 'svg'], true)) {
                                return [
                                    'type' => $unitModel::getUnitType(),
                                    'width' => $this->getSize($media['media_image_size'], 'width'),
                                    'height' => $this->getSize($media['media_image_size'], 'height'),
                                    'path' => $path,
                                ];
                            }
                        }
                    } else {
                        [$width, $height] = PublicStorage::getImageSize(ARCHIVES_DIR . $path);
                        return [
                            'type' => $unitModel::getUnitType(),
                            'width' => $width,
                            'height' => $height,
                            'path' => $path,
                        ];
                    }
                }
            }
        }
        return false;
    }

    /**
     * @return false|array{
     *   type: 'image' | 'media',
     *   width: int,
     *   height: int,
     *   path: string
     * }
     */
    protected function getCategoryImage()
    {
        if (!CID) { // @phpstan-ignore-line
            return false;
        }
        $field_name = $this->config->get('ogp_image_category_field_name', null); // @phpstan-ignore-line
        if (!empty($field_name)) {
            $field = loadCategoryField(CID);
            if ($mediaId = $field->get($field_name . '@media')) {
                if ($media = Media::getMedia($mediaId)) {
                    if ($media['type'] === 'image' || $media['type'] === 'svg') {
                        return [
                            'type' => 'media',
                            'width' => $this->getSize($media['size'], 'width'),
                            'height' => $this->getSize($media['size'], 'height'),
                            'path' => $media['path'],
                        ];
                    }
                }
            }
            if ($image = $field->get($field_name . '@path')) {
                list($width, $height) = PublicStorage::getImageSize(ARCHIVES_DIR . $image);
                return [
                    'type' => 'image',
                    'width' => $width,
                    'height' => $height,
                    'path' => $image,
                ];
            }
        }
        return false;
    }

    /**
     * @param int $bid
     * @return false|array{
     *   type: 'image' | 'media',
     *   width: int,
     *   height: int,
     *   path: string
     * }
     */
    protected function getBlogImage($bid = BID)
    {
        if (!$bid) {
            return false;
        }
        $field_name = $this->config->get('ogp_image_blog_field_name', null);
        if ($field_name) {
            $field = loadBlogField($bid);
            if ($mediaId = $field->get($field_name . '@media')) {
                $mediaId = intval($mediaId);
                if ($media = Media::getMedia($mediaId)) {
                    if ($media['type'] === 'image' || $media['type'] === 'svg') {
                        return [
                            'type' => 'media',
                            'width' => $this->getSize($media['size'], 'width'),
                            'height' => $this->getSize($media['size'], 'height'),
                            'path' => $media['path'],
                        ];
                    }
                }
            }
            if ($image = $field->get($field_name . '@path')) {
                $size = PublicStorage::getImageSize(ARCHIVES_DIR . $image);
                return [
                    'type' => 'image',
                    'width' => $size[0] ?? 0,
                    'height' => $size[1] ?? 0,
                    'path' => $image,
                ];
            }
        }
        return false;
    }

    /**
     * @param bool $hide
     * @return bool|string
     */
    protected function getEntryDescription($hide = false)
    {
        if (!EID) { // @phpstan-ignore-line
            return false;
        }
        $field_name = $this->config->get('ogp_description_entry_field_name', null); // @phpstan-ignore-line
        if ($field_name) {
            $field = loadEntryField(EID);
            if ($description = $field->get($field_name)) {
                return $description;
            }
        }
        if ($hide) {
            return false;
        }
        $vars = [];
        if ($vars = TemplateHelper::buildSummaryFulltext($vars, EID, TemplateHelper::eagerLoadFullText([EID]))) {
            if (isset($vars['summary'])) {
                return $vars['summary'];
            }
        }
        return false;
    }

    /**
     * @return bool|string
     */
    protected function getCategoryDescription()
    {
        if (!CID) { // @phpstan-ignore-line
            return false;
        }
        $field_name = $this->config->get('ogp_description_category_field_name', null); // @phpstan-ignore-line
        if ($field_name) {
            $field = loadCategoryField(CID);
            if ($description = $field->get($field_name)) {
                return $description;
            }
        }
        return false;
    }

    /**
     * @param int $bid
     * @return bool|string
     */
    protected function getBlogDescription($bid = BID)
    {
        if (!$bid) {
            return false;
        }
        $field_name = $this->config->get('ogp_description_blog_field_name', null);
        if ($field_name) {
            $field = loadBlogField($bid);
            if ($description = $field->get($field_name)) {
                return $description;
            }
        }
        return false;
    }

    /**
     * @return bool|string
     */
    protected function getEntryKeywords()
    {
        if (!EID) { // @phpstan-ignore-line
            return false;
        }
        $field_name = $this->config->get('ogp_keywords_entry_field_name', null); // @phpstan-ignore-line
        if ($field_name) {
            $field = loadEntryField(EID);
            if ($keywords = $field->get($field_name)) {
                return $keywords;
            }
        }
        return false;
    }

    /**
     * @return bool|string
     */
    protected function getCategoryKeywords()
    {
        if (!CID) { // @phpstan-ignore-line
            return false;
        }
        $field_name = $this->config->get('ogp_keywords_category_field_name', null); // @phpstan-ignore-line
        if ($field_name) {
            $field = loadCategoryField(CID);
            if ($keywords = $field->get($field_name)) {
                return $keywords;
            }
        }
        return false;
    }

    /**
     * @param int $bid
     * @return bool|string
     */
    protected function getBlogKeywords($bid = BID)
    {
        if (!$bid) {
            return false;
        }
        $field_name = $this->config->get('ogp_keywords_blog_field_name', null);
        if ($field_name) {
            $field = loadBlogField($bid);
            if ($keywords = $field->get($field_name)) {
                return $keywords;
            }
        }
        return false;
    }

    /**
     * @return bool|string
     */
    protected function getEntryTitle()
    {
        if (!EID) { // @phpstan-ignore-line
            return false;
        }
        if ($this->config->get('ogp_title_entry_code_empty') === 'on' && ACMS_RAM::entryCode(EID) === '') { // @phpstan-ignore-line
            return false;
        }
        $field_name = $this->config->get('ogp_title_entry_field_name', null);
        if ($field_name) {
            $field = loadEntryField(EID);
            if ($title = $field->get($field_name)) {
                return $title;
            }
        }
        return ACMS_RAM::entryTitle(EID);
    }

    /**
     * @return bool|string
     */
    protected function getCategoryTitle()
    {
        if (!CID) { // @phpstan-ignore-line
            return false;
        }
        $field_name = $this->config->get('ogp_title_category_field_name', null); // @phpstan-ignore-line
        $level = $this->config->get('ogp_title_category_levels') === 'on';

        $sql = SQL::newSelect('category');
        ACMS_Filter::categoryStatus($sql);
        if ($level) {
            // 階層表示
            ACMS_Filter::categoryTree($sql, CID, 'self-ancestor');
            $sql->addOrder('category_right', 'asc');
        } else {
            // 一件表示
            $sql->addWhereOpr('category_id', CID);
        }
        if ($field_name) {
            $field = SQL::newSelect('field');
            $field->addSelect('field_cid');
            $field->addSelect('field_value');
            $field->addWhereOpr('field_key', $field_name);
            $sql->addLeftJoin($field, 'field_cid', 'category_id', 'field');
        }
        $all = DB::query($sql->get(dsn()), 'all');

        $title = [];
        foreach ($all as $category) {
            if (isset($category['field_value']) && $category['field_value']) {
                $title[] = $category['field_value'];
                continue;
            }
            $title[] = $category['category_name'];
        }
        return implode($this->glue, $title);
    }

    /**
     * @param int|null $bid
     * @return bool|string
     */
    protected function getBlogTitle($bid = BID)
    {
        if (!$bid) {
            return false;
        }
        $field_name = $this->config->get('ogp_title_blog_field_name', null);
        $level = $this->config->get('ogp_title_blog_levels') === 'on';

        $sql = SQL::newSelect('blog');
        ACMS_Filter::blogStatus($sql);
        if ($level) {
            // 階層表示
            ACMS_Filter::blogTree($sql, $bid, 'self-ancestor');
            $sql->addOrder('blog_right', 'asc');
        } else {
            // 一件表示
            $sql->addWhereOpr('blog_id', $bid);
        }
        if ($bid !== RBID) { // @phpstan-ignore-line
            $sql->addWhereOpr('blog_id', RBID, '<>');
        }
        if ($field_name) {
            $field = SQL::newSelect('field');
            $field->addSelect('field_bid');
            $field->addSelect('field_value');
            $field->addWhereOpr('field_key', $field_name);
            $sql->addLeftJoin($field, 'field_bid', 'blog_id', 'field');
        }
        $q = $sql->get(dsn());
        $all = DB::query($q, 'all');
        $title = [];
        foreach ($all as $blog) {
            if (isset($blog['field_value']) && $blog['field_value']) {
                $title[] = $blog['field_value'];
                continue;
            }
            $title[] = $blog['blog_name'];
        }
        return implode($this->glue, $title);
    }

    /**
     * @return bool|string
     */
    protected function getRootBlogTitle()
    {
        $root = loadAncestorBlog('root', 'id');
        if (!$root) {
            return false;
        }
        $root = (int) $root;
        if ($root === BID) {
            return false;
        }
        return $this->getBlogTitle(intval($root));
    }

    /**
     * @return bool|string
     */
    protected function getPageTitle()
    {
        if (!PAGE || PAGE < 2) {
            return false;
        }
        $suffix = $this->config->get('ogp_title_page_suffix', '');
        return str_replace('{page}', strval(PAGE), $suffix);
    }

    /**
     * @return bool|string
     */
    protected function getTagTitle()
    {
        if (!TAG) {
            return false;
        }
        $string = '';
        $glue = $this->config->get('ogp_title_tag_delimiter', '/');
        $tags = Common::getTagsFromString(TAG);
        foreach ($tags as $i => $tag) {
            if ($i > 0) {
                $string .= $glue;
            }
            $string .= $tag;
        }
        return $string;
    }

    /**
     * @return bool|null|string
     */
    protected function getKeywordTitle()
    {
        if (!KEYWORD) {
            return false;
        }
        return KEYWORD;
    }

    /**
     * @return bool|false|string
     */
    protected function getDateTitle()
    {
        if (!DATE) { // @phpstan-ignore-line
            return false;
        }
        if (preg_match('/^\d{4}(\/\d{2})?$/', DATE)) { // @phpstan-ignore-line
            return DATE;
        }
        $format = $this->config->get('ogp_title_date_format', 'Y-m-d');
        return date($format, strtotime(str_replace('/', '-', DATE)));
    }

    /**
     * @return bool|string
     */
    protected function getAdminTitle()
    {
        if (!ADMIN) {
            return false;
        }
        return $this->config->get('ogp_title_admin_label', 'Admin Page');
    }

    /**
     * @return bool|string
     */
    protected function get404Title()
    {
        if (defined('ROOT_TPL_NAME') && ROOT_TPL_NAME === '404') {
            return $this->config->get('ogp_title_404_label', '404 Not Found');
        }
        return false;
    }
}
