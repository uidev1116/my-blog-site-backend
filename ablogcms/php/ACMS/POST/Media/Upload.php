<?php

use Acms\Services\Facades\Common;
use Acms\Services\Facades\Media;

class ACMS_POST_Media_Upload extends ACMS_POST
{
    function post()
    {
        $mid = 0;
        $data = [];

        try {
            if (!Media::validate()) {
                throw new \RuntimeException('メディア機能が有効でないか、権限がありません');
            }
            $tags = $this->Post->get('tags');
            $name = $this->Post->get('name');
            Common::validateFileUpload('file');

            $info = Media::getBaseInfo($_FILES['file'], $tags, $name);
            if ($info === false) {
                throw new \RuntimeException('アップロードファイルの情報が取得できませんでした');
            }
            $type = mime_content_type($_FILES['file']['tmp_name']);
            if (Media::isImageFile($type)) {
                $data = Media::uploadImage('file');
            } elseif (Media::isSvgFile($type)) {
                $data = Media::uploadSvg($info['size'], 'file');
            } else {
                $data = Media::uploadFile($info['size'], 'file');
            }
            if (empty($data)) {
                throw new \RuntimeException('Upload failed.');
            }
            $mid = DB::query(SQL::nextval('media_id', dsn()), 'seq');

            if (isset($_FILES['media_pdf_thumbnail'])) {
                $res = Media::uploadPdfThumbnail('media_pdf_thumbnail');
                if (isset($res['path'])) {
                    $data['thumbnail'] = $res['path'];
                    $data['size'] = $res['size'];
                    $data['field_6'] = 1;
                }
            }
            Media::insertMedia($mid, $data, BID);
            Media::saveTags($mid, $tags, BID);

            $data = Media::getMedia($mid);
            $tags = Media::getMediaLabel($mid);
            $json = Media::buildJson($mid, $data, $tags, BID);
            $json['status'] = 'success';

            if (HOOK_ENABLE) {
                $Hook = ACMS_Hook::singleton();
                $Hook->call('saveMedia', [$mid, 'insert', true]);
            }

            AcmsLogger::info('メディアをアップロードしました', [
                'mid' => $mid,
                'data' => $data,
            ]);

            Common::responseJson($json);
        } catch (\Exception $e) {
            AcmsLogger::notice('メディアのアップロードに失敗しました。' . $e->getMessage(), Common::exceptionArray($e, ['mid' => $mid, 'data' => $data]));

            Common::responseJson([
                'status' => 'failure',
                'message' => $e->getMessage(),
            ]);
        }
    }
}
