<?php

declare(strict_types=1);

namespace GrupoAwamotos\StoreSetup\Model;

use Magento\Cms\Model\BlockFactory;
use Magento\Cms\Model\PageFactory;
use Magento\Cms\Model\ResourceModel\Block\CollectionFactory as BlockCollectionFactory;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as PageCollectionFactory;
use Psr\Log\LoggerInterface;
use Rokanthemes\Blog\Model\PostFactory;
use Rokanthemes\Blog\Model\ResourceModel\Post as PostResource;
use Rokanthemes\Blog\Model\ResourceModel\Post\CollectionFactory as PostCollectionFactory;

class PublicEmailNormalizer
{
    private const CANONICAL_EMAIL = 'awamotos@awamotos.com.br';

    /**
     * E-mails públicos/institucionais da AWA → caixa única.
     * Não cobre caixas geradas (whatsapp_*, vendedor*) nem Gmail/SMTP.
     */
    private const EMAIL_PATTERN = '/\b(?:contato|atacado|privacidade|suporte|entregas|sac|falecom|teste|oportunidades|admin|rh|vendas|noreply|monitoring|awamotos)@(?:awamotos\.com\.br|awamotos\.com|grupoawamotos\.com\.br)\b/i';

    public function __construct(
        private readonly BlockCollectionFactory $blockCollectionFactory,
        private readonly PageCollectionFactory $pageCollectionFactory,
        private readonly PostCollectionFactory $postCollectionFactory,
        private readonly BlockFactory $blockFactory,
        private readonly PageFactory $pageFactory,
        private readonly PostFactory $postFactory,
        private readonly PostResource $postResource,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return array{
     *     blocks:int,
     *     pages:int,
     *     posts:int,
     *     replaced:int,
     *     failed_blocks:string[],
     *     failed_pages:string[],
     *     failed_posts:string[]
     * }
     */
    public function normalizeAll(): array
    {
        $blocks = $this->normalizeBlocks();
        $pages = $this->normalizePages();
        $posts = $this->normalizePosts();

        return [
            'blocks' => $blocks['updated'],
            'pages' => $pages['updated'],
            'posts' => $posts['updated'],
            'replaced' => $blocks['replaced'] + $pages['replaced'] + $posts['replaced'],
            'failed_blocks' => $blocks['failed'],
            'failed_pages' => $pages['failed'],
            'failed_posts' => $posts['failed'],
        ];
    }

    /**
     * @return array{updated:int,replaced:int,failed:string[]}
     */
    public function normalizeBlocks(): array
    {
        $updatedCount = 0;
        $replacedCount = 0;
        $failedIdentifiers = [];
        $collection = $this->blockCollectionFactory->create();

        foreach ($collection as $block) {
            $content = (string) $block->getContent();
            $normalizationResult = $this->normalizeContent($content);

            if ($normalizationResult['content'] === $content) {
                continue;
            }

            $identifier = (string) $block->getIdentifier();

            try {
                $normalizableBlock = $this->blockFactory->create();
                $normalizableBlock->load((int) $block->getId());
                $normalizableBlock->setContent($normalizationResult['content']);
                $normalizableBlock->save();
                $updatedCount++;
                $replacedCount += $normalizationResult['replaced'];
            } catch (\Throwable $exception) {
                $failedIdentifiers[] = $identifier;
                $this->logger->error(
                    sprintf(
                        '[PublicEmailNormalizer] Erro ao normalizar bloco "%s": %s',
                        $identifier,
                        $exception->getMessage()
                    )
                );
            }
        }

        return [
            'updated' => $updatedCount,
            'replaced' => $replacedCount,
            'failed' => $failedIdentifiers,
        ];
    }

    /**
     * @return array{updated:int,replaced:int,failed:string[]}
     */
    public function normalizePages(): array
    {
        $updatedCount = 0;
        $replacedCount = 0;
        $failedIdentifiers = [];
        $collection = $this->pageCollectionFactory->create();

        foreach ($collection as $page) {
            $content = (string) $page->getContent();
            $normalizationResult = $this->normalizeContent($content);

            if ($normalizationResult['content'] === $content) {
                continue;
            }

            $identifier = (string) $page->getIdentifier();

            try {
                $normalizablePage = $this->pageFactory->create();
                $normalizablePage->load((int) $page->getId());
                $normalizablePage->setContent($normalizationResult['content']);
                $normalizablePage->save();
                $updatedCount++;
                $replacedCount += $normalizationResult['replaced'];
            } catch (\Throwable $exception) {
                $failedIdentifiers[] = $identifier;
                $this->logger->error(
                    sprintf(
                        '[PublicEmailNormalizer] Erro ao normalizar página "%s": %s',
                        $identifier,
                        $exception->getMessage()
                    )
                );
            }
        }

        return [
            'updated' => $updatedCount,
            'replaced' => $replacedCount,
            'failed' => $failedIdentifiers,
        ];
    }

    /**
     * @return array{updated:int,replaced:int,failed:string[]}
     */
    public function normalizePosts(): array
    {
        $updatedCount = 0;
        $replacedCount = 0;
        $failedIdentifiers = [];
        $collection = $this->postCollectionFactory->create();

        foreach ($collection as $post) {
            $content = (string) $post->getContent();
            $normalizationResult = $this->normalizeContent($content);

            if ($normalizationResult['content'] === $content) {
                continue;
            }

            $identifier = (string) $post->getIdentifier();

            try {
                $normalizablePost = $this->postFactory->create();
                $this->postResource->load($normalizablePost, (int) $post->getId());
                $normalizablePost->setContent($normalizationResult['content']);
                $this->postResource->save($normalizablePost);
                $updatedCount++;
                $replacedCount += $normalizationResult['replaced'];
            } catch (\Throwable $exception) {
                $failedIdentifiers[] = $identifier;
                $this->logger->error(
                    sprintf(
                        '[PublicEmailNormalizer] Erro ao normalizar post "%s": %s',
                        $identifier,
                        $exception->getMessage()
                    )
                );
            }
        }

        return [
            'updated' => $updatedCount,
            'replaced' => $replacedCount,
            'failed' => $failedIdentifiers,
        ];
    }

    /**
     * @return array{content:string,replaced:int}
     */
    public function normalizeContent(string $content): array
    {
        $replacedCount = 0;
        $normalizedContent = (string) preg_replace_callback(
            self::EMAIL_PATTERN,
            static function (array $matches) use (&$replacedCount): string {
                $email = strtolower($matches[0]);
                if ($email === self::CANONICAL_EMAIL) {
                    return self::CANONICAL_EMAIL;
                }

                $replacedCount++;

                return self::CANONICAL_EMAIL;
            },
            $content
        );

        return [
            'content' => $normalizedContent,
            'replaced' => $replacedCount,
        ];
    }
}
