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
use React\Promise\Promise;

use function React\Async\await;

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
    protected $maxPublish;

    /**
     * @var string
     */
    protected $nameServer;

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
     * @param string $nameServer
     * @param \stdClass $config
     * @throws \Exception
     */
    public function init($logger, $destination, $maxPublish, $nameServer, $config)
    {
        $this->logger = $logger;
        $this->destination = $destination;
        $this->maxPublish = $maxPublish;
        $this->nameServer = $nameServer;
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
     */
    public function run()
    {
        $themes = $this->extractTheme($this->config->theme);

        try {
            // アセットの書き出し
            $this->processExportAssets();

            // テーマのアセット書き出し
            $this->processExportThemeAssets($themes);

            // css の url属性のパス解決
            $this->processResolvCssPath($themes);

            // テーマのテンプレート書き出し
            await($this->processExportTheme($themes));

            // トップページの書き出し
            DB::reconnect(dsn());
            await($this->processExportTop());

            if ($this->config->static_export_dafault_max_page > 1) {
                // ページの書き出し
                DB::reconnect(dsn());
                await($this->processExportPagenation($this->config->static_export_dafault_max_page));
            }

            // カテゴリートップページの書き出し
            DB::reconnect(dsn());
            await($this->processExportCategoryTop());

            // エントリーの書き出し
            DB::reconnect(dsn());
            await($this->processExportEntry());

            // カテゴリーページの書き出し
            DB::reconnect(dsn());
            await($this->processExportCategoryPagenation($this->config->static_page_cid));

            // カテゴリーアーカイブページの書き出し
            DB::reconnect(dsn());
            await($this->processExportCategoryArchivePage($this->config->static_archive_cid));

            // 古いファイルの削除
            $this->deleteOldFiles();
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
     */
    protected function processExportAssets()
    {
        $this->logger->start('アセットの書き出し');
        $this->logger->processing();
        try {
            $this->copyAssets();
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
        $this->copyThemeItems(THEMES_DIR . 'system/');
        $this->copyThemeRequireItems(THEMES_DIR . 'system/');
        foreach ($themes as $theme) {
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
        $this->resolvePathInCss(THEMES_DIR . 'system/');
        foreach ($themes as $theme) {
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
     * @param array $themes
     * @return \React\Promise\PromiseInterface<null>
     */
    protected function processExportTheme($themes): \React\Promise\PromiseInterface
    {
        return new Promise(
            function (callable $resolve) use ($themes) {
                foreach ($themes as $theme) {
                    $path = THEMES_DIR . $theme . '/';
                    $themeGenerator = new ThemeGenerator(
                        $this->compiler,
                        $this->destination,
                        $this->logger,
                        $this->maxPublish,
                        $this->nameServer
                    );
                    $themeGenerator->setSourceTheme($path);
                    $themeGenerator->setExclusionList($this->config->exclusion_list);
                    try {
                        await($themeGenerator->run());
                    } catch (\Throwable $th) {
                        $this->logger->error('不明なエラーが発生したため、「' . $theme . '」のテンプレートの書き出しを中断します');
                        \AcmsLogger::error('「' . $theme . '」のテンプレートの静的書き出しに失敗しました。', Common::exceptionArray($th));
                    }


                    $requireThemeGenerator = new RequireThemeGenerator(
                        $this->compiler,
                        $this->destination,
                        $this->logger,
                        $this->maxPublish,
                        $this->nameServer
                    );
                    $requireThemeGenerator->setSourceTheme($path);
                    $requireThemeGenerator->setIncludeList($this->config->include_list);
                    try {
                        await($requireThemeGenerator->run());
                    } catch (\Throwable $th) {
                        $this->logger->error('不明なエラーが発生したため、「' . $theme . '」の必須テンプレートの書き出しを中断します');
                        \AcmsLogger::error('「' . $theme . '」の必須テンプレートの静的書き出しに失敗しました。', Common::exceptionArray($th));
                    }
                }
                $resolve(null);
            }
        );
    }

    /**
     * トップページの書き出し
     * @return \React\Promise\PromiseInterface<null>
     */
    protected function processExportTop(): \React\Promise\PromiseInterface
    {
        return new Promise(
            function (callable $resolve) {
                $generator = new TopGenerator(
                    $this->compiler,
                    $this->destination,
                    $this->logger,
                    $this->maxPublish,
                    $this->nameServer
                );
                $generator->setExclusionList($this->config->exclusion_list);
                try {
                    await($generator->run());
                } catch (\Throwable $th) {
                    $this->logger->error('不明なエラーが発生したため、トップページの書き出しを中断します');
                    \AcmsLogger::error('トップページの静的書き出しに失敗しました。', Common::exceptionArray($th));
                }
                $resolve(null);
            }
        );
    }

    /**
     * カテゴリートップの書き出し
     * @return \React\Promise\PromiseInterface<null>
     */
    protected function processExportCategoryTop(): \React\Promise\PromiseInterface
    {
        return new Promise(
            function (callable $resolve) {
                $SQL = SQL::newSelect('category');
                $SQL->setSelect('category_id');
                $SQL->addLeftJoin('blog', 'blog_id', 'category_blog_id');
                ACMS_Filter::blogTree($SQL, BID, 'ancestor-or-self');
                $SQL->addWhereOpr('category_status', 'open');
                $Where  = SQL::newWhere();
                $Where->addWhereOpr('category_blog_id', BID, '=', 'OR');
                $Where->addWhereOpr('category_scope', 'global', '=', 'OR');
                $SQL->addWhere($Where);
                $categoryIds = DB::query($SQL->get(dsn()), 'list');
                if ($categoryIds === false) {
                    $this->logger->error('カテゴリーの取得に失敗したため、カテゴリートップページの書き出しを中止します。');
                    $resolve(null);
                    return;
                }
                $categoryIds = array_map('intval', $categoryIds);
                $generator = new CategoryGenerator(
                    $this->compiler,
                    $this->destination,
                    $this->logger,
                    $this->maxPublish,
                    $this->nameServer
                );
                $generator->setCategoryIds($categoryIds);
                try {
                    await($generator->run());
                } catch (\Throwable $th) {
                    $this->logger->error('不明なエラーが発生したため、カテゴリートップページの書き出しを中断します');
                    \AcmsLogger::error('カテゴリートップページの静的書き出しに失敗しました。', Common::exceptionArray($th));
                }
                $resolve(null);
            }
        );
    }

    /**
     * エントリーの書き出し
     * @return \React\Promise\PromiseInterface<null>
     */
    protected function processExportEntry(): \React\Promise\PromiseInterface
    {
        return new Promise(
            function (callable $resolve) {
                $SQL = SQL::newSelect('entry');
                $SQL->setSelect('entry_id');
                $SQL->addLeftJoin('blog', 'blog_id', 'entry_blog_id');
                $SQL->addLeftJoin('category', 'category_id', 'entry_category_id');
                $SQL->addWhereOpr('entry_blog_id', BID);
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
                    $resolve(null);
                    return;
                }

                $entryIds = array_map('intval', $entryIds);
                $generator = new EntryGenerator(
                    $this->compiler,
                    $this->destination,
                    $this->logger,
                    $this->maxPublish,
                    $this->nameServer
                );
                $generator->setEntryIds($entryIds);
                try {
                    await($generator->run());
                } catch (\Throwable $th) {
                    $this->logger->error('不明なエラーが発生したため、エントリーの書き出しを中断します');
                    \AcmsLogger::error('エントリーの静的書き出しに失敗しました。', Common::exceptionArray($th));
                }
                $resolve(null);
            }
        );
    }

    /**
     * ページの書き出し
     * @param int $maxPageCount
     * @return \React\Promise\PromiseInterface<null>
     */
    protected function processExportPagenation(int $maxPageCount): \React\Promise\PromiseInterface
    {
        return new Promise(
            function (callable $resolve) use ($maxPageCount) {
                if ($maxPageCount < 2) {
                    $resolve(null);
                    return;
                }
                $generator = new PageGenerator(
                    $this->compiler,
                    $this->destination,
                    $this->logger,
                    $this->maxPublish,
                    $this->nameServer
                );
                $generator->setMaxPage($maxPageCount);
                try {
                    await($generator->run());
                } catch (\Throwable $th) {
                    $this->logger->error('不明なエラーが発生したため、ページの書き出しを中断します');
                    \AcmsLogger::error('ページの静的書き出しに失敗しました。', Common::exceptionArray($th));
                }
                $resolve(null);
            }
        );
    }

    /**
     * カテゴリーページの書き出し
     *
     * @param int[] $categoryIds
     * @return \React\Promise\PromiseInterface<null>
     */
    protected function processExportCategoryPagenation(array $categoryIds): \React\Promise\PromiseInterface
    {
        return new Promise(
            function (callable $resolve) use ($categoryIds) {
                foreach ($categoryIds as $i => $categoryId) {
                    // カテゴリーのページを書き出し
                    $maxPage = $this->getConfig('static_page_max', 5, $i);
                    if ($maxPage < 2) {
                        continue;
                    }
                    $generator = new CategoryPageGenerator(
                        $this->compiler,
                        $this->destination,
                        $this->logger,
                        $this->maxPublish,
                        $this->nameServer
                    );
                    $generator->setCategoryId($categoryId);
                    $generator->setMaxPage($maxPage);
                    try {
                        await($generator->run());
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
                $resolve(null);
            }
        );
    }

    /**
     * カテゴリーアーカイブページの書き出し
     *
     * @param int[] $categoryIds
     * @return \React\Promise\PromiseInterface<null>
     */
    protected function processExportCategoryArchivePage(array $categoryIds): \React\Promise\PromiseInterface
    {
        return new Promise(
            function (callable $resolve) use ($categoryIds) {
                foreach ($categoryIds as $i => $categoryId) {
                    $start = $this->getConfig('static_archive_start', date('Y-m-d', REQUEST_TIME), $i);
                    $startDatetime = (new \DateTime())->setTimestamp(strtotime($start));
                    $endDatetime = null;

                    // そのカテゴリーの最後の日付のエントリーまでアーカイブを作る
                    $SQL = SQL::newSelect('entry');
                    $SQL->addLeftJoin('category', 'category_id', 'entry_category_id');
                    $SQL->addSelect('entry_datetime');
                    $SQL->addWhereOpr('entry_status', 'open');
                    $SQL->addWhereOpr('entry_start_datetime', date('Y-m-d H:i:s', requestTime()), '<=');
                    $SQL->addWhereOpr('entry_end_datetime', date('Y-m-d H:i:s', requestTime()), '>=');
                    $SQL->addWhereOpr('entry_status', 'open');
                    $where = SQL::newWhere();
                    $where->addWhereOpr('category_status', null, '=', 'OR');
                    $where->addWhereOpr('category_status', 'open', '=', 'OR');
                    $SQL->addWhere($where);
                    $SQL->setOrder('entry_datetime', 'DESC');
                    $SQL->setLimit(1);
                    if ($categoryId > 0) {
                        $SQL->addWhereOpr('category_status', 'open');
                        ACMS_Filter::categoryTree($SQL, $categoryId, 'descendant-or-self');
                    }
                    if ($last = DB::query($SQL->get(dsn()), 'one')) {
                        $last = date('Y-m-31 23:23:59', strtotime($last));
                        $endDatetime = (new \DateTime())->setTimestamp(strtotime($last));
                    }
                    if (is_null($endDatetime)) {
                        $endDatetime = (new \DateTime())->setTimestamp(REQUEST_TIME);
                    }
                    $nextMonthInterval = new \DateInterval('P1M');

                    $monthRange = [];
                    while ($startDatetime < $endDatetime) {
                        $year = $startDatetime->format('Y');
                        $month = $startDatetime->format('m');
                        if (array_search($year, $monthRange, true) === false) {
                            $monthRange[] = $year;
                        }
                        $monthRange[] = $year . '/' . $month;
                        $startDatetime->add($nextMonthInterval);
                    }
                    if (empty($monthRange)) {
                        continue;
                    }

                    $maxPage = $this->getConfig('static_archive_max', 5, $i);

                    if ($maxPage < 2) {
                        continue;
                    }
                    try {
                        $generator = new CategoryArchivesGenerator(
                            $this->compiler,
                            $this->destination,
                            $this->logger,
                            $this->maxPublish,
                            $this->nameServer
                        );
                        $generator->setCategoryId($categoryId);
                        $generator->setMonthRange($monthRange);
                        $generator->setMaxPage($this->getConfig('static_archive_max', 5, $i));
                        await($generator->run());
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
                $resolve(null);
            }
        );
    }

    /**
     * copy assets
     *
     * @return void
     */
    protected function copyAssets()
    {
        $blog_archives_dir = sprintf('%03d', BID);

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
    protected function copyThemeItems($theme)
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
    protected function copyThemeRequireItems($theme)
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
    protected function resolvePathInCss($theme)
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
     * @return void
     */
    protected function deleteOldFiles()
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
        $SQL->addWhereOpr('blog_parent', BID);
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
