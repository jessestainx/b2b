/**
 * AWA Motos — GraphQL queries customizadas para B2B
 *
 * Estas queries extendem o schema Magento padrão para suportar
 * funcionalidades B2B específicas da AWA Motos:
 * - CNPJ ao invés de email
 * - Grupo de atendimento (tabela de preço)
 * - Limite de crédito
 * - Cotação online
 *
 * Para ativar no Magento, criar módulo custom:
 * app/code/GrupoAwamotos/B2BGraphQL/etc/schema.graphqls
 */

import { gql } from '@apollo/client';

// === Queries B2B ===

export const GET_B2B_DASHBOARD = gql`
    query GetB2BDashboard {
        customer {
            id
            email
            firstname
            lastname
            awa_b2b_customer {
                cnpj
                razao_social
                grupo_atendimento
                credit_limit
                credit_used
                credit_available
                approved_quotes_count
                pending_orders_count
            }
        }
    }
`;

export const GET_QUICK_ORDER_PRODUCTS = gql`
    query GetQuickOrderProducts($sku: String!) {
        products(filter: { sku: { eq: $sku } }, pageSize: 1) {
            items {
                id
                sku
                name
                awa_b2b_price {
                    price
                    original_price
                    discount_percent
                }
                awa_b2b_stock {
                    quantity
                    warehouse
                    expected_date
                }
            }
        }
    }
`;

export const REQUEST_QUOTE = gql`
    mutation RequestQuote($items: [QuoteItemInput!]!) {
        awaRequestQuote(items: $items) {
            quote_id
            expires_at
            total
            payment_terms
        }
    }
`;

// === Fragments reutilizáveis ===

export const B2B_CUSTOMER_FRAGMENT = gql`
    fragment B2BCustomerFields on Customer {
        id
        email
        firstname
        lastname
        awa_b2b_customer {
            cnpj
            razao_social
            grupo_atendimento
        }
    }
`;
