<?php

namespace Acms\Services\Entry;

use SQL;
use DB;
use ACMS_RAM;

class Lock
{
    /**
     * ロック機能が有効か
     * @param boolean $enable
     */
    protected $enable = false;

    /**
     * アラートのみで実際のロックはしない
     * @param boolean $alertOnly
     */
    protected $alertOnly = false;

    /**
     * ロックの有効時間
     * @param boolean $expire
     */
    protected $expire = 48;

    /**
     * Construct
     */
    public function __construct($enable, $alertOnly, $expire)
    {
        $this->enable = $enable === 'on' && editionWithProfessional(); // プロ版以上に限定
        $this->alertOnly = $alertOnly === 'on';
        $this->expire = intval($expire);
    }

    /**
     * @return boolean
     */
    public function isAlertOnly()
    {
        return $this->alertOnly;
    }

    /**
     * @return int
     */
    public function getExpiredDatetime()
    {
        return REQUEST_TIME - ($this->expire * 60 * 60);
    }

    /**
     * エントリーを保存できるか判定
     *
     * @param int $eid
     * @param int|null $rvid
     * @param int $suid
     * @return false|array
     */
    public function getLockedUser($eid, $rvid, $suid = null)
    {
        $expire = $this->getExpiredDatetime();
        $table = $this->getLockTarget($eid, $rvid);
        $sql = SQL::newSelect($table);
        $sql->addWhereOpr('entry_id', $eid);

        if ($table === 'entry_rev') {
            $sql->addWhereOpr('entry_rev_id', $rvid);
        }
        $sql->addWhereOpr('entry_lock_datetime', date('Y-m-d H:i:s', $expire), '>');
        if ($suid) {
            $sql->addWhereOpr('entry_lock_uid', $suid, '<>');
        }
        $sql->addWhereOpr('entry_lock_uid', 1, '>=');
        if ($row = DB::query($sql->get(dsn()), 'row')) {
            return [
                'uid' => $row['entry_lock_uid'],
                'name' => ACMS_RAM::userName($row['entry_lock_uid']),
                'datetime' => $row['entry_lock_datetime'],
                'expire' => date('Y-m-d H:i:s', strtotime($row['entry_lock_datetime']) + ($this->expire * 60 * 60)),
            ];
        }
        return false;
    }

    /**
     * エントリーをアンロック
     *
     * @param int $eid
     * @param int|null $rvid
     */
    public function unlock($eid, $rvid)
    {
        if ($this->getLockTarget($eid, $rvid) === 'entry_rev') {
            // リビジョンをアンロック
            $sql = SQL::newUpdate('entry_rev');
            $sql->addUpdate('entry_lock_datetime', '1000-01-01 00:00:00');
            $sql->addUpdate('entry_lock_uid', 0);
            $sql->addWhereOpr('entry_id', $eid);
            $sql->addWhereOpr('entry_rev_id', $rvid);
            DB::query($sql->get(dsn()), 'exec');
        } else {
            // エントリーをアンロック
            $sql = SQL::newUpdate('entry');
            $sql->addUpdate('entry_lock_datetime', '1000-01-01 00:00:00');
            $sql->addUpdate('entry_lock_uid', 0);
            $sql->addWhereOpr('entry_id', $eid);
            DB::query($sql->get(dsn()), 'exec');
        }
    }

    /**
     * エントリーをロック
     *
     * @param int $eid
     * @param int|null $rvid
     * @param int $suid
     * @return void
     */
    public function lock($eid, $rvid, $suid)
    {
        $this->validateLock($eid, $rvid, $suid);

        if ($this->getLockTarget($eid, $rvid) === 'entry_rev') {
            // リビジョンをロック
            $sql = SQL::newUpdate('entry_rev');
            $sql->addUpdate('entry_lock_datetime', date('Y-m-d H:i:s', REQUEST_TIME));
            $sql->addUpdate('entry_lock_uid', $suid);
            $sql->addWhereOpr('entry_id', $eid);
            $sql->addWhereOpr('entry_rev_id', $rvid);
            DB::query($sql->get(dsn()), 'exec');
        } else {
            // エントリーをロック
            $sql = SQL::newUpdate('entry');
            $sql->addUpdate('entry_lock_datetime', date('Y-m-d H:i:s', REQUEST_TIME));
            $sql->addUpdate('entry_lock_uid', $suid);
            $sql->addWhereOpr('entry_id', $eid);
            DB::query($sql->get(dsn()), 'exec');
        }
    }

    /**
     * 指定されたUIDがロックしているものを全て解除
     *
     * @param int $uid
     * @return void
     */
    public function unlockByUser($uid)
    {
        $sql = SQL::newUpdate('entry');
        $sql->addUpdate('entry_lock_datetime', '1000-01-01 00:00:00');
        $sql->addUpdate('entry_lock_uid', 0);
        $sql->addWhereOpr('entry_lock_uid', $uid);
        DB::query($sql->get(dsn()), 'exec');

        $sql = SQL::newUpdate('entry_rev');
        $sql->addUpdate('entry_lock_datetime', '1000-01-01 00:00:00');
        $sql->addUpdate('entry_lock_uid', 0);
        $sql->addWhereOpr('entry_lock_uid', $uid);
        DB::query($sql->get(dsn()), 'exec');
    }

    /**
     * エントリーをロックできるかバリデート
     *
     * @param int $eid
     * @param int $rvid
     * @param int $suid
     */
    protected function validateLock($eid, $rvid, $suid)
    {
        if (!$this->enable) {
            throw new \RuntimeException('Lock function is not enabled.');
        }
        if ($this->getLockedUser($eid, $rvid, $suid) !== false) {
            throw new \RuntimeException('Already locked by another user.');
        }
    }

    /**
     * ロック判定するテーブルを判定して取得
     *
     * @return string
     */
    protected function getLockTarget($eid, $rvid)
    {
        if ($rvid && $rvid > 1) {
            return 'entry_rev';
        }
        if (sessionWithApprovalAdministrator()) {
            return 'entry';
        }
        if (enableApproval()) {
            if (ACMS_RAM::entryApproval($eid) === 'pre_approval') {
                return 'entry';
            }
            return 'entry_rev';
        }
        return 'entry';
    }
}
