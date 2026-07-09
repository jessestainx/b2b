<?php

declare(strict_types=1);

namespace GrupoAwamotos\StoreSetup\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Psr\Log\LoggerInterface;

/**
 * Backfill de seed Ayo com nomes de tabelas/campos corretos dos modulos
 * Rokanthemes instalados no projeto.
 */
class AyoSeedContentV2 implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly LoggerInterface $logger
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->startSetup();

        $connection = $this->moduleDataSetup->getConnection();
        $this->seedSlider($connection);
        $this->seedTestimonials($connection);
        $this->seedFaq($connection);

        $this->moduleDataSetup->endSetup();

        return $this;
    }

    public static function getDependencies(): array
    {
        return [
            AyoSeedContent::class,
        ];
    }

    public function getAliases(): array
    {
        return [];
    }

    /**
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
     */
    private function seedSlider($connection): void
    {
        $sliderTable = $this->moduleDataSetup->getTable('rokanthemes_slider');
        $slideTable = $this->moduleDataSetup->getTable('rokanthemes_slide');

        if (!$connection->isTableExists($sliderTable) || !$connection->isTableExists($slideTable)) {
            $this->logger->warning('[AyoSeedContentV2] Tabelas do SlideBanner nao encontradas.');
            return;
        }

        $sliderId = $connection->fetchOne(
            $connection->select()
                ->from($sliderTable, ['slider_id'])
                ->where('slider_identifier = ?', 'homepageslider')
        );

        if (!$sliderId) {
            $connection->insert($sliderTable, [
                'slider_title'      => 'Homepage Slider - AWA Motos',
                'slider_identifier' => 'homepageslider',
                'slider_status'     => 1,
                'store_ids'         => '0',
                'slider_setting'    => '{"items":1,"itemsDesktop":"[1199,1]","itemsDesktopSmall":"[980,1]","itemsTablet":"[768,1]","itemsMobile":"[479,1]","slideSpeed":500,"paginationSpeed":500,"rewindSpeed":500,"autoPlay":5000,"navigation":true,"pagination":true}',
            ]);
            $sliderId = (int) $connection->lastInsertId($sliderTable);
        } else {
            $sliderId = (int) $sliderId;
        }

        $existingSlides = (int) $connection->fetchOne(
            $connection->select()
                ->from($slideTable, ['COUNT(*)'])
                ->where('slider_id = ?', $sliderId)
        );

        if ($existingSlides >= 3) {
            $this->logger->info('[AyoSeedContentV2] Slider ja possui 3+ slides, sem alteracao.');
            return;
        }

        $slides = [
            [
                'slider_id'      => $sliderId,
                'slide_type'     => 2,
                'slide_status'   => 1,
                'slide_position' => 1,
                'slide_text'     => '<div class="slide-content slide-1"><h2>Pecas e Acessorios para Motos</h2><p>Bagageiros, baus, retrovisores e mais para sua moto.</p></div>',
                'slide_link'     => '/catalogsearch/result/?q=bagageiro',
            ],
            [
                'slider_id'      => $sliderId,
                'slide_type'     => 2,
                'slide_status'   => 1,
                'slide_position' => 2,
                'slide_text'     => '<div class="slide-content slide-2"><h2>Atacado para Lojistas e Oficinas</h2><p>Cadastre-se no programa B2B e tenha condicoes especiais.</p></div>',
                'slide_link'     => '/b2b/register',
            ],
            [
                'slider_id'      => $sliderId,
                'slide_type'     => 2,
                'slide_status'   => 1,
                'slide_position' => 3,
                'slide_text'     => '<div class="slide-content slide-3"><h2>Frete Gratis Acima de R$ 299</h2><p>Entrega rapida e segura para todo o Brasil.</p></div>',
                'slide_link'     => '/ofertas',
            ],
        ];

        foreach ($slides as $slide) {
            $connection->insert($slideTable, $slide);
        }

        $this->logger->info('[AyoSeedContentV2] Slider homepageslider preenchido com 3 slides.');
    }

    /**
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
     */
    private function seedTestimonials($connection): void
    {
        $table = $this->moduleDataSetup->getTable('tv_testimonials');
        $storeTable = $this->moduleDataSetup->getTable('tv_testimonials_store');

        if (!$connection->isTableExists($table) || !$connection->isTableExists($storeTable)) {
            $this->logger->warning('[AyoSeedContentV2] Tabelas de testimonials nao encontradas.');
            return;
        }

        $existingCount = (int) $connection->fetchOne(
            $connection->select()->from($table, ['COUNT(*)'])
        );

        if ($existingCount > 0) {
            $this->logger->info('[AyoSeedContentV2] Testimonials ja existem, sem alteracao.');
            return;
        }

        $rows = [
            ['name' => 'Carlos Silva', 'email' => 'carlos.silva@email.com', 'testimonial' => 'Excelente loja, entrega rapida e pecas de qualidade.', 'rating' => 5, 'job' => 'Motoboy - Sao Paulo', 'position' => 1, 'is_active' => 1],
            ['name' => 'Ana Pereira', 'email' => 'ana.pereira@email.com', 'testimonial' => 'Atendimento muito bom e compatibilidade confirmada antes da compra.', 'rating' => 5, 'job' => 'Motociclista - Campinas', 'position' => 2, 'is_active' => 1],
            ['name' => 'Roberto Mendes', 'email' => 'roberto.mendes@email.com', 'testimonial' => 'Programa B2B excelente para oficina, com condicoes diferenciadas.', 'rating' => 5, 'job' => 'Oficina - Ribeirao Preto', 'position' => 3, 'is_active' => 1],
        ];

        foreach ($rows as $row) {
            $connection->insert($table, $row);
            $testimonialId = (int) $connection->lastInsertId($table);
            $connection->insert($storeTable, [
                'testimonial_id' => $testimonialId,
                'store_id' => 0,
            ]);
        }

        $this->logger->info('[AyoSeedContentV2] Testimonials seed aplicados (3 registros).');
    }

    /**
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
     */
    private function seedFaq($connection): void
    {
        $table = $this->moduleDataSetup->getTable('rokan_faq');

        if (!$connection->isTableExists($table)) {
            $this->logger->warning('[AyoSeedContentV2] Tabela rokan_faq nao encontrada.');
            return;
        }

        $existingCount = (int) $connection->fetchOne(
            $connection->select()->from($table, ['COUNT(*)'])
        );

        if ($existingCount > 0) {
            $this->logger->info('[AyoSeedContentV2] FAQ ja possui registros, sem alteracao.');
            return;
        }

        $rows = [
            ['question' => 'Qual o prazo de entrega?', 'answer' => 'Apos aprovacao do pagamento, o pedido e preparado em ate 2 dias uteis.', 'status' => 1, 'parent_id' => 0],
            ['question' => 'Voces oferecem frete gratis?', 'answer' => 'Sim, para compras acima de R$ 299,00.', 'status' => 1, 'parent_id' => 0],
            ['question' => 'Como confirmar compatibilidade da peca?', 'answer' => 'Use busca por aplicacao ou fale com atendimento para validacao.', 'status' => 1, 'parent_id' => 0],
            ['question' => 'Quais meios de pagamento?', 'answer' => 'PIX, boleto, cartao de credito e debito online.', 'status' => 1, 'parent_id' => 0],
            ['question' => 'Como solicitar troca?', 'answer' => 'Solicite em ate 7 dias corridos apos recebimento, conforme CDC.', 'status' => 1, 'parent_id' => 0],
        ];

        foreach ($rows as $row) {
            $connection->insert($table, $row);
        }

        $this->logger->info('[AyoSeedContentV2] FAQ seed aplicados (5 registros).');
    }
}
