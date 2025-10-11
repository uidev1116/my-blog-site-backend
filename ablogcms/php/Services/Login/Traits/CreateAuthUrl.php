<?php

namespace Acms\Services\Login\Traits;

use Acms\Services\Facades\Common;
use Acms\Services\Facades\Mailer;
use Field_Validation;
use Exception;
use DB;
use SQL;

/**
 * 認証URLを作成し、メール認証するための機能
 */
trait CreateAuthUrl
{
    /**
     * トークンのキーを取得
     *
     * @return string
     */
    abstract protected function getTokenKey(): string;

    /**
     * トークンのタイプを取得
     *
     * @return string
     */
    abstract protected function getTokenType(): string;

    /**
     * 認証メールの件名テンプレートを取得
     *
     * @return string
     */
    abstract protected function getSubjectMailTemplate(): string;

    /**
     * 認証メールの本文テンプレートを取得
     *
     * @return string
     */
    abstract protected function getBodyMailTemplate(): string;

    /**
     *　認証メールの本文（HTML）テンプレートを取得
     *
     * @return string
     */
    abstract protected function getBodyHtmlMailTemplate(): string;

    /**
     * 認証メールの送信元アドレスを取得
     *
     * @return string
     */
    abstract protected function getFromAddress(): string;

    /**
     * 認証メールのBCCアドレスを取得
     *
     * @return string
     */
    abstract protected function getBccAddress(): string;

    /**
     * ランダムなトークンを生成
     *
     * @return string
     */
    protected function createToken(): string
    {
        return Common::genPass(32);
    }

    /**
     * 有効期限付きの認証用URLを作成
     *
     * @param array $urlContext
     * @param string $token
     * @param array $data
     * @param int $lifetime
     * @return string
     */
    protected function createAuthUrl(array $urlContext, string $token, array $data, int $lifetime): string
    {
        $baseUrlCoctext = [
            'protocol' => SSL_ENABLE ? 'https' : 'http',
            'bid' => BID,
        ];
        $uri = acmsLink(array_merge($baseUrlCoctext, $urlContext), false);
        $parameters = $this->createAuthQueryParams($token, $lifetime);
        $this->saveToken($token, $data, $lifetime);

        return "{$uri}?{$parameters}";
    }

    /**
     * あとで比較用にトークンを保存
     *
     * @param string $token
     * @param array $data
     * @return void
     */
    protected function saveToken(string $token, array $data, int $lifetime): void
    {
        $key = $this->getTokenKey();
        $type = $this->getTokenType();

        if (empty($key) || empty($type)) {
            return;
        }
        $data = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $sql = SQL::newInsert('token');
        $sql->addInsert('token_key', $key);
        $sql->addInsert('token_type', $type);
        $sql->addInsert('token_value', hash('sha256', $token));
        $sql->addInsert('token_data', $data ? $data : '');
        $sql->addInsert('token_expire', date('Y-m-d H:i:s', REQUEST_TIME + $lifetime));
        DB::query($sql->get(dsn()), 'exec');
    }

    /**
     * 認証パラメータを組み立て
     *
     * @param string $token
     * @param int $lifetime
     * @return string
     */
    protected function createAuthQueryParams(string $token, int $lifetime): string
    {
        $salt = Common::genPass(32); // 事前共有鍵
        $data = [
            'token' => $token,
            'expire' => REQUEST_TIME + $lifetime, // 有効期限
        ];
        $context = acmsSerialize($data);
        $prk = hash_hmac('sha256', Common::getCurrentSalt(), $salt);
        $derivedKey = hash_hmac('sha256', $prk, $context);
        $params = http_build_query([
            'key' => $derivedKey,
            'salt' => $salt,
            'context' => $context,
        ]);
        return $params;
    }

    /**
     * 認証メールを送信
     *
     * @param string $to
     * @param Field_Validation $inputField
     * @param string $authUrl
     * @return bool
     * @throws Exception
     */
    protected function send(string $to, Field_Validation $inputField, string $authUrl): bool
    {
        $isSend = false;
        $inputField->setField('authUrl', $authUrl);

        if (empty($to)) {
            return false;
        }
        $subjectTpl = $this->getSubjectMailTemplate();
        $bodyTpl = $this->getBodyMailTemplate();
        $bodyHtmlTpl = $this->getBodyHtmlMailTemplate();
        $from = $this->getFromAddress();
        $bcc = $this->getBccAddress();

        if (empty($subjectTpl) || empty($bodyTpl) || empty($from)) {
            return false;
        }
        $subject = Common::getMailTxt($subjectTpl, $inputField);
        $body = Common::getMailTxt($bodyTpl, $inputField);

        try {
            $mailer = Mailer::init();
            $mailer = $mailer->setFrom($from)
                ->setTo($to)
                ->setSubject($subject)
                ->setBody($body);
            if ($bcc) {
                $mailer = $mailer->setBcc($bcc);
            }
            if ($bodyHtmlTpl) {
                $bodyHtml = Common::getMailTxt($bodyHtmlTpl, $inputField);
                $mailer = $mailer->setHtml($bodyHtml);
            }
            $mailer->send();
            $isSend = true;
        } catch (Exception $e) {
            return false;
        }
        return $isSend;
    }
}
