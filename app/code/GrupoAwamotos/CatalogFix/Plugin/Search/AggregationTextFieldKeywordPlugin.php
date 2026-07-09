<?php

declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Plugin\Search;

use Magento\Elasticsearch\Model\Adapter\FieldMapper\Product\AttributeProvider;
use Magento\Elasticsearch\Model\Adapter\FieldMapper\Product\FieldProvider\FieldType\ConverterInterface
    as FieldTypeConverterInterface;
use Magento\Elasticsearch\SearchAdapter\Query\Builder\Aggregation;
use Magento\Framework\Search\RequestInterface;

/**
 * Fix OpenSearch 400 on PLP aggregations for text EAV attributes (marca_moto, etc.).
 *
 * Term filters append ".keyword" for text fields, but Aggregation builder does not.
 * OpenSearch rejects terms aggregations on text fields → Adapter swallows the 400
 * and returns 0 hits → empty product grid with non-zero toolbar count.
 *
 * @see \Magento\Elasticsearch\SearchAdapter\Filter\Builder\Term::buildFilter()
 * @see \Magento\Elasticsearch\SearchAdapter\Query\Builder\Aggregation::buildBucket()
 */
class AggregationTextFieldKeywordPlugin
{
    /**
     * Attributes indexed as integer in OpenSearch despite varchar backend.
     *
     * @var string[]
     */
    private array $integerTypeAttributes = ['category_ids'];

    public function __construct(
        private readonly AttributeProvider $attributeProvider
    ) {
    }

    /**
     * Append .keyword to aggregation fields for text attributes.
     *
     * @param Aggregation $subject
     * @param array $searchQuery
     * @param RequestInterface $request
     * @param array $originalSearchQuery
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterBuild(
        Aggregation $subject,
        array $searchQuery,
        RequestInterface $request,
        array $originalSearchQuery
    ): array {
        if (!isset($searchQuery['body']['aggregations'])) {
            return $searchQuery;
        }

        foreach ($request->getAggregation() as $bucket) {
            $bucketName = $bucket->getName();
            $aggField = $searchQuery['body']['aggregations'][$bucketName]['terms']['field'] ?? null;
            if ($aggField === null) {
                continue;
            }

            $attributeCode = $bucket->getField();
            if (in_array($attributeCode, $this->integerTypeAttributes, true)) {
                continue;
            }

            try {
                $attribute = $this->attributeProvider->getByAttributeCode($attributeCode);
            } catch (\Throwable) {
                continue;
            }

            if (!$attribute->isTextType()) {
                continue;
            }

            $keywordField = $attributeCode . '.' . FieldTypeConverterInterface::INTERNAL_DATA_TYPE_KEYWORD;
            if ($aggField === $keywordField) {
                continue;
            }

            $searchQuery['body']['aggregations'][$bucketName]['terms']['field'] = $keywordField;
        }

        return $searchQuery;
    }
}
