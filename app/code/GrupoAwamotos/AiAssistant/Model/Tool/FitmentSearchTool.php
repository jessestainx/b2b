<?php

declare(strict_types=1);

namespace GrupoAwamotos\AiAssistant\Model\Tool;

use GrupoAwamotos\AiAssistant\Api\AssistantOrchestratorInterface;
use GrupoAwamotos\AiAssistant\Api\ToolInterface;
use GrupoAwamotos\WhatsAppCommerce\Api\CatalogInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Busca peças por compatibilidade com moto (marca / modelo / ano).
 *
 * 1) Atributos EAV marca_moto, modelo_moto, ano_moto
 * 2) Fallback no catálogo textual (nome + marca + modelo + ano)
 *
 * Não recria o módulo Fitment removido — só consulta o cadastro vigente.
 */
class FitmentSearchTool implements ToolInterface
{
    private const ATTR_BRAND = 'marca_moto';
    private const ATTR_MODEL = 'modelo_moto';
    private const ATTR_YEAR  = 'ano_moto';
    private const UNIVERSAL_YEAR = 'todos os anos';
    private const PAGE_SIZE = 8;

    private const PART_SYNONYMS = [
        'retrovisor' => ['retrovisor', 'ret.', 'ret '],
        'retrovisores' => ['retrovisor', 'ret.', 'ret '],
    ];

    private const TOKEN_ALIASES = [
        'bis' => ['biz', 'bis'],
        'biz' => ['biz', 'bis'],
        'biss' => ['biz', 'bis', 'biss'],
    ];

    private const MODEL_BRAND = [
        'biz' => 'Honda',
        'bis' => 'Honda',
        'cg' => 'Honda',
        'titan' => 'Honda',
        'bros' => 'Honda',
        'bross' => 'Honda',
        'fan' => 'Honda',
        'pop' => 'Honda',
        'xre' => 'Honda',
        'cb' => 'Honda',
        'fazer' => 'Yamaha',
        'factor' => 'Yamaha',
        'ybr' => 'Yamaha',
        'ninja' => 'Kawasaki',
    ];

    public function __construct(
        private readonly ProductCollectionFactory $productCollectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly CatalogInterface $catalog,
    ) {
    }

    public function getName(): string
    {
        return 'fitment_search';
    }

    public function getDescription(): string
    {
        return 'Busca peças compatíveis com uma moto (marca, modelo e ano). '
            . 'Use quando o cliente citar a moto, mesmo junto com o nome da peça. '
            . 'Exemplos: "pastilha Honda CG 160", "o que serve na Fazer 250 2023". '
            . 'Biz, CG, Titan, Bros, Fan, Pop, XRE e CB são Honda — não envie brand Yamaha nesses modelos. '
            . 'Fazer, Factor e YBR são Yamaha. Ninja é Kawasaki. '
            . 'Se só a marca for informada, a ferramenta pede o modelo. '
            . 'Não invente compatibilidade — só retorne o que o catálogo confirmar.';
    }

    public function getParametersSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'brand' => [
                    'type'        => 'string',
                    'description' => 'Marca da moto (Honda, Yamaha, Suzuki, Kawasaki…).',
                ],
                'model' => [
                    'type'        => 'string',
                    'description' => 'Modelo da moto (CG 160, Fazer 250, Biz 125, Ninja 400…).',
                ],
                'year' => [
                    'type'        => 'string',
                    'description' => 'Ano da moto, se o cliente informar (ex.: 2022).',
                ],
                'part' => [
                    'type'        => 'string',
                    'description' => 'Nome da peça, se informado (pastilha, manete, kit relação…).',
                ],
                'page' => [
                    'type'        => 'integer',
                    'description' => 'Página de resultados (padrão: 1).',
                    'default'     => 1,
                ],
            ],
        ];
    }

    public function execute(array $arguments, array $context = []): array
    {
        $brand = $this->normalize((string) ($arguments['brand'] ?? ''));
        $model = $this->normalize((string) ($arguments['model'] ?? ''));
        $year  = $this->normalizeYear((string) ($arguments['year'] ?? ''));
        $part  = $this->normalize((string) ($arguments['part'] ?? ''));
        $page  = max(1, (int) ($arguments['page'] ?? 1));
        $brandDropped = '';

        $inferredBrand = $this->inferBrandFromModel($model);
        if ($inferredBrand !== '' && $brand !== '' && !$this->sameBrand($brand, $inferredBrand)) {
            $brandDropped = $brand;
            $brand = '';
        }

        if ($brand === '' && $model === '' && $part === '') {
            return ['error' => 'Informe a moto (marca/modelo) ou o nome da peça.'];
        }

        if ($brand !== '' && $model === '' && $part === '') {
            return [
                'strategy' => 'ask_model',
                'brand'    => $brand,
                'models'   => [],
                'products' => [],
                'total'    => 0,
                'note'     => 'Informe o modelo da moto (ex.: CG 160, Biz 125, Bros, Fazer 250) para buscar peças compatíveis.',
            ];
        }

        $matched = $this->searchByAttributes($brand, $model, $year, $part, $page);
        if ($this->productCount($matched) === 0 && $year !== '') {
            $matched = $this->searchByAttributes($brand, $model, '', $part, $page);
            if ($this->productCount($matched) > 0) {
                $matched['year_relaxed'] = $year;
            }
        }
        if ($this->productCount($matched) === 0 && $brand !== '') {
            $matched = $this->searchByAttributes('', $model, $year, $part, $page);
            if ($this->productCount($matched) > 0) {
                $matched['brand_relaxed'] = $brand;
            }
        }
        if ($this->productCount($matched) > 0) {
            $matched['strategy'] = 'catalog_match';
            $matched['note'] = 'Resultados que mencionam essa moto no nome ou na ficha. '
                . 'Peça para o cliente confirmar a aplicação na página do produto. '
                . 'Liste SOMENTE estes produtos; não invente SKU, preço ou URL.';
            if (!empty($matched['year_relaxed'])) {
                $matched['note'] .= ' Ano ' . $matched['year_relaxed'] . ' não estava no cadastro; a busca ignorou o ano.';
            }
            if ($brandDropped !== '') {
                $matched['note'] .= ' Marca "' . $brandDropped . '" ignorada: o modelo indica ' . $inferredBrand . '.';
            }
            if (!empty($matched['brand_relaxed'])) {
                $matched['note'] .= ' Marca "' . $matched['brand_relaxed'] . '" não aparecia no nome; a busca ignorou a marca.';
            }
            return $matched;
        }

        $fallbackQuery = trim(implode(' ', array_filter([$part, $brand, $model, $year])));
        $fallback = $this->catalog->search($fallbackQuery, $page);
        if (!is_array($fallback)) {
            $fallback = ['products' => [], 'total' => 0, 'page' => $page, 'pages' => 1];
        }
        $fallback['strategy'] = 'catalog_fallback';
        $fallback['query'] = [
            'brand' => $brand,
            'model' => $model,
            'year'  => $year,
            'part'  => $part,
        ];
        $fallback['note'] = 'Não há ficha de compatibilidade para essa moto. '
            . 'Resultados por busca textual — peça para o cliente confirmar a aplicação na página do produto. '
            . 'Se products estiver vazio, diga que não achou. NÃO invente produto, SKU, preço ou URL.';

        return $fallback;
    }

    public function getAllowedChannels(): array
    {
        return [
            AssistantOrchestratorInterface::CHANNEL_STOREFRONT,
            AssistantOrchestratorInterface::CHANNEL_B2B,
            AssistantOrchestratorInterface::CHANNEL_ADMIN,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function searchByAttributes(
        string $brand,
        string $model,
        string $year,
        string $part,
        int $page
    ): array {
        $collection = $this->baseCollection();
        $collection->addAttributeToSelect([
            'name',
            'price',
            'special_price',
            'thumbnail',
            'url_key',
            'short_description',
            self::ATTR_BRAND,
            self::ATTR_MODEL,
            self::ATTR_YEAR,
        ]);
        $collection->addFinalPrice();

        $sqlNeedle = $this->primarySqlNeedle($part, $model, $brand);
        if ($sqlNeedle !== '') {
            $collection->addAttributeToFilter('name', ['like' => '%' . $this->likeEscape($sqlNeedle) . '%']);
        }

        $collection->setPageSize(80);
        $collection->setCurPage(1);

        $matched = [];
        foreach ($collection as $product) {
            if (!$this->productMatches($product, $brand, $model, $year, $part)) {
                continue;
            }
            $matched[] = $this->formatProduct($product);
        }

        $total = count($matched);
        $slice = array_slice($matched, ($page - 1) * self::PAGE_SIZE, self::PAGE_SIZE);

        return [
            'products' => $slice,
            'total'    => $total,
            'page'     => $page,
            'pages'    => max(1, (int) ceil(max($total, 1) / self::PAGE_SIZE)),
            'query'    => [
                'brand' => $brand,
                'model' => $model,
                'year'  => $year,
                'part'  => $part,
            ],
        ];
    }

    private function primarySqlNeedle(string $part, string $model, string $brand): string
    {
        $modelTokens = $this->tokenize($model);
        if ($modelTokens !== []) {
            $aliases = $this->tokenAliases($modelTokens[0]);

            return $aliases[0];
        }
        $partTokens = $this->tokenize($part);
        if ($partTokens !== []) {
            return $partTokens[0];
        }

        return $brand;
    }

    private function productMatches(
        Product $product,
        string $brand,
        string $model,
        string $year,
        string $part
    ): bool {
        $hay = mb_strtolower(trim(implode(' ', [
            (string) $product->getName(),
            (string) $product->getSku(),
        ])));

        foreach ($this->partNeedles($part) as $group) {
            $hit = false;
            foreach ($group as $needle) {
                if (str_contains($hay, mb_strtolower($needle))) {
                    $hit = true;
                    break;
                }
            }
            if (!$hit) {
                return false;
            }
        }
        foreach ($this->tokenize($model) as $token) {
            $hit = false;
            foreach ($this->tokenAliases($token) as $needle) {
                if (str_contains($hay, $needle)) {
                    $hit = true;
                    break;
                }
            }
            if (!$hit) {
                return false;
            }
        }
        if ($brand !== '' && !str_contains($hay, mb_strtolower($brand))) {
            return false;
        }
        if ($year !== '') {
            $yearOk = str_contains($hay, $year);
            if (!$yearOk) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    private function baseCollection()
    {
        $collection = $this->productCollectionFactory->create();
        $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
        $collection->addAttributeToFilter('visibility', ['in' => [
            Visibility::VISIBILITY_IN_CATALOG,
            Visibility::VISIBILITY_IN_SEARCH,
            Visibility::VISIBILITY_BOTH,
        ]]);
        $collection->addStoreFilter($this->storeManager->getStore()->getId());

        return $collection;
    }

    /**
     * @param array<string, mixed> $result
     */
    private function productCount(array $result): int
    {
        return count($result['products'] ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatProduct(Product $product): array
    {
        $mediaUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
        $baseUrl  = $this->storeManager->getStore()->getBaseUrl();
        $thumbnail = $product->getThumbnail();
        $imageUrl = ($thumbnail && $thumbnail !== 'no_selection')
            ? $mediaUrl . 'catalog/product' . $thumbnail
            : '';

        $price = (float) $product->getFinalPrice();
        $originalPrice = (float) $product->getPrice();
        $hasDiscount = $originalPrice > $price && $price > 0;
        $cadastro = $this->formatFitment($product);

        return [
            'sku' => $product->getSku(),
            'name' => $product->getName(),
            'price' => $price,
            'price_formatted' => 'R$ ' . number_format($price, 2, ',', '.'),
            'original_price' => $hasDiscount ? $originalPrice : null,
            'discount_percent' => $hasDiscount ? (int) round((1 - $price / $originalPrice) * 100) : null,
            'image_url' => $imageUrl,
            'url' => $baseUrl . $product->getUrlKey() . '.html',
            'short_description' => strip_tags((string) $product->getShortDescription()),
            // Nunca promover o fitment solicitado pelo usuário como dado do produto.
            // A vitrine deve exibir apenas compatibilidade cadastrada no catálogo.
            'fitment' => $cadastro,
            'fitment_cadastro' => $cadastro,
            'marca_moto' => trim((string) $product->getData(self::ATTR_BRAND)),
            'modelo_moto' => trim((string) $product->getData(self::ATTR_MODEL)),
            'ano_moto' => trim((string) $product->getData(self::ATTR_YEAR)),
        ];
    }

    private function formatFitment(Product $product): string
    {
        $parts = array_filter([
            trim((string) $product->getData(self::ATTR_BRAND)),
            trim((string) $product->getData(self::ATTR_MODEL)),
            trim((string) $product->getData(self::ATTR_YEAR)),
        ], static fn(string $value): bool => $value !== '');

        return $parts !== [] ? implode(' · ', $parts) : '';
    }

    /**
     * @return list<list<string>>
     */
    private function partNeedles(string $part): array
    {
        $groups = [];
        foreach ($this->tokenize($part) as $token) {
            $synonyms = self::PART_SYNONYMS[$token] ?? [$token];
            $groups[] = $synonyms;
        }

        return $groups;
    }

    /**
     * @return string[]
     */
    private function tokenAliases(string $token): array
    {
        $token = mb_strtolower($token);
        $aliases = self::TOKEN_ALIASES[$token] ?? [$token];

        return array_values(array_unique($aliases));
    }

    private function inferBrandFromModel(string $model): string
    {
        foreach ($this->tokenize($model) as $token) {
            foreach ($this->tokenAliases($token) as $alias) {
                if (isset(self::MODEL_BRAND[$alias])) {
                    return self::MODEL_BRAND[$alias];
                }
            }
        }

        return '';
    }

    private function sameBrand(string $left, string $right): bool
    {
        return mb_strtolower($left) === mb_strtolower($right);
    }

    /**
     * @return string[]
     */
    private function tokenize(string $value): array
    {
        $parts = preg_split('/[\s,;\/|+]+/u', mb_strtolower($value)) ?: [];
        $terms = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if (mb_strlen($part) < 2) {
                continue;
            }
            $terms[] = $part;
        }

        return array_values(array_unique($terms));
    }

    private function normalize(string $value): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        return $value;
    }

    private function normalizeYear(string $value): string
    {
        $value = $this->normalize($value);
        if ($value === '') {
            return '';
        }
        if (mb_strtolower($value) === self::UNIVERSAL_YEAR) {
            return '';
        }
        if (preg_match('/(19|20)\d{2}/', $value, $match) === 1) {
            return $match[0];
        }

        return $value;
    }

    private function likeEscape(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
