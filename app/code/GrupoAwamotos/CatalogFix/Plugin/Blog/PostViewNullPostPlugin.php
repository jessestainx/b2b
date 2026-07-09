<?php

declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Plugin\Blog;

use Psr\Log\LoggerInterface;
use Rokanthemes\Blog\Block\Post\View;

/**
 * Fix: TypeError when blog post is null (URL with invalid/deleted post).
 *
 * Rokanthemes\Blog\Block\Post\View::_prepareLayout() calls _addBreadcrumbs($post)
 * with a strict type hint, but getPost() can return null when the registry
 * 'current_blog_post' is not set — causing a fatal TypeError (HTTP 500).
 *
 * This around plugin intercepts toHtml() and returns an empty string when
 * no valid post is registered, preventing the crash.
 */
final class PostViewNullPostPlugin
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param View $subject
     * @param callable $proceed
     * @return string
     */
    public function aroundToHtml(View $subject, callable $proceed): string
    {
        if ($subject->getPost() === null) {
            $this->logger->warning(
                '[CatalogFix] Blog Post View rendered with null post — skipping block to prevent TypeError.',
                ['block' => $subject->getNameInLayout()]
            );
            return '';
        }

        return $proceed();
    }
}
