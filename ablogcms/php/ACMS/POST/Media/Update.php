<?php

use Acms\Services\Facades\Common;
use Acms\Services\Facades\Media;

class ACMS_POST_Media_Update extends ACMS_POST_Media
{
    protected $uploadFieldName = 'media_file';

    public function post()
    {
        $mid = (int)$this->Get->get('_mid');
        $data = [];

        try {
            if (!Media::validate()) {
                throw new \RuntimeException('メディア機能が有効でないか、権限がありません');
            }
            $Media = $this->extract('media');
            if ($mid === 0 || !Media::canEdit($mid)) {
                $Media->setMethod('media', 'operable', false);
            }
            $Media->validate(new ACMS_Validator_Media());
            if (!$this->Post->isValidAll()) {
                if (!$Media->isValid('media', 'operable')) {
                    throw new \RuntimeException('メディアが指定されていない、または権限がありません');
                }
            }
            $tags = $Media->get('media_label');
            $oldData = Media::getMedia($mid);

            if (isset($_FILES[$this->uploadFieldName])) {
                // ファイルアップロードがある場合（メディアを変更機能 or メディア画像編集機能利用時）
                $name = $Media->get('file_name');
                Common::validateFileUpload($this->uploadFieldName);

                $info = Media::getBaseInfo($_FILES[$this->uploadFieldName], $tags, $name);
                $replaced = $Media->get('replaced') === 'true';
                $type = mime_content_type($_FILES[$this->uploadFieldName]['tmp_name']);

                $_FILES[$this->uploadFieldName]['name'] = $name; // ファイル名を設定
                if (Media::isImageFile($type)) {
                    Media::deleteImage($mid, $replaced);
                    $data = Media::uploadImage($this->uploadFieldName, $replaced);
                    if ($replaced) {
                        $data['original'] = otherSizeImagePath($data['path'], 'large');
                    }
                } elseif (Media::isSvgFile($type)) {
                    $data = Media::uploadSvg($info['size'], $this->uploadFieldName);
                    Media::deleteFile($mid);
                } else {
                    $data = Media::uploadFile($info['size'], $this->uploadFieldName);
                    Media::deleteFile($mid);
                }
                $data['upload_date'] = $oldData['upload_date'];
            } else {
                $data = $oldData;

                $filename = $Media->get('file_name');
                if ($filename !== '' && $filename !== $oldData['name']) {
                    // ファイルアップロードを行う場合は、アップロード時にファイル名を指定しているため、
                    // ファイル名の変更はファイルアップロードを行わない場合のみ行う
                    $data = Media::rename($data, $filename);
                }
            }
            // pdf thumbnail
            if (isset($_FILES['media_pdf_thumbnail'])) {
                Media::deleteThumbnail($mid);
                $res = Media::uploadPdfThumbnail('media_pdf_thumbnail');
                if (isset($res['path'])) {
                    $data['thumbnail'] = $res['path'];
                    $data['size'] = $res['size'];
                }
            }
            $data['update_date'] = date('Y-m-d H:i:s', REQUEST_TIME);
            $data['last_update_user_id'] = SUID;
            $data['status'] = $Media->get('status');
            $data['field_1'] = $Media->get('field_1');
            $data['field_2'] = $Media->get('field_2');
            $data['field_3'] = $Media->get('field_3');
            $data['field_4'] = $Media->get('field_4');

            $focalPoint = $this->detectFocalPoint($Media);
            if ($focalPoint !== null) {
                [$focalX, $focalY] = $focalPoint;
                $data['field_5'] = $focalX . ',' . $focalY;
            } else {
                $data['field_5'] = '';
            }
            if ($pdfPage = $Media->get('pdf_page')) {
                $data['field_6'] = $pdfPage;
            } else {
                $data['field_6'] = '';
            }
            Media::updateMedia($mid, $data, BID);
            Media::saveTags($mid, $tags, BID);

            $data = Media::getMedia($mid);
            $tags = Media::getMediaLabel($mid);
            $data['editable'] = true;
            $json = Media::buildJson($mid, $data, $tags, BID);
            $json['status'] = 'success';

            if (HOOK_ENABLE) {
                $Hook = ACMS_Hook::singleton();
                $Hook->call('saveMedia', [$mid, 'update', isset($_FILES[$this->uploadFieldName])]);
            }

            AcmsLogger::info('メディアを更新しました', [
                'mid' => $mid,
                'data' => $data,
            ]);

            Common::responseJson($json);
        } catch (\Exception $e) {
            AcmsLogger::info('メディアの更新に失敗しました。' . $e->getMessage(), Common::exceptionArray($e, ['mid' => $mid, 'data' => $data]));

            Common::responseJson([
                'status' => 'failure',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 画像の焦点を検出する
     * @param \Field $media
     * @return array{0:float, 1:float} | null
     */
    protected function detectFocalPoint(\Field $media): ?array
    {
        if ($media->get('focal_x') === '' || $media->get('focal_y') === '') {
            return null;
        }
        $focalX = (float)$media->get('focal_x');
        $focalY = (float)$media->get('focal_y');
        if ($focalX >= 0 && $focalX <= 100 && $focalY >= 0 && $focalY <= 100) {
            return [$focalX, $focalY];
        }
        return null;
    }
}
