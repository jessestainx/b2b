<?php

declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Plugin\Media;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\File\Transfer\Adapter\Http as HttpFileAdapter;
use Magento\Framework\HTTP\PhpEnvironment\Response as HttpResponse;
use Magento\MediaStorage\Model\File\Storage\Response as MediaStorageResponse;

/**
 * Lighthouse "uses-long-cache-ttl": public media via get.php must cache >= 30 days.
 *
 * @see https://developer.chrome.com/docs/performance/insights/cache
 */
class MediaFileCacheHeadersPlugin
{
    private const CACHE_CONTROL = 'public, max-age=31536000, immutable';

    private const CACHEABLE_EXTENSIONS = [
        'css',
        'js',
        'jpg',
        'jpeg',
        'png',
        'gif',
        'webp',
        'svg',
        'ico',
        'woff',
        'woff2',
        'ttf',
        'otf',
        'eot',
        'mp4',
        'webm',
        'avif',
    ];

    public function __construct(
        private readonly HttpRequest $request,
        private readonly HttpResponse $response,
    ) {
    }

    /**
     * Http adapter only — MediaStorage\Response also has send() but must use beforeSendResponse().
     *
     * @param mixed $subject
     * @param mixed $options
     * @return array<int, mixed>
     */
    public function beforeSend($subject, $options = null): array
    {
        if (!$subject instanceof HttpFileAdapter) {
            return $options !== null ? [$options] : [];
        }

        $filepath = $this->resolveFilePath($options);
        if ($filepath === null || !$this->isCacheableMediaFile($filepath)) {
            return [$options];
        }

        $this->applyLongCacheHeaders();

        return [$options];
    }

    public function beforeSendResponse(MediaStorageResponse $subject): void
    {
        if ((int) $subject->getHttpResponseCode() !== 200) {
            return;
        }

        $pathInfo = $this->request->getPathInfo();
        if ($pathInfo === '' || !str_starts_with($pathInfo, '/media/')) {
            return;
        }

        $this->applyLongCacheHeaders();
    }

    /**
     * @param mixed $options
     */
    private function resolveFilePath($options): ?string
    {
        if (is_string($options)) {
            return $options;
        }

        if (is_array($options) && isset($options['filepath']) && is_string($options['filepath'])) {
            return $options['filepath'];
        }

        return null;
    }

    private function isCacheableMediaFile(string $filepath): bool
    {
        $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
        if ($extension === '' || !in_array($extension, self::CACHEABLE_EXTENSIONS, true)) {
            return false;
        }

        if (str_contains($filepath, '/pub/media/') || str_contains($filepath, '/media/')) {
            return true;
        }

        $pathInfo = $this->request->getPathInfo();
        return $pathInfo !== '' && str_starts_with($pathInfo, '/media/');
    }

    private function applyLongCacheHeaders(): void
    {
        $this->response->clearHeader('Cache-Control');
        $this->response->clearHeader('Pragma');
        $this->response->clearHeader('Expires');
        $this->response->setHeader('Cache-Control', self::CACHE_CONTROL, true);
        $this->response->setHeader(
            'Expires',
            gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT',
            true
        );
    }
}
