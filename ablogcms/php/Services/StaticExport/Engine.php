<?php

namespace Acms\Services\StaticExport;

use App;
use DB;
use SQL;
use ACMS_Filter;
use ACMS_RAM;
use Acms\Services\Facades\LocalStorage;
use Acms\Services\Facades\Common;
use Acms\Services\StaticExport\Generator\TopGenerator;
use Acms\Services\StaticExport\Generator\ThemeGenerator;
use Acms\Services\StaticExport\Generator\RequireThemeGenerator;
use Acms\Services\StaticExport\Generator\CategoryGenerator;
use Acms\Services\StaticExport\Generator\CategoryPageGenerator;
use Acms\Services\StaticExport\Generator\CategoryArchivesGenerator;
use Acms\Services\StaticExport\Generator\EntryGenerator;
use Acms\Services\StaticExport\Generator\PageGenerator;
use Symfony\Component\Finder\Finder;

class Engine
{
    /**
     * @var \Acms\Services\StaticExport\Compiler
     */
    protected $compiler;

    /**
     * @var \Acms\Services\StaticExport\Destination
     */
    protected $destination;

    /**
     * @var \Acms\Services\StaticExport\TerminateCheck
     */
    protected $terminateFlag;

    /**
     * @var \Acms\Services\StaticExport\Logger
     */
    protected $logger;

    /**
     * @var \Symfony\Component\Finder\Finder
     */
    protected $finder;

    /**
     * @var int
     */
    protected $maxPublish = 3;

    /**
     * @var \stdClass
     */
    protected $config;

    /**
     * Engine constructor.
     */
    public function __construct()
    {
        $this->finder = new Finder();
    }

    /**
     * 初期設定
     *
     * @param \Acms\Services\StaticExport\Logger $logger
     * @param \Acms\Services\StaticExport\Destination $destination
     * @param int $maxPublish
     * @param \stdClass $config
     * @return void
     * @throws \Exception
     */
    public function init($logger, $destination, $maxPublish, $config): void
    {
        $this->logger = $logger;
        $this->destination = $destination;
        $this->maxPublish = $maxPublish;
        $this->config = $config;

        try {
            if (!LocalStorage::exists($this->destination->getDestinationPath())) {
                LocalStorage::makeDirectory($this->destination->getDestinationPath());
            }
            if (!LocalStorage::isWritable($this->destination->getDestinationPath())) {
                $this->logger->error('データの書き込みに失敗しました。', $this->destination->getDestinationPath());
            }
            $this->compiler = App::make('static-export.compiler');
            $this->compiler->setDestination($this->destination);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Run
     *
     * @param int $bid
     * @return void
     */
    public function run(int $bid): void
    {
        $themes = $this->extractTheme($this->config->theme);
        array_unshift($themes, 'system');

        try {
            // アセットの書き出し
            $this->processExportAssets($bid);

            // テーマのアセット書き出し
            $this->processExportThemeAssets($themes);

            // css の url属性のパス解決
            $this->processResolvCssPath($themes);

            // テーマのテンプレート書き出し
            $this->processExportTheme($bid, $themes);

            // トップページの書き出し
            $this->processExportTop($bid);

            if ($this->config->static_export_dafault_max_page > 1) {
                // ページの書き出し
                $this->processExportPagenation($bid, $this->config->static_export_dafault_max_page);
            }

            // カテゴリートップページの書き出し
            $this->processExportCategoryTop($bid);

            // エントリーの書き出し
            $this->processExportEntry($bid);

            // カテゴリーページの書き出し
            $this->processExportCategoryPagenation($bid, $this->config->static_page_cid, $this->config->static_page_max);

            // カテゴリーアーカイブページの書き出し
            $this->processExportCategoryArchivePage($bid, $this->config->static_archive_cid, $this->config->static_archive_start, $this->config->static_archive_max);

            // 古いファイルの削除
            $this->deleteOldFiles($bid);
        } catch (\Throwable $th) {
            $this->logger->error('不明なエラーが発生したため、書き出し処理を中断します');
            throw $th;
        } finally {
            $this->logger->start('書き出し完了');
            $this->logger->processing();

            sleep(1);

            $this->logger->destroy();
        }
    }

    /**
     * アセットの書き出し
     *
     * @param int $bid
     * @return void
     */
    protected function processExportAssets(int $bid): void
    {
        $this->logger->start('アセットの書き出し');
        $this->logger->processing();
        try {
            $this->copyAssets($bid);
        } catch (\Throwable $th) {
            $this->logger->error('不明なエラーが発生したため、アセットの書き出しを中断します');
            \AcmsLogger::error('アセットの静的書き出しに失敗しました。', Common::exceptionArray($th));
        }
    }

    /**
     * テーマのアセット書き出し
     *
     * @param string[] $themes
     */
    protected function processExportThemeAssets($themes)
    {
        static $executedThemes = [];

        foreach ($themes as $theme) {
            if (in_array($theme, $executedThemes, true)) {
                continue;
            }
            $executedThemes[] = $theme;
            $path = THEMES_DIR . $theme . '/';
            try {
                $this->copyThemeItems($path);
                $this->copyThemeRequireItems($path);
            } catch (\Throwable $th) {
                $this->logger->error('不明なエラーが発生したため、「' . $theme . '」のアセットの書き出しを中断します');
                \AcmsLogger::error('「' . $theme . '」のアセットの静的書き出しに失敗しました。', Common::exceptionArray($th));
            }
        }
    }

    /**
     * css の url属性のパス解決
     *
     * @param string[] $themes
     */
    protected function processResolvCssPath(array $themes)
    {
        static $executedThemes = [];

        foreach ($themes as $theme) {
            if (in_array($theme, $executedThemes, true)) {
                continue;
            }
            $executedThemes[] = $theme;
            $path = THEMES_DIR . $theme . '/';
            try {
                $this->resolvePathInCss($path);
            } catch (\Throwable $th) {
                $this->logger->error('不明なエラーが発生したため、「' . $theme . '」のCSSのurl属性のパス解決を中断します');
                \AcmsLogger::error('「' . $theme . '」のCSSのurl属性のパス解決に失敗しました。', Common::exceptionArray($th));
            }
        }
    }

    /**
     *  テーマのテンプレート書き出し
     *
     * @param int $bid
     * @param array $themes
     * @return void
     */
    protected function processExportTheme(int $bid, array $themes): void
    {
        static $executedThemes = [];

        foreach ($themes as $theme) {
            if (in_array($theme, $executedThemes, true)) {
                continue;
            }
            $executedThemes[] = $theme;
            $path = THEMES_DIR . $theme . '/';
            $themeGenerator = new ThemeGenerator(
                $bid,
                $this->compiler,
                $this->destination,
                $this->logger,
                $this->maxPublish
            );
            $themeGenerator->setSourceTheme($path);
            $themeGenerator->setExclusionList($this->config->exclusion_list);
            try {
                $themeGenerator->run();
            } catch (\Throwable $th) {
                $this->logger->error('不明なエラーが発生したため、「' . $theme . '」のテンプレートの書き出しを中断します');
                \AcmsLogger::error('「' . $theme . '」のテンプレートの静的書き出しに失敗しました。', Common::exceptionArray($th));
            }

            $requireThemeGenerator = new RequireThemeGenerator(
                $bid,
                $this->compiler,
                $this->destination,
                $this->logger,
                $this->maxPublish
            );
            $requireThemeGenerator->setSourceTheme($path);
            $requireThemeGenerator->setIncludeList($this->config->include_list);
            try {
                $requireThemeGenerator->run();
            } catch (\Throwable $th) {
                $this->logger->error('不明なエラーが発生したため、「' . $theme . '」の必須テンプレートの書き出しを中断します');
                \AcmsLogger::error('「' . $theme . '」の必須テンプレートの静的書き出しに失敗しました。', Common::exceptionArray($th));
            }
        }
    }

    /**
     * トップページの書き出し
     *
     * @param int $bid
     * @return void
     */
    protected function processExportTop(int $bid): void
    {
        $generator = new TopGenerator(
            $bid,
            $this->compiler,
            $this->destination,
            $this->logger,
            $this->maxPublish
        );
        $generator->setExclusionList($this->config->exclusion_list);
        try {
            $generator->run();
        } catch (\Throwable $th) {
            $this->logger->error('不明なエラーが発生したため、トップページの書き出しを中断します');
            \AcmsLogger::error('トップページの静的書き出しに失敗しました。', Common::exceptionArray($th));
        }
    }

    /**
     * カテゴリートップの書き出し
     *
     * @param int $bid
     * @return void
     */
    protected function processExportCategoryTop(int $bid): void
    {
        $SQL = SQL::newSelect('category');
        $SQL->setSelect('category_id');
        $SQL->addLeftJoin('blog', 'blog_id', 'category_blog_id');
        ACMS_Filter::blogTree($SQL, $bid, 'ancestor-or-self');
        $SQL->addWhereOpr('category_status', 'open');
        $Where  = SQL::newWhere();
        $Where->addWhereOpr('category_blog_id', $bid, '=', 'OR');
        $Where->addWhereOpr('category_scope', 'global', '=', 'OR');
        $SQL->addWhere($Where);
        $categoryIds = DB::query($SQL->get(dsn()), 'list');
        if ($categoryIds === false) {
            $this->logger->error('カテゴリーの取得に失敗したため、カテゴリートップページの書き出しを中止します。');
            return;
        }
        $categoryIds = array_map('intval', $categoryIds);
        $generator = new CategoryGenerator(
            $bid,
            $this->compiler,
            $this->destination,
            $this->logger,
            $this->maxPublish
        );
        $generator->setCategoryIds($categoryIds);
        try {
            $generator->run();
        } catch (\Throwable $th) {
            $this->logger->error('不明なエラーが発生したため、カテゴリートップページの書き出しを中断します');
            \AcmsLogger::error('カテゴリートップページの静的書き出しに失敗しました。', Common::exceptionArray($th));
        }
    }

    /**
     * エントリーの書き出し
     *
     * @param int $bid
     * @return void
     */
    protected function processExportEntry(int $bid): void
    {
        $SQL = SQL::newSelect('entry');
        $SQL->setSelect('entry_id');
        $SQL->addLeftJoin('blog', 'blog_id', 'entry_blog_id');
        $SQL->addLeftJoin('category', 'category_id', 'entry_category_id');
        $SQL->addWhereOpr('entry_blog_id', $bid);
        $SQL->addWhereOpr('entry_start_datetime', date('Y-m-d H:i:s', requestTime()), '<=');
        $SQL->addWhereOpr('entry_end_datetime', date('Y-m-d H:i:s', requestTime()), '>=');
        $SQL->addWhereOpr('entry_status', 'open');
        $where = SQL::newWhere();
        $where->addWhereOpr('category_status', null, '=', 'OR');
        $where->addWhereOpr('category_status', 'open', '=', 'OR');
        $SQL->addWhere($where);
        $entryIds = DB::query($SQL->get(dsn()), 'list');
        if ($entryIds === false) {
            $this->logger->error('エントリーの取得に失敗したため、エントリーの書き出しを中止します。');
            return;
        }

        $entryIds = array_map('intval', $entryIds);
        $generator = new EntryGenerator(
            $bid,
            $this->compiler,
            $this->destination,
            $this->logger,
            $this->maxPublish
        );
        $generator->setEntryIds($entryIds);
        try {
            $generator->run();
        } catch (\Throwable $th) {
            $this->logger->error('不明なエラーが発生したため、エントリーの書き出しを中断します');
            \AcmsLogger::error('エントリーの静的書き出しに失敗しました。', Common::exceptionArray($th));
        }
    }

    /**
     * ページの書き出し
     *
     * @param int $bid
     * @param int $maxPageCount
     * @return void
     */
    protected function processExportPagenation(int $bid, int $maxPageCount): void
    {
        if ($maxPageCount < 2) {
            return;
        }
        $generator = new PageGenerator(
            $bid,
            $this->compiler,
            $this->destination,
            $this->logger,
            $this->maxPublish
        );
        $generator->setMaxPage($maxPageCount);
        try {
            $generator->run();
        } catch (\Throwable $th) {
            $this->logger->error('不明なエラーが発生したため、ページの書き出しを中断します');
            \AcmsLogger::error('ページの静的書き出しに失敗しました。', Common::exceptionArray($th));
        }
    }

    /**
     * カテゴリーページの書き出し
     *
     * @param int $bid
     * @param int[] $categoryIds
     * @param int[] $maxPages
     * @return void
     */
    protected function processExportCategoryPagenation(int $bid, array $categoryIds, array $maxPages): void
    {
        foreach ($categoryIds as $i => $categoryId) {
            // カテゴリーのページを書き出し
            $maxPage = $maxPages[$i] ?? 5;
            if ($maxPage < 2) {
                continue;
            }
            $generator = new CategoryPageGenerator(
                $bid,
                $this->compiler,
                $this->destination,
                $this->logger,
                $this->maxPublish
            );
            $generator->setCategoryId($categoryId);
            $generator->setMaxPage($maxPage);
            try {
                $generator->run();
            } catch (\Throwable $th) {
                $categoryName = ACMS_RAM::categoryName($categoryId);
                $this->logger->error(
                    '不明なエラーが発生したため、カテゴリーページの書き出しを中断します【' . $categoryName . '（' . $categoryName . '）】'
                );
                \AcmsLogger::error(
                    'カテゴリーページの静的書き出しに失敗しました【' . $categoryName . '（' . $categoryName . '）】',
                    Common::exceptionArray($th)
                );
            }
        }
    }

    /**
     * カテゴリーアーカイブページの書き出し
     *
     * @param int $bid
     * @param int[] $categoryIds
     * @param array $startDates
     * @param array $maxPages
     * @param bool $generateMonthArchivePage
     * @param string[] $years
     *
     * @return void
     */
    protected function processExportCategoryArchivePage(int $bid, array $categoryIds, array $startDates, array $maxPages, bool $generateMonthArchivePage = true, array $years = []): void
    {
        foreach ($categoryIds as $i => $categoryId) {
            $start = $startDates[$i] ?? date('Y-m-d', REQUEST_TIME);
            $startDatetime = (new \DateTime())->setTimestamp(strtotime($start));
            $endDatetime = (new \DateTime())->setTimestamp(REQUEST_TIME);
            $monthRange = [];

            if ($years) {
                $monthRange = $years;
            } else {
                $nextMonthInterval = new \DateInterval('P1M');
                while ($startDatetime < $endDatetime) {
                    $year = $startDatetime->format('Y');
                    $month = $startDatetime->format('m');
                    if (array_search($year, $monthRange, true) === false) {
                        $monthRange[] = $year;
                    }
                    if ($generateMonthArchivePage) {
                        $monthRange[] = $year . '/' . $month;
                    }
                    $startDatetime->add($nextMonthInterval);
                }
            }
            if (empty($monthRange)) {
                continue;
            }
            $maxPage = $maxPages[$i] ?? 5;
            if ($maxPage < 2) {
                continue;
            }
            try {
                foreach ($monthRange as $ym) {
                    $generator = new CategoryArchivesGenerator(
                        $bid,
                        $this->compiler,
                        $this->destination,
                        $this->logger,
                        $this->maxPublish
                    );
                    $generator->setCategoryId($categoryId);
                    $generator->setRange($ym);
                    $generator->setMaxPage($maxPage);
                    $generator->run();
                }
            } catch (\Throwable $th) {
                $categoryName = ACMS_RAM::categoryName($categoryId);
                $this->logger->error(
                    '不明なエラーが発生したため、カテゴリーアーカイブページの書き出しを中断します【' . $categoryName . '（' . $categoryName . '）】'
                );
                \AcmsLogger::error(
                    'カテゴリーアーカイブページの静的書き出しに失敗しました【' . $categoryName . '（' . $categoryName . '）】',
                    Common::exceptionArray($th)
                );
            }
        }
    }

    /**
     * copy assets
     *
     * @param int $bid
     * @return void
     */
    protected function copyAssets(int $bid): void
    {
        $blog_archives_dir = sprintf('%03d', $bid);

        $src_archives_dir = ARCHIVES_DIR . $blog_archives_dir;
        $dest_archives_dir = $this->destination->getDestinationPath() . ARCHIVES_DIR . $blog_archives_dir;
        LocalStorage::copyDirectory($src_archives_dir, $dest_archives_dir);

        $src_media_dir = MEDIA_LIBRARY_DIR;
        $dest_media_dir = $this->destination->getDestinationPath() . MEDIA_LIBRARY_DIR;
        LocalStorage::copyDirectory($src_media_dir, $dest_media_dir);

        $src_storage_dir = MEDIA_STORAGE_DIR;
        $dest_storage_dir = $this->destination->getDestinationPath() . MEDIA_STORAGE_DIR;
        LocalStorage::copyDirectory($src_storage_dir, $dest_storage_dir);
        LocalStorage::remove($this->destination->getDestinationPath() . MEDIA_STORAGE_DIR . '.htaccess');

        LocalStorage::copyDirectory(JS_DIR, $this->destination->getDestinationPath() . JS_DIR);
        LocalStorage::copy('acms.js', $this->destination->getDestinationPath() . 'acms.js');
    }

    /**
     * copy theme items
     *
     * @param string $theme
     * @return void
     */
    protected function copyThemeItems(string $theme): void
    {
        if (empty($theme)) {
            return;
        }
        $finder = new Finder();
        $iterator = $finder
            ->in($theme)
            ->name('/\.(js|json|css|ttf|img|png|gif|jpeg|jpg|svg|txt|pdf|ppt|xls|csv|docx|pptx|xlsx|zip)$/')
            ->exclude('acms-code')
            ->exclude('admin');
        if (property_exists($this->config, 'exclusion_list')) {
            foreach ($this->config->exclusion_list as $path) {
                if (!empty($path)) {
                    $iterator->notPath($path);
                }
            }
        }
        $iterator->files();
        $this->logger->start('テーマのリソース書き出し ( ' . $theme . ' )', iterator_count($iterator));

        foreach ($iterator as $file) {
            try {
                $relative_dir_path = $file->getRelativePath();
                $relative_file_path = $file->getRelativePathname();
                $this->logger->processing();
                LocalStorage::makeDirectory($this->destination->getDestinationPath() . $this->destination->getBlogCode() . $relative_dir_path);
                LocalStorage::copy($theme . $relative_file_path, $this->destination->getDestinationPath() . $this->destination->getBlogCode() . $relative_file_path);
            } catch (\Exception $e) {
                $this->logger->error($e->getMessage(), $file->getRelativePathname());
            }
        }
    }

    /**
     * copy theme require items
     *
     * @param string $theme
     * @return void
     */
    protected function copyThemeRequireItems(string $theme): void
    {
        if (empty($theme)) {
            return;
        }
        if (property_exists($this->config, 'include_list')) {
            $includeList = [];
            foreach ($this->config->include_list as $path) {
                if (!empty($path)) {
                    $includeList[] = $path;
                }
            }
            if (count($includeList) > 0) {
                $finder = new Finder();
                $iterator = $finder->in($theme);
                foreach ($includeList as $path) {
                    $iterator->path($path);
                }
                $iterator->files();
                $this->logger->start('テーマの必須リソース書き出し ( ' . $theme . ' )', iterator_count($iterator));

                foreach ($iterator as $file) {
                    try {
                        $relative_dir_path = $file->getRelativePath();
                        $relative_file_path = $file->getRelativePathname();
                        $this->logger->processing();
                        LocalStorage::makeDirectory($this->destination->getDestinationPath() . $this->destination->getBlogCode() . $relative_dir_path);
                        LocalStorage::copy($theme . $relative_file_path, $this->destination->getDestinationPath() . $this->destination->getBlogCode() . $relative_file_path);
                    } catch (\Exception $e) {
                        $this->logger->error($e->getMessage(), $file->getRelativePathname());
                    }
                }
            }
        }
    }

    /**
     * css の url属性のパス解決
     *
     * @param string $theme
     * @return void
     */
    protected function resolvePathInCss(string $theme): void
    {
        $finder = new Finder();
        $iterator = $finder
            ->in($theme)
            ->name('/\.css$/')
            ->exclude('acms-code')
            ->exclude('admin');

        if (property_exists($this->config, 'exclusion_list')) {
            foreach ($this->config->exclusion_list as $path) {
                if (!empty($path)) {
                    $iterator->notPath($path);
                }
            }
        }
        $iterator->files();

        $this->logger->start('CSSのURL属性を解決 ( ' . $theme . ' )', iterator_count($iterator));

        foreach ($iterator as $file) {
            $relative_file_path = $file->getRelativePathname();
            $this->logger->processing($relative_file_path);
            if ($file->isReadable()) {
                $data = LocalStorage::get($theme . $relative_file_path);
                if ($data = $this->compiler->compile($data)) {
                    $destPath = $this->destination->getDestinationPath() . $this->destination->getBlogCode() . $relative_file_path;
                    LocalStorage::makeDirectory(dirname($destPath));
                    LocalStorage::put($destPath, $data);
                }
            }
        }
    }

    /**
     * delete files
     *
     * @param int $bid
     * @return void
     */
    protected function deleteOldFiles(int $bid): void
    {
        $finder = new Finder();
        $iterator = $finder
            ->in($this->destination->getDestinationPath() . $this->destination->getBlogCode())
            ->date('< ' . date('Y-m-d H:i:s', REQUEST_TIME));

        $iterator->notPath(ARCHIVES_DIR)
            ->notPath(MEDIA_LIBRARY_DIR)
            ->notPath(MEDIA_STORAGE_DIR);

        $SQL = SQL::newSelect('blog');
        $SQL->addSelect('blog_code');
        $SQL->addWhereOpr('blog_parent', $bid);
        $all = DB::query($SQL->get(dsn()), 'all');
        foreach ($all as $blog) {
            if ($bcd = $blog['blog_code']) {
                $iterator->notPath($bcd);
            }
        }
        if (property_exists($this->config, 'delete_exclusion_list')) {
            foreach ($this->config->delete_exclusion_list as $path) {
                if (!empty($path)) {
                    $iterator->notPath($path);
                }
            }
        }
        $iterator->files();
        $this->logger->start('古いファイルを削除', iterator_count($iterator));

        foreach ($iterator as $file) {
            $path = $this->destination->getDestinationPath() . $this->destination->getBlogCode() . $file->getRelativePathname();
            $this->logger->processing($path);
            $this->logger->removedFile($path);
            LocalStorage::remove($path);
        }
    }

    /**
     * get themes
     *
     * @param string $theme
     * @return array
     */
    protected function extractTheme($theme)
    {
        $theme = trim($theme, '@');
        $themes[] = $theme;
        while ($pos = strpos($theme, '@')) {
            $theme = substr($theme, $pos + 1);
            $themes[] = $theme;
        }
        return array_reverse(array_unique($themes));
    }

    protected function getConfig($key, $default, $i)
    {
        if (property_exists($this->config, $key)) {
            $array = $this->config->$key;
            if (isset($array[$i])) {
                return $array[$i];
            }
        }
        return $default;
    }
}
