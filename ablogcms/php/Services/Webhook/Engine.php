<?php

namespace Acms\Services\Webhook;

use DB;
use SQL;
use HTTP;
use ACMS_Filter;
use Acms\Services\Facades\Logger;
use Acms\Services\Facades\Common;
use RuntimeException;
use Exception;

class Engine
{
    /**
     * @var \Acms\Services\Webhook\Payload;
     */
    protected $payload;

    /**
     * @var array
     */
    protected $whiteList = [];

    /**
     * Engine constructor.
     */
    public function __construct($payload, $whiteList)
    {
        if (!defined('CURL_SSLVERSION_TLSv1_2')) {
            define('CURL_SSLVERSION_TLSv1_2', 6); // phpcs:ignore
        }
        $this->payload = $payload;
        $this->whiteList = $whiteList;
    }

    /**
     * @param int $bid
     * @param string $type
     * @param array|string $events
     * @param array $args
     */
    public function call($bid, $type, $events, $args = [])
    {
        if (!is_array($events)) {
            $events = [$events];
        }
        $hooks = $this->getHooks($bid, $type, $events);
        if (empty($hooks)) {
            return;
        }
        $payload = $this->getPayload($type, $events, $args);

        foreach ($hooks as $hook) {
            $payload['webhook_id'] = $hook['webhook_id'];
            $payload['webhook_name'] = $hook['webhook_name'];
            $this->send($hook, $payload, $events);
        }
    }

    /**
     * URLのスキーマが http or https か確認する
     *
     * @param string $url
     * @return bool
     */
    public function validateUrlScheme(string $url): bool
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (in_array($scheme, ['http', 'https'], true)) {
            return true;
        }
        return false;
    }

    /**
     * URLのホストがホワイトリストに含まれるか確認
     *
     * @param string $url
     * @return bool
     */
    public function validateUrlWhiteList(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (in_array($host, $this->whiteList, true)) {
            return true;
        }
        return false;
    }

    /**
     * @param int $bid
     * @param string $type
     * @param array $events
     * @return mixed
     */
    protected function getHooks($bid, $type, $events)
    {
        $sql = SQL::newSelect('webhook');
        $sql->addLeftJoin('blog', 'blog_id', 'webhook_blog_id');
        ACMS_Filter::blogTree($sql, $bid, 'ancestor-or-self');
        $sql->addWhereOpr('webhook_status', 'open');
        $sql->addWhereOpr('webhook_type', $type);
        $where = SQL::newWhere();
        foreach ($events as $event) {
            $event = DB::quote($event);
            $where->addWhere(SQL::newFunction("{$event}, `webhook_events`", 'FIND_IN_SET', null, false), 'OR');
        }
        $sql->addWhere($where);
        $where = SQL::newWhere();
        $where->addWhereOpr('webhook_blog_id', $bid, '=', 'OR');
        $where->addWhereOpr('webhook_scope', 'global', '=', 'OR');
        $sql->addWhere($where);
        $sql->addOrder('webhook_id', 'DESC');
        $q = $sql->get(dsn());

        return DB::query($q, 'all');
    }

    /**
     * @param string $type
     * @param array $events
     * @param array $args
     * @return false|mixed
     */
    protected function getPayload($type, $events, $args = [])
    {
        if (!is_array($args)) {
            $args = [$args];
        }
        array_unshift($args, $events);

        $methodName = "{$type}Hook";
        $callback = [$this->payload, $methodName];
        if (is_callable($callback)) {
            /** @var callable $callback */
            return call_user_func_array($callback, $args);
        }
        return false;
    }

    /**
     * @param array $hook
     * @param string $payload
     * @param array $events
     */
    protected function send($hook, $payload, $events)
    {
        $url = (string)$hook['webhook_url'];
        $ch = curl_init($url);
        try {
            if ($ch === false) {
                throw new RuntimeException('CURLの初期化に失敗しました。');
            }
            if (empty($url)) {
                throw new RuntimeException('空のURLでWebhookが実行されそうになりました。');
            }
            if (!$this->validateUrlScheme($url)) {
                throw new RuntimeException('不正なURLのWebhookが実行されそうになりました。');
            }
            if (!$this->validateUrlWhiteList($url)) {
                throw new RuntimeException('ホワイトリストに登録されていないWebhookが実行されそうになりました。');
            }

            $headers = [
                'Content-Type: application/json',
            ];
            if ($hook['webhook_payload'] === 'custom') {
                $data = $this->buildPayload($payload, $hook['webhook_payload_tpl']);
            } else {
                $data = json_encode($payload, JSON_PRETTY_PRINT);
            }

            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1);
            curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS); // 使用できるプロトコルを限定（SSRF対策）
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

            Http::setCurlProxy($ch);

            $response = curl_exec($ch);
            if ($hook['webhook_history'] === 'on') {
                $this->saveLog($ch, $response, $hook['webhook_id'], $hook['webhook_url'], $events, implode("\n", $headers), $data);
            }
        } catch (Exception $e) {
            Logger::warning($e->getMessage(), Common::exceptionArray($e, $hook));
        } finally {
            if ($ch !== false) {
                curl_close($ch);
            }
        }
    }

    /**
     * @param $payload
     * @param $tpl
     * @return mixed
     */
    protected function buildPayload($payload, $tpl)
    {
        $tplEngine = new Template();
        return $tplEngine->render(setGlobalVars($tpl), $payload);
    }

    /**
     * @param $curl
     * @param $response
     * @param $id
     * @param $endpoint
     * @param $events
     * @param $requestHeader
     * @param $requestBody
     */
    protected function saveLog($curl, $response, $id, $endpoint, $events, $requestHeader, $requestBody)
    {
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $time = curl_getinfo($curl, CURLINFO_TOTAL_TIME);
        $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $responseHeader = substr($response, 0, $headerSize);
        $responseBody = substr($response, $headerSize);

        $sql = SQL::newInsert('log_webhook');
        $sql->addInsert('log_webhook_datetime', date('Y-m-d H:i:s', REQUEST_TIME));
        $sql->addInsert('log_webhook_endpoint', $endpoint);
        $sql->addInsert('log_webhook_status_code', $status);
        $sql->addInsert('log_webhook_event', implode(',', $events));
        $sql->addInsert('log_webhook_id', $id);
        $sql->addInsert('log_webhook_request_header', $requestHeader);
        $sql->addInsert('log_webhook_request_body', $requestBody);
        $sql->addInsert('log_webhook_response_header', $responseHeader);
        $sql->addInsert('log_webhook_response_body', $responseBody);
        $sql->addInsert('log_webhook_response_time', $time);
        DB::query($sql->get(dsn()), 'exec');
    }
}
