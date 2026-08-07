<?php
/**
 * Mirasvit
 *
 * This source file is subject to the Mirasvit Software License, which is available at https://mirasvit.com/license/.
 * Do not edit or add to this file if you wish to upgrade the to newer versions in the future.
 * If you wish to customize this module for your needs.
 * Please refer to http://www.magentocommerce.com for more information.
 *
 * @category  Mirasvit
 * @package   mirasvit/module-search-ultimate
 * @version   2.2.70
 * @copyright Copyright (C) 2024 Mirasvit (https://mirasvit.com/)
 */


declare(strict_types=1);

namespace Mirasvit\SearchElastic\InstantProvider;

use Mirasvit\Search\Model\ConfigProvider;
use Mirasvit\SearchAutocomplete\InstantProvider\InstantProvider;
use Mirasvit\SearchElastic\SearchAdapter\QueryBuilder;

class  EngineProvider extends InstantProvider
{
    private $query          = [];

    private $activeFilters  = [];

    private $applyFilter    = false;

    private $filtersToApply = [];

    private $buckets        = [];

    public function getResults(string $indexIdentifier): array
    {
        $queryBuilder = new QueryBuilder($this->queryService);

        $storeId = $this->getStoreId();
        $groupId = $this->getCustomerGroupId();

        $this->query = [
            'index'            => $this->configProvider->getIndexName($indexIdentifier),
            'body'             => [
                'from'          => $this->getFrom($indexIdentifier),
                'size'          => $this->getLimit($indexIdentifier),
                'stored_fields' => [
                    '_id',
                    '_score',
                    '_source',
                ],
                'sort'          => [
                    [
                        '_score' => [
                            'order' => 'desc',
                        ],
                    ],
                ],
                'query'         => [
                    'bool' => [
                        'minimum_should_match' => 0,
                    ],
                ],
            ],
            'track_total_hits' => true,
        ];

        $fields = $this->configProvider->getIndexFields($indexIdentifier);

        $fields[ConfigProvider::MISC_FIELD] = 1;

        $this->query['body']['query'] = $queryBuilder->build($this->query['body']['query'], $this->getQueryText(), $fields);

        $this->setMustCondition($indexIdentifier);

        if ($indexIdentifier === 'magento_catalog_product') {
            // [AWA][SRCH-005] Instant fast-mode always called setBuckets(), even when
            // displayFilters=disable. Text buckets (marca_moto/modelo_moto/ano_moto)
            // then throw illegal_argument_exception and getResults() swallows it into
            // totalItems=0 — autocomplete empty while OpenSearch/PLP still work.
            // Only build aggregations when Instant UI actually consumes them.
            $layeredNav = $this->configProvider->getLayeredNavigationPosition();
            if ($layeredNav === 'filters_top' || $layeredNav === 'filters_sidebar') {
                $this->setBuckets();
            }

            if ($this->getCategoryId()) {
                $this->query['body']['query']['bool']['must'][] = [
                    'term' => [
                        'category_ids' => $this->getCategoryId(),
                    ],
                ];
            }

            if ($this->configProvider->isCatalogPermissionsFeatureEnabled()) {
                $this->query['body']['query']['bool']['must_not'][] = [
                    'term' => [
                        'category_permission_' . $storeId . '_' . $groupId => -2,
                    ],
                ];
            }

            $this->query['body']['query']['script_score']['query']  = $this->query['body']['query'];
            $this->query['body']['query']['script_score']['script'] = [
                'source' => ConfigProvider::DEFAULT_SCORE . " + _score * doc['" . ConfigProvider::MULTIPLY_ATTRIBUTE . "'].value + doc['" . ConfigProvider::SUM_ATTRIBUTE . "'].value",
            ];

            unset($this->query['body']['query']['bool']);
        }

        if ($indexIdentifier === 'magento_catalog_category' && $this->configProvider->isCatalogPermissionsFeatureEnabled()) {
            $this->query['body']['query']['bool']['must_not'][] = [
                'term' => [
                    'grant_catalog_category_view_'. $storeId . '_' . $groupId => -2,
                ],
            ];
        }

        try {
            $rawResponse = $this->getClient()->search($this->query);
        } catch (\Exception $e) {
            $correctedQuery = $this->suggest();
            if ($correctedQuery && $correctedQuery != $this->getQueryText()) {
                $this->setQueryText($correctedQuery);

                return $this->getResults($indexIdentifier);
            } else {
                return [
                    'totalItems' => 0,
                    'items'      => [],
                    'buckets'    => [],
                ];
            }
        }

        if ($this->isDebug()) {
            print_r('<pre>');
            print_r($this->query);
            print_r($rawResponse);
            // @codingStandardsIgnoreStart
            die();
            // @codingStandardsIgnoreEnd
        }

        if ($this->configProvider->getEngine() == 'elasticsearch6') {
            $totalItems = (int)$rawResponse['hits']['total'];
        } else {
            $totalItems = (int)$rawResponse['hits']['total']['value'];
        }

        $correctedQuery = $this->suggest();
        if ($totalItems < 1 && $correctedQuery && $correctedQuery != $this->getQueryText()) {
            $this->setQueryText($correctedQuery);

            return $this->getResults($indexIdentifier);
        }

        $items   = [];

        foreach ($rawResponse['hits']['hits'] as $data) {
            if (!isset($data['_source']['_instant'])) {
                continue;
            }

            $items[] = $data['_source']['_instant'];
        }

        $buckets = [];


        if (isset($rawResponse['aggregations'])) {
            foreach ($this->configProvider->getBuckets() as $code => $bucket) {
                if (!isset($rawResponse['aggregations'][$code])) {
                    continue;
                }
                $data = $rawResponse['aggregations'][$code];

                if ($code == 'price') {
                    $bucketData = $this->configProvider->getBucketOptionsData($code, []);

                    $bucketData['min']   = $data['min'];
                    $bucketData['max']   = $data['max'];
                    $bucketData['items'] = [];

                    $buckets[$code]       = $bucketData;
                    $this->buckets[$code] = $bucketData;
                } else {
                    $bucketData = $this->configProvider->getBucketOptionsData($code, $data['buckets']);
                    if (empty($bucketData)) {
                        continue;
                    }
                    if (in_array($code, $this->filtersToApply)) {
                        continue;
                    }

                    $buckets[$code]       = $bucketData;
                    $this->buckets[$code] = $bucketData;
                }
            }


            if (!empty($this->filtersToApply)) {
                foreach (array_diff(array_keys($this->buckets), array_keys($buckets)) as $bucketCode) {
                    if (!in_array($bucketCode, $this->filtersToApply)) {
                        unset($this->buckets[$bucketCode]);
                    }
                }
            } else {
                $this->buckets = $buckets;
            }
        }

        if (count($this->getActiveFilters()) > 0 && !$this->applyFilter) {
            $this->applyFilter = true;

            foreach ($this->getActiveFilters() as $filterKey => $value) {
                $this->filtersToApply[] = $filterKey;

                $result  = $this->getResults($indexIdentifier);
                $buckets = $this->prepareBuckets($buckets);
                foreach ($result['buckets'] as $bucketKey => $bucket) {
                    if (in_array($bucketKey, $this->filtersToApply)) {
                        continue;
                    }

                    $this->buckets[$bucketKey] = $bucket;
                }

                $totalItems = $result['totalItems'];
                $items      = $result['items'];
            }
        }

        return [
            'totalItems' => count($items) > 0 ? $totalItems : 0,
            'items'      => $items,
            'buckets'    => $this->buckets,
        ];
    }

    public function suggest(): ?string
    {
        if (!in_array('mst_misspell_index', $this->configProvider->getIndexes())) {
            return null;
        }

        $query    = preg_split('/[\s]+/', $this->getQueryText());
        $response = [];

        if (!is_array($query)) {
            $query = [$query];
        }

        try {
            foreach ($query as $term) {
                $result = $this->getClient()->search($this->prepareTermSuggestQuery($term));
                if (is_object($result)) { // ES8
                    $result = $result->asArray();
                }
                $processedResponse = $this->processResponse($result);

                if (empty($processedResponse)) {
                    $result = $this->getClient()->search($this->preparePhraseSuggestQuery($term));
                    if (is_object($result)) { // ES8
                        $result = $result->asArray();
                    }
                    $processedResponse = $this->processResponse($result);
                }

                $response[] = $processedResponse;
            }
        } catch (\Exception $e) {
        }

        $response = array_filter($response);
        $response = array_unique($response);

        if (empty($response)) {
            return null;
        }

        return implode(' ', $response);
    }

    private function getActiveFilters(): array
    {
        if (empty($this->activeFilters)) {
            $this->activeFilters = $this->configProvider->getActiveFilters();
        }

        if (!empty($this->filtersToApply)) {
            return array_intersect_key($this->activeFilters, array_flip($this->filtersToApply));
        }

        return $this->activeFilters;
    }

    private function setMustCondition(string $indexIdentifier): void
    {
        if ($indexIdentifier === 'magento_catalog_product') {
            $this->query['body']['query']['bool']['must'][] = [
                'terms' => [
                    'visibility' => ['3', '4'],
                ],
            ];

            if ($this->applyFilter) {
                foreach ($this->getActiveFilters() as $filterCode => $filterValue) {
                    if ($filterCode == 'price') {
                        $priceFilter = [];
                        foreach ($filterValue as $value) {
                            [$from, $to] = explode('_', $value);
                            $priceFilter['bool']['should'][] = [
                                'range' => [
                                    'price_0_1' => [
                                        'gte' => $from,
                                        'lte' => $to,
                                    ],
                                ],
                            ];
                        }

                        $this->query['body']['query']['bool']['must'] = array_merge($this->query['body']['query']['bool']['must'], [$priceFilter]);
                    } else {
                        $termStatement = is_array($filterValue) ? 'terms' : 'term';

                        $this->query['body']['query']['bool']['must'][] = [
                            $termStatement => [
                                $filterCode => $filterValue,
                            ],
                        ];
                    }
                }
            }
        }
    }

    private function setBuckets(): void
    {
        foreach ($this->getBuckets() as $fieldName) {
            if ($fieldName == 'price') {
                $this->query['body']['aggregations'][$fieldName] = ['extended_stats' => ['field' => 'price_0_1']];
            } else {
                $this->query['body']['aggregations'][$fieldName] = ['terms' => ['field' => $this->resolveAggregationField($fieldName), 'size' => 500]];
            }
        }
    }

    /**
     * [AWA][SRCH-004] Analyzed "text" fields cannot be used directly in a terms
     * aggregation (OpenSearch/Elasticsearch throws illegal_argument_exception:
     * "fielddata=false"). Attributes such as marca_moto/modelo_moto/ano_moto are
     * free-text and get indexed as "text" with a ".keyword" sub-field by the
     * Mirasvit indexer, while select attributes (manufacturer, color) are
     * already indexed as "integer" and do not have (nor need) a ".keyword"
     * sub-field. Resolve the correct field name dynamically instead of
     * assuming one convention for all bucket fields, so a single
     * misconfigured field cannot break the whole product search query.
     *
     * Why this is a direct edit to a Mirasvit file (exception to the
     * plugin-only policy used elsewhere in app/code/GrupoAwamotos/*): this
     * class is never instantiated through Magento's ObjectManager. It is
     * created with `new` inside
     * Mirasvit\SearchAutocomplete\InstantProvider\InstantProvider, a
     * standalone script required directly by
     * Mirasvit/SearchAutocomplete/registration.php and executed during early
     * bootstrap, before the DI container exists. Magento plugins/preferences
     * only work through generated Interceptor classes that wrap objects
     * created by the ObjectManager, so there is no way to intercept this code
     * path with a plugin, preference, or virtual type. See SRCH-003 in
     * app/code/GrupoAwamotos/CatalogFix/etc/di.xml for the equivalent fix
     * applied as a real DI plugin on the native catalog/layered-navigation
     * aggregation builder, where the DI container is available.
     *
     * @var array<string, string>
     */
    private static $aggregationFieldCache = [];

    private function resolveAggregationField(string $fieldName): string
    {
        $indexName = $this->configProvider->getIndexName('magento_catalog_product');
        $cacheKey  = $indexName . ':' . $fieldName;

        if (array_key_exists($cacheKey, self::$aggregationFieldCache)) {
            return self::$aggregationFieldCache[$cacheKey];
        }

        $resolved = $fieldName;

        try {
            $mapping = $this->getClient()->indices()->getFieldMapping([
                'index'  => $indexName,
                'fields' => $fieldName,
            ]);
            if (is_object($mapping) && method_exists($mapping, 'asArray')) {
                $mapping = $mapping->asArray();
            }

            foreach ($mapping as $indexMapping) {
                $fieldDef = $indexMapping['mappings'][$fieldName]['mapping'][$fieldName] ?? null;
                if (
                    is_array($fieldDef)
                    && ($fieldDef['type'] ?? null) === 'text'
                    && isset($fieldDef['fields']['keyword'])
                ) {
                    $resolved = $fieldName . '.keyword';
                }
                break;
            }
        } catch (\Exception $e) {
            // Keep $resolved as the original field name; the aggregation may still
            // fail for this bucket, but it will no longer take down the whole query
            // since callers of getResults() already catch search exceptions.
        }

        self::$aggregationFieldCache[$cacheKey] = $resolved;

        return $resolved;
    }

    private function prepareBuckets($buckets): array
    {
        foreach ($buckets as $key => $bucket) {
            if ($key == 'price') {
                continue;
            }

            foreach ($bucket['items'] as $optionKey => $option) {
                $buckets[$key]['items'][$optionKey]['count'] = 0;
            }
        }

        return $buckets;
    }

    private function getClient()
    {
        $esConfig = $this->configProvider->getEngineConnection();

        // [AWA][SRCH-006] OpenSearch rejects the Elasticsearch PHP v8 client Content-Type
        // (application/vnd.elasticsearch+json; compatible-with=8) with HTTP 406.
        // Prefer OpenSearch\ClientBuilder, then ES7 client. ES8 only as last resort.
        if (class_exists('OpenSearch\ClientBuilder')) {
            return \OpenSearch\ClientBuilder::fromConfig($esConfig, true);
        }

        if (class_exists('Elasticsearch\ClientBuilder')) {
            return \Elasticsearch\ClientBuilder::fromConfig($esConfig, true);
        }

        if (class_exists('Elastic\Elasticsearch\ClientBuilder')) {
            return \Elastic\Elasticsearch\ClientBuilder::fromConfig($esConfig, true);
        }

        throw new \RuntimeException('No compatible search client for InstantProvider');
    }

    private function prepareTermSuggestQuery(string $query): array
    {
        return [
            'index' => $this->getIndexName(),
            'body'  => [
                'suggest' => [
                    'suggestion' => [
                        'text' => $query,
                        'term' => [
                            'field'         => 'keyword',
                            'size'          => 1,
                            'prefix_length' => 0,
                        ],
                    ],
                ],
            ],
        ];
    }

    private function preparePhraseSuggestQuery(string $query): array
    {
        return [
            'index' => $this->getIndexName(),
            'body'  => [
                'suggest' => [
                    'text'       => $query,
                    'suggestion' => [
                        'phrase' => [
                            'field'            => 'keyword.trigram',
                            'size'             => 1,
                            'gram_size'        => 3,
                            'max_errors'       => 100,
                            'direct_generator' => [
                                [
                                    'field'        => 'keyword.trigram',
                                    'suggest_mode' => 'always',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function getIndexName(): string
    {
        return $this->configProvider->getIndexName('mst_misspell_index');
    }

    private function processResponse(array $response): ?string
    {
        $result = null;
        if (isset($response['suggest']['suggestion'][0]['options'][0]['text'])) {
            $result = $response['suggest']['suggestion'][0]['options'][0]['text'];
        } else {
            if (isset($response['suggest']['suggestion'][0]['text'])) {
                $result = $response['suggest']['suggestion'][0]['text'];
            }
        }

        return $result;
    }
}
