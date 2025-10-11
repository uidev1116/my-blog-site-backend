<?php

namespace Acms\Services\SocialLogin;

use HTTP;
use Acms\Services\Facades\Session;

class Line
{
    /**
     * @var string
     */
    protected $appId;

    /**
     * @var string
     */
    protected $appSecret;

    /**
     * @var string
     */
    protected $accessToken;

    /**
     * CSRF用
     *
     * @var string
     */
    protected $state;

    /**
     * @var array
     */
    protected $loginUrlParam;

    /**
     * Line constructor.
     * @param string $appId
     * @param string $appSecret
     */
    public function __construct(string $appId, string $appSecret)
    {
        $this->appId = $appId;
        $this->appSecret = $appSecret;

        $session = Session::handle();
        if ($state = $session->get('line_login_state')) {
            $this->state = $state;
        } else {
            $this->state = uniqueString();
            $session->set('line_login_state', $this->state);
            $session->save();
        }

        $this->loginUrlParam = [
            'response_type' => 'code',
            'client_id' => $this->appId,
            'redirect_uri' => $this->getRedirectUrl(),
            'state' => $this->state,
            'scope' => 'profile',
        ];
    }

    /**
     * 認証URLを取得
     *
     * @return string
     */
    public function getAuthUrl(): string
    {
        $query = [];
        foreach ($this->loginUrlParam as $key => $val) {
            $query[] = "{$key}={$val}";
        }
        return 'https://access.line.me/oauth2/v2.1/authorize?' . implode('&', $query);
    }

    /**
     * アクセストークンを取得
     *
     * @param string $code
     * @return string
     */
    public function getAccessToken(string $code): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }
        if (empty($code)) {
            throw new \RuntimeException('Empty code.');
        }
        $postData = [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $this->getRedirectUrl(),
            'client_id'     => $this->appId,
            'client_secret' => $this->appSecret,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        curl_setopt($ch, CURLOPT_URL, 'https://api.line.me/oauth2/v2.1/token');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        Http::setCurlProxy($ch);

        $response = curl_exec($ch);
        curl_close($ch);

        $json = json_decode($response);
        if (!property_exists($json, 'access_token')) {
            throw new \RuntimeException('Failed to get access token.');
        }
        $this->accessToken = $json->access_token;

        return $this->accessToken;
    }

    /**
     * ユーザー情報を取得
     *
     * @param string $accessToken
     * @return array
     */
    public function getLineAccount($accessToken): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
        curl_setopt($ch, CURLOPT_URL, 'https://api.line.me/v2/profile');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        Http::setCurlProxy($ch);

        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response, true);
        if (!isset($data['userId'])) {
            throw new \RuntimeException('Failed to get profile.');
        }
        return $data;
    }

    /**
     * Line OAuth認証のコールバック
     *
     * @return string
     */
    protected function getRedirectUrl(): string
    {
        return acmsLink([
            'protocol' => SSL_ENABLE ? 'https' : 'http',
            'bid' => BID,
        ], false) . 'callback/signin/line.html';
    }
}
