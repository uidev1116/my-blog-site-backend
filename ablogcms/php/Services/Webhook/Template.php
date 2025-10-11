<?php

namespace Acms\Services\Webhook;

use Twig\Loader\FilesystemLoader;
use Twig\Loader\ArrayLoader;
use Twig\Sandbox\SecurityPolicy;
use Twig\Extension\SandboxExtension;
use Twig\Environment;
use Twig\Source;
use Exception;
use Acms\Services\Facades\Logger;
use Acms\Services\Facades\Common;
use LogicException;
use Throwable;

class Template
{
    /**
     * @var \Twig\Environment
     */
    protected $twig;

    /**
     * @return void
     * @throws LogicException
     */
    public function __construct()
    {
        $tags = [];
        $filters = ['escape']; // 使用を許可するフィルタだけ
        $methods = [];
        $properties = [];
        $functions = []; // 使用を許可する関数だけ

        $loader = new FilesystemLoader(__DIR__);
        $this->twig = new Environment($loader, [
            'cache' => CACHE_DIR . '/webhook-twig',
        ]);

        $policy = new SecurityPolicy($tags, $filters, $methods, $properties, $functions);
        $sandbox = new SandboxExtension($policy, true);
        $this->twig->addExtension($sandbox);
    }

    /**
     * render payload
     *
     * @param string $code
     * @param array $data
     * @return string
     * @throws Throwable
     */
    public function render($code, $data)
    {
        try {
            $this->twig->setLoader(new ArrayLoader([
                'webhook_payload' => $code,
            ]));
            $this->twig->parse($this->twig->tokenize(new Source($code, 'webhook_payload')));
            return $this->twig->render('webhook_payload', $data);
        } catch (Exception $e) {
            Logger::warning('Webhookのペイロードのレンダリングに失敗しました。' . $e->getMessage(), Common::exceptionArray($e));
        }
        return '';
    }
}
