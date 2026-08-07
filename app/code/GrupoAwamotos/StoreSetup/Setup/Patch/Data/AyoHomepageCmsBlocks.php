<?php

declare(strict_types=1);

namespace GrupoAwamotos\StoreSetup\Setup\Patch\Data;

use GrupoAwamotos\StoreSetup\Setup\CmsBlockData;
use Magento\Cms\Model\Block;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Psr\Log\LoggerInterface;

/**
 * Cria os blocos CMS da homepage que antes só existiam via CLI (awa:setup).
 *
 * Estes 13 blocos são referenciados por top-home.phtml e ficam vazios
 * se o StoreConfigurator nunca foi executado manualmente.
 *
 * Blocos cobertos:
 * - block_top             (barra de benefícios — 5 itens)
 * - banner_mid_home5      (3 banners mid-page)
 * - home_product_promo_banners (3 banners promocionais de produto)
 * - notification_home5    (ticker de notificações promo)
 * - category1_home5       (Categorytab widget: mais vendidos)
 * - category2_home5       (Categorytab widget: categorias populares)
 * - home_hero             (fallback hero quando slider não existe)
 * - home_new_products     (widget Newproduct: lançamentos)
 * - trust_badges_homepage (selos de confiança)
 * - home_faq_quick        (FAQ rápido accordion)
 * - home_schema_org       (FAQPage JSON-LD)
 * - featured_categories   (Categorytab widget: coleções)
 *
 * @see top-home.phtml — renderiza todos estes blocos via $renderCmsBlock()
 */
class AyoHomepageCmsBlocks implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly Block $blockModel,
        private readonly WriterInterface $configWriter,
        private readonly LoggerInterface $logger
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->startSetup();

        foreach ($this->getBlockDefinitions() as $data) {
            $this->createOrUpdateBlock($data);
        }

        $this->applyConfigFixes();

        $this->logger->info('[AyoHomepageCmsBlocks] Todos os 13 blocos homepage criados/atualizados + configs corrigidas.');

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

    private function createOrUpdateBlock(array $data): void
    {
        try {
            $block = clone $this->blockModel;
            $block->setStoreId(0);
            $block->load($data['identifier'], 'identifier');
            $action = $block->getId() ? 'atualizado' : 'criado';

            $block->addData([
                'title'      => $data['title'],
                'identifier' => $data['identifier'],
                'content'    => $data['content'],
                'is_active'  => 1,
            ]);
            $block->setStores([0]);
            $block->save();

            $this->logger->info(
                sprintf('[AyoHomepageCmsBlocks] Bloco "%s" %s.', $data['identifier'], $action)
            );
        } catch (\Throwable $e) {
            $this->logger->error(
                sprintf('[AyoHomepageCmsBlocks] Erro no bloco "%s": %s', $data['identifier'], $e->getMessage())
            );
        }
    }

    /**
     * @return array<int, array{identifier: string, title: string, content: string}>
     */
    private function getBlockDefinitions(): array
    {
        return [
            [
                'identifier' => 'block_top',
                'title'      => 'Homepage — Barra de Benefícios (Home5)',
                'content'    => $this->blockTopContent(),
            ],
            [
                'identifier' => 'banner_mid_home5',
                'title'      => 'Homepage — Banners Mid-Page (Home5)',
                'content'    => $this->bannerMidHome5Content(),
            ],
            [
                'identifier' => 'notification_home5',
                'title'      => 'Homepage — Notificações Promo (Home5)',
                'content'    => $this->notificationHome5Content(),
            ],
            [
                'identifier' => 'category1_home5',
                'title'      => 'Homepage — Mais Vendidos (Categorytab)',
                'content'    => $this->category1Home5Content(),
            ],
            [
                'identifier' => 'category2_home5',
                'title'      => 'Homepage — Categorias Populares (Categorytab)',
                'content'    => $this->category2Home5Content(),
            ],
            [
                'identifier' => 'home_hero',
                'title'      => 'Homepage — Hero Fallback',
                'content'    => $this->homeHeroContent(),
            ],
            [
                'identifier' => 'home_new_products',
                'title'      => 'Homepage — Lançamentos (Newproduct)',
                'content'    => $this->homeNewProductsContent(),
            ],
            [
                'identifier' => 'trust_badges_homepage',
                'title'      => 'Homepage — Selos de Confiança',
                'content'    => $this->trustBadgesHomepageContent(),
            ],
            [
                'identifier' => 'home_faq_quick',
                'title'      => 'Homepage — FAQ Rápido',
                'content'    => $this->homeFaqQuickContent(),
            ],
            [
                'identifier' => 'home_schema_org',
                'title'      => 'Homepage — Schema.org FAQPage',
                'content'    => CmsBlockData::schemaOrgHomepageContent(),
            ],
            [
                'identifier' => 'featured_categories',
                'title'      => 'Homepage — Coleções (Categorytab)',
                'content'    => $this->featuredCategoriesContent(),
            ],
            [
                'identifier' => 'home_product_promo_banners',
                'title'      => 'Homepage — Banners Promocionais de Produto',
                'content'    => CmsBlockData::homeProductPromoBannersContent(),
            ],
        ];
    }

    // ========================================================================
    // CORREÇÕES DE CONFIGURAÇÃO
    // ========================================================================

    /**
     * Corrige configs que ficam com valores errados/ausentes
     * quando StoreConfigurator (CLI) nunca foi executado.
     */
    private function applyConfigFixes(): void
    {
        $configs = [
            // SuperDeals: end_date padrão do módulo é 05/18/2017 (expirada)
            'superdeals/configuration/end_date' => '12/31/2030 23:59',

            // ProductTab: nomes em PT-BR (padrão do módulo é inglês)
            'producttab/new_status/enabled'          => '1',
            'producttab/new_status/items'             => '5',
            'producttab/new_status/row'               => '1',
            'producttab/new_status/speed'             => '400',
            'producttab/new_status/qty'               => '20',
            'producttab/new_status/addtocart'         => '1',
            'producttab/new_status/wishlist'           => '1',
            'producttab/new_status/compare'            => '0',
            'producttab/new_status/navigation'         => '1',
            'producttab/new_status/pagination'          => '0',
            'producttab/new_status/auto'               => '1',
            'producttab/new_status/shownew'            => '1',
            'producttab/new_status/newname'            => 'Lançamentos',
            'producttab/new_status/showbestseller'     => '1',
            'producttab/new_status/bestsellername'     => 'Mais vendidos',
            'producttab/new_status/showfeature'        => '1',
            'producttab/new_status/featurename'        => 'Destaques',
            'producttab/new_status/showonsale'         => '1',
            'producttab/new_status/onsalename'         => 'Promoções',
            'producttab/new_status/showrandom'         => '0',
            'producttab/new_status/randomname'         => 'Descubra também',

            // Cores: normalizar para formato sem # (Rokanthemes adiciona # automaticamente)
            'themeoption/colors/link_color'            => 'b73337',
            'themeoption/colors/link_hover_color'      => '8e2629',
            'themeoption/colors/button_bg_color'       => 'b73337',
            'themeoption/colors/button_hover_bg_color'  => '8e2629',
            'themeoption/colors/text_color'            => '333333',
            'themeoption/colors/button_text_color'     => 'FFFFFF',
            'themeoption/colors/button_hover_text_color' => 'FFFFFF',
        ];

        foreach ($configs as $path => $value) {
            try {
                $this->configWriter->save($path, $value);
            } catch (\Throwable $e) {
                $this->logger->error(
                    sprintf('[AyoHomepageCmsBlocks] Erro ao salvar config "%s": %s', $path, $e->getMessage())
                );
            }
        }

        $this->logger->info('[AyoHomepageCmsBlocks] Config fixes aplicados (SuperDeals, ProductTab, cores).');
    }

    // ========================================================================
    // CONTEÚDO DOS BLOCOS — espelhado de StoreConfigurator para idempotência
    // ========================================================================

    private function blockTopContent(): string
    {
        return <<<'HTML'
<div class="velaServicesInner velaServicesInner--home5">
    <div class="velaContent">
        <div class="rowFlex rowFlexMargin flexJustifyCenter">
            <div class="col-xs-6 col-sm-3 col-2">
                <div class="boxService d-flex flexJustifyCenter">
                    <div class="boxServiceImage boxServiceImage1"></div>
                    <div class="boxServiceContent">
                        <h4 class="boxServiceTitle">Entrega expressa</h4>
                        <div class="boxServiceDesc"><a href="{{store url='shipping'}}" title="Ver política de frete">Envio rápido para todo o Brasil</a></div>
                    </div>
                </div>
            </div>
            <div class="col-xs-6 col-sm-3 col-2">
                <div class="boxService d-flex flexJustifyCenter">
                    <div class="boxServiceImage boxServiceImage2"></div>
                    <div class="boxServiceContent">
                        <h4 class="boxServiceTitle">Pagamento seguro</h4>
                        <div class="boxServiceDesc"><a href="{{store url='formas-pagamento'}}" title="Ver formas de pagamento">Pix, boleto e à vista</a></div>
                    </div>
                </div>
            </div>
            <div class="col-xs-6 col-sm-3 col-2">
                <div class="boxService d-flex flexJustifyCenter">
                    <div class="boxServiceImage boxServiceImage3"></div>
                    <div class="boxServiceContent">
                        <h4 class="boxServiceTitle">Compra garantida</h4>
                        <div class="boxServiceDesc"><a href="{{store url='customer-service'}}" title="Ir para atendimento">Suporte técnico especializado</a></div>
                    </div>
                </div>
            </div>
            <div class="col-xs-6 col-sm-3 col-2">
                <div class="boxService d-flex flexJustifyCenter">
                    <div class="boxServiceImage boxServiceImage4"></div>
                    <div class="boxServiceContent">
                        <h4 class="boxServiceTitle">Atendimento especializado</h4>
                        <div class="boxServiceDesc"><a href="https://wa.me/5516997367588" target="_blank" rel="noopener" title="Falar no WhatsApp">Equipe pronta para ajudar</a></div>
                    </div>
                </div>
            </div>
            <div class="col-xs-6 col-sm-3 col-2">
                <div class="boxService d-flex flexJustifyCenter">
                    <div class="boxServiceImage boxServiceImage5"></div>
                    <div class="boxServiceContent">
                        <h4 class="boxServiceTitle">Trocas facilitadas</h4>
                        <div class="boxServiceDesc"><a href="{{store url='returns'}}" title="Ver política de trocas">Troca e devolução sem burocracia</a></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
HTML;
    }

    private function bannerMidHome5Content(): string
    {
        return <<<'HTML'
<div class="rowFlex">
<div class="col-xs-12 col-sm-4 col-md-4 col_banner1">
<div class="bs-banner "><a class="banner-hover" href="{{store url="shipping"}}"><img loading="lazy" decoding="async" src="{{media url=wysiwyg/home-banners/banner-envio.webp}}" alt="Envio Imediato para todo o Brasil" width="1024" height="1024"></a></div>
</div>
<div class="col-xs-12 col-sm-4 col-md-4 col_banner2">
<div class="bs-banner "><a class="banner-hover" href="{{store url="formas-pagamento"}}"><img loading="lazy" decoding="async" src="{{media url=wysiwyg/home-banners/banner-pagamento.webp}}" alt="Pagamento Seguro - Pix, Boleto e à vista" width="1024" height="1024"></a></div>
</div>
<div class="col-xs-12 col-sm-4 col-md-4 col_banner3">
<div class="bs-banner bs-banner-last"><a class="banner-hover" href="{{store url="ofertas.html"}}"><img loading="lazy" decoding="async" src="{{media url=wysiwyg/home-banners/banner-ofertas.webp}}" alt="Ofertas e Promoções AWA Motos" width="1024" height="1024"></a></div>
</div>
</div>
HTML;
    }

    private function notificationHome5Content(): string
    {
        return <<<'HTML'
<div class="notification-home5-inner">
    <p>Frete grátis para compras acima de R$200 | Retire grátis na loja</p>
    <p>Pedidos sendo enviados normalmente. Confira nossas novidades e promoções exclusivas.</p>
    <p>Em compras acima de R$300 ganhe cupom de 15% de desconto na hora.</p>
</div>
HTML;
    }

    private function category1Home5Content(): string
    {
        return <<<'HTML'
<div class="ayo-home5-product-grid">
    {{widget type="Rokanthemes\Categorytab\Block\CateWidget"
        title="Mais vendidos"
        color_box="red-box"
        identify="categorytab"
        category_id="71,72,73,74,75"
        limit_qty="11"
        show_pager="0"
        slide_row="1"
        slide_limit="6"
        template="categorytab/grid.phtml"}}
</div>
HTML;
    }

    private function category2Home5Content(): string
    {
        return <<<'HTML'
<div class="ayo-home5-product-grid">
    {{widget type="Rokanthemes\Categorytab\Block\CateWidget"
        title="Categorias populares"
        color_box="red-box"
        identify="categorytab2"
        category_id="40,44,45,67,68,86"
        limit_qty="10"
        show_pager="0"
        slide_row="1"
        slide_limit="6"
        default="6"
        desktop="5"
        desktop_small="4"
        tablet="3"
        mobile="1"
        template="categorytab/grid-original.phtml"}}
</div>
HTML;
    }

    private function homeHeroContent(): string
    {
        return <<<'HTML'
<section class="hero section">
    <div>
        <h1 class="hero__title">Peças e acessórios para sua moto</h1>
        <p class="hero__subtitle">Compre com confiança: entrega rápida, suporte especialista e compra segura.</p>
        <div class="hero__ctas">
            <a href="{{store url='super-ofertas.html'}}" class="action primary">Ver ofertas</a>
            <a href="{{store url='b2b/register'}}" class="action secondary">Cadastro atacado / B2B</a>
        </div>
        <ul class="hero__highlights" role="list">
            <li>Compra segura via SSL</li>
            <li>Envio para todo Brasil</li>
            <li>Suporte para compatibilidade</li>
        </ul>
        <p class="hero__help">
            <a href="https://wa.me/5516997367588" target="_blank" rel="noopener">Precisa de ajuda? Chamar no WhatsApp</a>
        </p>
    </div>
    <div>
        <img class="card__media" loading="eager" fetchpriority="high" src="{{view url='Magento_Catalog::images/product/placeholder/image.jpg'}}" alt="Peças e acessórios para motos" width="600" height="400" />
    </div>
</section>
HTML;
    }

    private function homeNewProductsContent(): string
    {
        return <<<'HTML'
<div class="ayo-home5-product-grid ayo-home5-product-grid--carousel" aria-label="Novos Produtos">
    {{widget type="Rokanthemes\Newproduct\Block\Widget\Newproduct"
        template="widget/newproduct_list.phtml"
        limit="12"
        row="1"
        navigation="1"
        pagination="0"}}
</div>
HTML;
    }

    private function trustBadgesHomepageContent(): string
    {
        return <<<'HTML'
<section class="trust-badges-homepage" aria-label="Selos de confiança">
    <div class="trust-badges-grid">
        <div class="trust-badge-item">
            <img src="{{view url='images/awamotos-seguranca-ssl.svg'}}" alt="Conexão Segura SSL" width="120" height="40" loading="lazy">
            <span>Site Seguro</span>
        </div>
        <div class="trust-badge-item">
            <img src="{{view url='images/awamotos-compra-protegida.svg'}}" alt="Compra Protegida" width="120" height="40" loading="lazy">
            <span>Compra Protegida</span>
        </div>
        <div class="trust-badge-item">
            <img src="{{view url='images/payment_methods.png'}}" alt="Pagamento Seguro — Pix, Boleto, Cartão" width="160" height="40" loading="lazy">
            <span>Pagamento Seguro</span>
        </div>
    </div>
</section>
HTML;
    }

    private function homeFaqQuickContent(): string
    {
        return <<<'HTML'
<section class="aw-home-faq" aria-label="Perguntas frequentes">
    <div class="aw-home-faq__inner">
        <header class="aw-home-faq__header">
            <h2 class="aw-home-faq__title">Dúvidas rápidas</h2>
            <p class="aw-home-faq__subtitle">Respostas objetivas para comprar com mais segurança.</p>
        </header>

        <div class="aw-home-faq__items">
            <details class="aw-home-faq__item">
                <summary>Como acompanhar meu pedido?</summary>
                <div class="aw-home-faq__answer">
                    Acesse <a href="{{store url='sales/order/history'}}"><strong>Minha Conta › Meus Pedidos</strong></a> para ver o status e o código de rastreamento.
                    Também enviamos atualizações por e-mail. Dúvidas? Chame no
                    <a href="https://wa.me/5516997367588" target="_blank" rel="noopener">WhatsApp</a>.
                </div>
            </details>

            <details class="aw-home-faq__item">
                <summary>Onde vejo prazo e valor do frete?</summary>
                <div class="aw-home-faq__answer">
                    O prazo e o valor são calculados no <strong>carrinho/checkout</strong>, conforme CEP e itens do pedido.
                </div>
            </details>

            <details class="aw-home-faq__item">
                <summary>Como funcionam trocas e devoluções?</summary>
                <div class="aw-home-faq__answer">
                    Trocas e devoluções seguem a política da loja. Veja detalhes em
                    <a href="{{store url='customer-service'}}">Ajuda</a>.
                </div>
            </details>

            <details class="aw-home-faq__item">
                <summary>Quero comprar para revenda (B2B). Como faço?</summary>
                <div class="aw-home-faq__answer">
                    Faça seu <a href="{{store url='b2b/register'}}">cadastro B2B</a> e, se preferir, envie uma
                    <a href="{{store url='b2b/quote/index'}}">solicitação de cotação</a>.
                </div>
            </details>
        </div>
    </div>
</section>
HTML;
    }

    private function featuredCategoriesContent(): string
    {
        return <<<'HTML'
<div class="ayo-home5-product-grid">
    {{widget type="Rokanthemes\Categorytab\Block\CateWidget"
        title=""
        color_box="red-box"
        identify="featured_categories"
        category_id="44,45,67,71,72,73,74,75,76,86,88,109"
        slide_row="2"
        slide_limit="6"
        template="categorytab/grid-original.phtml"}}
</div>
HTML;
    }
}
