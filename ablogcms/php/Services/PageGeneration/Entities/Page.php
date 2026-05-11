<?php

namespace Acms\Services\PageGeneration\Entities;

class Page
{
    /**
     * ページのURL
     * @var string
     */
    private $url;

    /**
     * 出力先ファイルのパス
     * @var string
     */
    private $destinationPathname;

    /**
     * ユーザーエージェント
     * @var string|null
     */
    private $userAgent;

    /**
     * セッションを含むかどうか
     * @var bool
     */
    private $withSession;

    public function __construct(string $url, string $destinationPathname, ?string $userAgent = null, bool $withSession = false)
    {
        $this->url = $url;
        $this->destinationPathname = $destinationPathname;
        $this->userAgent = $userAgent;
        $this->withSession = $withSession;
    }

    /**
     * ページのURLを取得
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * 出力先ファイルのファイル名を含むパスを取得
     * @return string
     */
    public function getDestinationPathname(): string
    {
        return $this->destinationPathname;
    }

    /**
     * ユーザーエージェントを取得
     * @return string|null
     */
    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    /**
     * セッションを含むかどうかを取得
     * @return bool
     */
    public function isWithSession(): bool
    {
        return $this->withSession;
    }
}
