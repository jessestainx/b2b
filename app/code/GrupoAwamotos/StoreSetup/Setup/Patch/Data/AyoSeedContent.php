<?php

declare(strict_types=1);

namespace GrupoAwamotos\StoreSetup\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Psr\Log\LoggerInterface;

/**
 * Popula conteúdo inicial para módulos Rokanthemes que dependem de registros no BD:
 *
 * 1. Slider Homepage (rokanthemes_slidebanner) — slider + 3 slides placeholder
 * 2. Testimonials (rokanthemes_testimonials) — 6 depoimentos seed
 * 3. FAQ (rokanthemes_faq) — 20 perguntas frequentes sobre peças para motos
 *
 * Os dados são inseridos diretamente via SQL para evitar dependência da
 * compilação de DI dos módulos Rokanthemes (que pode não estar disponível
 * durante setup:upgrade).
 *
 * @see docs/AUDITORIA_TEMA_AYO.md — seções 7, 10, 23
 */
class AyoSeedContent implements DataPatchInterface
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
            AyoContentSetup::class,
        ];
    }

    public function getAliases(): array
    {
        return [];
    }

    // ========================================================================
    // SLIDER HOMEPAGE
    // ========================================================================

    /**
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
     */
    private function seedSlider($connection): void
    {
        $sliderTable = $this->moduleDataSetup->getTable('rokanthemes_slider');
        $slideTable = $this->moduleDataSetup->getTable('rokanthemes_slide');

        // Verificar se as tabelas existem
        if (!$connection->isTableExists($sliderTable) || !$connection->isTableExists($slideTable)) {
            $this->logger->warning('[AyoSeedContent] Tabelas do SlideBanner não encontradas — pulando slider seed.');
            return;
        }

        // Verificar se slider já existe
        $existingSlider = $connection->fetchOne(
            $connection->select()
                ->from($sliderTable, ['slider_id'])
                ->where('slider_identifier = ?', 'homepageslider')
        );

        if ($existingSlider) {
            $this->logger->info('[AyoSeedContent] Slider "homepageslider" já existe — pulando.');
            return;
        }

        try {
            // Criar o slider principal
            $connection->insert($sliderTable, [
                'slider_title'      => 'Homepage Slider - AWA Motos',
                'slider_identifier' => 'homepageslider',
                'slider_status'     => 1,
                'store_ids'         => '0',
                'slider_setting'    => '{"items":1,"itemsDesktop":"[1199,1]","itemsDesktopSmall":"[980,1]","itemsTablet":"[768,1]","itemsMobile":"[479,1]","slideSpeed":500,"paginationSpeed":500,"rewindSpeed":500,"autoPlay":5000,"navigation":true,"pagination":true}',
            ]);

            $sliderId = $connection->lastInsertId($sliderTable);

            // Criar 3 slides placeholder com conteúdo real
            $slides = $this->getSlideDefinitions((int) $sliderId);

            foreach ($slides as $slide) {
                $connection->insert($slideTable, $slide);
            }

            $this->logger->info(
                sprintf('[AyoSeedContent] Slider "homepageslider" criado com %d slides.', count($slides))
            );
        } catch (\Throwable $e) {
            $this->logger->error(
                sprintf('[AyoSeedContent] Erro ao criar slider: %s', $e->getMessage())
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getSlideDefinitions(int $sliderId): array
    {
        return [
            [
                'slider_id'          => $sliderId,
                'slide_type'         => 1,
                'slide_status'       => 1,
                'slide_position'     => 1,
                'slide_image'        => 'slidebanner/s/l/slider_guidao_cb_300.jpg',
                'slide_image_mobile' => 'slidebanner/s/l/slider_guidao_cb_300.jpg',
                'slide_text'         => null,
                'slide_link'         => '/guidoes.html',
            ],
            [
                'slider_id'          => $sliderId,
                'slide_type'         => 1,
                'slide_status'       => 1,
                'slide_position'     => 2,
                'slide_image'        => 'slidebanner/b/a/bauletos_1.jpg',
                'slide_image_mobile' => 'slidebanner/b/a/bauletos_1.jpg',
                'slide_text'         => null,
                'slide_link'         => '/bauletos.html',
            ],
            [
                'slider_id'          => $sliderId,
                'slide_type'         => 1,
                'slide_status'       => 1,
                'slide_position'     => 3,
                'slide_image'        => 'slidebanner/s/l/slider_005.jpg',
                'slide_image_mobile' => 'slidebanner/s/l/slider_005_mobile.jpg',
                'slide_text'         => '<div class="slide-content slide-3"><div class="slide-text-wrap"><h2 class="slide-title">Atacado para Lojistas e Oficinas</h2><p class="slide-desc">Cadastre-se no programa B2B e tenha preços especiais</p><a href="/b2b/register" class="slide-btn btn btn-primary">Cadastro B2B</a></div></div>',
                'slide_link'         => '/b2b/register',
            ],
        ];
    }

    // ========================================================================
    // TESTIMONIALS
    // ========================================================================

    /**
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
     */
    private function seedTestimonials($connection): void
    {
        $table = $this->moduleDataSetup->getTable('tv_testimonials');
        $storeTable = $this->moduleDataSetup->getTable('tv_testimonials_store');

        if (!$connection->isTableExists($table) || !$connection->isTableExists($storeTable)) {
            $this->logger->warning('[AyoSeedContent] Tabela de testimonials não encontrada — pulando.');
            return;
        }

        // Verificar se já existem depoimentos
        $existingCount = (int) $connection->fetchOne(
            $connection->select()->from($table, ['COUNT(*)'])
        );

        if ($existingCount > 0) {
            $this->logger->info(
                sprintf('[AyoSeedContent] Já existem %d depoimentos — pulando seed.', $existingCount)
            );
            return;
        }

        try {
            $testimonials = $this->getTestimonialDefinitions();

            foreach ($testimonials as $testimonial) {
                $connection->insert($table, $testimonial);
                $testimonialId = (int) $connection->lastInsertId($table);
                $connection->insert($storeTable, [
                    'testimonial_id' => $testimonialId,
                    'store_id'       => 0,
                ]);
            }

            $this->logger->info(
                sprintf('[AyoSeedContent] %d depoimentos criados.', count($testimonials))
            );
        } catch (\Throwable $e) {
            $this->logger->error(
                sprintf('[AyoSeedContent] Erro ao criar depoimentos: %s', $e->getMessage())
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getTestimonialDefinitions(): array
    {
        return [
            [
                'name'       => 'Carlos Silva',
                'email'      => 'carlos.silva@email.com',
                'testimonial' => 'Excelente loja! Comprei um bagageiro para minha CG 160 e chegou rapido, bem embalado. Qualidade top! Ja e minha segunda compra e recomendo.',
                'rating'     => 5,
                'job'        => 'Motoboy - Sao Paulo',
                'position'   => 1,
                'is_active'  => 1,
            ],
            [
                'name'       => 'Ana Pereira',
                'email'      => 'ana.pereira@email.com',
                'testimonial' => 'Atendimento incrivel pelo WhatsApp! Tive duvida sobre compatibilidade do retrovisor com minha Fazer 250 e me ajudaram na hora.',
                'rating'     => 5,
                'job'        => 'Motociclista - Campinas',
                'position'   => 2,
                'is_active'  => 1,
            ],
            [
                'name'       => 'Roberto Mendes',
                'email'      => 'roberto.mendes@email.com',
                'testimonial' => 'Sou dono de oficina e compro no atacado da AWA. Precos competitivos e entrega pontual. O programa B2B facilita muito.',
                'rating'     => 5,
                'job'        => 'Proprietario de Oficina - Ribeirao Preto',
                'position'   => 3,
                'is_active'  => 1,
            ],
            [
                'name'       => 'Fernanda Costa',
                'email'      => 'fernanda.costa@email.com',
                'testimonial' => 'Comprei o bau 45L para minha Bros 160 e superou as expectativas. Acabamento de qualidade e instalacao simples.',
                'rating'     => 5,
                'job'        => 'Viajante - Belo Horizonte',
                'position'   => 4,
                'is_active'  => 1,
            ],
            [
                'name'       => 'Marcos Oliveira',
                'email'      => 'marcos.oliveira@email.com',
                'testimonial' => 'Melhor loja de pecas para motos que ja comprei online. Site facil de navegar e busca por modelo funciona muito bem.',
                'rating'     => 4,
                'job'        => 'Entregador - Araraquara',
                'position'   => 5,
                'is_active'  => 1,
            ],
            [
                'name'       => 'Luciana Ramos',
                'email'      => 'luciana.ramos@email.com',
                'testimonial' => 'Precisava de pecas para minha XRE 300 com urgencia. Fiz o pedido e em 3 dias uteis ja tinha tudo em casa. Nota 10!',
                'rating'     => 5,
                'job'        => 'Motociclista - Curitiba',
                'position'   => 6,
                'is_active'  => 1,
            ],
        ];
    }

    // ========================================================================
    // FAQ
    // ========================================================================

    /**
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
     */
    private function seedFaq($connection): void
    {
        $table = $this->moduleDataSetup->getTable('rokan_faq');

        if (!$connection->isTableExists($table)) {
            $this->logger->warning('[AyoSeedContent] Tabela de FAQ não encontrada — pulando.');
            return;
        }

        // Verificar se já existem FAQs
        $existingCount = (int) $connection->fetchOne(
            $connection->select()->from($table, ['COUNT(*)'])
        );

        if ($existingCount > 0) {
            $this->logger->info(
                sprintf('[AyoSeedContent] Já existem %d FAQs — pulando seed.', $existingCount)
            );
            return;
        }

        try {
            $faqs = $this->getFaqDefinitions();

            foreach ($faqs as $faq) {
                $connection->insert($table, $faq);
            }

            $this->logger->info(
                sprintf('[AyoSeedContent] %d perguntas FAQ criadas.', count($faqs))
            );
        } catch (\Throwable $e) {
            $this->logger->error(
                sprintf('[AyoSeedContent] Erro ao criar FAQs: %s', $e->getMessage())
            );
        }
    }

    /**
     * 20 perguntas frequentes cobrindo todos os cenários de uma loja de peças para motos.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getFaqDefinitions(): array
    {
        $sortOrder = 0;

        return [
            // === ENVIO E ENTREGA ===
            [
                'question'  => 'Qual o prazo de entrega?',
                'answer'    => 'O prazo de entrega varia pela regiao e transportadora escolhida. Apos aprovacao do pagamento, o pedido e preparado em ate 2 dias uteis.',
                'status'    => 1,
                'parent_id' => 0,
            ],
            [
                'question'  => 'Voces oferecem frete gratis?',
                'answer'    => 'Sim! Oferecemos frete gratis para compras acima de R$ 299,00 para todo o Brasil.',
                'status'    => 1,
                'parent_id' => 0,
            ],
            [
                'question'  => 'Como rastreio meu pedido?',
                'answer'    => 'Apos o envio, voce recebe o codigo por e-mail e pode acompanhar em Minha Conta > Meus Pedidos.',
                'status'    => 1,
                'parent_id' => 0,
            ],
            [
                'question'  => 'Entregam em todo o Brasil?',
                'answer'    => 'Sim, entregamos para todas as regioes do Brasil pelos Correios e transportadoras parceiras.',
                'status'    => 1,
                'parent_id' => 0,
            ],
            // === PAGAMENTO ===
            [
                'question'  => 'Quais formas de pagamento sao aceitas?',
                'answer'    => 'Aceitamos PIX, boleto, cartoes de credito e debito online.',
                'status'    => 1,
                'parent_id' => 0,
            ],
            [
                'question'  => 'Posso parcelar minha compra?',
                'answer'    => 'Sim, parcelamos em ate 12x sem juros no cartao de credito.',
                'status'    => 1,
                'parent_id' => 0,
            ],
            [
                'question'  => 'O pagamento por PIX e seguro?',
                'answer'    => 'Sim. O PIX e regulamentado pelo Banco Central e o pedido aprova rapidamente.',
                'status'    => 1,
                'parent_id' => 0,
            ],
            // === PRODUTOS ===
            [
                'question'  => 'Como sei se a peca serve na minha moto?',
                'answer'    => 'Cada produto possui compatibilidade por marca, modelo e ano. Em caso de duvida, fale no WhatsApp.',
                'status'    => 1,
                'parent_id' => 0,
            ],
            [
                'question'  => 'Os produtos possuem garantia?',
                'answer'    => 'Sim, todos os produtos possuem garantia do fabricante.',
                'status'    => 1,
                'parent_id' => 0,
            ],
            [
                'question'  => 'Voces vendem pecas originais ou paralelas?',
                'answer'    => 'Trabalhamos com pecas de marcas reconhecidas e com procedencia.',
                'status'    => 1,
                'parent_id' => 0,
            ],
            // === TROCAS E DEVOLUÇÕES ===
            [
                'question'  => 'Como faco para trocar ou devolver um produto?',
                'answer'    => 'Voce tem ate 7 dias corridos apos o recebimento, conforme CDC.',
                'status'    => 1,
                'parent_id' => 0,
            ],
            [
                'question'  => 'Se a peca nao servir, posso devolver?',
                'answer'    => 'Sim. Em caso de incompatibilidade confirmada, providenciamos troca ou reembolso.',
                'status'    => 1,
                'parent_id' => 0,
            ],
            [
                'question'  => 'Em quanto tempo recebo o reembolso?',
                'answer'    => 'Apos confirmacao da devolucao, o reembolso e processado em ate 5 dias uteis.',
                'status'    => 1,
                'parent_id' => 0,
            ],
            // === CONTA E CADASTRO ===
            [
                'question'  => 'Preciso criar conta para comprar?',
                'answer'    => 'Nao e obrigatorio no varejo, mas criar conta facilita acompanhar pedidos e enderecos.',
                'status'    => 1,
                'parent_id' => 0,
            ],
            [
                'question'  => 'O que e o programa B2B?',
                'answer'    => 'Cadastro para lojistas, oficinas e revendedores com condicoes especiais de atacado.',
                'status'    => 1,
                'parent_id' => 0,
            ],
            [
                'question'  => 'Como me cadastro no programa B2B?',
                'answer'    => 'Acesse a pagina de cadastro B2B, envie os dados da empresa e aguarde aprovacao.',
                'status'    => 1,
                'parent_id' => 0,
            ],
            // === SEGURANÇA ===
            [
                'question'  => 'A loja e confiavel?',
                'answer'    => 'Sim. Somos distribuidora estabelecida em Araraquara-SP e usamos SSL e gateways seguros.',
                'status'    => 1,
                'parent_id' => 0,
            ],
            [
                'question'  => 'Meus dados estao protegidos?',
                'answer'    => 'Sim. Seguimos LGPD e usamos criptografia para proteger os dados.',
                'status'    => 1,
                'parent_id' => 0,
            ],
            // === CONTATO ===
            [
                'question'  => 'Como entro em contato com voces?',
                'answer'    => 'WhatsApp (16) 99736-7588, telefone, e-mail e formulario no site.',
                'status'    => 1,
                'parent_id' => 0,
            ],
            [
                'question'  => 'Posso retirar na loja fisica?',
                'answer'    => 'Sim. Selecione retirada na loja no checkout e aguarde confirmacao.',
                'status'    => 1,
                'parent_id' => 0,
            ],
        ];
    }
}
