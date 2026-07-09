<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Setup\Patch\Data;

use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Reduz a quantidade de colunas visíveis por padrão na grid de clientes do admin.
 *
 * Os atributos B2B (CNAE, UTM, score de aprovação etc.) foram cadastrados com
 * is_visible_in_grid=true, somando ~20 colunas extras às padrão do Magento e
 * causando overflow horizontal de página inteira na grid (Customers > All
 * Customers). Esta patch mantém os atributos disponíveis via seletor de
 * colunas (is_used_in_grid permanece true) mas oculta por padrão os menos
 * usados no dia a dia, preservando os essenciais (CNPJ, Razão Social, Status
 * de Aprovação, Limite de Crédito) visíveis.
 */
class OptimizeCustomerGridColumnVisibility implements DataPatchInterface
{
    private const HIDE_BY_DEFAULT = [
        'b2b_approval_score',
        'b2b_approval_score_reason',
        'b2b_approved_at',
        'b2b_cnae_code',
        'b2b_cnae_description',
        'b2b_cnae_profile',
        'b2b_inscricao_estadual',
        'b2b_origin_host',
        'b2b_person_type',
        'b2b_registration_campaign',
        'b2b_suggested_group_id',
        'b2b_utm_campaign',
        'b2b_utm_content',
        'b2b_utm_medium',
        'b2b_utm_source',
        'b2b_utm_term',
    ];

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly CustomerSetupFactory $customerSetupFactory
    ) {
    }

    public function apply(): void
    {
        $this->moduleDataSetup->startSetup();

        $customerSetup = $this->customerSetupFactory->create(['setup' => $this->moduleDataSetup]);

        foreach (self::HIDE_BY_DEFAULT as $code) {
            if (!$customerSetup->getAttributeId(Customer::ENTITY, $code)) {
                continue;
            }

            $attribute = $customerSetup->getEavConfig()->getAttribute(Customer::ENTITY, $code);
            $attribute->setData('is_visible_in_grid', 0);
            $attribute->save();
        }

        $this->moduleDataSetup->endSetup();
    }

    /**
     * @return string[]
     */
    public static function getDependencies(): array
    {
        return [
            CreateB2BCustomerAttributes::class,
            AddRegistrationAttributionAttributes::class,
            AddApprovalScoreAttributes::class,
            AddCnaeProfileAttributes::class,
            AddErpPendingGridAttributes::class,
        ];
    }

    /**
     * @return string[]
     */
    public function getAliases(): array
    {
        return [];
    }
}
