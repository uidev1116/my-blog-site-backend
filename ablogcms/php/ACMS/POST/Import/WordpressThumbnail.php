<?php

use Acms\Services\Facades\LocalStorage;

class ACMS_POST_Import_WordpressThumbnail extends ACMS_POST_Import_Wordpress
{
    public function init()
    {
        @set_time_limit(-1);
        $this->importType = 'WordPress Thumbnail';
        $this->uploadFiledName = 'wordpress_import_file';
    }

    public function import()
    {
        $this->httpFile->validateFormat(['xml']);
        $path = $this->httpFile->getPath();
        $data = LocalStorage::get($path, dirname($path));
        if ($data) {
            $data = LocalStorage::removeIllegalCharacters($data); // 不正な文字コードを削除
        }
        $this->validateXml($data);

        $xml = new XMLReader();
        $xml->XML($data); // @phpstan-ignore-line

        while ($xml->read()) {
            if ($xml->name === 'item' and intval($xml->nodeType) === XMLReader::ELEMENT) {
                $id = $this->getNodeValue($xml, 'wp:post_id');
                $type = $this->getNodeValue($xml, 'wp:post_type');
                $url = $this->getNodeValue($xml, 'wp:attachment_url');

                if (empty($id) || empty($url)) {
                    continue;
                }
                if ($type !== 'attachment') {
                    continue;
                }
                while ($xml->read()) {
                    if (intval($xml->nodeType) === XMLReader::END_ELEMENT and $xml->name === 'item') {
                        if ($eids = $this->find($id)) {
                            $this->updateThumbnailImage($eids, $url);
                            $this->entryCount++;
                        }
                        break;
                    }
                }
            }
        }
        $xml->close();
        Cache::flush('field');
    }

    protected function find($id)
    {
        $sql = SQL::newSelect('field');
        $sql->setSelect('field_eid');
        $sql->addWhereOpr('field_key', 'wp_thumbnail_id');
        $sql->addWhereOpr('field_value', $id);
        $sql->addWhereOpr('field_blog_id', BID);

        return DB::query($sql->get(dsn()), 'list');
    }

    protected function updateThumbnailImage($eids, $url)
    {
        if (empty($eids) || empty($url)) {
            return;
        }
        $sql = SQL::newDelete('field');
        $sql->addWhereOpr('field_key', 'wp_thumbnail_url');
        $sql->addWhereIn('field_eid', $eids);
        $sql->addWhereOpr('field_blog_id', BID);
        DB::query($sql->get(dsn()), 'exec');

        $sql = SQL::newBulkInsert('field');
        foreach ($eids as $eid) {
            $sql->addInsert([
                'field_key' => 'wp_thumbnail_url',
                'field_value' => $url,
                'field_eid' => $eid,
                'field_blog_id' => BID,
            ]);
        }
        if ($sql->hasData()) {
            DB::query($sql->get(dsn()), 'exec');
        }
    }
}
