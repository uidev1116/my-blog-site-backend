<?php

namespace Acms\Services\StaticExport;

use Acms\Services\StaticExport\Contracts\Compiler as ContractsCompiler;

class Compiler extends ContractsCompiler
{
    /**
     * resolver
     *
     * @var array
     */
    protected $resolver = [
        'source'    => null,
        'link'      => null,
    ];

    /**
     * compile
     *
     * @param string $html
     * @return string
     */
    public function compile($html)
    {
        $document_root = $this->destination->getDestinationDocumentRoot();
        $offset_dir = $this->destination->getDestinationOffsetDir();
        $domain = $this->destination->getDestinationDomain();
        $blog_code = $this->destination->getBlogCode();

        foreach ($this->resolver as $resolver => $obj) {
            $class = 'Acms\\Services\\StaticExport\\Compiler\\' . ucfirst(strtolower($resolver)) . 'Resolver';
            if (!class_exists($class)) {
                continue;
            }
            $resolver = empty($obj) ? new $class() : $obj;
            $html = $resolver->resolve($html, $document_root, $offset_dir, $domain, $blog_code);
        }
        $blogPath = \ACMS_RAM::blogDomain(BID) ?? '';
        if (DIR_OFFSET) {
            $blogPath .= '/' . DIR_OFFSET;
        }
        $html = preg_replace('@(https?)?://' . preg_quote($blogPath, '@') . '(?::\d+)?/?@', '/', $html);

        return $html;
    }
}
