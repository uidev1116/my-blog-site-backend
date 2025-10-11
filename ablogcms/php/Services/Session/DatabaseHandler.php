<?php

namespace Acms\Services\Session;

use SessionHandlerInterface;
use DB;
use SQL;

class DatabaseHandler implements SessionHandlerInterface
{
    /**
     * @inheritDoc
     */
    public function open($savePath, $sessionName): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    #[\ReturnTypeWillChange]
    public function read($id)
    {
        if (!$this->isValidSessionId($id)) {
            return '';
        }
        $sql = SQL::newSelect('session_php');
        $sql->addSelect('session_data');
        $sql->addWhereOpr('session_id', $id);
        $data = DB::query($sql->get(dsn()), 'one');

        return $data ? $data : '';
    }

    /**
     * @inheritDoc
     */
    public function write($id, $data): bool
    {
        if (!$this->isValidSessionId($id)) {
            return false;
        }
        $sql = SQL::newSelect('session_php');
        $sql->addSelect('session_id');
        $sql->addWhereOpr('session_id', $id);
        $expire = REQUEST_TIME + (int)ini_get('session.gc_maxlifetime');

        if (DB::query($sql->get(dsn()), 'one')) {
            $sql = SQL::newUpdate('session_php');
            $sql->addUpdate('session_expire', $expire);
            $sql->addUpdate('session_data', $data);
            $sql->addWhereOpr('session_id', $id);
        } else {
            $sql = SQL::newInsert('session_php');
            $sql->addInsert('session_id', $id);
            $sql->addInsert('session_expire', $expire);
            $sql->addInsert('session_data', $data);
        }
        DB::query($sql->get(dsn()), 'exec');

        return true; // 必ず true を返すことでセッション保存を失敗と誤認しない
    }

    /**
     * @inheritDoc
     */
    public function destroy($id): bool
    {
        if (!$this->isValidSessionId($id)) {
            return false;
        }
        $sql = SQL::newDelete('session_php');
        $sql->addWhereOpr('session_id', $id);
        DB::query($sql->get(dsn()), 'exec');

        return true;
    }

    /**
     * @inheritDoc
     */
    #[\ReturnTypeWillChange]
    public function gc($maxlifetime)
    {
        $sql = SQL::newDelete('session_php');
        $sql->addWhereOpr('session_expire', REQUEST_TIME, '<');
        DB::query($sql->get(dsn()), 'exec');

        return  DB::affected_rows();
    }

    /**
     * セッションIDとして安全かどうかを検証する
     */
    private function isValidSessionId(string $id): bool
    {
        return preg_match('/^[a-zA-Z0-9,-]{16,128}$/', $id) === 1;
    }
}
