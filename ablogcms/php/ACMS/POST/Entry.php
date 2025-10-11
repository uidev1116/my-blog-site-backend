<?php

class ACMS_POST_Entry extends ACMS_POST
{
    /**
     * @param $Entry
     * @return bool
     */
    function fix($Entry)
    {
        // strtotimeは、半角スペースを渡すとタイムスタンプを返し、空文字であればfalseを返す
        // dateとtimeを連結した際に，半角スペースしか残らなければ、正しくfalseにするため空文字に置き換える
        $strDt = ' ' !== ($strDt = $Entry->get('date') . ' ' . $Entry->get('time')) ? $strDt : '';
        $strStartDt = ' ' !== ($strStartDt = $Entry->get('start_date') . ' ' . $Entry->get('start_time')) ? $strStartDt : '';
        $strEndDt = ' ' !== ($strEndDt = $Entry->get('end_date') . ' ' . $Entry->get('end_time')) ? $strEndDt   : '';

        //----------
        // datetime
        if (false !== ($dt = strtotime($strDt))) {
            $Entry->setField('date', date('Y-m-d', $dt));
            $Entry->setField('time', date('H:i:s', $dt));
        } else {
            $Entry->setField('date', date('Y-m-d', REQUEST_TIME));
            $Entry->setField('time', date('H:i:s', REQUEST_TIME));
        }
        if (false !== ($dt = strtotime($strStartDt))) {
            $Entry->setField('start_date', date('Y-m-d', $dt));
            $Entry->setField('start_time', date('H:i:s', $dt));
        } else {
            $Entry->setField('start_date', '1000-01-01');
            $Entry->setField('start_time', '00:00:00');
        }
        if (false !== ($dt = strtotime($strEndDt))) {
            $Entry->setField('end_date', date('Y-m-d', $dt));
            $Entry->setField('end_time', date('H:i:s', $dt));
        } else {
            $Entry->setField('end_date', '9999-12-31');
            $Entry->setField('end_time', '23:59:59');
        }

        return true;
    }

    /**
     * @param $url
     */
    function responseRedirect($url)
    {
        $this->redirect($url);
    }

    /**
     * @return Field_Validation
     */
    function responseGet()
    {
        return $this->Post;
    }

    /**
     * ToDo: deprecated method 2.7.0
     */
    function validEntryCodeDouble($code, $bid = BID, $cid = null, $eid = null)
    {
        return Entry::validEntryCodeDouble($code, $bid, $cid, $eid);
    }

    /**
     * ToDo: deprecated method 2.7.0
     */
    function entryDelete($eid)
    {
        Entry::entryDelete($eid);
        return true;
    }

    /**
     * ToDo: deprecated method 2.7.0
     */
    function revisionDelete($eid)
    {
        Entry::revisionDelete($eid);
        return true;
    }

    /**
     * ToDo: deprecated method 2.7.0
     */
    function changeRevision($rvid, $eid, $bid)
    {
        return Entry::changeRevision($rvid, $eid, $bid);
    }

    /**
     * ToDo: deprecated method 2.7.0
     */
    function saveRelatedEntries($eid, $entryAry = [], $rvid = null)
    {
        Entry::saveRelatedEntries($eid, $entryAry, $rvid);
    }

    /**
     * ToDo: deprecated method 2.7.0
     */
    function saveEntryRevision($eid, $entryAry, $type = null, $memo = '')
    {
        return Entry::saveEntryRevision($eid, $entryAry, $type, $memo);
    }

    /**
     * ToDo: deprecated method 2.7.0
     */
    function saveFieldRevision($eid, $Field, $rvid)
    {
        return Entry::saveFieldRevision($eid, $Field, $rvid);
    }

    /**
     * ToDo: deprecated method 2.7.0
     */
    function updateCacheControl($start, $end, $bid = BID, $eid = EID)
    {
        return Entry::updateCacheControl($start, $end, $bid, $eid);
    }

    /**
     * ToDo: deprecated method 2.7.0
     */
    function deleteCacheControl($eid = EID)
    {
        return Entry::deleteCacheControl($eid);
    }

    /**
     * @param \Field $input
     * @param string $datetime
     * @return string
     */
    protected function getFixPublicDate($input, $datetime)
    {
        if (config('entry_edit_start_date_equal_with_entry') === 'on') {
            return $datetime;
        }
        return $input->get('start_date') . ' ' . $input->get('start_time');
    }
}
