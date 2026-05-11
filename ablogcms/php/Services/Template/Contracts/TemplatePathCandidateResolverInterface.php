<?php

namespace Acms\Services\Template\Contracts;

interface TemplatePathCandidateResolverInterface
{
    /**
     * findTemplate / Twig Loader 用のテンプレートパス候補（探索順）
     *
     * @return list<string>
     */
    public function getCandidates(string $path): array;
}
