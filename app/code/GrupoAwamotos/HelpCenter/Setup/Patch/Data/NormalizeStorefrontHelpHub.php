<?php

declare(strict_types=1);

namespace GrupoAwamotos\HelpCenter\Setup\Patch\Data;

use GrupoAwamotos\HelpCenter\Api\CategoryRepositoryInterface;
use GrupoAwamotos\HelpCenter\Api\Data\CategoryInterface;
use GrupoAwamotos\HelpCenter\Api\Data\TopicInterface;
use GrupoAwamotos\HelpCenter\Api\TopicRepositoryInterface;
use GrupoAwamotos\HelpCenter\Model\CategoryFactory;
use GrupoAwamotos\HelpCenter\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use GrupoAwamotos\HelpCenter\Model\ResourceModel\Topic\CollectionFactory as TopicCollectionFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Aligns Help Center categories with the public 6-theme hub.
 *
 * Idempotent by normalized name/title: a store that already has the split
 * (Cadastro, Produtos, Pedidos, Pagamento, Entrega, Devolução) is a no-op.
 * A fresh install after {@see InstallHelpTopics} is transformed the same way.
 */
class NormalizeStorefrontHelpHub implements DataPatchInterface
{
    /** @var array<string, CategoryInterface> */
    private array $categoriesByName = [];

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly CategoryFactory $categoryFactory,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly TopicRepositoryInterface $topicRepository,
        private readonly CategoryCollectionFactory $categoryCollectionFactory,
        private readonly TopicCollectionFactory $topicCollectionFactory,
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->startSetup();
        $this->loadCategories();
        $this->ensureStorefrontCategories();
        $this->pushInternalCategorySort();
        $this->reassignStorefrontTopics();
        $this->moduleDataSetup->endSetup();

        return $this;
    }

    public static function getDependencies(): array
    {
        return [InstallHelpTopics::class];
    }

    public function getAliases(): array
    {
        return [];
    }

    private function ensureStorefrontCategories(): void
    {
        foreach ($this->getStorefrontCategorySpecs() as $spec) {
            $this->ensureCategory($spec);
        }
    }

    private function pushInternalCategorySort(): void
    {
        $this->ensureSortAndAudience(
            ['Painel Comercial — Primeiros Passos', 'Painel Comercial - Primeiros Passos'],
            200,
            CategoryInterface::AUDIENCE_SELLER
        );
        $this->ensureSortAndAudience(
            ['Gestao da Equipe Comercial', 'Gestão da Equipe Comercial'],
            210,
            CategoryInterface::AUDIENCE_SUPERVISOR
        );
    }

    private function reassignStorefrontTopics(): void
    {
        $topics = [];
        foreach ($this->topicCollectionFactory->create() as $topic) {
            $topics[$this->normalizeName($topic->getTitle())] = $topic;
        }

        foreach ($this->getTopicAssignments() as $title => $assignment) {
            $topic = $topics[$this->normalizeName($title)] ?? null;
            if (!$topic instanceof TopicInterface) {
                continue;
            }
            $category = $this->findCategoryByNames([$assignment['category']]);
            $categoryId = $category?->getCategoryId();
            if ($categoryId === null) {
                continue;
            }

            $dirty = (int) $topic->getCategoryId() !== $categoryId
                || $topic->getAudience() !== $assignment['audience']
                || $topic->getSortOrder() !== $assignment['sort_order'];
            if (!$dirty) {
                continue;
            }

            $topic->setCategoryId($categoryId);
            $topic->setAudience($assignment['audience']);
            $topic->setSortOrder($assignment['sort_order']);
            $this->topicRepository->save($topic);
        }
    }

    /**
     * @param array{
     *     name: string,
     *     aliases: string[],
     *     description: string,
     *     sort_order: int,
     *     audience: string
     * } $spec
     */
    private function ensureCategory(array $spec): CategoryInterface
    {
        $names = array_merge([$spec['name']], $spec['aliases']);
        $category = $this->findCategoryByNames($names);
        $isNew = $category === null;
        if ($isNew) {
            $category = $this->categoryFactory->create();
            $category->setStatus(1);
        }

        $oldName = $category->getName();
        $dirty = $isNew
            || $category->getName() !== $spec['name']
            || (string) $category->getDescription() !== $spec['description']
            || $category->getSortOrder() !== $spec['sort_order']
            || $category->getAudience() !== $spec['audience']
            || $category->getStatus() !== 1;

        if (!$dirty) {
            return $category;
        }

        $category->setName($spec['name']);
        $category->setDescription($spec['description']);
        $category->setSortOrder($spec['sort_order']);
        $category->setAudience($spec['audience']);
        $category->setStatus(1);
        $this->categoryRepository->save($category);

        if ($oldName !== '') {
            unset($this->categoriesByName[$this->normalizeName($oldName)]);
        }
        $this->categoriesByName[$this->normalizeName($category->getName())] = $category;

        return $category;
    }

    /**
     * @param string[] $names
     */
    private function ensureSortAndAudience(array $names, int $sortOrder, string $audience): void
    {
        $category = $this->findCategoryByNames($names);
        if ($category === null) {
            return;
        }
        if ($category->getSortOrder() === $sortOrder && $category->getAudience() === $audience) {
            return;
        }

        $category->setSortOrder($sortOrder);
        $category->setAudience($audience);
        $this->categoryRepository->save($category);
        $this->categoriesByName[$this->normalizeName($category->getName())] = $category;
    }

    /**
     * @param string[] $names
     */
    private function findCategoryByNames(array $names): ?CategoryInterface
    {
        foreach ($names as $name) {
            $category = $this->categoriesByName[$this->normalizeName($name)] ?? null;
            if ($category instanceof CategoryInterface) {
                return $category;
            }
        }

        return null;
    }

    private function loadCategories(): void
    {
        $this->categoriesByName = [];
        foreach ($this->categoryCollectionFactory->create() as $category) {
            $this->categoriesByName[$this->normalizeName($category->getName())] = $category;
        }
    }

    private function normalizeName(string $name): string
    {
        $name = mb_strtolower(trim($name));

        return strtr($name, [
            'á' => 'a',
            'à' => 'a',
            'ã' => 'a',
            'â' => 'a',
            'é' => 'e',
            'ê' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ô' => 'o',
            'õ' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ç' => 'c',
            '—' => '-',
            '–' => '-',
        ]);
    }

    /**
     * @return list<array{
     *     name: string,
     *     aliases: string[],
     *     description: string,
     *     sort_order: int,
     *     audience: string
     * }>
     */
    private function getStorefrontCategorySpecs(): array
    {
        return [
            [
                'name' => 'Cadastro',
                'aliases' => ['Conta B2B e Cadastro'],
                'description' => 'Cadastro CNPJ, aprovação e acesso B2B.',
                'sort_order' => 10,
                'audience' => CategoryInterface::AUDIENCE_ALL,
            ],
            [
                'name' => 'Produtos',
                'aliases' => ['Encontrando Produtos e Pecas', 'Encontrando Produtos e Peças'],
                'description' => 'Como buscar, filtrar e verificar compatibilidade.',
                'sort_order' => 20,
                'audience' => CategoryInterface::AUDIENCE_ALL,
            ],
            [
                'name' => 'Pedidos',
                'aliases' => ['Pedidos, Pagamentos e Entregas'],
                'description' => 'Como comprar e acompanhar o pedido.',
                'sort_order' => 30,
                'audience' => CategoryInterface::AUDIENCE_ALL,
            ],
            [
                'name' => 'Pagamento',
                'aliases' => [],
                'description' => 'Formas de pagamento aceitas na loja.',
                'sort_order' => 40,
                'audience' => CategoryInterface::AUDIENCE_ALL,
            ],
            [
                'name' => 'Entrega',
                'aliases' => [],
                'description' => 'Prazos, frete e rastreio do pedido.',
                'sort_order' => 50,
                'audience' => CategoryInterface::AUDIENCE_ALL,
            ],
            [
                'name' => 'Devolução',
                'aliases' => ['Devolucao'],
                'description' => 'Trocas, arrependimento e garantia.',
                'sort_order' => 60,
                'audience' => CategoryInterface::AUDIENCE_ALL,
            ],
        ];
    }

    /**
     * @return array<string, array{category: string, audience: string, sort_order: int}>
     */
    private function getTopicAssignments(): array
    {
        return [
            'Como buscar pecas por nome ou SKU' => [
                'category' => 'Produtos',
                'audience' => CategoryInterface::AUDIENCE_ALL,
                'sort_order' => 1,
            ],
            'Como verificar compatibilidade de pecas' => [
                'category' => 'Produtos',
                'audience' => CategoryInterface::AUDIENCE_ALL,
                'sort_order' => 2,
            ],
            'Como fazer um pedido' => [
                'category' => 'Pedidos',
                'audience' => CategoryInterface::AUDIENCE_ALL,
                'sort_order' => 1,
            ],
            'Formas de pagamento aceitas' => [
                'category' => 'Pagamento',
                'audience' => CategoryInterface::AUDIENCE_ALL,
                'sort_order' => 1,
            ],
            'Como rastrear meu pedido' => [
                'category' => 'Entrega',
                'audience' => CategoryInterface::AUDIENCE_ALL,
                'sort_order' => 1,
            ],
            'Politica de trocas e devolucoes' => [
                'category' => 'Devolução',
                'audience' => CategoryInterface::AUDIENCE_ALL,
                'sort_order' => 1,
            ],
            'Como criar uma conta B2B' => [
                'category' => 'Cadastro',
                'audience' => CategoryInterface::AUDIENCE_ALL,
                'sort_order' => 1,
            ],
            'Precos e condicoes especiais para B2B' => [
                'category' => 'Cadastro',
                'audience' => CategoryInterface::AUDIENCE_B2B,
                'sort_order' => 2,
            ],
        ];
    }
}
