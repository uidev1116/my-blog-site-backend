<?php

namespace Acms\Traits\Utilities;

trait PaginationTrait
{
    /**
     * ページャーの組み立て
     *
     * @param int $page
     * @param bool $nextPage
     * @return array
     */
    public function buildPagerTrait(int $page, bool $nextPage = true): array
    {
        $data = [];

        // prev page
        $data['prevPageLink'] = $page > 1 ? acmsLink([
            'page' => $page - 1,
        ], true) : null;

        // next page
        $data['nextPageLink'] = $nextPage ? acmsLink([
            'page' => $page + 1,
        ], true) : null;

        return $data;
    }

    /**
     * ページネーションの組み立て
     *
     * @param int $page
     * @param int $total
     * @param int $limit
     * @param int $maxPages
     * @param array $context
     * @return array
     */
    public function buildPaginationTrait(int $page, int $total, int $limit, int $maxPages, $context = []): array
    {
        $currentPage = intval($page);
        $totalPages = intval($total % $limit === 0 ? floor($total / $limit) : floor($total / $limit) + 1);

        if ($totalPages <= $maxPages) {
            $startPage = 1;
            $endPage = $totalPages;
        } else {
            $halfPagesToShow = floor($maxPages / 2);
            if ($currentPage <= $halfPagesToShow) {
                $startPage = 1;
                $endPage = $maxPages;
            } elseif ($currentPage + $halfPagesToShow >= $totalPages) {
                $startPage = $totalPages - $maxPages + 1;
                $endPage = $totalPages;
            } else {
                $startPage = $currentPage - $halfPagesToShow;
                $endPage = $currentPage + $halfPagesToShow;
            }
        }
        $from = ($currentPage - 1) * $limit + 1;
        $to = $currentPage === $totalPages ? $total : $currentPage * $limit;

        $data = [
            'total' => $total,
            'limit' => $limit,
            'from' => $from,
            'to' => $to,
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
        ];

        // 前ページリンク
        $data['prevPage'] = $currentPage > 1 ? [
            'page' => $page - 1,
            'url' => acmsLink($context + [
                'page' => $page - 1,
            ]),
            'count' => $limit,
        ] : null;
        // ファーストページリンク
        $data['firstPage'] = $startPage > 1 ? [
            'page' => 1,
            'url' => acmsLink($context + [
                'page' => 1,
            ]),
            'current' => $currentPage === 1,
        ] : null;
        // 前方省略記号（...）
        $data['prevEllipsis'] = $startPage > 2;
        // ページリンク
        for ($i = $startPage; $i <= $endPage; $i++) {
            $data['pages'][] = [
                'page' => $i,
                'url' => acmsLink($context + [
                    'page' => $i,
                ]),
                'current' => $currentPage === intval($i),
            ];
        }
        // 後方省略記号（...）
        $data['nextEllipsis'] = $totalPages - $endPage > 1;
        // ラストページリンク
        $data['lastPage'] = ($totalPages - $endPage > 0) ? [
            'page' => $totalPages,
            'url' => acmsLink($context + [
                'page' => $totalPages,
            ]),
            'current' => $currentPage === $totalPages,
        ] : null;
        // 次ページリンク
        $count = $total - $to;
        if ($limit < $count) {
            $count = $limit;
        }
        $data['nextPage'] = ($currentPage < $totalPages) ? [
            'page' => $page + 1,
            'url' => acmsLink($context + [
                'page' => $page + 1,
            ]),
            'count' => $count,
        ] : null;
        return $data;
    }
}
