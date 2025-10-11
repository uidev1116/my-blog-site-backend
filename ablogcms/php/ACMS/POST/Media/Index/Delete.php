<?php

use Acms\Services\Facades\Media;
use Acms\Services\Facades\Common;
use Acms\Services\Facades\Logger as AcmsLogger;

class ACMS_POST_Media_Index_Delete extends ACMS_POST
{
    function post()
    {

        try {
            $this->Post->reset(true);
            $this->Post->setMethod('checks', 'required');
            if (!Media::validate()) {
                $this->Post->setMethod('media', 'operable', false);
            }
            $this->Post->validate(new ACMS_Validator());
            if (!$this->Post->isValidAll()) {
                throw new \RuntimeException('Permission denied');
            }
            @set_time_limit(0);
            $targetMIDs = [];
            foreach ($this->Post->getArray('checks') as $mid) {
                $id = preg_split('@:@', $mid, 2, PREG_SPLIT_NO_EMPTY);
                $mbid = intval($id[0]);
                $mid = intval($id[1]);
                if (
                    !(1
                    && $mid && $mbid
                    && ACMS_RAM::blogLeft(SBID) <= ACMS_RAM::blogLeft($mbid)
                    && ACMS_RAM::blogRight(SBID) >= ACMS_RAM::blogRight($mbid)
                    )
                ) {
                    continue;
                }
                Media::deleteItem($mid);
                $targetMIDs[] = $mid;
            }
            if (!empty($targetMIDs)) {
                AcmsLogger::info('メディアを一覧から一括削除しました', [
                    'targetMIDs' => $targetMIDs,
                ]);
            }
            Common::responseJson([
                'status' => 'success'
            ]);
        } catch (\Exception $e) {
            if (!$this->Post->isValid('media', 'operable')) {
                AcmsLogger::info('権限がないため、メディアを一覧から削除できませんでした');
            } elseif (!$this->Post->isValid('checks', 'required')) {
                AcmsLogger::info('メディアが指定されていないため、メディアを一覧から削除できませんでした');
            } else {
                AcmsLogger::warning($e->getMessage(), Common::exceptionArray($e));
            }
            Common::responseJson([
                'status' => 'failure',
                'message' => $e->getMessage(),
            ]);
        }
    }
}
