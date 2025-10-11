<?php

namespace Acms\Services\Mailer\Transport;

use Google_Client;
use Google_Exception;
use Acms\Services\Facades\LocalStorage;
use Acms\Services\Facades\Logger as AcmsLogger;
use Acms\Services\Facades\Config;
use Acms\Services\Facades\Cache;
use Acms\Services\Facades\Common;
use Field;
use InvalidArgumentException;
use RuntimeException;

class GoogleApi
{
    /**
     * @var Google_Client
     */
    private $client;

    /**
     * @var Field
     */
    private $config;

    /**
     * @var string
     */
    private $scopes = 'https://mail.google.com/';

    /**
     * @var string
     */
    private $jsonPathConfigKey = 'mail_google_smtp_clientid_json';

    /**
     * @var string
     */
    private $accessTokenConfigKey = 'mail_google_smtp_access_token';

    /**
     * @var int
     */
    private $bid;

    /**
     * @var int|null
     */
    private $setid = null;

    /**
     * 初期化
     *
     * @param int $bid
     * @param null|int $setid
     * @return void
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @throws Google_Exception
     */
    public function init(int $bid, ?int $setid = null): void
    {
        if (empty($setid)) {
            $setid = null;
        }
        $this->bid = $bid;
        $this->setid = $setid;

        $this->client = new Google_Client();
        $this->config = Config::loadDefaultField();
        if ($setid) {
            $this->config->overload(Config::loadConfigSet($this->setid));
        } else {
            $this->config->overload(Config::loadBlogConfig($this->bid));
        }

        $idJsonPath = $this->config->get($this->jsonPathConfigKey);
        $this->client->setApplicationName('ACMS_GMAIL_SMTP');
        $this->client->setScopes($this->scopes);
        if ($idJsonPath !== '' && LocalStorage::exists($idJsonPath)) {
            $this->setAuthConfig($idJsonPath);
        }
        $this->client->setAccessType('offline');
        $this->client->setPrompt("consent");
        $redirect_uri = acmsLink([
            'bid' => BID,
        ], false) . 'callback/smtp/google.html';

        $this->client->setRedirectUri($redirect_uri);
        $accessToken = json_decode($this->config->get($this->accessTokenConfigKey), true);

        if ($accessToken) {
            // AcmsLogger::debug('アクセストークンがDBに見つかりました');
            $this->client->setAccessToken($accessToken);
            if ($this->client->isAccessTokenExpired()) {
                try {
                    // AcmsLogger::debug('アクセストークンが期限切れ');
                    $refreshToken = $this->client->getRefreshToken();
                    if (!$refreshToken) {
                        throw new RuntimeException('リフレッシュトークンが取得できません。再認証が必要です。');
                    }
                    // AcmsLogger::debug('リフレッシュトークンを取得', [
                    //     'refreshToken' => $re                freshToken,
                    // ]);
                    $this->client->refreshToken($refreshToken);
                    $accessToken = $this->client->getAccessToken();
                    $accessToken['refresh_token'] = $refreshToken; // approval_prompt=force だと、初回時のみしかリフレッシュトークンが取得できないため、再設定する
                    // AcmsLogger::debug('リフレッシュトークンからアクセストークンを取得', [
                    //     'accessToken' => $accessToken,
                    // ]);
                    $this->updateAccessToken(json_encode($accessToken));
                } catch (\Exception $e) {
                    AcmsLogger::error(
                        'Gmail API のアクセストークンの更新に失敗しました。',
                        Common::exceptionArray($e)
                    );
                }
            }
        }
    }

    /**
     * APIのスコープを取得
     *
     * @return string
     */
    public function getScopes(): string
    {
        return $this->scopes;
    }

    /**
     * アクセストークンを保存するコンフィグのキーを取得する
     *
     * @return string
     */
    public function getAccessTokenConfigKey(): string
    {
        return $this->accessTokenConfigKey;
    }

    /**
     * 認証情報をセット
     *
     * @param string $json
     * @return void
     * @throws InvalidArgumentException
     * @throws RuntimeException
     * @throws Google_Exception
     */
    public function setAuthConfig(string $json): void
    {
        if ($json === '') {
            throw new InvalidArgumentException('Invalid client secret JSON file path.');
        }
        if (!LocalStorage::exists($json)) {
            throw new RuntimeException('Failed to open ' . $json);
        }
        $json = file_get_contents($json);

        /** @var \stdClass $data */
        $data = json_decode($json);
        $key = isset($data->installed) ? 'installed' : 'web';
        if (!property_exists($data, $key)) {
            throw new Google_Exception("Invalid client secret JSON file.");
        }
        $obj = $data->$key;
        $this->client->setClientId($obj->client_id);
        $this->client->setClientSecret($obj->client_secret);
    }

    /**
     * Get Google Client
     *
     * @return Google_Client
     */
    public function getClient(): Google_Client
    {
        return $this->client;
    }

    /**
     * Get access token.
     *
     * @return array
     */
    public function getAccessToken()
    {
        $accessToken = json_decode($this->config->get($this->accessTokenConfigKey), true);
        // AcmsLogger::debug('アクセストークンを取得', $accessToken);
        return $accessToken;
    }

    /**
     * Update access token.
     *
     * @param string $accessToken
     * @return void
     */
    public function updateAccessToken(string $accessToken): void
    {
        $this->config->set($this->accessTokenConfigKey, $accessToken);

        $config = new Field();
        $config->set($this->accessTokenConfigKey, $accessToken);
        Config::saveConfig($config, $this->bid, null, null, $this->setid);
        Cache::flush('config');
    }
}
