<?php

namespace Acms\Services\SocialLogin;

use Acms\Services\Facades\Session;
use Acms\Services\Facades\Logger;
use Acms\Services\Facades\Common;
use League\OAuth1\Client\Credentials\CredentialsException;
use League\OAuth1\Client\Server\Twitter as TwitterOAuth;
use League\OAuth1\Client\Credentials\TokenCredentials;
use RuntimeException;

class Twitter
{
    /**
     * OAuthプロバイダー
     *
     * @var \League\OAuth1\Client\Server\Twitter
     */
    protected $server;

    /**
     * Constructor
     *
     * @param string $consumerKey
     * @param string $consumerSecret
     */
    public function __construct(string $consumerKey, string $consumerSecret)
    {
        $this->server = new TwitterOAuth([
            'identifier' => $consumerKey,
            'secret' => $consumerSecret,
            'callback_uri' => $this->getRedirectUrl(),
            'scope' => 'read',
        ]);
    }

    /**
     * OAuth認証URLを取得
     *
     * @return string
     */
    public function getAuthUrl(): string
    {
        try {
            // Retrieve temporary credentials
            $temporaryCredentials = $this->server->getTemporaryCredentials();
        } catch (CredentialsException $e) {
            Logger::error(
                'X（旧Twitter）の認証情報の取得に失敗しました。コンフィグで設定したConsumer Key及びConsumer Secretが間違っている可能性があります。',
                Common::exceptionArray($e)
            );
            return '';
        }

        // Store credentials in the session, we'll need them later
        $session = Session::handle();
        $session->set('twitter_temporary_credentials', $temporaryCredentials);
        $session->save();

        // Second part of OAuth 1.0 authentication is to redirect the
        // resource owner to the login screen on the server.
        return $this->server->getAuthorizationUrl($temporaryCredentials);
    }

    /**
     * OAuth認証のコールバック
     *
     * @return string
     */
    protected function getRedirectUrl(): string
    {
        return acmsLink([
            'protocol' => SSL_ENABLE ? 'https' : 'http',
            'bid' => BID,
        ], false) . 'callback/signin/twitter.html';
    }

    /**
     * Token Credentials を取得
     *
     * @param string $oauthToken
     * @param string $oauthVerifier
     * @return TokenCredentials;
     * @throws RuntimeException
     */
    public function getTokenCredentials(string $oauthToken, string $oauthVerifier): TokenCredentials
    {
        if (empty($oauthToken)) {
            throw new RuntimeException('Empty OAuth token.');
        }
        if (empty($oauthVerifier)) {
            throw new RuntimeException('Empty OAuth Verifier.');
        }
        $session = Session::handle();

        // Retrieve the temporary credentials we saved before
        $temporaryCredentials = $session->get('twitter_temporary_credentials');

        // We will now retrieve token credentials from the server
        $tokenCredentials = $this->server->getTokenCredentials($temporaryCredentials, $oauthToken, $oauthVerifier);

        return $tokenCredentials;
    }

    /**
     * ユーザー情報を取得
     *
     * @param TokenCredentials $tokenCredentials
     * @return array
     */
    public function getTwitterAccount(TokenCredentials $tokenCredentials): array
    {
        $user = $this->server->getUserDetails($tokenCredentials);

        return [
            'sub' => $user->uid,
            'email' => $user->email,
            'name' => $user->nickname,
            'picture' => $user->imageUrl,
        ];
    }
}
