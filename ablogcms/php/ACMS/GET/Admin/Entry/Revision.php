<?php

class ACMS_GET_Admin_Entry_Revision extends ACMS_GET_Admin
{
    /**
     * @param int $eid
     * @param int $rvid
     * @return array<string, mixed>|false
     */
    protected function getRevision($eid, $rvid)
    {
        $sql = SQL::newSelect('entry_rev');
        $sql->addWhereOpr('entry_id', $eid);
        $sql->addWhereOpr('entry_rev_id', $rvid);

        return DB::query($sql->get(dsn()), 'row');
    }

    protected function getCurrentRevisionId($eid)
    {
        $sql = SQL::newSelect('entry');
        $sql->addSelect('entry_current_rev_id');
        $sql->addWhereOpr('entry_id', $eid);
        $currentRvid = DB::query($sql->get(dsn()), 'one');
        return intval($currentRvid);
    }

    protected function getReserveRevisionId($eid)
    {
        $sql = SQL::newSelect('entry');
        $sql->addSelect('entry_reserve_rev_id');
        $sql->addWhereOpr('entry_id', $eid);
        $reserveRvid = DB::query($sql->get(dsn()), 'one');
        return intval($reserveRvid);
    }


    protected function getRevisionsData($eid)
    {
        $sql = SQL::newSelect('entry_rev');
        $sql->addWhereOpr('entry_id', $eid);
        $sql->setOrder('entry_rev_datetime', 'desc');

        return DB::query($sql->get(dsn()), 'all');
    }

    protected function countRevisions($eid)
    {
        $sql = SQL::newSelect('entry_rev');
        $sql->addSelect('entry_id', 'revision_amount', null, 'COUNT');
        $sql->addWhereOpr('entry_id', $eid);

        return DB::query($sql->get(dsn()), 'one');
    }
}
