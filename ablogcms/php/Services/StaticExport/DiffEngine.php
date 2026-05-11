<?php

namespace Acms\Services\StaticExport;

use DB;
use SQL;
use Acms\Services\StaticExport\Generator\RequireThemeGenerator;
use Acms\Services\StaticExport\Generator\CategoryGenerator;
use Acms\Services\StaticExport\Generator\EntryGenerator;
use Acms\Services\Facades\Common;

class DiffEngine extends Engine
{
    /**
     * @var int[]
     */
    protected $targetEntryIds = [];

    /**
     * @var int[]
     */
    protected $targetCategoryIds = [];

    /**
     * DiffEngine constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Run
     *
     * @param int $bid
     * @param string $from (YYYY-MM-DD HH:ii:ss)
     */
    public function runDiff(int $bid, string $from)
    {
        if (!preg_match('/\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}/', $from)) {
            throw new \RuntimeException("Datetime format is invalid.（{$from}）");
        }
        $themes = $this->extractTheme($this->config->theme);
        $this->setDiffItems($bid, $from);

        try {
            // テーマの必須アセット書き出し
            $this->processExportThemeAssets($themes);

            // テーマの必須テンプレート書き出し
            $this->processExportTheme($bid, $themes);

            // トップページの書き出し
            $this->processExportTop($bid);

            // カテゴリートップページの書き出し
            $this->processExportCategoryTop($bid);

            // エントリーの書き出し
            $this->processExportEntry($bid);

            // カテゴリーページの書き出し
            $this->processExportCategoryPagenation($bid, array_intersect($this->config->static_page_cid, $this->targetCategoryIds), $this->config->static_page_max);

            // カテゴリーアーカイブページの書き出し
            $this->processExportCategoryArchivePage($bid, array_intersect($this->config->static_archive_cid, $this->targetCategoryIds), $this->config->static_archive_start, $this->config->static_archive_max);
        } catch (\Throwable $th) {
            $this->logger->error('不明なエラーが発生したため、部分書き出し処理を中断します');
            throw $th;
        } finally {
            $this->logger->start('書き出し完了');
            $this->logger->processing();

            sleep(1);

            $this->logger->destroy();
        }
    }

    /**
     * 指定された日付より新しいエントリーを設定
     *
     * @param int $bid
     * @param string $from (YYYY-MM-DD HH:ii:ss)
     */
    private function setDiffItems(int $bid, string $from)
    {
        $SQL = SQL::newSelect('entry');
        $SQL->addSelect('entry_id');
        $SQL->addSelect('entry_category_id');
        $SQL->addLeftJoin('blog', 'blog_id', 'entry_blog_id');
        $SQL->addWhereOpr('entry_updated_datetime', $from, '>=');
        $SQL->addWhereOpr('entry_blog_id', $bid);
        $SQL->addWhereOpr('entry_status', 'open');
        $all = DB::query($SQL->get(dsn()), 'all');

        foreach ($all as $entry) {
            $this->targetEntryIds[] = intval($entry['entry_id']);
            $this->targetCategoryIds[] = intval($entry['entry_category_id']);
        }
    }

    /**
     * @inheritDoc
     */
    protected function processExportCategoryTop(int $bid): void
    {
        $generator = new CategoryGenerator(
            $bid,
            $this->compiler,
            $this->destination,
            $this->logger,
            $this->maxPublish
        );
        $generator->setCategoryIds($this->targetCategoryIds);
        try {
            $generator->run();
        } catch (\Throwable $th) {
            $this->logger->error('不明なエラーが発生したため、カテゴリートップページの書き出しを中断します');
            \AcmsLogger::error('カテゴリートップページの部分静的書き出しに失敗しました。', Common::exceptionArray($th));
        }
    }

    /**
     * @inheritDoc
     */
    protected function processExportEntry(int $bid): void
    {
        $generator = new EntryGenerator(
            $bid,
            $this->compiler,
            $this->destination,
            $this->logger,
            $this->maxPublish
        );
        $generator->setEntryIds($this->targetEntryIds);
        $generator->setWithArchive(true);
        try {
            $generator->run();
        } catch (\Throwable $th) {
            $this->logger->error('不明なエラーが発生したため、エントリーの書き出しを中断します');
            \AcmsLogger::error('エントリーの部分静的書き出しに失敗しました。', Common::exceptionArray($th));
        }
    }

    /**
     * @inheritDoc
     */
    protected function processExportThemeAssets($themes)
    {
        $this->copyThemeRequireItems(THEMES_DIR . 'system/');

        foreach ($themes as $theme) {
            $path = THEMES_DIR . $theme . '/';
            $this->copyThemeRequireItems($path);
        }
    }

    /**
     *  テーマのテンプレート書き出し
     *
     * @inheritDoc
     */
    protected function processExportTheme(int $bid, array $themes): void
    {
        foreach ($themes as $theme) {
            $path = THEMES_DIR . $theme . '/';
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
                \AcmsLogger::error('「' . $theme . '」の必須テンプレートの部分静的書き出しに失敗しました。', Common::exceptionArray($th));
            }
        }
    }
}
