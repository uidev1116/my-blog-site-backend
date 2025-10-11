<?php

class ACMS_POST_Media_Index_Tags extends ACMS_POST_Media_Tags
{
    function post()
    {
        $this->Post->reset(true);
        $this->Post->setMethod('checks', 'required');
        $this->Post->setMethod('tags', 'required');
        $this->Post->validate(new ACMS_Validator());

        if ($this->Post->isValidAll()) {
            @set_time_limit(0);
            $targetMIDs = [];
            foreach ($this->Post->getArray('checks') as $mid) {
                try {
                    $id     = preg_split('@:@', $mid, 2, PREG_SPLIT_NO_EMPTY);
                    $mbid   = intval($id[0]);
                    $mid    = intval($id[1]);
                    if (
                        !(1
                        && $mid && $mbid
                        && ACMS_RAM::blogLeft(SBID) <= ACMS_RAM::blogLeft($mbid)
                        && ACMS_RAM::blogRight(SBID) >= ACMS_RAM::blogRight($mbid)
                        )
                    ) {
                        continue;
                    }
                    $this->addTag($mid, $mbid, $this->Post->get('tags'));
                    $targetMIDs[] = $mid;
                } catch (\Exception $e) {
                    AcmsLogger::info('メディアタグを一覧から追加することができませんでした', [
                        'mid' => $targetMIDs,
                        'tags' => $this->Post->get('tags'),
                        'message' => $e->getMessage(),
                    ]);
                }
            }
            if (!empty($targetMIDs)) {
                AcmsLogger::info('メディアタグを一覧から追加しました', [
                    'mid' => $targetMIDs,
                    'tags' => $this->Post->get('tags'),
                ]);
            }
        }
        Common::setSafeHeadersWithoutCache(200, 'text/plain');
        die();
    }
}
