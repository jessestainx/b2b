<?php

declare(strict_types=1);

namespace GrupoAwamotos\StoreSetup\Plugin;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\Visitor;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\Fields;
use Magento\Framework\GraphQl\Query\IntrospectionConfiguration;

/**
 * Enforces Adobe Magento graphql/disable_introspection before schema expansion.
 *
 * Magento's webonyx DisableIntrospection rule runs after SchemaGenerator::generate().
 * Introspection queries empty the field list (Fields::setQuery), so generate()
 * instantiates every type. That fatals when optional Payment Services modules
 * are disabled and leaks a production 500 instead of a GraphQL error.
 */
class DisableGraphQlIntrospectionPlugin
{
    public function __construct(
        private readonly IntrospectionConfiguration $introspectionConfiguration
    ) {
    }

    /**
     * @param DocumentNode|string $query
     * @param array<string, mixed>|null $variables
     * @return array{0: DocumentNode|string, 1: array<string, mixed>|null}
     * @throws GraphQlInputException
     */
    public function beforeSetQuery(
        Fields $subject,
        DocumentNode|string $query,
        ?array $variables = null
    ): array {
        if (!$this->introspectionConfiguration->isIntrospectionDisabled()) {
            return [$query, $variables];
        }

        if ($this->isIntrospectionQuery($query)) {
            throw new GraphQlInputException(__('GraphQL introspection is not allowed.'));
        }

        return [$query, $variables];
    }

    private function isIntrospectionQuery(DocumentNode|string $query): bool
    {
        $names = [];
        if (is_string($query)) {
            return $this->sourceLooksLikeIntrospection($query);
        }

        try {
            Visitor::visit(
                $query,
                [
                    'leave' => [
                        NodeKind::NAME => static function (Node $node) use (&$names): void {
                            if (isset($node->value) && is_string($node->value)) {
                                $names[$node->value] = true;
                            }
                        },
                    ],
                ]
            );
        } catch (\Throwable) {
            return false;
        }

        return isset($names['__schema'])
            || isset($names['__type'])
            || isset($names['IntrospectionQuery']);
    }

    private function sourceLooksLikeIntrospection(string $source): bool
    {
        return (bool) preg_match('/\b(?:__schema|__type|IntrospectionQuery)\b/', $source);
    }
}
