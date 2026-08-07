<?php

declare(strict_types=1);

namespace GrupoAwamotos\StoreSetup\Setup\Patch\Data;

use Magento\Cms\Model\BlockFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Psr\Log\LoggerInterface;

/**
 * Cria/atualiza TODOS os blocos CMS referenciados pelo tema Ayo
 * com HTML seguindo a estrutura da documentação oficial.
 *
 * Blocos cobertos:
 * - top-left-static (mensagem promo no topo)
 * - hotline_header (telefone/WhatsApp no header)
 * - header_promo_message (frete grátis / promo do dia)
 * - footer_static (rodapé principal com colunas: info, links, política, contato)
 * - footer_payment (ícones de pagamento: PIX, Boleto, Visa, etc.)
 * - fixed_right (menu fixo lateral: conta, wishlist, WhatsApp)
 * - social_block (redes sociais)
 * - footer_info (informações da empresa no footer)
 * - footer_menu (menu do rodapé)
 * - home_slider (widget do slide banner)
 *
 * @see docs/AUDITORIA_TEMA_AYO.md — seções 5, 6, 7, 27
 */
class AyoContentSetup implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly BlockFactory $blockFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->startSetup();

        foreach ($this->getBlockDefinitions() as $data) {
            $this->createOrUpdateBlock($data);
        }

        $this->logger->info('[AyoContentSetup] Todos os blocos CMS criados/atualizados.');

        $this->moduleDataSetup->endSetup();

        return $this;
    }

    public static function getDependencies(): array
    {
        return [
            AyoThemeFullConfiguration::class,
        ];
    }

    public function getAliases(): array
    {
        return [];
    }

    private function createOrUpdateBlock(array $data): void
    {
        try {
            $block = $this->blockFactory->create();
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
                sprintf('[AyoContentSetup] Bloco "%s" %s.', $data['identifier'], $action)
            );
        } catch (\Throwable $e) {
            $this->logger->error(
                sprintf('[AyoContentSetup] Erro no bloco "%s": %s', $data['identifier'], $e->getMessage())
            );
        }
    }

    /**
     * Definições de todos os blocos CMS do tema Ayo.
     *
     * @return array<int, array{identifier: string, title: string, content: string}>
     */
    private function getBlockDefinitions(): array
    {
        return [
            [
                'identifier' => 'top-contact',
                'title'      => 'Topo — Contato Rapido',
                'content'    => $this->contentTopContact(),
            ],
            [
                'identifier' => 'top-left-static',
                'title'      => 'Topo — Mensagem Promocional',
                'content'    => $this->contentTopLeftStatic(),
            ],
            [
                'identifier' => 'hotline_header',
                'title'      => 'Header — Hotline / WhatsApp',
                'content'    => $this->contentHotlineHeader(),
            ],
            [
                'identifier' => 'header_promo_message',
                'title'      => 'Header — Faixa Promocional',
                'content'    => $this->contentHeaderPromo(),
            ],
            [
                'identifier' => 'footer_static',
                'title'      => 'Rodapé — Conteúdo Principal',
                'content'    => $this->contentFooterStatic(),
            ],
            [
                'identifier' => 'footer_static5',
                'title'      => 'Rodapé Home5 — Conteúdo Principal',
                'content'    => $this->contentFooterStatic(),
            ],
            [
                'identifier' => 'footer-tags',
                'title'      => 'Rodapé — Tags Populares',
                'content'    => $this->contentFooterTags(),
            ],
            [
                'identifier' => 'footer_payment',
                'title'      => 'Rodapé — Métodos de Pagamento',
                'content'    => $this->contentFooterPayment(),
            ],
            [
                'identifier' => 'footer_payment6',
                'title'      => 'Rodapé — Métodos de Pagamento (Layout 6)',
                'content'    => $this->contentFooterPayment(),
            ],
            [
                'identifier' => 'footer_info',
                'title'      => 'Rodapé — Sobre a Loja',
                'content'    => $this->contentFooterInfo(),
            ],
            [
                'identifier' => 'social_block',
                'title'      => 'Redes Sociais',
                'content'    => $this->contentSocialBlock(),
            ],
            [
                'identifier' => 'footer_menu',
                'title'      => 'Rodapé — Menu de Links',
                'content'    => $this->contentFooterMenu(),
            ],
            [
                'identifier' => 'fixed_right',
                'title'      => 'Menu Fixo Lateral Direito',
                'content'    => $this->contentFixedRight(),
            ],
            [
                'identifier' => 'home_slider',
                'title'      => 'Homepage — Slider Principal',
                'content'    => $this->contentHomeSlider(),
            ],
            [
                'identifier' => 'home_benefits_bar',
                'title'      => 'Homepage — Barra de Benefícios',
                'content'    => $this->contentBenefitsBar(),
            ],
            [
                'identifier' => 'home_banner_promo',
                'title'      => 'Homepage — Banner Promocional',
                'content'    => $this->contentBannerPromo(),
            ],
        ];
    }

    // ========================================================================
    // BLOCOS HEADER
    // ========================================================================

    private function contentTopContact(): string
    {
        return <<<'HTML'
<div class="top-contact" role="complementary" aria-label="Informacoes de atendimento">
    <ul class="top-contact-list d-flex">
        <li><a href="tel:+551633220000"><i class="fa fa-phone" aria-hidden="true"></i> (16) 3322-0000</a></li>
        <li><a href="https://wa.me/5516997367588" target="_blank" rel="noopener"><i class="fa fa-whatsapp" aria-hidden="true"></i> WhatsApp</a></li>
        <li><a href="/store-locator"><i class="fa fa-map-marker" aria-hidden="true"></i> Loja Fisica</a></li>
    </ul>
</div>
HTML;
    }

    private function contentTopLeftStatic(): string
    {
        return <<<'HTML'
<div class="top-left-static" role="banner">
    <div class="top-static-content">
        <span class="promo-icon">🏍️</span>
        <span class="promo-text">
            <strong>Frete Grátis</strong> para compras acima de R$ 299 |
            <a href="/ofertas" title="Ver ofertas">Ofertas do Dia</a>
        </span>
    </div>
</div>
HTML;
    }

    private function contentHotlineHeader(): string
    {
        return <<<'HTML'
<div class="hotline-header" role="complementary" aria-label="Contato">
    <div class="hotline-content d-flex align-items-center">
        <div class="hotline-icon">
            <i class="fa fa-phone" aria-hidden="true"></i>
        </div>
        <div class="hotline-info">
            <span class="hotline-label">Atendimento:</span>
            <a href="tel:+551633220000" class="hotline-number" aria-label="Ligar para AWA Motos">(16) 3322-0000</a>
            <span class="hotline-separator">|</span>
            <a href="https://wa.me/5516997367588" class="hotline-whatsapp" target="_blank" rel="noopener" aria-label="WhatsApp AWA Motos">
                <i class="fa fa-whatsapp" aria-hidden="true"></i> WhatsApp
            </a>
        </div>
    </div>
</div>
HTML;
    }

    private function contentHeaderPromo(): string
    {
        return <<<'HTML'
<div class="header-promo-message" role="banner">
    <div class="promo-marquee">
        <span>⚡ <strong>Peças para motos</strong> com os melhores preços — Atacado e Varejo | Parcele em até 12x sem juros</span>
    </div>
</div>
HTML;
    }

    // ========================================================================
    // BLOCOS FOOTER — Estrutura HTML oficial do tema Ayo
    // ========================================================================

    private function contentFooterStatic(): string
    {
        return <<<'HTML'
<div class="footer-static velaBlock" role="contentinfo">
    <div class="container">
        <div class="row">
            <!-- Coluna 1: Sobre a Empresa -->
            <div class="col-lg-3 col-md-6 col-sm-12">
                <div class="vela-content footer-about">
                    <h4 class="velaFooterTitle">AWA Motos</h4>
                    <div class="velaContent">
                        <p>
                            Distribuidora de peças e acessórios para motos em Araraquara-SP.
                            Atendemos atacado e varejo com qualidade e preço justo desde 2010.
                        </p>
                        <div class="footer-contact-list">
                            <div class="contact-item d-flex">
                                <i class="fa fa-map-marker" aria-hidden="true"></i>
                                <span>Rua Castro Alves, 1234 — Centro, Araraquara-SP</span>
                            </div>
                            <div class="contact-item d-flex">
                                <i class="fa fa-phone" aria-hidden="true"></i>
                                <a href="tel:+551633220000">(16) 3322-0000</a>
                            </div>
                            <div class="contact-item d-flex">
                                <i class="fa fa-whatsapp" aria-hidden="true"></i>
                                <a href="https://wa.me/5516997367588" target="_blank" rel="noopener">(16) 99736-7588</a>
                            </div>
                            <div class="contact-item d-flex">
                                <i class="fa fa-envelope" aria-hidden="true"></i>
                                <a href="mailto:awamotos@awamotos.com.br">awamotos@awamotos.com.br</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Coluna 2: Links Rápidos -->
            <div class="col-lg-3 col-md-6 col-sm-12">
                <div class="vela-content">
                    <h4 class="velaFooterTitle">Links Rápidos</h4>
                    <div class="velaContent">
                        <ul class="footer-links" role="list">
                            <li><a href="/about-us">Sobre Nós</a></li>
                            <li><a href="/blog">Blog</a></li>
                            <li><a href="/brands">Nossas Marcas</a></li>
                            <li><a href="/store-locator">Nossa Loja</a></li>
                            <li><a href="/faq">Perguntas Frequentes</a></li>
                            <li><a href="/customer/account/create">Crie Sua Conta</a></li>
                            <li><a href="/b2b/register">Cadastro B2B</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            <!-- Coluna 3: Políticas -->
            <div class="col-lg-3 col-md-6 col-sm-12">
                <div class="vela-content">
                    <h4 class="velaFooterTitle">Políticas e Ajuda</h4>
                    <div class="velaContent">
                        <ul class="footer-links" role="list">
                            <li><a href="/termos-e-condicoes">Termos e Condições</a></li>
                            <li><a href="/politica-de-privacidade">Política de Privacidade</a></li>
                            <li><a href="/shipping">Política de Envio</a></li>
                            <li><a href="/returns">Trocas e Devoluções</a></li>
                            <li><a href="/warranty">Garantia</a></li>
                            <li><a href="/customer-service">Atendimento ao Cliente</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            <!-- Coluna 4: Horário e Redes -->
            <div class="col-lg-3 col-md-6 col-sm-12">
                <div class="vela-content">
                    <h4 class="velaFooterTitle">Horário de Atendimento</h4>
                    <div class="velaContent">
                        <ul class="footer-hours" role="list">
                            <li><strong>Seg–Sex:</strong> 08h às 18h</li>
                            <li><strong>Sábado:</strong> 08h às 12h</li>
                            <li><strong>Domingo:</strong> Fechado</li>
                        </ul>
                        <div class="footer-social-section">
                            <h5>Siga-nos:</h5>
                            {{block class="Magento\Cms\Block\Block" block_id="social_block"}}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
HTML;
    }

    private function contentFooterPayment(): string
    {
        return <<<'HTML'
<div class="footer-payment-methods" role="complementary" aria-label="Métodos de pagamento">
    <div class="payment-icons d-flex justify-content-center align-items-center flex-wrap">
        <span class="payment-item" title="PIX">
            <span class="payment-label">PIX</span>
        </span>
        <span class="payment-item" title="Boleto Bancário">
            <span class="payment-label">Boleto</span>
        </span>
        <span class="payment-item" title="Visa">
            <i class="fa fa-cc-visa" aria-hidden="true"></i>
        </span>
        <span class="payment-item" title="Mastercard">
            <i class="fa fa-cc-mastercard" aria-hidden="true"></i>
        </span>
        <span class="payment-item" title="American Express">
            <i class="fa fa-cc-amex" aria-hidden="true"></i>
        </span>
        <span class="payment-item" title="Elo">
            <span class="payment-label">Elo</span>
        </span>
        <span class="payment-item" title="Hipercard">
            <span class="payment-label">Hipercard</span>
        </span>
    </div>
    <div class="payment-info text-center">
        <small>Parcele em até <strong>12x sem juros</strong> no cartão de crédito</small>
    </div>
</div>
HTML;
    }

    private function contentFooterTags(): string
    {
        return <<<'HTML'
<div class="footer-tags velaBlock" role="navigation" aria-label="Categorias populares">
    <div class="vela-content">
        <ul class="footer-tag-list d-flex flex-wrap" role="list">
            <li><a href="/retrovisores.html">Retrovisores</a></li>
            <li><a href="/bauletos.html">Bauletos</a></li>
            <li><a href="/bagageiros.html">Bagageiros</a></li>
            <li><a href="/protetor-de-carenagem.html">Protetor de Carenagem</a></li>
            <li><a href="/manoplas.html">Manoplas</a></li>
            <li><a href="/guidoes.html">Guidoes</a></li>
        </ul>
    </div>
</div>
HTML;
    }

    private function contentFooterInfo(): string
    {
        return <<<'HTML'
<div class="vela-contactinfo velaBlock">
    <div class="vela-content">
        <div class="contacinfo-logo clearfix">
            <div class="velaFooterLogo">
                <a href="/" title="AWA Motos — Home">
                    <span class="footer-logo-text">AWA Motos</span>
                </a>
            </div>
        </div>
        <div class="intro-footer d-flex">
            Distribuidora de peças e acessórios para motos com foco em qualidade e preço justo.
            Atendemos todo o Brasil — atacado e varejo.
        </div>
        <div class="contacinfo-phone contactinfo-item clearfix">
            <div class="d-flex">
                <div class="image_hotline"></div>
                <div class="wrap">
                    <label>Atendimento:</label>
                    <a href="tel:+551633220000">(16) 3322-0000</a>
                </div>
            </div>
        </div>
        <div class="contacinfo-email contactinfo-item clearfix">
            <div class="d-flex">
                <div class="wrap">
                    <label>E-mail:</label>
                    <a href="mailto:awamotos@awamotos.com.br">awamotos@awamotos.com.br</a>
                </div>
            </div>
        </div>
    </div>
</div>
HTML;
    }

    private function contentSocialBlock(): string
    {
        return <<<'HTML'
<div class="social-block" role="navigation" aria-label="Redes Sociais">
    <ul class="social-icons d-flex">
        <li class="social-icon">
            <a href="https://instagram.com/awamotos" target="_blank" rel="noopener" title="Instagram AWA Motos" aria-label="Instagram">
                <i class="fa fa-instagram" aria-hidden="true"></i>
            </a>
        </li>
        <li class="social-icon">
            <a href="https://facebook.com/awamotos" target="_blank" rel="noopener" title="Facebook AWA Motos" aria-label="Facebook">
                <i class="fa fa-facebook" aria-hidden="true"></i>
            </a>
        </li>
        <li class="social-icon">
            <a href="https://youtube.com/@awamotos" target="_blank" rel="noopener" title="YouTube AWA Motos" aria-label="YouTube">
                <i class="fa fa-youtube-play" aria-hidden="true"></i>
            </a>
        </li>
        <li class="social-icon">
            <a href="https://wa.me/5516997367588" target="_blank" rel="noopener" title="WhatsApp AWA Motos" aria-label="WhatsApp">
                <i class="fa fa-whatsapp" aria-hidden="true"></i>
            </a>
        </li>
    </ul>
</div>
HTML;
    }

    private function contentFooterMenu(): string
    {
        return <<<'HTML'
<div class="footer-menu velaBlock" role="navigation" aria-label="Menu do Rodapé">
    <div class="vela-content">
        <div class="velaContent">
            <ul class="footer-nav-links d-flex flex-wrap justify-content-center" role="list">
                <li><a href="/">Início</a></li>
                <li><a href="/about-us">Sobre Nós</a></li>
                <li><a href="/blog">Blog</a></li>
                <li><a href="/faq">FAQ</a></li>
                <li><a href="/brands">Marcas</a></li>
                <li><a href="/store-locator">Loja Física</a></li>
                <li><a href="/customer-service">Atendimento</a></li>
                <li><a href="/termos-e-condicoes">Termos</a></li>
                <li><a href="/politica-de-privacidade">Privacidade</a></li>
            </ul>
        </div>
    </div>
</div>
HTML;
    }

    private function contentFixedRight(): string
    {
        return <<<'HTML'
<div class="fixed-right-sidebar" role="complementary" aria-label="Ações rápidas">
    <ul class="fixed-right-menu">
        <li class="fixed-right-item fixed-right-account" title="Minha Conta">
            <a href="/customer/account" aria-label="Minha Conta">
                <i class="fa fa-user" aria-hidden="true"></i>
                <span class="fixed-right-label">Conta</span>
            </a>
        </li>
        <li class="fixed-right-item fixed-right-wishlist" title="Lista de Desejos">
            <a href="/wishlist" aria-label="Lista de Desejos">
                <i class="fa fa-heart" aria-hidden="true"></i>
                <span class="fixed-right-label">Desejos</span>
            </a>
        </li>
        <li class="fixed-right-item fixed-right-compare" title="Comparar">
            <a href="/catalog/product_compare" aria-label="Comparar Produtos">
                <i class="fa fa-exchange" aria-hidden="true"></i>
                <span class="fixed-right-label">Comparar</span>
            </a>
        </li>
        <li class="fixed-right-item fixed-right-whatsapp" title="WhatsApp">
            <a href="https://wa.me/5516997367588" target="_blank" rel="noopener" aria-label="Fale pelo WhatsApp">
                <i class="fa fa-whatsapp" aria-hidden="true"></i>
                <span class="fixed-right-label">WhatsApp</span>
            </a>
        </li>
        <li class="fixed-right-item fixed-right-top" title="Voltar ao Topo">
            <a href="#top" aria-label="Voltar ao topo da página">
                <i class="fa fa-chevron-up" aria-hidden="true"></i>
                <span class="fixed-right-label">Topo</span>
            </a>
        </li>
    </ul>
</div>
HTML;
    }

    // ========================================================================
    // HOMEPAGE
    // ========================================================================

    private function contentHomeSlider(): string
    {
        return <<<'HTML'
<div class="banner-slider homepage-slider">
    {{block class="Rokanthemes\SlideBanner\Block\Slider" slider_id="homepageslider" template="slider.phtml"}}
</div>
HTML;
    }

    private function contentBenefitsBar(): string
    {
        return <<<'HTML'
<div class="benefits-bar" role="complementary" aria-label="Benefícios da loja">
    <div class="container">
        <div class="row benefits-row">
            <div class="col-lg-3 col-md-6 col-sm-6 col-6">
                <div class="benefit-item d-flex align-items-center">
                    <div class="benefit-icon">
                        <i class="fa fa-truck" aria-hidden="true"></i>
                    </div>
                    <div class="benefit-text">
                        <strong>Frete Grátis</strong>
                        <span>Acima de R$ 299</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-sm-6 col-6">
                <div class="benefit-item d-flex align-items-center">
                    <div class="benefit-icon">
                        <i class="fa fa-credit-card" aria-hidden="true"></i>
                    </div>
                    <div class="benefit-text">
                        <strong>12x Sem Juros</strong>
                        <span>No cartão de crédito</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-sm-6 col-6">
                <div class="benefit-item d-flex align-items-center">
                    <div class="benefit-icon">
                        <i class="fa fa-shield" aria-hidden="true"></i>
                    </div>
                    <div class="benefit-text">
                        <strong>Compra Segura</strong>
                        <span>Certificado SSL</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 col-sm-6 col-6">
                <div class="benefit-item d-flex align-items-center">
                    <div class="benefit-icon">
                        <i class="fa fa-refresh" aria-hidden="true"></i>
                    </div>
                    <div class="benefit-text">
                        <strong>Troca Fácil</strong>
                        <span>Em até 30 dias</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
HTML;
    }

    private function contentBannerPromo(): string
    {
        return <<<'HTML'
<div class="home-banner-promo" role="banner">
    <div class="container">
        <div class="row">
            <div class="col-md-6 col-sm-12">
                <div class="promo-banner promo-banner-left">
                    <a href="/ofertas" title="Ofertas em Peças para Motos">
                        <div class="promo-content">
                            <h3 class="promo-title">Ofertas da Semana</h3>
                            <p class="promo-desc">Até 40% OFF em peças selecionadas</p>
                            <span class="promo-btn">Ver Ofertas</span>
                        </div>
                    </a>
                </div>
            </div>
            <div class="col-md-6 col-sm-12">
                <div class="promo-banner promo-banner-right">
                    <a href="/b2b/register" title="Cadastro B2B — Atacado">
                        <div class="promo-content">
                            <h3 class="promo-title">Compra B2B</h3>
                            <p class="promo-desc">Preços de atacado para lojistas e oficinas</p>
                            <span class="promo-btn">Cadastre-se</span>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
HTML;
    }
}
