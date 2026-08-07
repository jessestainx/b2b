<?php

declare(strict_types=1);

namespace GrupoAwamotos\StoreSetup\Model;

use Magento\Cms\Model\PageFactory;
use Psr\Log\LoggerInterface;

class InstitutionalContentNormalizer
{
    /**
     * @var string[]
     */
    private const TARGET_PAGE_IDENTIFIERS = [
        'terms',
        'privacy-policy',
        'privacy-policy-cookie-restriction-mode',
        'customer-service',
        'faq',
        'returns',
        'warranty',
        'shipping',
        'atacado/condicoes',
        'lgpd',
    ];

    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    public function normalizeAll(): int
    {
        $updatedPages = 0;

        foreach (self::TARGET_PAGE_IDENTIFIERS as $identifier) {
            if ($this->normalizeByIdentifier($identifier)) {
                $updatedPages++;
            }
        }

        $this->logger->info('[StoreSetup] Institutional content normalization completed', [
            'updated_pages' => $updatedPages,
            'target_pages' => self::TARGET_PAGE_IDENTIFIERS,
        ]);

        return $updatedPages;
    }

    private function normalizeByIdentifier(string $identifier): bool
    {
        $page = $this->pageFactory->create();
        $page->load($identifier, 'identifier');

        if (!$page->getId()) {
            return false;
        }

        $originalContent = (string) $page->getContent();
        $normalizedContent = $this->normalizeContent($originalContent);

        if ($normalizedContent === $originalContent) {
            return false;
        }

        $page->setContent($normalizedContent);
        $page->save();

        $this->logger->info('[StoreSetup] Institutional CMS page normalized', [
            'identifier' => $identifier,
        ]);

        return true;
    }

    private function normalizeContent(string $content): string
    {
        $normalized = $content;

        $replacements = [
            '/\{\{store\s+url=[\'\"]privacy-policy[\'\"]\}\}/i' => "{{store url='privacy-policy-cookie-restriction-mode'}}",
            '#https?://awamotos\.com/privacy-policy/?#i' => 'https://awamotos.com/privacy-policy-cookie-restriction-mode/',
            '#/b2b/account/register/?#i' => '/b2b/register',
            '#b2b/quote/request#i' => 'b2b/quote/index',
            '/\(\s*11\s*\)\s*99999-9999/' => '(16) 99736-7588',
            '/\(\s*11\s*\)\s*4002-8922/' => '(16) 99736-7588',
            '/\+55\s*16\s*99999-9999/' => '+55 16 99736-7588',
            '/\b(?:contato|atacado|privacidade|suporte|entregas|sac|falecom|teste|oportunidades|admin|rh|vendas|noreply|monitoring)@(?:awamotos\.com\.br|awamotos\.com|grupoawamotos\.com\.br)\b/i' => 'awamotos@awamotos.com.br',
        ];

        foreach ($replacements as $pattern => $replacement) {
            $newContent = preg_replace($pattern, $replacement, $normalized);
            if ($newContent === null) {
                continue;
            }

            $normalized = $newContent;
        }

        return $normalized;
    }
}
