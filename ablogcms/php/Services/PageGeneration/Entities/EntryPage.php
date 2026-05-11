<?php

namespace Acms\Services\PageGeneration\Entities;

class EntryPage extends Page
{
    /**
     * ページのエントリーID
     * @var int
     */
    private $entryId;

    public function __construct(
        string $url,
        string $destinationPathname,
        int $entryId,
        ?string $userAgent = null,
        bool $withSession = false
    ) {
        parent::__construct($url, $destinationPathname, $userAgent, $withSession);
        $this->entryId = $entryId;
    }

    /**
     * ページのエントリーIDを取得
     * @return int
     */
    public function getEntryId(): int
    {
        return $this->entryId;
    }
}
