<?php

namespace Acms\Services\Template\Twig\NodeVisitor;

use Acms\Services\Template\Twig\Node\CommentedIncludeNode;
use Twig\Environment;
use Twig\Node\Expression\AbstractExpression;
use Twig\Node\IncludeNode;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

/**
 * IncludeNode を CommentedIncludeNode に置換する NodeVisitor。
 * Environment の debug が true のときのみ置換。
 */
class CommentedIncludeNodeVisitor implements NodeVisitorInterface
{
    public function enterNode(Node $node, Environment $env): Node
    {
        return $node;
    }

    public function leaveNode(Node $node, Environment $env): ?Node
    {
        if (!$node instanceof IncludeNode || !$env->isDebug()) {
            return $node;
        }

        $expr = $node->getNode('expr');
        $variables = $node->hasNode('variables') ? $node->getNode('variables') : null;

        assert($expr instanceof AbstractExpression);
        assert($variables === null || $variables instanceof AbstractExpression);

        return new CommentedIncludeNode(
            $expr,
            $variables,
            $node->getAttribute('only'),
            $node->getAttribute('ignore_missing'),
            $node->getTemplateLine()
        );
    }

    public function getPriority(): int
    {
        return 0;
    }
}
