<?php

namespace Acms\Services\Login\Traits;

use Acms\Services\Facades\Common;
use DB;
use SQL;

/**
 * 確認コードを発行し、本人確認するための機能
 */
trait VeryfyCode
{
    /**
     * 確認コードのタイプを取得
     *
     * @return string
     */
    abstract protected function getVerifyCodeType(): string;

    /**
     * ランダムなコードを生成
     *
     * @param string $key
     * @return string
     */
    protected function createVerifyCode(string $key, int $lifetime): string
    {
        $code = strtoupper(Common::genPass(6));
        $this->saveVerifyCode($key, $code, $lifetime);

        return $code;
    }

    /**
     * あとで比較用にトークンを保存
     *
     * @param string $key
     * @param string $code
     * @param int $lifetime
     * @return void
     */
    protected function saveVerifyCode(string $key, string $code, int $lifetime): void
    {
        $type = $this->getVerifyCodeType();
        if (empty($key) || empty($type)) {
            return;
        }
        $sql = SQL::newInsert('token');
        $sql->addInsert('token_key', $key);
        $sql->addInsert('token_type', $type);
        $sql->addInsert('token_value', hash('sha256', $code));
        $sql->addInsert('token_expire', date('Y-m-d H:i:s', REQUEST_TIME + $lifetime));
        DB::query($sql->get(dsn()), 'exec');
    }

    /**
     * コードを確認
     *
     * @param string $key
     * @param string $code
     * @return bool
     */
    protected function varifyCode(string $key, string $code): bool
    {
        $type = $this->getVerifyCodeType();
        if (empty($key) || empty($type) || empty($code)) {
            return false;
        }
        $sql = SQL::newSelect('token');
        $sql->addWhereOpr('token_key', $key);
        $sql->addWhereOpr('token_type', $type);
        $sql->addWhereOpr('token_value', hash('sha256', $code));
        $sql->addWhereOpr('token_expire', date('Y-m-d H:i:s', REQUEST_TIME), '>');
        $t = DB::query($sql->get(dsn()), 'row');
        if (!isset($t['token_value'])) {
            return false;
        }
        if (hash_equals((string) $t['token_value'], hash('sha256', $code))) {
            return true;
        }
        return false;
    }

    /**
     * 使用したトークンを削除
     *
     * @param string $key
     * @return void
     */
    protected function removeVerifyCode(string $key): void
    {
        $type = $this->getVerifyCodeType();
        if (empty($key) || empty($type)) {
            return;
        }
        // 使用済みのトークンを削除
        $sql = SQL::newDelete('token');
        $sql->addWhereOpr('token_key', $key);
        $sql->addWhereOpr('token_type', $type);
        DB::query($sql->get(dsn()), 'exec');

        // 有効期限切れのトークンを削除
        $sql = SQL::newDelete('token');
        $sql->addWhereOpr('token_expire', date('Y-m-d H:i:s', REQUEST_TIME), '<');
        DB::query($sql->get(dsn()), 'exec');
    }
}
