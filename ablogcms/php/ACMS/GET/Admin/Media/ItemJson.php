<?php

use Acms\Services\Facades\Media;

class ACMS_GET_Admin_Media_ItemJson extends ACMS_GET
{
    public function get()
    {
        try {
            if (!Media::validate()) {
                throw new \RuntimeException('You are not authorized to upload media.');
            }
            $mid = (int) $this->Get->get('_mid');
            $json = $this->buildJson($mid);
            $json['status'] = 'success';
            Common::responseJson($json);
        } catch (\Exception $e) {
            AcmsLogger::notice('メディアの詳細情報のJSON取得に失敗しました', Common::exceptionArray($e));

            Common::responseJson([
                'status' => 'failure',
                'message' => $e->getMessage(),
            ]);
        }
    }


    /**
     * @param int $mid
     * @return array{item: array{media_status: string, media_title: string, media_label: string, media_last_modified: string, media_datetime: string, media_id: int, media_bid: int, media_blog_name: string, media_user_id: int, media_user_name: string, media_last_update_user_id: int|'', media_last_update_user_name: string, media_size: string, media_filesize: int, media_path: string, media_edited: string, media_original: string, media_thumbnail: string, media_permalink: string, media_type: string, media_ext: string, media_caption: string, media_link: string, media_alt: string, media_text: string, media_focal_point: string, media_editable: bool, media_pdf_page: string, checked: false}}
     */
    protected function buildJson(int $mid): array
    {
        $data = Media::getMedia($mid);
        if ($data === null) {
            throw new \RuntimeException(sprintf(
                'The specified media could not be found. Media ID: %d. It may have been deleted or the provided ID does not exist.',
                $mid
            ));
        }
        $tags = Media::getMediaLabel($mid);

        return [
            'item' => Media::buildJson($mid, $data, $tags, $data['bid']),
        ];
    }
}
