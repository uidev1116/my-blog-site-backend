<?php

namespace Acms\Services\Template;

use Acms\Services\Template\Acms\TemplatePathCandidateResolver as AcmsTemplatePathCandidateResolver;
use Acms\Services\Template\Contracts\TemplatePathCandidateResolverInterface;
use Acms\Services\Template\Twig\TemplatePathCandidateResolver as TwigTemplatePathCandidateResolver;

/**
 * findTemplate 向けに、Twig 有効可否に応じた候補生成器を返す
 */
final class TemplatePathCandidateResolverFactory
{
    public static function fromTwigEnabled(bool $twigEnabled): TemplatePathCandidateResolverInterface
    {
        return $twigEnabled
            ? new TwigTemplatePathCandidateResolver()
            : new AcmsTemplatePathCandidateResolver();
    }
}
