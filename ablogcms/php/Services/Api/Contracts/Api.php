<?php

namespace Acms\Services\Api\Contracts;

use Acms\Services\Api\Exceptions\ApiKeyException;
use Acms\Services\Api\Exceptions\ForbiddenException;
use Acms\Services\Facades\Common;
use Acms\Services\Facades\Logger as AcmsLogger;
use Exception;

abstract class Api
{
    /**
     * @var string
     */
    protected $apiKey = '';

    /**
     * @var array
     */
    protected $restrictionReferrer = [];

    /**
     * @var array
     */
    protected $restrictionAddress = [];

    /**
     * @var array
     */
    protected $allowOriginDomains = [];

    /**
     * @var array
     */
    protected $apiInfo = [];

    /**
     * APIのレスポンスを組み立て
     * @param array $apiInfo
     * @return string
     */
    abstract protected function buildResponse(array $apiInfo): string;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->apiKey = config('x_api_key');
        $this->restrictionReferrer = configArray('api_restriction_referer');
        $this->restrictionAddress = configArray('api_restriction_address');
        $this->allowOriginDomains = configArray('api_allow_domain');
    }

    /**
     * GET APIを実行
     * @param array $apiInfo
     * @return never
     */
    protected function exec(array $apiInfo): void
    {
        try {
            $this->validateAddress();
            $this->validateReferrer();
            $this->validateApiKey();
            $json = $this->buildResponse($apiInfo);
        } catch (ApiKeyException $e) {
            $this->logging($e, $apiInfo);
            httpStatusCode('401 Unauthorized');
            $json = json_encode([
                'status' => 401,
                'error' => '401 Unauthorized',
                'message' => $e->getMessage(),
                'path' => REQUEST_PATH,
            ]);
        } catch (ForbiddenException $e) {
            $this->logging($e, $apiInfo);
            httpStatusCode('403 Forbidden');
            $json = json_encode([
                'status' => 403,
                'error' => '403 Forbidden',
                'message' => $e->getMessage(),
                'path' => REQUEST_PATH,
            ]);
        } catch (Exception $e) {
            $this->logging($e, $apiInfo);
            httpStatusCode('404 Not Found');
            $json = json_encode([
                'status' => 404,
                'error' => '404 Not Found',
                'message' => $e->getMessage(),
                'path' => REQUEST_PATH,
            ]);
        }
        $this->response($json);
    }

    /**
     * 404 Not Found
     * @param string $message
     * @return void
     */
    protected function notFound(string $message): void
    {
        httpStatusCode('404 Not Found');
        $json = json_encode([
            'status' => 404,
            'error' => '404 Not Found',
            'message' => $message,
            'path' => REQUEST_PATH,
        ]);
        $this->response($json);
    }

    /**
     * レスポンス
     * @param string $json
     * @return never
     */
    protected function response(string $json): void
    {
        header(PROTOCOL . ' ' . httpStatusCode());
        header("Content-Type: application/json; charset=utf-8");
        header('X-Robots-Tag: noindex');
        $this->addAllowOriginHeader();
        Common::addSecurityHeader();
        Common::clientCacheHeader(true);

        $responseBody = gzencode($json);
        if (ZIP_USE) {
            header('Content-Encoding: gzip');
            header('Vary: Accept-Encoding');
            echo $responseBody;
        } else {
            echo gzdecode($responseBody);
        }
        die();
    }

    /**
     * ロギング
     * @param Exception $e
     * @param array $info
     * @return void
     */
    protected function logging(Exception $e, array $info): void
    {
        if (isset($_SERVER['HTTP_X_API_KEY'])) {
            $info['x-api-key'] = $_SERVER['HTTP_X_API_KEY'];
        }
        AcmsLogger::error('API機能: ' . $e->getMessage(), $info);
    }

    /**
     * Validate API Key
     * @return void
     * @throws ApiKeyException
     */
    protected function validateApiKey(): void
    {
        if (empty($this->apiKey)) {
            throw new ApiKeyException('APIキーが設定されていません。');
        }
        if (!isset($_SERVER['HTTP_X_API_KEY']) || empty($_SERVER['HTTP_X_API_KEY'])) {
            throw new ApiKeyException('X-API-KEY ヘッダーがありません。');
        }
        if ($this->apiKey !== $_SERVER['HTTP_X_API_KEY']) {
            throw new ApiKeyException('APIキーが一致しません。');
        }
    }

    /**
     * Validate Http Referrer
     * @return void
     * @throws ForbiddenException
     */
    protected function validateReferrer(): void
    {
        $referer = preg_replace('/^https?:\/\//', '', REFERER);
        $match = true;
        if (count($this->restrictionReferrer) > 0) {
            $match = false;
            foreach ($this->restrictionReferrer as $pattern) {
                if (fnmatch($pattern, $referer)) {
                    $match = true;
                    break;
                }
            }
        }
        if (!$match) {
            throw new ForbiddenException("リファラーの制限によりアクセスが拒否されました。");
        }
    }

    /**
     * Validate Remote Address
     * @return void
     * @throws ForbiddenException
     */
    protected function validateAddress(): void
    {
        $match = true;
        if (count($this->restrictionAddress) > 0) {
            $match = false;
            foreach ($this->restrictionAddress as $ipband) {
                if (in_ipband(REMOTE_ADDR, $ipband)) {
                    $match = true;
                    break;
                }
            }
        }
        if (!$match) {
            throw new ForbiddenException("許可されていない接続元からのアクセスです。");
        }
    }

    /**
     * Add Allow Origin Header
     * @return void
     */
    protected function addAllowOriginHeader(): void
    {
        $match = false;
        header('Access-Control-Allow-Methods: GET, OPTIONS, HEAD');
        header('Access-Control-Allow-Headers: *');

        foreach ($this->allowOriginDomains as $allowOriginDomain) {
            $regex = '/^https?:\/\/' . preg_quote($allowOriginDomain, '/') . '/i';
            if (preg_match($regex, REFERER, $m)) {
                header('Access-Control-Allow-Origin: ' . $m[0]);
                $match = true;
                break;
            }
        }
        if (!$match && isset($this->allowOriginDomains[0])) {
            header('Access-Control-Allow-Origin: ' . $this->allowOriginDomains[0]);
        }
        if (isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) === 'OPTIONS') {
            header(PROTOCOL . ' 200 OK');
            header('Access-Control-Max-Age: 3600');
            die();
        }
    }

    /**
     * 文字列が有効なJSONかどうかを検証する
     * @param string $json
     * @return bool
     */
    protected function jsonValidate(string $json): bool
    {
        return jsonValidate($json);
    }
}
