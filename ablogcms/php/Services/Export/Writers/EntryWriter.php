<?php

namespace Acms\Services\Export\Writers;

use Acms\Services\Facades\Application;
use Acms\Services\Facades\Database;
use Acms\Services\Common\Logger;
use XMLWriter;

class EntryWriter
{
    /**
     * @var \Acms\Services\Export\Repositories\EntryRepository
     */
    protected $entryRepository;

    /**
     * @var \Acms\Services\Export\Helper;
     */
    protected $helper;


    /**
     * Constructor
     */
    public function __construct()
    {
        $this->entryRepository = Application::make('export-repository-entry');
        $this->helper = Application::make('export-helper');
    }

    /**
     * エントリーアイテム配列を書き出し
     *
     * @param \XMLWriter $writer
     * @param int $bid
     * @param bool $includeChildBlogs
     * @param \Acms\Services\Common\Logger $logger
     * @return void
     */
    public function writeEntries(XMLWriter $writer, int $bid, bool $includeChildBlogs, Logger $logger): void
    {
        $count = $this->entryRepository->getEntryCount($bid, $includeChildBlogs);
        $sql = $this->entryRepository->getEntriesQuery($bid, $includeChildBlogs);
        $query = $sql->get(dsn());
        $statement = Database::query($query, 'exec');

        $increase = 80 / $count;
        $i = 0;

        $logger->addMessage("エントリーを書き出し開始", 0, 1, false);
        sleep(2);

        while ($item = Database::next($statement)) {
            $i++;
            $postId = $this->writeMainImage($writer, $item);
            $this->writeEntryElement($writer, $item, $postId);
            $logger->addMessage("エントリー $i / $count", $increase, 1, false);
        }
    }

    /**
     * メイン画像アイテムを書き出し
     *
     * @param \XMLWriter $writer
     * @param array $item
     * @return int|null
     */
    protected function writeMainImage(XMLWriter $writer, array $item): ?int
    {
        $unitId = $item['entry_primary_image'] ?? null;
        $data = $this->entryRepository->getMainImage($item, $unitId);
        if (!$data) {
            return null;
        }
        $writer->startElement('item');
        $writer->writeElement('title', $data['title']);
        $writer->writeElement('link', $data['url']);
        $writer->writeElement('wp:post_id', $data['id']);
        $writer->writeElement('wp:post_type', 'attachment');
        $writer->writeElement('wp:attachment_url', $data['url']);

        $writer->startElement('wp:postmeta');
        $this->helper->writeCData($writer, 'wp:meta_key', '_wp_attached_file');
        $this->helper->writeCData($writer, 'wp:meta_value', $data['path']);
        $writer->endElement(); // wp:postmeta

        $writer->endElement(); // item

        return $data['id'];
    }

    /**
     * エントリーアイテムを書き出し
     *
     * @param \XMLWriter $writer
     * @param array $item
     * @param int|null $postId
     * @return void
     */
    protected function writeEntryElement(XMLWriter $writer, array $item, ?int $postId): void
    {
        $eid = (int) $item['entry_id'];
        $entryField = $this->entryRepository->getEntryField($eid);

        $writer->startElement('item');
        $writer->writeElement('title', $item['entry_title']);
        $writer->writeElement('link', $this->entryRepository->getEntryUrl($eid));
        $writer->writeElement('pubDate', $this->helper->dateFormat($item['entry_datetime']));
        $this->helper->writeCData($writer, 'dc:creator', $this->helper->computeUserCode($item['user_code'], $item['user_mail']));

        $writer->startElement('guid');
        $writer->writeAttribute('isPermaLink', 'false');
        $writer->text($this->entryRepository->getEntryUrl($eid));
        $writer->endElement();

        $writer->writeElement('description', $entryField->get('entry_meta_description'));
        $this->helper->writeCData($writer, 'content:encoded', $this->entryRepository->getEntryBody($eid));
        $this->helper->writeCData($writer, 'excerpt:encoded', $this->entryRepository->getEntrySummary($eid));

        $status = $this->computeStatus($item);

        $writer->writeElement('wp:post_id', (string) $eid);
        if ($status === 'future') {
            $this->helper->writeCData($writer, 'wp:post_date', $item['entry_start_datetime']);
        } else {
            $this->helper->writeCData($writer, 'wp:post_date', $item['entry_datetime']);
        }
        $this->helper->writeCData($writer, 'wp:post_modified', $item['entry_updated_datetime']);
        $this->helper->writeCData($writer, 'wp:post_name', $item['entry_code']);
        $writer->writeElement('wp:status', $status);
        $writer->writeElement('wp:post_type', 'post');
        $writer->writeElement('wp:is_sticky', '0');

        $this->writeEntryCategory($writer, $item);

        if ($postId) {
            // メイン画像
            $writer->startElement('wp:postmeta');
            $this->helper->writeCData($writer, 'wp:meta_key', '_thumbnail_id');
            $this->helper->writeCData($writer, 'wp:meta_value', (string) $postId);
            $writer->endElement();
        }

        $this->writeEntryField($writer, $eid);

        $writer->endElement(); // item
    }

    /**
     * エントリーステータスを公開期限・承認状態・ステータスから計算
     *
     * @param array $item
     * @return 'publish'|'draft'|'pending'|'future'
     */
    protected function computeStatus(array $item): string
    {
        $status = $item['entry_status'];
        if ($status !== 'open') {
            return 'draft';
        }
        if (strtotime($item['entry_start_datetime']) > REQUEST_TIME) {
            return 'future'; // 未来公開
        }
        if (strtotime($item['entry_end_datetime']) < REQUEST_TIME) {
            return 'draft'; // 掲載終了
        }
        if ($item['entry_approval'] === 'pre_approval') {
            return 'draft'; // 承認前
        }
        return 'publish';
    }

    /**
     * カスタムフィールドを書き出し
     *
     * @param \XMLWriter $writer
     * @param int $eid
     * @return void
     */
    protected function writeEntryField(XMLWriter $writer, int $eid): void
    {
        $field = loadEntryField($eid);

        foreach ($field->listFields() as $fd) {
            $writer->startElement('wp:postmeta');
            $this->helper->writeCData($writer, 'wp:meta_key', $fd);
            $this->helper->writeCData($writer, 'wp:meta_value', implode(',', $field->getArray($fd)));
            $writer->endElement();
        }
    }

    /**
     * カテゴリーを書き出し
     *
     * @param \XMLWriter $writer
     * @param array $item
     * @return void
     */
    protected function writeEntryCategory(XMLWriter $writer, array $item): void
    {
        if ($code = $item['category_code']) {
            $writer->startElement('category');
            $writer->writeAttribute('domain', 'category');
            $writer->writeAttribute('nicename', $code);
            $writer->writeCdata($item['category_name']);
            $writer->endElement();
        }
        $eid = $item['entry_id'];
        $subCategories = loadSubCategoriesAll($eid);
        foreach ($subCategories as $i => $category) {
            $writer->startElement('category');
            $writer->writeAttribute('domain', 'category');
            $writer->writeAttribute('nicename', $category['category_code']);
            $writer->writeCdata($category['category_name']);
            $writer->endElement();
        }
    }
}
