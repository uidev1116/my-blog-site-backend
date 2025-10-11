<?php

namespace Acms\Services\Export\Repositories;

use Acms\Services\Facades\Application;
use Acms\Services\Facades\Common;
use Acms\Services\Facades\Database;
use ACMS_Filter;
use ACMS_Corrector;
use ACMS_RAM;
use SQL_Select;
use SQL;
use Field;
use Template;

class EntryRepository
{
    use \Acms\Traits\Utilities\EagerLoadingTrait;

    /**
     * 一番大きいエントリーID
     *
     * @var int
     */
    private $maxEntryId = 0;

    public function __construct()
    {
        $this->maxEntryId = Database::query(SQL::nextval('entry_id', dsn()), 'seq');
    }


    /**
     * エントリー一覧を取得クエリを取得
     *
     * @param int $bid
     * @param bool $includeChildBlogs
     * @return SQL_Select
     */
    public function getEntriesQuery(int $bid, bool $includeChildBlogs): SQL_Select
    {
        $sql = $this->getIndexQuery($bid, $includeChildBlogs);
        $sql->setOrder('entry_id', 'ASC');

        return $sql;
    }

    public function getEntryCount(int $bid, bool $includeChildBlogs): int
    {
        $sql = $this->getIndexQuery($bid, $includeChildBlogs);
        $sql->setSelect('entry_id', 'amount', null, 'COUNT');

        return Database::query($sql->get(dsn()), 'one');
    }

    /**
     * ブログURLを取得
     *
     * @param int $eid
     * @return string
     */
    public function getEntryUrl(int $eid): string
    {
        return acmsLink([
            'bid' => ACMS_RAM::entryBlog($eid),
            'eid' => $eid,
        ], false);
    }

    /**
     * エントリーフィールドを取得
     *
     * @param int $eid
     * @return Field
     */
    public function getEntryField(int $eid): Field
    {
        return loadEntryField($eid);
    }

    /**
     * エントリー本文を取得
     *
     * @param int $eid
     * @return string
     */
    public function getEntryBody(int $eid): string
    {
        if (!defined('MIME_TYPE')) {
            define('MIME_TYPE', 'text/html');
        }
        $tpl = $this->getUnitTemplate('/include/unit.html');

        /** @var \Acms\Services\Unit\Repository $unitService */
        $unitService = Application::make('unit-repository');
        /** @var \Acms\Services\Unit\Rendering\Front $unitRenderingService */
        $unitRenderingService = Application::make('unit-rendering-front');
        $tplEngine = new Template($tpl, new ACMS_Corrector());

        $collection = $unitService->loadUnits($eid);
        $unitRenderingService->render($collection, $tplEngine, $eid);

        $unitHtml = buildIF($tplEngine->get());
        $unitHtml = removeComments($unitHtml);
        $unitHtml = removeBlank($unitHtml);

        return $unitHtml;
    }

    /**
     * エントリーのサマリーを取得
     *
     * @param int $eid
     * @return string
     */
    public function getEntrySummary(int $eid): string
    {
        /** @var \Acms\Services\Unit\Repository $unitService */
        $unitService = Application::make('unit-repository');
        /** @var \Acms\Services\Unit\Rendering\Front $unitRenderingService */
        $unitRenderingService = Application::make('unit-rendering-front');

        $collection = $unitService->loadUnits($eid);
        return $unitRenderingService->renderSummaryText($collection)[0] ?? '';
    }

    /**
     * メイン画像の情報を取得
     *
     * @param array $entry
     * @param string|null $unitId
     * @return array|null
     */
    public function getMainImage(array $entry, ?string $unitId): ?array
    {
        $fieldName = config('main_image_field_name', '');
        $eid = (int) $entry['entry_id'];
        $tempId = $this->maxEntryId + $eid;
        $mainImageData = $this->eagerLoadMainImageTrait([$entry], 'field', $fieldName);

        $entryHelper = new \Acms\Modules\Get\Helpers\Entry\EntryHelper([]);
        $primaryImageInfo = $entryHelper->buildMainImage($unitId, $eid, $mainImageData);

        if (isset($primaryImageInfo['path'])) {
            $path = $primaryImageInfo['path'];
            if (isset($primaryImageInfo['media']) && $primaryImageInfo['media']) {
                $url = Common::toAbsoluteUrl($path, MEDIA_LIBRARY_DIR, true);
            } else {
                $url = Common::toAbsoluteUrl($path, ARCHIVES_DIR, true);
            }
            return [
                'id' => $tempId,
                'title' => "メディア画像{$tempId}",
                'url' => $url,
                'path' => $path,
            ];
        }
        return null;
    }

    /**
     * エントリー一覧を取得するベースクエリを取得
     *
     * @param int $bid
     * @param bool $includeChildBlogs
     * @return \SQL_Select
     */
    protected function getIndexQuery(int $bid, bool $includeChildBlogs): SQL_Select
    {
        $sql = SQL::newSelect('entry');
        $sql->addLeftJoin('blog', 'entry_blog_id', 'blog_id');
        $sql->addLeftJoin('category', 'entry_category_id', 'category_id');
        $sql->addLeftJoin('user', 'entry_user_id', 'user_id');
        if ($includeChildBlogs) {
            ACMS_Filter::blogTree($sql, $bid, 'descendant-or-self');
        } else {
            $sql->addWhereOpr('entry_blog_id', $bid);
        }
        return $sql;
    }

    /**
     * ユニットテンプレートを取得
     *
     * @param string $templatePath
     * @return string
     */
    protected function getUnitTemplate(string $templatePath): string
    {
        $acmsTplEngine = Application::make('template.acms');
        $acmsTplEngine->load($templatePath, config('theme'), BID);
        return $acmsTplEngine->render();
    }
}
