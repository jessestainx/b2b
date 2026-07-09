<?php

declare(strict_types=1);

namespace GrupoAwamotos\StoreSetup\Setup;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Cms\Model\BlockFactory;
use Magento\Cms\Model\PageFactory;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\State;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Theme\Model\ResourceModel\Theme\CollectionFactory as ThemeCollectionFactory;
use Symfony\Component\Console\Output\OutputInterface;

class StoreConfigurator
{
    private const CMS_HOMEPAGE_IDENTIFIER = 'home';
    private const CMS_HOMEPAGE_HOME5_IDENTIFIER = 'homepage_ayo_home5';

    private State $appState;
    private BlockFactory $blockFactory;
    private PageFactory $pageFactory;
    private WriterInterface $configWriter;
    private ReinitableConfigInterface $reinitableConfig;
    private ScopeConfigInterface $scopeConfig;
    private CategoryFactory $categoryFactory;
    private CategoryCollectionFactory $categoryCollectionFactory;
    private CategoryRepositoryInterface $categoryRepository;
    private StoreManagerInterface $storeManager;
    private DirectoryList $directoryList;
    private ThemeCollectionFactory $themeCollectionFactory;
    private \Rokanthemes\SlideBanner\Model\SliderFactory $sliderFactory;
    private \Rokanthemes\SlideBanner\Model\SlideFactory $slideFactory;

    public function __construct(
        State $appState,
        BlockFactory $blockFactory,
        PageFactory $pageFactory,
        WriterInterface $configWriter,
        ReinitableConfigInterface $reinitableConfig,
        ScopeConfigInterface $scopeConfig,
        CategoryFactory $categoryFactory,
        CategoryCollectionFactory $categoryCollectionFactory,
        CategoryRepositoryInterface $categoryRepository,
        StoreManagerInterface $storeManager,
        DirectoryList $directoryList,
        ThemeCollectionFactory $themeCollectionFactory,
        \Rokanthemes\SlideBanner\Model\SliderFactory $sliderFactory,
        \Rokanthemes\SlideBanner\Model\SlideFactory $slideFactory
    ) {
        $this->appState = $appState;
        $this->blockFactory = $blockFactory;
        $this->pageFactory = $pageFactory;
        $this->configWriter = $configWriter;
        $this->reinitableConfig = $reinitableConfig;
        $this->scopeConfig = $scopeConfig;
        $this->categoryFactory = $categoryFactory;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->categoryRepository = $categoryRepository;
        $this->storeManager = $storeManager;
        $this->directoryList = $directoryList;
        $this->themeCollectionFactory = $themeCollectionFactory;
        $this->sliderFactory = $sliderFactory;
        $this->slideFactory = $slideFactory;
    }

    public function run(OutputInterface $output): void
    {
        $this->ensureAreaCode();

        $this->createBlocks($output);
        $this->createOrUpdatePages($output);
        $this->createOrUpdateHomepage($output);
        $this->configureHomepage($output);
        $this->createCategories($output);
        $this->applyThemeConfigurations($output);
        $this->cleanupLegacyThemeConfigurations($output);

        $this->ensurePlaceholderBanners($output);
        $this->seedSlider($output);

        $this->reinitableConfig->reinit();
    }

    private function ensureAreaCode(): void
    {
        try {
            $this->appState->getAreaCode();
        } catch (LocalizedException $e) {
            $this->appState->setAreaCode('adminhtml');
        }
    }

    private function createBlocks(OutputInterface $output): void
    {
        // Alguns presets do tema Ayo referenciam CMS blocks numerados (ex.: footer_static6).
        // Para manter o projeto idempotente e evitar rodapé quebrado ao alternar variantes,
        // replicamos os blocos seeded principais como aliases.
        $aliasMap = [
            'footer_static' => [
                'footer_static4',
                'footer_static5',
                'footer_static6',
                'footer_static7',
                'footer_static9',
                'footer_static10',
                'footer_static11',
                'footer_static13',
                'footer_static14',
                'footer_static15',
                'footer_static16',
            ],
            'footer_payment' => [
                'footer_payment5',
                'footer_payment6',
            ],
        ];

        foreach ($this->getBlockDefinitions() as $blockData) {
            try {
                $block = $this->blockFactory->create();
                $block->setStoreId(0);
                $block->load($blockData['identifier'], 'identifier');
                $wasExisting = (bool)$block->getId();

                $block->addData([
                    'title' => $blockData['title'],
                    'identifier' => $blockData['identifier'],
                    'content' => $blockData['content'],
                    'is_active' => 1
                ]);
                $block->setStores([0]);
                $block->save();
                $output->writeln(sprintf(' - Bloco %s %s', $blockData['identifier'], $wasExisting ? 'atualizado' : 'criado'));

                // Aplicar aliases (se existirem para esse bloco)
                if (isset($aliasMap[$blockData['identifier']])) {
                    foreach ($aliasMap[$blockData['identifier']] as $aliasIdentifier) {
                        try {
                            $aliasBlock = $this->blockFactory->create();
                            $aliasBlock->setStoreId(0);
                            $aliasBlock->load($aliasIdentifier, 'identifier');
                            $aliasWasExisting = (bool)$aliasBlock->getId();

                            $aliasBlock->addData([
                                'title' => $blockData['title'],
                                'identifier' => $aliasIdentifier,
                                'content' => $blockData['content'],
                                'is_active' => 1
                            ]);
                            $aliasBlock->setStores([0]);
                            $aliasBlock->save();

                            $output->writeln(sprintf(' - Bloco %s %s (alias de %s)', $aliasIdentifier, $aliasWasExisting ? 'atualizado' : 'criado', $blockData['identifier']));
                        } catch (\Throwable $aliasException) {
                            $output->writeln(sprintf('<error>   ✗ Erro ao criar/atualizar bloco alias %s: %s</error>', $aliasIdentifier, $aliasException->getMessage()));
                        }
                    }
                }
            } catch (\Throwable $e) {
                $output->writeln(sprintf('<error>   ✗ Erro ao criar/atualizar bloco %s: %s</error>', $blockData['identifier'], $e->getMessage()));
            }
        }
    }

    private function createOrUpdateHomepage(OutputInterface $output): void
    {
        $page = $this->pageFactory->create();
        $page->setStoreId(0);
        $page->load(self::CMS_HOMEPAGE_IDENTIFIER, 'identifier');

        $seedPageContent = $this->getHomepageContent();
        $effectivePageContentForAlias = $seedPageContent;

        try {
            if ($page->getId()) {
                $currentContent = (string) $page->getContent();
                if (trim($currentContent) !== '') {
                    $effectivePageContentForAlias = $currentContent;
                }

                $output->writeln(sprintf(' - Página %s já existe (não alterada)', self::CMS_HOMEPAGE_IDENTIFIER));
            } else {
                $page->setData([
                    'title' => 'Home Page',
                    'identifier' => self::CMS_HOMEPAGE_IDENTIFIER,
                    'content' => $seedPageContent,
                    'is_active' => 1,
                    'page_layout' => '1column'
                ]);
                $page->setStores([0]);

                $page->save();
                $output->writeln(sprintf(' - Página %s criada', self::CMS_HOMEPAGE_IDENTIFIER));
            }

            $this->ensureHome5HomepageAlias($output, $effectivePageContentForAlias);
        } catch (\Throwable $exception) {
            $output->writeln(sprintf('<error>   ✗ Erro na página inicial: %s</error>', $exception->getMessage()));
        }
    }

    /**
     * Garante que exista uma CMS Page com identifier `homepage_ayo_home5`.
     *
     * Importante: se a página já existir (conteúdo editado no Admin), não sobrescrevemos.
     */
    private function ensureHome5HomepageAlias(OutputInterface $output, string $pageContent): void
    {
        $page = $this->pageFactory->create();
        $page->setStoreId(0);
        $page->load(self::CMS_HOMEPAGE_HOME5_IDENTIFIER, 'identifier');

        if ($page->getId()) {
            $output->writeln(sprintf(' - Página %s já existe (não alterada)', self::CMS_HOMEPAGE_HOME5_IDENTIFIER));
            return;
        }

        try {
            $page->setData([
                'title' => 'Homepage Ayo Home 5',
                'identifier' => self::CMS_HOMEPAGE_HOME5_IDENTIFIER,
                'content' => $pageContent,
                'is_active' => 1,
                'page_layout' => '1column'
            ]);
            $page->setStores([0]);
            $page->save();

            $output->writeln(sprintf(' - Página %s criada (alias da %s)', self::CMS_HOMEPAGE_HOME5_IDENTIFIER, self::CMS_HOMEPAGE_IDENTIFIER));
        } catch (\Throwable $exception) {
            $output->writeln(sprintf('<error>   ✗ Erro ao criar página %s: %s</error>', self::CMS_HOMEPAGE_HOME5_IDENTIFIER, $exception->getMessage()));
        }
    }

    private function configureHomepage(OutputInterface $output): void
    {
        try {
            $this->configWriter->save('web/default/cms_home_page', self::CMS_HOMEPAGE_HOME5_IDENTIFIER);
            $output->writeln(' - Homepage padrão configurada');
        } catch (\Throwable $exception) {
            $output->writeln(sprintf('<error>   ✗ Erro ao configurar homepage: %s</error>', $exception->getMessage()));
        }
    }

    /**
     * Cria páginas CMS mínimas usadas em links do rodapé.
     *
     * Importante: para não sobrescrever conteúdo já customizado via Admin,
     * este método cria apenas páginas inexistentes.
     */
    private function createOrUpdatePages(OutputInterface $output): void
    {
        foreach ($this->getPageDefinitions() as $pageData) {
            try {
                $page = $this->pageFactory->create();
                $page->setStoreId(0);
                $page->load($pageData['identifier'], 'identifier');

                if ($page->getId()) {
                    $output->writeln(sprintf(' - Página já existe (não alterada): %s', $pageData['identifier']));
                    continue;
                }

                $page->setData([
                    'title' => $pageData['title'],
                    'identifier' => $pageData['identifier'],
                    'content' => $pageData['content'],
                    'is_active' => 1,
                    'page_layout' => $pageData['page_layout'] ?? '1column'
                ]);
                $page->setStores([0]);
                $page->save();

                $output->writeln(sprintf(' - Página criada: %s', $pageData['identifier']));
            } catch (\Throwable $e) {
                $output->writeln(sprintf('<error>   ✗ Erro ao criar página %s: %s</error>', $pageData['identifier'], $e->getMessage()));
            }
        }
    }

    private function getPageDefinitions(): array
    {
        return [
            [
                'identifier' => 'about-us',
                'title' => 'Sobre nós',
                'content' => $this->cmsPageAboutUsContent(),
            ],
            [
                'identifier' => 'customer-service',
                'title' => 'Atendimento',
                'content' => $this->cmsPageCustomerServiceContent(),
            ],
            [
                'identifier' => 'faq',
                'title' => 'Perguntas frequentes (FAQ)',
                'content' => $this->cmsPageFaqContent(),
            ],
            [
                'identifier' => 'returns',
                'title' => 'Trocas e devoluções',
                'content' => $this->cmsPageReturnsContent(),
            ],
            [
                'identifier' => 'warranty',
                'title' => 'Garantia',
                'content' => $this->cmsPageWarrantyContent(),
            ],
            [
                'identifier' => 'store-locator',
                'title' => 'Lojas e retirada',
                'content' => $this->cmsPageStoreLocatorContent(),
            ],
            [
                'identifier' => 'atacado/condicoes',
                'title' => 'Condições para Atacado',
                'content' => $this->cmsPageAtacadoCondicoesContent(),
            ],
            [
                'identifier' => 'lgpd',
                'title' => 'Seus Direitos - LGPD',
                'content' => $this->cmsPageLgpdContent(),
            ],
            [
                'identifier' => 'terms',
                'title' => 'Termos de Uso',
                'content' => $this->cmsPageTermsContent(),
            ],
            [
                'identifier' => 'shipping',
                'title' => 'Frete e Entrega',
                'content' => $this->cmsPageShippingContent(),
            ],
            [
                'identifier' => 'atacado/tabela-descontos',
                'title' => 'Tabela de Descontos B2B',
                'content' => $this->cmsPageTabelaDescontosContent(),
            ],
        ];
    }

    private function cmsPageAboutUsContent(): string
    {
        return <<<HTML
<div class="cms-page">
  <h1>Sobre nós</h1>
  <p>Bem-vindo(a) à <strong>{{config path="general/store_information/name"}}</strong>. Somos especialistas em peças e acessórios para duas rodas.</p>
  <p><strong>Precisa de ajuda?</strong> Fale com a gente pela página de <a href="{{store url='customer-service'}}">Atendimento</a>.</p>
</div>
HTML;
    }

    private function cmsPageCustomerServiceContent(): string
    {
        return <<<HTML
<div class="cms-page">
  <h1>Atendimento</h1>
  <p>Estamos aqui para ajudar você antes, durante e depois da compra.</p>
  <ul>
    <li><strong>Telefone:</strong> (11) 4002-8922</li>
    <li><strong>E-mail:</strong> <a href="mailto:suporte@awamotos.com.br">suporte@awamotos.com.br</a></li>
    <li><strong>Horário:</strong> {{config path="general/store_information/hours"}}</li>
  </ul>
  <p>Se preferir, use a página de <a href="{{store url='contact'}}">Contato</a>.</p>
</div>
HTML;
    }

    private function cmsPageFaqContent(): string
    {
        return <<<HTML
<div class="cms-page">
  <h1>FAQ</h1>
  <h2>Como acompanhar meu pedido?</h2>
  <p>Use a opção <a href="{{store url='sales/guest/form'}}">Rastrear pedido</a> (compras como visitante) ou acesse <a href="{{store url='customer/account'}}">Minha Conta</a>.</p>

  <h2>Quais formas de pagamento aceitamos?</h2>
  <p>As formas disponíveis aparecem no checkout. Em caso de dúvida, fale com o <a href="{{store url='customer-service'}}">Atendimento</a>.</p>

  <h2>Como escolher a peça correta?</h2>
  <p>Se precisar, chame nosso time com o modelo/ano da moto e a referência da peça.</p>
</div>
HTML;
    }

    private function cmsPageReturnsContent(): string
    {
        return <<<HTML
<div class="cms-page">
  <h1>Trocas e devoluções</h1>
  <p>Nosso objetivo é que você compre com tranquilidade.</p>
  <p>Para solicitar troca/devolução, entre em contato pelo <a href="{{store url='customer-service'}}">Atendimento</a> com o número do pedido.</p>
  <p><small>Observação: regras detalhadas podem variar conforme o tipo de produto e condições de uso/instalação.</small></p>
</div>
HTML;
    }

    private function cmsPageWarrantyContent(): string
    {
        return <<<HTML
<div class="cms-page">
  <h1>Garantia</h1>
  <p>Produtos podem ter garantia legal e/ou do fabricante. Guarde a nota fiscal e embalagem quando aplicável.</p>
  <p>Para acionar a garantia, fale com nosso <a href="{{store url='customer-service'}}">Atendimento</a> informando o pedido e o problema encontrado.</p>
</div>
HTML;
    }

    private function cmsPageStoreLocatorContent(): string
    {
        return <<<HTML
<div class="cms-page">
  <h1>Lojas e retirada</h1>
  <p>Confira abaixo nossos dados e disponibilidade de retirada (quando habilitada).</p>
  <ul>
    <li><strong>Endereço:</strong> {{config path="general/store_information/street_line1"}} {{config path="general/store_information/street_line2"}} - {{config path="general/store_information/city"}}</li>
    <li><strong>Telefone:</strong> {{config path="general/store_information/phone"}}</li>
    <li><strong>Horário:</strong> {{config path="general/store_information/hours"}}</li>
  </ul>
  <p>Se precisar, use o <a href="{{store url='contact'}}">Contato</a>.</p>
</div>
HTML;
    }

    private function cmsPageAtacadoCondicoesContent(): string
    {
        return <<<HTML
<style>
.atacado-page { max-width: 900px; margin: 0 auto; }
.atacado-page h1 { color: #A33B3B; margin-bottom: 1.5rem; }
.atacado-page h2 { color: #333; border-bottom: 2px solid #A33B3B; padding-bottom: 0.5rem; margin-top: 2rem; }
.atacado-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin: 2rem 0; }
.atacado-card { background: #f8f8f8; border-radius: 8px; padding: 1.5rem; border-left: 4px solid #A33B3B; }
.atacado-card h3 { color: #A33B3B; margin-bottom: 0.75rem; font-size: 1.1rem; }
.atacado-card ul { margin: 0; padding-left: 1.2rem; }
.atacado-card li { margin-bottom: 0.5rem; }
.atacado-cta { background: linear-gradient(135deg, #A33B3B, #8e2629); color: #fff; padding: 2rem; border-radius: 8px; text-align: center; margin: 2rem 0; }
.atacado-cta h3 { color: #fff; margin-bottom: 1rem; }
.atacado-cta a { display: inline-block; background: #fff; color: #A33B3B; padding: 0.75rem 2rem; border-radius: 4px; text-decoration: none; font-weight: bold; }
.atacado-cta a:hover { background: #333; color: #fff; }
</style>
<div class="cms-page atacado-page">
  <h1>Condições para Compras no Atacado</h1>
  <p>O Grupo Awamotos oferece condições especiais para <strong>revendedores</strong>, <strong>oficinas mecânicas</strong> e <strong>distribuidores</strong> de todo o Brasil.</p>

  <div class="atacado-grid">
    <div class="atacado-card">
      <h3>🎯 Quem pode participar?</h3>
      <ul>
        <li>Lojas de peças e acessórios</li>
        <li>Oficinas e mecânicas especializadas</li>
        <li>Distribuidores regionais</li>
        <li>Empresas com CNPJ ativo</li>
      </ul>
    </div>
    <div class="atacado-card">
      <h3>💰 Benefícios exclusivos</h3>
      <ul>
        <li>Descontos de até <strong>20%</strong> no catálogo</li>
        <li>Tabela de preços especial</li>
        <li>Limite de crédito (análise)</li>
        <li>Condições de pagamento flexíveis</li>
      </ul>
    </div>
    <div class="atacado-card">
      <h3>📦 Pedido mínimo</h3>
      <ul>
        <li>Primeiro pedido: R$ 500,00</li>
        <li>Pedidos seguintes: R$ 300,00</li>
        <li>Frete grátis acima de R$ 1.500,00</li>
        <li>Sem quantidade mínima por item</li>
      </ul>
    </div>
    <div class="atacado-card">
      <h3>📋 Como se cadastrar?</h3>
      <ul>
        <li>Cadastro online com CNPJ</li>
        <li>Aprovação em até 24h úteis</li>
        <li>Documentos: Contrato Social, CNPJ</li>
        <li>Referências comerciais (opcional)</li>
      </ul>
    </div>
  </div>

  <h2>Grupos de Clientes</h2>
  <p>Após aprovação, você será incluído em um dos nossos grupos de desconto:</p>
  <ul>
    <li><strong>Revendedor:</strong> 10% de desconto em todo catálogo</li>
    <li><strong>Atacado:</strong> 15% de desconto + condições de pagamento</li>
    <li><strong>VIP:</strong> 20% de desconto + atendimento prioritário + gerente dedicado</li>
  </ul>

  <h2>Formas de Pagamento</h2>
  <ul>
    <li><strong>Boleto:</strong> 28 dias para clientes com cadastro aprovado</li>
    <li><strong>Cartão de Crédito:</strong> Parcelamento em até 6x sem juros</li>
    <li><strong>Pix:</strong> 3% de desconto adicional à vista</li>
    <li><strong>Crédito Rotativo:</strong> Disponível para clientes VIP após análise</li>
  </ul>

  <div class="atacado-cta">
    <h3>Pronto para começar?</h3>
    <p>Cadastre-se agora e receba seu acesso em até 24h úteis</p>
    <a href="{{store url='b2b/register'}}">Criar Conta B2B</a>
  </div>

  <h2>Dúvidas?</h2>
  <p>Nossa equipe B2B está à disposição para atender você:</p>
  <ul>
    <li><strong>WhatsApp Comercial:</strong> (11) 99999-8888</li>
    <li><strong>E-mail:</strong> <a href="mailto:atacado@awamotos.com.br">atacado@awamotos.com.br</a></li>
    <li><strong>Horário:</strong> Segunda a sexta, das 8h às 18h</li>
  </ul>
</div>
HTML;
    }

    private function cmsPageLgpdContent(): string
    {
        return <<<HTML
<style>
.lgpd-page { max-width: 900px; margin: 0 auto; line-height: 1.7; }
.lgpd-page h1 { color: #333; margin-bottom: 1.5rem; }
.lgpd-page h2 { color: #444; border-bottom: 2px solid #A33B3B; padding-bottom: 0.5rem; margin-top: 2rem; }
.lgpd-card { background: #f8f8f8; border-radius: 8px; padding: 1.5rem; margin: 1.5rem 0; border-left: 4px solid #A33B3B; }
.lgpd-rights { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; margin: 1.5rem 0; }
.lgpd-right { background: #fff; border: 1px solid #e5e5e5; border-radius: 8px; padding: 1rem; }
.lgpd-right h4 { color: #A33B3B; margin-bottom: 0.5rem; font-size: 1rem; }
.lgpd-cta { background: #333; color: #fff; padding: 1.5rem; border-radius: 8px; text-align: center; margin: 2rem 0; }
.lgpd-cta a { display: inline-block; background: #A33B3B; color: #fff; padding: 0.75rem 1.5rem; border-radius: 4px; text-decoration: none; font-weight: bold; }
</style>
<div class="cms-page lgpd-page">
  <h1>Seus Direitos - Lei Geral de Proteção de Dados (LGPD)</h1>

  <div class="lgpd-card">
    <p><strong>O Grupo Awamotos está comprometido com a proteção dos seus dados pessoais.</strong> Esta página explica seus direitos conforme a Lei nº 13.709/2018 (LGPD) e como você pode exercê-los.</p>
  </div>

  <h2>🔒 Quais dados coletamos?</h2>
  <ul>
    <li><strong>Dados cadastrais:</strong> Nome, CPF/CNPJ, e-mail, telefone, endereço</li>
    <li><strong>Dados de navegação:</strong> Cookies, IP, páginas visitadas</li>
    <li><strong>Dados de compra:</strong> Histórico de pedidos, formas de pagamento utilizadas</li>
    <li><strong>Dados empresariais (B2B):</strong> Razão social, inscrição estadual, contrato social</li>
  </ul>

  <h2>📋 Seus Direitos</h2>
  <div class="lgpd-rights">
    <div class="lgpd-right">
      <h4>✓ Confirmação e Acesso</h4>
      <p>Você pode confirmar se tratamos seus dados e solicitar uma cópia de todas as informações que temos sobre você.</p>
    </div>
    <div class="lgpd-right">
      <h4>✓ Correção</h4>
      <p>Solicite a correção de dados incompletos, inexatos ou desatualizados a qualquer momento.</p>
    </div>
    <div class="lgpd-right">
      <h4>✓ Anonimização ou Bloqueio</h4>
      <p>Peça o bloqueio ou anonimização de dados desnecessários ou tratados em desconformidade.</p>
    </div>
    <div class="lgpd-right">
      <h4>✓ Portabilidade</h4>
      <p>Solicite a transferência dos seus dados para outro fornecedor de serviço ou produto.</p>
    </div>
    <div class="lgpd-right">
      <h4>✓ Eliminação</h4>
      <p>Peça a exclusão dos dados tratados com base no seu consentimento (exceto obrigações legais).</p>
    </div>
    <div class="lgpd-right">
      <h4>✓ Revogação do Consentimento</h4>
      <p>Cancele a qualquer momento o consentimento dado para tratamento de dados.</p>
    </div>
  </div>

  <h2>📧 Como exercer seus direitos?</h2>
  <p>Para exercer qualquer um dos direitos acima, entre em contato conosco:</p>
  <ul>
    <li><strong>E-mail do DPO:</strong> <a href="mailto:privacidade@awamotos.com.br">privacidade@awamotos.com.br</a></li>
    <li><strong>Formulário:</strong> <a href="{{store url='contact'}}">Página de Contato</a> (selecione "LGPD/Privacidade")</li>
    <li><strong>Prazo de resposta:</strong> Até 15 dias úteis</li>
  </ul>

  <h2>🍪 Cookies</h2>
  <p>Utilizamos cookies para melhorar sua experiência. Você pode gerenciar suas preferências de cookies nas configurações do seu navegador. Saiba mais na nossa <a href="{{store url='privacy-policy-cookie-restriction-mode'}}">Política de Privacidade</a>.</p>

  <div class="lgpd-cta">
    <p>Tem dúvidas sobre como tratamos seus dados?</p>
    <a href="{{store url='contact'}}">Fale Conosco</a>
  </div>
</div>
HTML;
    }

    private function cmsPageTermsContent(): string
    {
        return <<<HTML
<style>
.terms-page { max-width: 900px; margin: 0 auto; line-height: 1.7; }
.terms-page h1 { color: #333; }
.terms-page h2 { color: #444; margin-top: 2rem; border-bottom: 1px solid #ddd; padding-bottom: 0.5rem; }
.terms-page h3 { color: #555; margin-top: 1.5rem; }
.terms-highlight { background: #fff3e0; padding: 1rem; border-radius: 4px; margin: 1rem 0; }
</style>
<div class="cms-page terms-page">
  <h1>Termos de Uso</h1>
  <p><strong>Última atualização:</strong> Janeiro de 2026</p>

  <h2>1. Aceitação dos Termos</h2>
  <p>Ao acessar e utilizar o site do Grupo Awamotos, você concorda com estes Termos de Uso. Se não concordar, não utilize nossos serviços.</p>

  <h2>2. Sobre a Empresa</h2>
  <p>O Grupo Awamotos é uma empresa especializada na comercialização de peças, acessórios e equipamentos para motocicletas, atendendo tanto consumidores finais quanto empresas (B2B).</p>
  <ul>
    <li><strong>Razão Social:</strong> {{config path="general/store_information/name"}}</li>
    <li><strong>CNPJ:</strong> {{config path="general/store_information/merchant_vat_number"}}</li>
    <li><strong>Endereço:</strong> {{config path="general/store_information/street_line1"}}, {{config path="general/store_information/city"}}</li>
  </ul>

  <h2>3. Cadastro e Conta</h2>
  <h3>3.1 Pessoa Física</h3>
  <p>Ao criar uma conta, você declara ser maior de 18 anos e fornecer informações verdadeiras.</p>

  <h3>3.2 Pessoa Jurídica (B2B)</h3>
  <p>Para cadastro empresarial, é necessário:</p>
  <ul>
    <li>CNPJ ativo e regular</li>
    <li>Contrato social ou documento equivalente</li>
    <li>Dados do responsável legal</li>
  </ul>
  <p>O cadastro B2B está sujeito à aprovação e análise de crédito.</p>

  <h2>4. Preços e Pagamentos</h2>
  <div class="terms-highlight">
    <p><strong>Importante:</strong> Os preços podem variar entre clientes B2C e B2B. Clientes cadastrados em grupos especiais (Atacado, VIP, Revendedor) visualizam preços diferenciados após login.</p>
  </div>
  <p>Todos os preços estão em Reais (BRL) e podem ser alterados sem aviso prévio, não afetando pedidos já confirmados.</p>

  <h2>5. Entrega</h2>
  <p>Os prazos de entrega são estimativas e podem variar conforme região e disponibilidade. Consulte nossa página de <a href="{{store url='shipping'}}">Frete e Entrega</a> para mais detalhes.</p>

  <h2>6. Trocas e Devoluções</h2>
  <p>Conforme o Código de Defesa do Consumidor, você tem até 7 dias corridos após o recebimento para desistir da compra. Consulte nossa <a href="{{store url='returns'}}">Política de Trocas e Devoluções</a>.</p>

  <h2>7. Propriedade Intelectual</h2>
  <p>Todo o conteúdo do site (textos, imagens, logos, layout) é de propriedade do Grupo Awamotos ou licenciado. É proibida a reprodução sem autorização.</p>

  <h2>8. Limitação de Responsabilidade</h2>
  <p>O Grupo Awamotos não se responsabiliza por:</p>
  <ul>
    <li>Uso inadequado dos produtos adquiridos</li>
    <li>Danos causados por instalação incorreta</li>
    <li>Interrupções no serviço por motivos de força maior</li>
  </ul>

  <h2>9. Privacidade</h2>
  <p>Seus dados são tratados conforme nossa <a href="{{store url='privacy-policy-cookie-restriction-mode'}}">Política de Privacidade</a> e a <a href="{{store url='lgpd'}}">LGPD</a>.</p>

  <h2>10. Foro</h2>
  <p>Fica eleito o foro da comarca de {{config path="general/store_information/city"}} para dirimir quaisquer controvérsias.</p>

  <h2>11. Contato</h2>
  <p>Dúvidas sobre estes termos? Entre em contato pelo <a href="{{store url='contact'}}">formulário de contato</a> ou e-mail <a href="mailto:contato@awamotos.com.br">contato@awamotos.com.br</a>.</p>
</div>
HTML;
    }

    private function cmsPageShippingContent(): string
    {
        return <<<HTML
<style>
.shipping-page { max-width: 900px; margin: 0 auto; }
.shipping-page h1 { color: #333; }
.shipping-page h2 { color: #444; margin-top: 2rem; border-bottom: 2px solid #A33B3B; padding-bottom: 0.5rem; }
.shipping-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin: 1.5rem 0; }
.shipping-card { background: #f8f8f8; border-radius: 8px; padding: 1.5rem; text-align: center; }
.shipping-card h3 { color: #A33B3B; margin-bottom: 0.5rem; }
.shipping-card .price { font-size: 1.5rem; font-weight: bold; color: #333; }
.shipping-table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
.shipping-table th, .shipping-table td { padding: 0.75rem; border: 1px solid #ddd; text-align: left; }
.shipping-table th { background: #f5f5f5; }
.shipping-b2b { background: linear-gradient(135deg, #fbe9e9, #fff); padding: 1.5rem; border-radius: 8px; border-left: 4px solid #A33B3B; margin: 1.5rem 0; }
</style>
<div class="cms-page shipping-page">
  <h1>Frete e Entrega</h1>

  <h2>📦 Modalidades de Entrega</h2>
  <div class="shipping-grid">
    <div class="shipping-card">
      <h3>🚚 Correios PAC</h3>
      <p>Entrega econômica para todo Brasil</p>
      <p class="price">5 a 15 dias úteis</p>
    </div>
    <div class="shipping-card">
      <h3>✈️ Correios SEDEX</h3>
      <p>Entrega expressa</p>
      <p class="price">1 a 5 dias úteis</p>
    </div>
    <div class="shipping-card">
      <h3>🏪 Retirada na Loja</h3>
      <p>Retire em nosso endereço</p>
      <p class="price">GRÁTIS</p>
    </div>
  </div>

  <h2>💰 Frete Grátis</h2>
  <table class="shipping-table">
    <thead>
      <tr>
        <th>Tipo de Cliente</th>
        <th>Valor Mínimo</th>
        <th>Regiões</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>Pessoa Física</td>
        <td>R$ 299,00</td>
        <td>Sul e Sudeste</td>
      </tr>
      <tr>
        <td>Pessoa Física</td>
        <td>R$ 499,00</td>
        <td>Todo Brasil</td>
      </tr>
      <tr>
        <td>B2B / Atacado</td>
        <td>R$ 1.500,00</td>
        <td>Todo Brasil</td>
      </tr>
    </tbody>
  </table>

  <div class="shipping-b2b">
    <h3>🏭 Frete para Clientes B2B</h3>
    <p>Clientes cadastrados no programa B2B têm condições especiais de frete:</p>
    <ul>
      <li><strong>Frete CIF:</strong> Frete por conta do Grupo Awamotos em pedidos acima de R$ 1.500,00</li>
      <li><strong>Frete FOB:</strong> Frete por conta do cliente, com valores negociados com transportadoras parceiras</li>
      <li><strong>Transportadora preferencial:</strong> Possibilidade de usar sua transportadora de confiança</li>
    </ul>
    <p><a href="{{store url='atacado/condicoes'}}">Saiba mais sobre o programa B2B</a></p>
  </div>

  <h2>📍 Prazo de Entrega</h2>
  <p>O prazo é contado em dias úteis a partir da confirmação do pagamento. Prazos estimados:</p>
  <table class="shipping-table">
    <thead>
      <tr>
        <th>Região</th>
        <th>PAC</th>
        <th>SEDEX</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>Sudeste (SP, RJ, MG, ES)</td>
        <td>3-7 dias</td>
        <td>1-2 dias</td>
      </tr>
      <tr>
        <td>Sul (PR, SC, RS)</td>
        <td>5-8 dias</td>
        <td>2-3 dias</td>
      </tr>
      <tr>
        <td>Centro-Oeste</td>
        <td>7-12 dias</td>
        <td>3-5 dias</td>
      </tr>
      <tr>
        <td>Nordeste</td>
        <td>10-15 dias</td>
        <td>4-6 dias</td>
      </tr>
      <tr>
        <td>Norte</td>
        <td>12-18 dias</td>
        <td>5-8 dias</td>
      </tr>
    </tbody>
  </table>

  <h2>📋 Informações Importantes</h2>
  <ul>
    <li>O rastreamento é enviado por e-mail após a postagem</li>
    <li>Produtos volumosos podem ter frete diferenciado</li>
    <li>Em caso de ausência, 3 tentativas de entrega serão realizadas</li>
    <li>Confira o produto no momento da entrega</li>
  </ul>

  <h2>❓ Dúvidas?</h2>
  <p>Entre em contato pelo <a href="{{store url='customer-service'}}">Atendimento</a> ou WhatsApp <a href="https://wa.me/5516997367588">(16) 99736-7588</a>.</p>
</div>
HTML;
    }

    private function cmsPageTabelaDescontosContent(): string
    {
        return <<<HTML
<style>
.descontos-page { max-width: 900px; margin: 0 auto; }
.descontos-page h1 { color: #A33B3B; }
.descontos-page h2 { color: #333; margin-top: 2rem; }
.descontos-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin: 2rem 0; }
.desconto-card { background: #fff; border: 2px solid #e5e5e5; border-radius: 12px; padding: 1.5rem; text-align: center; transition: all 0.3s ease; }
.desconto-card:hover { border-color: #A33B3B; box-shadow: 0 8px 25px rgba(183, 51, 55, 0.15); transform: translateY(-5px); }
.desconto-card.destaque { border-color: #A33B3B; background: linear-gradient(135deg, #fbe9e9, #fff); }
.desconto-card h3 { margin-bottom: 0.5rem; }
.desconto-percent { font-size: 3rem; font-weight: bold; color: #A33B3B; line-height: 1; }
.desconto-percent span { font-size: 1.5rem; }
.desconto-features { text-align: left; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #eee; }
.desconto-features li { margin-bottom: 0.5rem; padding-left: 1.5rem; position: relative; }
.desconto-features li::before { content: "✓"; position: absolute; left: 0; color: #4caf50; font-weight: bold; }
.desconto-cta { background: #333; color: #fff; padding: 2rem; border-radius: 8px; text-align: center; margin: 2rem 0; }
.desconto-cta a { display: inline-block; background: #A33B3B; color: #fff; padding: 1rem 2rem; border-radius: 4px; text-decoration: none; font-weight: bold; font-size: 1.1rem; }
.desconto-note { background: #f5f5f5; padding: 1rem; border-radius: 4px; font-size: 0.9rem; color: #666; }
</style>
<div class="cms-page descontos-page">
  <h1>Tabela de Descontos B2B</h1>
  <p>Conheça os benefícios exclusivos para cada grupo de clientes empresariais do Grupo Awamotos.</p>

  <div class="descontos-grid">
    <div class="desconto-card">
      <h3>🏪 Revendedor</h3>
      <div class="desconto-percent">10<span>%</span></div>
      <p>Desconto em todo catálogo</p>
      <ul class="desconto-features">
        <li>Pedido mínimo: R$ 500</li>
        <li>Pagamento à vista ou boleto</li>
        <li>Suporte por e-mail</li>
        <li>Acesso ao portal B2B</li>
      </ul>
    </div>

    <div class="desconto-card destaque">
      <h3>🏭 Atacado</h3>
      <div class="desconto-percent">15<span>%</span></div>
      <p>Desconto em todo catálogo</p>
      <ul class="desconto-features">
        <li>Pedido mínimo: R$ 1.000</li>
        <li>Boleto 28 dias</li>
        <li>Frete grátis +R$ 1.500</li>
        <li>Atendimento prioritário</li>
        <li>Tabela de preços especial</li>
      </ul>
    </div>

    <div class="desconto-card">
      <h3>⭐ VIP</h3>
      <div class="desconto-percent">20<span>%</span></div>
      <p>Desconto em todo catálogo</p>
      <ul class="desconto-features">
        <li>Sem pedido mínimo</li>
        <li>Crédito rotativo</li>
        <li>Gerente de conta dedicado</li>
        <li>Lançamentos em primeira mão</li>
        <li>Condições personalizadas</li>
      </ul>
    </div>
  </div>

  <h2>📊 Descontos por Volume</h2>
  <p>Além do desconto do seu grupo, oferecemos descontos adicionais por volume de compra:</p>
  <table style="width: 100%; border-collapse: collapse; margin: 1rem 0;">
    <thead>
      <tr style="background: #f5f5f5;">
        <th style="padding: 0.75rem; border: 1px solid #ddd;">Valor do Pedido</th>
        <th style="padding: 0.75rem; border: 1px solid #ddd;">Desconto Adicional</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td style="padding: 0.75rem; border: 1px solid #ddd;">R$ 2.000 a R$ 5.000</td>
        <td style="padding: 0.75rem; border: 1px solid #ddd;">+2%</td>
      </tr>
      <tr>
        <td style="padding: 0.75rem; border: 1px solid #ddd;">R$ 5.000 a R$ 10.000</td>
        <td style="padding: 0.75rem; border: 1px solid #ddd;">+3%</td>
      </tr>
      <tr>
        <td style="padding: 0.75rem; border: 1px solid #ddd;">Acima de R$ 10.000</td>
        <td style="padding: 0.75rem; border: 1px solid #ddd;">+5%</td>
      </tr>
    </tbody>
  </table>

  <h2>💳 Formas de Pagamento B2B</h2>
  <ul>
    <li><strong>Pix:</strong> +3% de desconto adicional</li>
    <li><strong>Boleto à vista:</strong> Valor da tabela</li>
    <li><strong>Boleto 28 dias:</strong> Para grupos Atacado e VIP</li>
    <li><strong>Cartão de Crédito:</strong> Até 6x sem juros</li>
    <li><strong>Crédito Rotativo:</strong> Exclusivo grupo VIP</li>
  </ul>

  <div class="desconto-cta">
    <h3 style="color: #fff; margin-bottom: 1rem;">Quer fazer parte?</h3>
    <p style="margin-bottom: 1.5rem;">Cadastre-se agora e comece a economizar em suas compras!</p>
    <a href="{{store url='b2b/register'}}">Cadastrar como B2B</a>
  </div>

  <div class="desconto-note">
    <p><strong>Observações:</strong></p>
    <ul>
      <li>Descontos não cumulativos com promoções específicas</li>
      <li>Produtos em liquidação podem ter condições diferenciadas</li>
      <li>Grupo de cliente definido após análise cadastral</li>
      <li>Valores e condições sujeitos a alteração</li>
    </ul>
  </div>
</div>
HTML;
    }

    private function createCategories(OutputInterface $output): void
    {
        $store = $this->storeManager->getStore();
        $rootCategoryId = (int)$this->storeManager
            ->getGroup((string)$store->getStoreGroupId())
            ->getRootCategoryId();

        foreach ($this->getCategoryDefinitions() as $categoryData) {
            $collection = $this->categoryCollectionFactory->create();
            $collection->setStoreId(0);
            $collection->addAttributeToFilter('url_key', $categoryData['url_key']);
            $existingCategory = $collection->getFirstItem();

            if ($existingCategory && $existingCategory->getId()) {
                $output->writeln(sprintf(' - Categoria já existe: %s', $categoryData['name']));
                continue;
            }

            try {
                $category = $this->categoryFactory->create();
                $category->setStoreId(0);
                $category->setName($categoryData['name']);
                $category->setUrlKey($categoryData['url_key']);
                $category->setIsActive(true);
                $category->setIncludeInMenu(true);
                $category->setParentId($rootCategoryId);
                $category->setAttributeSetId($category->getDefaultAttributeSetId());
                $category->setIsAnchor(true);

                $this->categoryRepository->save($category);
                $output->writeln(sprintf(' - Categoria criada: %s', $categoryData['name']));
            } catch (\Throwable $exception) {
                $output->writeln(sprintf('<error>   ✗ Erro ao criar categoria %s: %s</error>', $categoryData['name'], $exception->getMessage()));
            }
        }
    }

    private function applyThemeConfigurations(OutputInterface $output): void
    {
        foreach ($this->getThemeConfigurations() as $config) {
            try {
                if (!array_key_exists('value', $config) || $config['value'] === null) {
                    continue;
                }
                $this->configWriter->save($config['path'], $config['value']);

                $output->writeln(sprintf(' - Configuração aplicada: %s', $config['path']));
            } catch (\Throwable $exception) {
                $output->writeln(sprintf('<error>   ✗ Erro ao salvar %s: %s</error>', $config['path'], $exception->getMessage()));
            }
        }
    }

    private function cleanupLegacyThemeConfigurations(OutputInterface $output): void
    {
        foreach ($this->getLegacyThemeConfigPaths() as $path) {
            try {
                $this->configWriter->delete($path, 'default', 0);
                $output->writeln(sprintf(' - Configuração legada removida: %s', $path));
            } catch (\Throwable $exception) {
                $output->writeln(sprintf('<error>   ✗ Erro ao remover %s: %s</error>', $path, $exception->getMessage()));
            }
        }
    }

    private function resolveFrontendThemeId(string $themePath): ?string
    {
        try {
            $theme = $this->themeCollectionFactory->create()
                ->addFieldToFilter('area', 'frontend')
                ->addFieldToFilter('theme_path', $themePath)
                ->getFirstItem();

            if (!$theme->getId()) {
                return null;
            }

            return (string)$theme->getId();
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function resolvePreferredAyoThemeId(): ?string
    {
        foreach (['AWA_Custom/ayo_home5_child', 'ayo/ayo_home5'] as $themePath) {
            $themeId = $this->resolveFrontendThemeId($themePath);
            if ($themeId !== null && $themeId !== '') {
                return $themeId;
            }
        }

        return null;
    }

    private function getBlockDefinitions(): array
    {
        return [
            [
                'identifier' => 'top-left-static',
                'title' => 'Barra superior - Endereço',
                'content' => $this->topLeftStaticContent()
            ],
            [
                'identifier' => 'head_contact',
                'title' => 'Head Contact',
                'content' => $this->headContactContent()
            ],
            [
                'identifier' => 'hotline_header',
                'title' => 'Hotline Header',
                'content' => $this->hotlineHeaderContent()
            ],
            [
                'identifier' => 'top-contact',
                'title' => 'Top Contact',
                'content' => $this->topContactContent()
            ],
            [
                'identifier' => 'footer_info',
                'title' => 'Footer - Informações',
                'content' => $this->footerInfoContent()
            ],
            [
                'identifier' => 'social_block',
                'title' => 'Redes Sociais',
                'content' => $this->socialBlockContent()
            ],
            [
                'identifier' => 'footer_menu',
                'title' => 'Footer - Menu',
                'content' => $this->footerMenuContent()
            ],
            [
                'identifier' => 'footer_static',
                'title' => 'Footer - Conteúdo principal',
                'content' => $this->footerStaticContent()
            ],
            [
                'identifier' => 'footer_payment',
                'title' => 'Footer - Pagamentos',
                'content' => $this->footerPaymentContent()
            ],
            [
                'identifier' => 'fixed_right',
                'title' => 'Atalhos Flutuantes',
                'content' => $this->fixedRightContent()
            ],
            [
                'identifier' => 'organization_schema',
                'title' => 'Schema.org - Organization',
                'content' => $this->organizationSchemaContent()
            ],
            [
                'identifier' => 'home_slider',
                'title' => 'Home - Slider Principal',
                'content' => $this->homeSliderContent()
            ],
            [
                'identifier' => 'home_category_nav_visual',
                'title' => 'Home - Navegação Visual por Categorias (B2B)',
                'content' => $this->homeCategoryNavVisualContent()
            ],
            [
                'identifier' => 'home_featured',
                'title' => 'Home - Produtos em Destaque',
                'content' => $this->homeFeaturedContent()
            ],
            [
                'identifier' => 'home_new_products',
                'title' => 'Home - Novos Produtos',
                'content' => $this->homeNewProductsContent()
            ],
            [
                'identifier' => 'home_banner_promo',
                'title' => 'Home - Banner Promocional',
                'content' => $this->homeBannerPromoContent()
            ],
            [
                'identifier' => 'top_slideshow_home1',
                'title' => 'Home 1 - Slider + Banners',
                'content' => $this->homeTopSlideshowContent()
            ],
            [
                'identifier' => 'list_ads1',
                'title' => 'Home 1 - Banners Laterais',
                'content' => $this->homeListAdsContent()
            ],
            [
                'identifier' => 'banner_mid_home5',
                'title' => 'Home 5 - Banners Centrais',
                'content' => $this->homeBannerMidHome5Content()
            ],
            [
                'identifier' => 'notification_home5',
                'title' => 'Home 5 - Notificações',
                'content' => $this->homeNotificationHome5Content()
            ],
            [
                'identifier' => 'block_top',
                'title' => 'Home - Benefícios superiores',
                'content' => $this->homeBenefitsContent()
            ],
            [
                'identifier' => 'category1_home5',
                'title' => 'Home 5 - Categorias destaque 1',
                'content' => $this->homeCategory1Content()
            ],
            [
                'identifier' => 'category2_home5',
                'title' => 'Home 5 - Categorias destaque 2',
                'content' => $this->homeCategory2Content()
            ],
            [
                'identifier' => 'category1_home1',
                'title' => 'Home 1 - Categorias destaque 1',
                'content' => $this->homeCategory1Content()
            ],
            [
                'identifier' => 'category2_home1',
                'title' => 'Home 1 - Categorias destaque 2',
                'content' => $this->homeCategory2Content()
            ],
            [
                'identifier' => 'featured_categories',
                'title' => 'Home 1 - Compre por categoria',
                'content' => $this->homeFeaturedCategoriesContent()
            ],
            [
                'identifier' => 'home1_product_thumb',
                'title' => 'Home 1 - Produtos com imagem',
                'content' => $this->homeProductThumbContent()
            ],
            [
                'identifier' => 'trust_badges_homepage',
                'title' => 'Home - Trust Badges (Selo de Confiança)',
                'content' => $this->trustBadgesHomepageContent()
            ],
            [
                'identifier' => 'home_testimonials',
                'title' => 'Home - Depoimentos de Clientes',
                'content' => $this->homeTestimonialsContent()
            ],
            [
                'identifier' => 'footer-tags',
                'title' => 'Footer - Tags/Categorias',
                'content' => $this->footerTagsContent()
            ],
            [
                'identifier' => 'baner-sidebar-product-page',
                'title' => 'Banner Lateral - Página de Produto',
                'content' => $this->bannerSidebarProductPageContent()
            ],
            [
                'identifier' => 'catalog-sidebar-adv',
                'title' => 'Banner Lateral - Catálogo',
                'content' => $this->catalogSidebarAdvContent()
            ],
            [
                'identifier' => 'rokanthemes_vertical_menu',
                'title' => 'Menu Vertical - Conteúdo Após',
                'content' => $this->verticalMenuAfterContent()
            ],
            [
                'identifier' => 'rokanthemes_vertical_menu_before',
                'title' => 'Menu Vertical - Conteúdo Antes',
                'content' => $this->verticalMenuBeforeContent()
            ],
            [
                'identifier' => 'home_benefits_bar',
                'title' => 'Barra de Benefícios',
                'content' => $this->homeBenefitsBarContent()
            ],
            [
                'identifier' => 'home_hero',
                'title' => 'Hero Principal',
                'content' => $this->homeHeroContent()
            ],
            [
                'identifier' => 'home_security_seals',
                'title' => 'Home - Selos de Segurança',
                'content' => $this->homeSecuritySealsContent()
            ],
            [
                'identifier' => 'home_b2b_invite',
                'title' => 'Home - Convite B2B/Atacado',
                'content' => $this->homeB2bInviteContent()
            ],
            [
                'identifier' => 'home_schema_org',
                'title' => 'Home - Schema.org (JSON-LD)',
                'content' => CmsBlockData::schemaOrgHomepageContent()
            ],
            [
                'identifier' => 'home_faq_quick',
                'title' => 'Home - FAQ Rápido',
                'content' => $this->homeFaqQuickContent()
            ],
            [
                'identifier' => 'topbar_links',
                'title' => 'Header - Links da Barra Superior',
                'content' => $this->topbarLinksContent()
            ],
            [
                'identifier' => 'rokanthemes_custom_menu_before',
                'title' => 'Menu - Links Antes das Categorias',
                'content' => $this->customMenuBeforeContent()
            ],
            [
                'identifier' => 'rokanthemes_custom_menu',
                'title' => 'Menu - Links Após Categorias',
                'content' => $this->customMenuAfterContent()
            ]
        ];
    }

    private function topbarLinksContent(): string
    {
        return <<<HTML
<ul class="topbar-links">
    <li class="topbar-links__item">
        <a href="{{store url='sales/order/history'}}" title="Rastrear pedido">
            <i class="fa fa-truck" aria-hidden="true"></i> Rastrear Pedido
        </a>
    </li>
    <li class="topbar-links__item">
        <a href="{{store url='contact'}}" title="Central de ajuda">
            <i class="fa fa-question-circle-o" aria-hidden="true"></i> Ajuda
        </a>
    </li>
    <li class="topbar-links__item">
        <a href="{{store url='b2b/register'}}" title="Cadastro B2B" class="topbar-highlight">
            <i class="fa fa-building-o" aria-hidden="true"></i> Atacado B2B
        </a>
    </li>
</ul>
HTML;
    }

    private function customMenuBeforeContent(): string
    {
        return <<<HTML
<li class="level0 nav-item level-top ui-menu-item home-link">
    <a href="{{store url=''}}" class="level-top ui-corner-all" title="Início">
        <i class="fa fa-home" aria-hidden="true"></i>
        <span>Início</span>
    </a>
</li>
HTML;
    }

    private function customMenuAfterContent(): string
    {
        return <<<HTML
<li class="level0 nav-item level-top ui-menu-item ofertas-link">
    <a href="{{store url='ofertas'}}" class="level-top ui-corner-all" title="Super Ofertas">
        <span class="menu-label menu-label--hot">Quente!</span>
        <span>Ofertas</span>
    </a>
</li>
<li class="level0 nav-item level-top ui-menu-item b2b-link">
    <a href="{{store url='b2b/register'}}" class="level-top ui-corner-all" title="Atacado B2B">
        <i class="fa fa-building-o" aria-hidden="true"></i>
        <span>Atacado/B2B</span>
    </a>
</li>
HTML;
    }

    private function homeBenefitsBarContent(): string
    {
            return <<<HTML
<div class="benefits-bar">
    <div class="benefits-bar__item"><img class="benefits-bar__icon" src="{{view url='images/icons/truck.svg'}}" alt="Envio"> Envio para todo o Brasil</div>
    <div class="benefits-bar__item"><img class="benefits-bar__icon" src="{{view url='images/icons/clock.svg'}}" alt="Prazos"> Prazos e valores no carrinho</div>
    <div class="benefits-bar__item"><img class="benefits-bar__icon" src="{{view url='images/icons/shield.svg'}}" alt="Suporte"> Ajuda para compatibilidade</div>
</div>
HTML;
    }

    private function homeFaqQuickContent(): string
    {
        return <<<HTML
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

    private function homeHeroContent(): string
    {
            return <<<HTML
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

    private function homeB2bInviteContent(): string
    {
        return <<<HTML
<section class="aw-home-b2b" aria-label="Atacado e revenda">
    <div class="aw-home-b2b__inner">
        <h2 class="aw-home-b2b__title">Compras para revenda?</h2>
        <p class="aw-home-b2b__text">Cadastre-se para acessar condições de <strong>Atacado</strong>, <strong>VIP</strong> e <strong>Revendedor</strong>, além de solicitar cotações.</p>
        <div class="aw-home-b2b__ctas">
            <a class="action primary" href="{{store url='b2b/register'}}">Cadastrar no B2B</a>
            <a class="action secondary" href="{{store url='b2b/quote/index'}}">Solicitar cotação</a>
        </div>
        <p class="aw-home-b2b__note"><a href="{{store url='customer/account/login'}}">Já tem conta? Entrar</a></p>
    </div>
</section>
HTML;
    }

    private function getCategoryDefinitions(): array
    {
        // Categorias principais para menu de navegação AWA Motos
        // As categorias reais já existem no banco (importadas).
        // Este método garante que categorias essenciais estejam sempre presentes.
        return [
            ['name' => 'Retrovisores', 'url_key' => 'retrovisores'],
            ['name' => 'Guidões', 'url_key' => 'guidoes'],
            ['name' => 'Bauletos', 'url_key' => 'bauletos'],
            ['name' => 'Bagageiros', 'url_key' => 'bagageiros'],
            ['name' => 'Manetes', 'url_key' => 'manetes'],
            ['name' => 'Linha Honda', 'url_key' => 'linha-honda'],
            ['name' => 'Linha Yamaha', 'url_key' => 'linha-yamaha'],
            ['name' => 'Ofertas', 'url_key' => 'ofertas'],
            ['name' => 'Atacado B2B', 'url_key' => 'atacado-b2b']
        ];
    }

    private function getThemeConfigurations(): array
    {
        return [
            // Tema: prioriza o child AWA_Custom/ayo_home5_child e usa ayo/ayo_home5 apenas como fallback.
            // Resolve o theme_id dinamicamente para não depender de IDs fixos do banco.
            ['path' => 'design/theme/theme_id', 'value' => $this->resolvePreferredAyoThemeId()],

            // Header layout & visibility (added for idempotence of Ayo header preset)
            ['path' => 'themeoption/header/header_type', 'value' => '5'],
            ['path' => 'themeoption/header/show_hotline', 'value' => '1'],
            ['path' => 'themeoption/header/show_search', 'value' => '1'],
            ['path' => 'themeoption/header/search_enable', 'value' => '1'],
            ['path' => 'themeoption/header/show_account', 'value' => '1'],
            ['path' => 'themeoption/header/show_minicart', 'value' => '1'],
            ['path' => 'themeoption/header/show_wishlist', 'value' => '1'],
            ['path' => 'themeoption/header/show_compare', 'value' => '0'],
            ['path' => 'themeoption/general/layout', 'value' => 'full_width'],
            ['path' => 'themeoption/header/sticky_enable', 'value' => '1'],
            ['path' => 'themeoption/header/sticky_select_bg_color', 'value' => 'custom'],
            ['path' => 'themeoption/header/sticky_bg_color_custom', 'value' => '#ffffff'],
            ['path' => 'themeoption/footer/footer_menu_mobile', 'value' => '1'],
            ['path' => 'themeoption/fake_order/enable_f_o', 'value' => '0'],
            ['path' => 'themeoption/newsletter/enable', 'value' => '1'],
            ['path' => 'themeoption/newsletter/content', 'value' => $this->newsletterPopupContent()],
            ['path' => 'themeoption/newsletter/width', 'value' => '580'],
            ['path' => 'themeoption/newsletter/height', 'value' => '520'],
            ['path' => 'themeoption/newsletter/bg_color', 'value' => '#ffffff'],
            ['path' => 'themeoption/newsletter/bg_custom_style', 'value' => 'padding:0;'],
            ['path' => 'producttab/new_status/enabled', 'value' => '1'],
            ['path' => 'producttab/new_status/items', 'value' => '5'],
            ['path' => 'producttab/new_status/row', 'value' => '1'],
            ['path' => 'producttab/new_status/speed', 'value' => '400'],
            ['path' => 'producttab/new_status/qty', 'value' => '20'],
            ['path' => 'producttab/new_status/addtocart', 'value' => '1'],
            ['path' => 'producttab/new_status/wishlist', 'value' => '1'],
            ['path' => 'producttab/new_status/compare', 'value' => '0'],
            ['path' => 'producttab/new_status/navigation', 'value' => '1'],
            ['path' => 'producttab/new_status/pagination', 'value' => '0'],
            ['path' => 'producttab/new_status/auto', 'value' => '1'],
            ['path' => 'producttab/new_status/shownew', 'value' => '1'],
            ['path' => 'producttab/new_status/newname', 'value' => 'Lançamentos'],
            ['path' => 'producttab/new_status/showbestseller', 'value' => '1'],
            ['path' => 'producttab/new_status/bestsellername', 'value' => 'Mais vendidos'],
            ['path' => 'producttab/new_status/showfeature', 'value' => '1'],
            ['path' => 'producttab/new_status/featurename', 'value' => 'Destaques'],
            ['path' => 'producttab/new_status/showonsale', 'value' => '1'],
            ['path' => 'producttab/new_status/onsalename', 'value' => 'Promoções'],
            ['path' => 'producttab/new_status/showrandom', 'value' => '0'],
            ['path' => 'producttab/new_status/randomname', 'value' => 'Descubra também'],
            ['path' => 'rokanthemes_quickview/general/enabled', 'value' => '1'],
            ['path' => 'ajaxsuite/general/enabled', 'value' => '1'],
            ['path' => 'ajaxsuite/ajaxcart/enabled', 'value' => '1'],
            ['path' => 'ajaxsuite/ajaxcompare/enabled', 'value' => '1'],
            ['path' => 'ajaxsuite/ajaxwishlist/enabled', 'value' => '1']
        ];
    }

    private function getLegacyThemeConfigPaths(): array
    {
        return [
            'rokanthemes_custommenu/general/animation',
            'rokanthemes_custommenu/general/default_menu_type',
            'rokanthemes_custommenu/general/show_icons',
            'rokanthemes_custommenu/general/visible_menu_depth',
            'rokanthemes_verticalmenu/general/enable',
            'rokanthemes_verticalmenu/general/limit_show_more',
            'rokanthemes_verticalmenu/general/show_less_text',
            'rokanthemes_verticalmenu/general/show_more_text',
            'rokanthemes_themeoption/newsletter_popup/enable',
            'rokanthemes_themeoption/newsletter_popup/delay',
            'rokanthemes_themeoption/newsletter_popup/cookie_lifetime',
            'rokanthemes_themeoption/newsletter_popup/width',
            'rokanthemes_themeoption/newsletter_popup/height',
        ];
    }

    private function homeSecuritySealsContent(): string
    {
            return <<<HTML
<div class="section">
    <div class="security-seals" aria-label="Selos de segurança e confiança">
        <img loading="lazy" src="{{view url='images/payment_methods.png'}}" alt="Formas de pagamento" width="280" height="40" />
        <img loading="lazy" src="{{view url='images/awamotos-seguranca-ssl.svg'}}" alt="Site seguro SSL" width="120" height="40" />
        <img loading="lazy" src="{{view url='images/awamotos-compra-protegida.svg'}}" alt="Compra protegida" width="120" height="40" />
        <span class="microcopy">
            Compra segura via SSL • Pagamentos: Pix, Cartões e Boleto.
            <a href="{{store url='privacy-policy-cookie-restriction-mode'}}">Privacidade</a> •
            <a href="{{store url='customer-service'}}">Ajuda</a>
        </span>
    </div>
</div>
HTML;
    }

    private function headContactContent(): string
    {
        return <<<HTML
<div class="head-contact">
    <span>Atendimento: <a href="https://wa.me/5516997367588" target="_blank" rel="noopener">(16) 99736-7588</a></span>
    <span class="separator">•</span>
    <span><a href="mailto:contato@awamotos.com.br">contato@awamotos.com.br</a></span>
</div>
HTML;
    }

    private function topLeftStaticContent(): string
    {
        return <<<HTML
<div class="top-left-static">
    <span class="topbar-info topbar-info--location">
        <i class="fa fa-map-marker" aria-hidden="true"></i>
        <span class="info-text">Araraquara/SP</span>
    </span>
    <span class="separator">•</span>
    <span class="topbar-info topbar-info--hours">
        <i class="fa fa-clock-o" aria-hidden="true"></i>
        <span class="info-text">Seg-Sex: 9h às 17h</span>
    </span>
    <span class="separator">•</span>
    <span class="topbar-info topbar-info--phone">
        <i class="fa fa-phone" aria-hidden="true"></i>
        <a href="tel:+551699736-7588" class="info-text">(16) 99736-7588</a>
    </span>
</div>
HTML;
    }

    private function hotlineHeaderContent(): string
    {
        return <<<HTML
<div class="hoteline_header">
    <a href="https://wa.me/5516997367588" target="_blank" rel="noopener" class="whatsapp-hotline" title="Fale conosco pelo WhatsApp">
        <i class="fa fa-whatsapp" aria-hidden="true"></i>
        <span class="hotline-wrap">
            <span class="hotline-label">Atendimento:</span>
            <span class="hotline-number">(16) 99736-7588</span>
        </span>
    </a>
</div>
HTML;
    }

    private function topContactContent(): string
    {
        return <<<HTML
<div class="top-contact">
    <div class="phone">
        <i class="fa fa-whatsapp" aria-hidden="true"></i>
        <span class="label">WhatsApp</span>
        <a href="https://wa.me/5516997367588" target="_blank" rel="noopener">(16) 99736-7588</a>
    </div>
    <div class="email">
        <i class="fa fa-envelope-o" aria-hidden="true"></i>
        <span class="label">E-mail</span>
        <a href="mailto:contato@awamotos.com.br">contato@awamotos.com.br</a>
    </div>
</div>
HTML;
    }

    private function footerInfoContent(): string
    {
        return <<<HTML
<div class="footer-info">
    <h4>Informações</h4>
    <p><strong>{{config path="general/store_information/name"}}</strong></p>
    <p><strong>Telefone:</strong> {{config path="general/store_information/phone"}}</p>
    <p><strong>Horário:</strong> {{config path="general/store_information/hours"}}</p>
    <p><strong>CNPJ:</strong> {{config path="general/store_information/merchant_vat_number"}}</p>
</div>
HTML;
    }

    private function socialBlockContent(): string
    {
        return <<<HTML
<div class="social-links" aria-label="Redes sociais">
    <a href="https://www.instagram.com/awamotos" target="_blank" rel="noopener" class="instagram">
        <span aria-hidden="true"><i class="fa fa-instagram"></i></span> Instagram
    </a>
    <a href="https://www.facebook.com/awamotos" target="_blank" rel="noopener" class="facebook">
        <span aria-hidden="true"><i class="fa fa-facebook"></i></span> Facebook
    </a>
    <a href="https://www.youtube.com/@awamotos" target="_blank" rel="noopener" class="youtube">
        <span aria-hidden="true"><i class="fa fa-youtube"></i></span> YouTube
    </a>
    <a href="https://wa.me/5516997367588" target="_blank" rel="noopener" class="whatsapp">
        <span aria-hidden="true"><i class="fa fa-whatsapp"></i></span> WhatsApp
    </a>
</div>
HTML;
    }

    private function footerMenuContent(): string
    {
        return <<<HTML
<div class="footer-menu">
    <h4>Links Úteis</h4>
    <ul>
        <li><a href="{{store url='about-us'}}">Sobre nós</a></li>
        <li><a href="{{store url='customer-service'}}">Atendimento</a></li>
        <li><a href="{{store url='faq'}}">FAQ</a></li>
        <li><a href="{{store url='privacy-policy-cookie-restriction-mode'}}">Privacidade</a></li>
    </ul>
</div>
HTML;
    }

    private function footerStaticContent(): string
    {
        return <<<HTML
<div class="velaNewsletterFooter">
    <div class="container">
        <div class="velaNewsletterInner clearfix">
            <div class="velaContent velaContentTitle">
                <h4 class="velaFooterTitle">Receba ofertas exclusivas</h4>
                <div class="newsletterDescription">
                    <span class="text-subcrib">Junte-se a milhares de clientes</span> e receba promoções, lançamentos e conteúdos técnicos sobre motos.
                </div>
            </div>
            <div class="velaContent velaContentForm">
                {{block class="Magento\Newsletter\Block\Subscribe" template="subscribe.phtml"}}
            </div>
            <div class="velaContent velaContentSupport">
                <div class="support-mail">
                    <label>Dúvidas?</label>
                    <strong><a href="mailto:contato@awamotos.com.br">contato@awamotos.com.br</a></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container aw-footer-highlights" aria-label="Benefícios da loja">
    <div class="row">
        <div class="col-xs-12 col-sm-4">
            <div class="aw-footer-highlight">
                <div class="aw-footer-highlight__icon">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                </div>
                <div class="aw-footer-highlight__content">
                    <div class="aw-footer-highlight__title">Compra 100% Segura</div>
                    <div class="aw-footer-highlight__text">Seus dados protegidos com criptografia SSL e práticas rigorosas de segurança.</div>
                </div>
            </div>
        </div>
        <div class="col-xs-12 col-sm-4">
            <div class="aw-footer-highlight">
                <div class="aw-footer-highlight__icon">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                </div>
                <div class="aw-footer-highlight__content">
                    <div class="aw-footer-highlight__title">Suporte Especializado</div>
                    <div class="aw-footer-highlight__text">Equipe técnica para ajudar na escolha da peça certa para sua moto.</div>
                </div>
            </div>
        </div>
        <div class="col-xs-12 col-sm-4">
            <div class="aw-footer-highlight">
                <div class="aw-footer-highlight__icon">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                </div>
                <div class="aw-footer-highlight__content">
                    <div class="aw-footer-highlight__title">Trocas Facilitadas</div>
                    <div class="aw-footer-highlight__text">Políticas claras de troca e devolução. Resolvemos rápido.</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <div class="rowFlex rowFlexMargin">
        <div class="col-xs-12 col-sm-12 col-md-4">
            <div class="vela-contactinfo velaBlock">
                <div class="vela-content">
                    <div class="contacinfo-logo clearfix">
                        <div class="velaFooterLogo">
                            <a href="{{store url=''}}" title='{{config path="general/store_information/name"}}'>
                                <svg width="40" height="40" viewBox="0 0 40 40" fill="none" aria-hidden="true"><circle cx="20" cy="20" r="20" fill="#A33B3B"/><path d="M12 28l8-16 8 16H12z" fill="#fff"/></svg>
                                {{config path="general/store_information/name"}}
                            </a>
                        </div>
                    </div>
                    <div class="intro-footer">
                        Especialistas em peças e acessórios para o mercado brasileiro de duas rodas. Qualidade, variedade e atendimento técnico.
                    </div>
                    <div class="contacinfo-phone contactinfo-item clearfix">
                        <div class="d-flex">
                            <div class="wrap">
                                <label>Telefone</label>
                                <a href="tel:+5516997367588">(16) 99736-7588</a>
                            </div>
                        </div>
                    </div>
                    <div class="contacinfo-address contactinfo-item">
                        <label>WhatsApp</label>
                        <a href="https://wa.me/5516997367588" target="_blank" rel="noopener noreferrer">Chamar agora</a>
                    </div>
                    <div class="contacinfo-address contactinfo-item">
                        <label>E-mail</label>
                        <a href="mailto:contato@awamotos.com.br">contato@awamotos.com.br</a>
                    </div>
                    <div class="contacinfo-address contactinfo-item">
                        <label>Endereço</label>
                        <address style="font-style: normal; margin: 0;">
                            {{config path="general/store_information/street_line1"}}<br>
                            {{config path="general/store_information/city"}} - {{config path="general/store_information/region_id"}}<br>
                            CEP: {{config path="general/store_information/postcode"}}
                        </address>
                    </div>
                    <div class="contacinfo-address contactinfo-item">
                        <label>Horário</label>
                        <span>{{config path="general/store_information/hours"}}</span>
                    </div>
                    <div class="aw-footer-social" aria-label="Redes sociais">
                        <a href="https://www.instagram.com/awamotos" target="_blank" rel="noopener noreferrer" class="instagram" aria-label="Instagram">
                            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                            <span class="sr-text">Instagram</span>
                        </a>
                        <a href="https://www.facebook.com/awamotos" target="_blank" rel="noopener noreferrer" class="facebook" aria-label="Facebook">
                            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                            <span class="sr-text">Facebook</span>
                        </a>
                        <a href="https://www.youtube.com/@awamotos" target="_blank" rel="noopener noreferrer" class="youtube" aria-label="YouTube">
                            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                            <span class="sr-text">YouTube</span>
                        </a>
                        <a href="https://wa.me/5516997367588" target="_blank" rel="noopener noreferrer" class="whatsapp" aria-label="WhatsApp">
                            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                            <span class="sr-text">WhatsApp</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xs-12 col-sm-12 col-md-4">
            <div class="rowFlex rowFlexMargin">
                <div class="col-xs-12 col-sm-6">
                    <div class="velaFooterMenu velaBlock">
                        <button type="button" class="velaFooterTitle" aria-expanded="false">Institucional</button>
                        <div class="velaContent">
                            <ul class="velaFooterLinks list-unstyled">
                                <li><a href="{{store url='about-us'}}">Sobre nós</a></li>
                                <li><a href="{{store url='store-locator'}}">Lojas e retirada</a></li>
                                <li><a href="{{store url='contact'}}">Contato</a></li>
                                <li><a href="{{store url='privacy-policy-cookie-restriction-mode'}}">Política de Privacidade</a></li>
                                <li><a href="{{store url='sales/guest/form'}}">Rastrear pedido</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-xs-12 col-sm-6">
                    <div class="velaFooterMenu velaBlock">
                        <button type="button" class="velaFooterTitle" aria-expanded="false">Ajuda</button>
                        <div class="velaContent">
                            <ul class="velaFooterLinks list-unstyled">
                                <li><a href="{{store url='customer-service'}}">Atendimento</a></li>
                                <li><a href="{{store url='faq'}}">Perguntas Frequentes</a></li>
                                <li><a href="{{store url='returns'}}">Trocas e Devoluções</a></li>
                                <li><a href="{{store url='warranty'}}">Garantia</a></li>
                                <li><a href="{{store url='shipping'}}">Frete e Entrega</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xs-12 col-sm-12 col-md-4">
            <div class="rowFlex rowFlexMargin">
                <div class="col-xs-12 col-sm-6">
                    <div class="velaFooterMenu velaBlock">
                        <button type="button" class="velaFooterTitle" aria-expanded="false">Minha Conta</button>
                        <div class="velaContent">
                            <ul class="velaFooterLinks list-unstyled">
                                <li><a href="{{store url='customer/account/login'}}">Entrar</a></li>
                                <li><a href="{{store url='customer/account/create'}}">Criar conta</a></li>
                                <li><a href="{{store url='customer/account'}}">Minha conta</a></li>
                                <li><a href="{{store url='sales/order/history'}}">Meus pedidos</a></li>
                                <li><a href="{{store url='wishlist'}}">Lista de desejos</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-xs-12 col-sm-6">
                    <div class="velaFooterMenu velaBlock velaBlock--b2b">
                        <button type="button" class="velaFooterTitle" aria-expanded="false">Atacado / B2B</button>
                        <div class="velaContent">
                            <ul class="velaFooterLinks list-unstyled">
                                <li><a href="{{store url='b2b/register'}}">Cadastro Empresarial</a></li>
                                <li><a href="{{store url='b2b/account/dashboard'}}">Painel B2B</a></li>
                                <li><a href="{{store url='b2b/quote/index'}}">Solicitar Cotação</a></li>
                                <li><a href="{{store url='b2b/quote/history'}}">Minhas Cotações</a></li>
                                <li><a href="{{store url='atacado/condicoes'}}">Condições Atacado</a></li>
                                <!-- Catálogo B2B desabilitado até criação/publicação da página -->
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contato B2B Dedicado -->
            <div class="aw-footer-b2b-contact" aria-labelledby="b2b-contact-title">
                <h5 id="b2b-contact-title" class="b2b-contact-title">Central de Atacado</h5>
                <div class="b2b-contact-content">
                    <div class="b2b-contact-item">
                        <span class="b2b-contact-label">WhatsApp B2B:</span>
                        <a href="https://wa.me/5516997367588?text=Olá! Gostaria de informações sobre atacado/B2B." target="_blank" rel="noopener noreferrer" class="b2b-contact-value">(16) 99736-7588</a>
                    </div>
                    <div class="b2b-contact-item">
                        <span class="b2b-contact-label">E-mail:</span>
                        <a href="mailto:atacado@awamotos.com.br" class="b2b-contact-value">atacado@awamotos.com.br</a>
                    </div>
                    <div class="b2b-contact-hours">
                        <strong>Atendimento B2B:</strong> Seg-Sex 8h às 18h | Sáb 8h às 12h
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Indicadores de Confiança B2B -->
    <div class="aw-footer-trust-b2b" aria-label="Indicadores de confiança">
        <div class="trust-item">
            <span class="trust-number">500+</span>
            <span class="trust-label">Revendedores Ativos</span>
        </div>
        <div class="trust-item">
            <span class="trust-number">15+</span>
            <span class="trust-label">Anos no Mercado</span>
        </div>
        <div class="trust-item">
            <span class="trust-number">50.000+</span>
            <span class="trust-label">Produtos</span>
        </div>
        <div class="trust-item">
            <span class="trust-number">98%</span>
            <span class="trust-label">Satisfação</span>
        </div>
    </div>

    <!-- Skip link para acessibilidade -->
    <a href="#maincontent" class="skip-to-main sr-only sr-only-focusable">Voltar ao conteúdo principal</a>

    <div class="aw-footer-legal" aria-label="Informações legais" role="contentinfo">
        <div class="legal-info">
            <small>
                © 2025–2026 {{config path="general/store_information/name"}}
                <span class="legal-separator" aria-hidden="true">·</span>
                <span class="legal-item">CNPJ: {{config path="general/store_information/merchant_vat_number"}}</span>
                <span class="legal-separator" aria-hidden="true">·</span>
                <span class="legal-item">IE: Isento</span>
            </small>
        </div>
        <nav class="legal-links" aria-label="Links legais">
            <a href="{{store url='privacy-policy-cookie-restriction-mode'}}">Política de Privacidade</a>
            <a href="{{store url='terms'}}">Termos de Uso</a>
            <a href="{{store url='lgpd'}}">Seus Direitos (LGPD)</a>
            <a href="{{store url='shipping'}}">Política de Frete</a>
        </nav>
    </div>

    <!--
        Schema.org Organization REMOVIDO daqui.
        O JSON-LD Organization é renderizado por:
        - Homepage: awa-seo-head.phtml (@graph consolidado)
        - Outras páginas: SchemaOrg/organization.phtml (via layout XML)
        Manter inline aqui causava 4+ duplicatas na homepage.
    -->
</div>
HTML;
    }

    private function footerPaymentContent(): string
    {
        return <<<HTML
<div class="footer-payment-methods">
    <div class="payment-methods-wrapper">
        <h5 class="footer-title">Pagamento Seguro</h5>
        <img src="{{view url='images/payment_methods.png'}}" alt="Bandeiras de cartão de crédito, boleto e pix" class="payment-methods-img" loading="lazy">
    </div>
    <div class="security-seals-wrapper">
        <h5 class="footer-title">Compra Segura</h5>
        <div class="seals">
            <span class="security-seal-item" aria-label="Selo Google Safe Browsing">
                <svg width="96" height="32" viewBox="0 0 96 32" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-labelledby="google-safe-browsing-title">
                    <title id="google-safe-browsing-title">Google Safe Browsing</title>
                    <path d="M7.2 16.5C7.2 15.6 7.3 14.7 7.5 13.8L0.6 8.7C0.2 10.9 0 13.2 0 15.5C0 17.8 0.2 20.1 0.6 22.3L7.5 17.2C7.3 16.9 7.2 16.6 7.2 16.5Z" fill="#FBBC04"/>
                    <path d="M15.5 6.3C14.1 4.8 12.2 3.8 10.1 3.8C5.9 3.8 2.3 6.9 0.7 10.8L7.6 15.9C8.4 13.2 10.7 11.2 13.5 11.2C14.5 11.2 15.4 11.5 16.2 12L20.1 8.1C18.6 7.1 17.1 6.3 15.5 6.3Z" fill="#EA4335"/>
                    <path d="M10.1 27.2C12.2 27.2 14.1 26.2 15.5 24.7L20.2 28.9C18.1 30.8 15.5 32 12.5 32C7.8 32 3.7 29.1 1.9 24.9L8.8 19.8C9.6 22.5 11.9 24.5 14.7 24.5C13.7 24.5 12.8 24.2 12 23.7L10.1 27.2Z" fill="#34A853"/>
                    <path d="M22.5 16C22.5 15.2 22.4 14.4 22.2 13.6L15.3 8.5C15.7 9.8 15.9 11.1 15.9 12.5C15.9 19.5 10.4 25.2 3.5 25.2L3.5 31.2C13.7 31.2 22.5 22.5 22.5 16Z" fill="#4285F4"/>
                    <text x="30" y="21" font-family="Arial, sans-serif" font-size="9" fill="#666">Google Safe Browsing</text>
                </svg>
            </span>
            <span class="security-seal-item" aria-label="Selo Conexão Segura SSL">
                <svg width="96" height="32" viewBox="0 0 96 32" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-labelledby="ssl-secure-title">
                    <title id="ssl-secure-title">SSL Secure Connection</title>
                    <path d="M14 7H8C7.45 7 7 7.45 7 8V14H15V8C15 7.45 14.55 7 14 7ZM13 12H9V9H13V12Z" fill="#34A853"/>
                    <path d="M17.5 15H4.5C3.67 15 3 15.67 3 16.5V23.5C3 24.33 3.67 25 4.5 25H17.5C18.33 25 19 24.33 19 23.5V16.5C19 15.67 18.33 15 17.5 15ZM11 22C9.9 22 9 21.1 9 20C9 18.9 9.9 18 11 18C12.1 18 13 18.9 13 20C13 21.1 12.1 22 11 22Z" fill="#34A853"/>
                    <text x="28" y="21" font-family="Arial, sans-serif" font-size="9" fill="#666">SSL Secure Connection</text>
                </svg>
            </span>
        </div>
    </div>
</div>
HTML;
    }

    private function newsletterPopupContent(): string
    {
        return <<<HTML
<div class="ayo-newsletter-popup">
    <div class="newsletter-popup-badge">
        <i class="fa fa-gift"></i>
    </div>
    <h3 class="newsletter-popup-title">GANHE 10% OFF</h3>
    <p class="newsletter-popup-subtitle">Na sua primeira compra!</p>
    <p class="newsletter-popup-description">Cadastre seu e-mail e receba um cupom exclusivo + novidades sobre equipamentos e acessórios para motos.</p>
    {{block class="Magento\\Newsletter\\Block\\Subscribe" template="subscribe.phtml"}}
    <p class="newsletter-popup-privacy">
        <small>Seus dados estão seguros. Não compartilhamos com terceiros.</small>
    </p>
</div>
HTML;
    }

    private function homeSliderContent(): string
    {
        return <<<HTML
<div class="banner-slider banner-slider--home5">
    <a href="{{store url='eletronicos'}}" class="banner-hero-link" title="Eletrônicos em Destaque">
            <img
                class="lazy banner-hero-img"
                src='data:image/gif;base64,R0lGODlhAQABAAAAACwAAAAAAQABAAA='
                data-src="{{view url='images/home/banner-hero.svg'}}"
                alt="Eletrônicos em Destaque"
            />
            <noscript>
                <img class="banner-hero-img" src="{{view url='images/home/banner-hero.svg'}}" alt="Eletrônicos em Destaque" />
            </noscript>
    </a>
</div>
HTML;
    }

    private function homeFeaturedContent(): string
    {
        return <<<HTML
<div class="ayo-home5-product-grid ayo-home5-product-grid--carousel" aria-label="Produtos em Destaque">
    {{widget type="Rokanthemes\\Featuredpro\\Block\\Widget\\Featuredpro"
        template="widget/featuredpro_list.phtml"
        limit="12"
        row="1"
        navigation="1"
        pagination="0"}}
</div>
HTML;
    }

    private function homeNewProductsContent(): string
    {
        return <<<HTML
<div class="ayo-home5-product-grid ayo-home5-product-grid--carousel" aria-label="Novos Produtos">
    {{widget type="Rokanthemes\\Newproduct\\Block\\Widget\\Newproduct"
        template="widget/newproduct_list.phtml"
        limit="12"
        row="1"
        navigation="1"
        pagination="0"}}
</div>
HTML;
    }

    private function homeBannerPromoContent(): string
    {
        return <<<HTML
<div class="ayo-home5-promo">
    <div class="ayo-home5-promo__inner">
        <span class="ayo-home5-promo__badge">{{trans "Linha exclusiva"}}</span>
        <h2>{{trans "Equipe-se para qualquer pista"}}</h2>
        <p>{{trans "Capacetes, jaquetas e acessórios selecionados com condições especiais para quem vive a estrada."}}</p>
        <a class="action primary ayo-home5-promo__cta" href="{{store url='ofertas'}}">{{trans "Ver ofertas"}}</a>
    </div>
    <div class="ayo-home5-promo__image">
        <img class="lazy" src='data:image/gif;base64,R0lGODlhAQABAAAAACwAAAAAAQABAAA=' data-src="{{view url='images/home5/support_icon.png'}}" alt="{{trans "Acessórios de moto"}}" />
        <noscript>
            <img src="{{view url='images/home5/support_icon.png'}}" alt="{{trans "Acessórios de moto"}}" />
        </noscript>
    </div>
</div>
HTML;
    }

    private function homeTopSlideshowContent(): string
    {
        return <<<HTML
<div class="ayo-home5-hero-layout">
    <div class="ayo-home5-hero-layout__main">
        {{block class="Magento\\Cms\\Block\\Block" block_id="home_slider"}}
    </div>
    <div class="ayo-home5-hero-layout__side">
        <a class="ayo-home5-hero-card ayo-home5-hero-card--primary" href="{{store url='moda'}}" title="Moda">
            <img class="lazy" src='data:image/gif;base64,R0lGODlhAQABAAAAACwAAAAAAQABAAA=' data-src="{{view url='images/home/banner-side-1.svg'}}" alt="Moda" />
            <noscript>
                <img src="{{view url='images/home/banner-side-1.svg'}}" alt="Moda" />
            </noscript>
            <span class="ayo-home5-hero-card__content">
                <span class="ayo-home5-hero-card__eyebrow">Coleção exclusiva</span>
                <strong class="ayo-home5-hero-card__title">Moda</strong>
                <span class="ayo-home5-hero-card__cta">Ver novidades</span>
            </span>
        </a>
        <a class="ayo-home5-hero-card ayo-home5-hero-card--secondary" href="{{store url='esportes'}}" title="Esportes">
            <img class="lazy" src='data:image/gif;base64,R0lGODlhAQABAAAAACwAAAAAAQABAAA=' data-src="{{view url='images/home/banner-side-2.svg'}}" alt="Esportes" />
            <noscript>
                <img src="{{view url='images/home/banner-side-2.svg'}}" alt="Esportes" />
            </noscript>
            <span class="ayo-home5-hero-card__content">
                <span class="ayo-home5-hero-card__eyebrow">Ofertas especiais</span>
                <strong class="ayo-home5-hero-card__title">Esportes</strong>
                <span class="ayo-home5-hero-card__cta">Explorar agora</span>
            </span>
        </a>
    </div>
</div>
HTML;
    }

    private function homeListAdsContent(): string
    {
        return <<<HTML
<div class="ayo-home5-hero-card-stack">
    <a class="ayo-home5-hero-card ayo-home5-hero-card--primary" href="{{store url='ofertas'}}" title="Coleção Performance">
        <img class="lazy" src='data:image/gif;base64,R0lGODlhAQABAAAAACwAAAAAAQABAAA=' data-src="{{view url='images/side-banner-promo.svg'}}" alt="Coleção Performance" />
        <noscript>
            <img src="{{view url='images/side-banner-promo.svg'}}" alt="Coleção Performance" />
        </noscript>
        <span class="ayo-home5-hero-card__content">
            <span class="ayo-home5-hero-card__eyebrow">{{trans "Coleção exclusiva"}}</span>
            <strong class="ayo-home5-hero-card__title">{{trans "Performance"}}</strong>
            <span class="ayo-home5-hero-card__cta">{{trans "Ver coleção"}}</span>
        </span>
    </a>
    <a class="ayo-home5-hero-card ayo-home5-hero-card--secondary" href="{{store url='formas-pagamento'}}" title="Combos com Desconto">
        <img class="lazy" src='data:image/gif;base64,R0lGODlhAQABAAAAACwAAAAAAQABAAA=' data-src="{{view url='images/side-banner-combos.svg'}}" alt="Combos com Desconto" />
        <noscript>
            <img src="{{view url='images/side-banner-combos.svg'}}" alt="Combos com Desconto" />
        </noscript>
        <span class="ayo-home5-hero-card__content">
            <span class="ayo-home5-hero-card__eyebrow">{{trans "Ofertas especiais"}}</span>
            <strong class="ayo-home5-hero-card__title">{{trans "Combos"}}</strong>
            <span class="ayo-home5-hero-card__cta">{{trans "Economize agora"}}</span>
        </span>
    </a>
</div>
HTML;
    }

    private function homeBannerMidHome5Content(): string
    {
        return <<<HTML
<div class="rowFlex">
<div class="col-xs-12 col-sm-4 col-md-4 col_banner1">
<div class="bs-banner "><a class="banner-hover" href="{{store url="shipping"}}"><img loading="lazy" src="{{media url=wysiwyg/home-banners/banner-envio.jpg}}" alt="Envio Imediato para todo o Brasil"></a></div>
</div>
<div class="col-xs-12 col-sm-4 col-md-4 col_banner2">
<div class="bs-banner "><a class="banner-hover" href="{{store url="formas-pagamento"}}"><img loading="lazy" src="{{media url=wysiwyg/home-banners/banner-pagamento.jpg}}" alt="Pagamento Seguro - Cartões, Pix e Boleto"></a></div>
</div>
<div class="col-xs-12 col-sm-4 col-md-4 col_banner3">
<div class="bs-banner bs-banner-last"><a class="banner-hover" href="{{store url="ofertas.html"}}"><img loading="lazy" src="{{media url=wysiwyg/home-banners/banner-ofertas.jpg}}" alt="Ofertas e Promoções AWA Motos"></a></div>
</div>
</div>
HTML;
    }

    private function homeNotificationHome5Content(): string
    {
        return <<<HTML
<div class="notification-home5-inner">
    <p>Frete grátis para compras acima de R$200 | Retire grátis na loja</p>
    <p>Pedidos sendo enviados normalmente. Confira nossas novidades e promoções exclusivas.</p>
    <p>Em compras acima de R$300 ganhe cupom de 15% de desconto na hora.</p>
</div>
HTML;
    }

    private function homeBenefitsContent(): string
    {
        return <<<HTML
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
                        <div class="boxServiceDesc"><a href="{{store url='formas-pagamento'}}" title="Ver formas de pagamento">Cartões, Pix e boleto</a></div>
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

    private function homeCategory1Content(): string
    {
        return <<<HTML
<div class="ayo-home5-product-grid">
    {{widget type="Rokanthemes\\Categorytab\\Block\\CateWidget"
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

    private function homeCategory2Content(): string
    {
        return <<<HTML
<div class="ayo-home5-product-grid">
    {{widget type="Rokanthemes\\Categorytab\\Block\\CateWidget"
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

    private function homeFeaturedCategoriesContent(): string
    {
        return <<<HTML
<div class="ayo-home5-product-grid">
    {{widget type="Rokanthemes\\Categorytab\\Block\\CateWidget"
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

    private function homeProductThumbContent(): string
    {
        return <<<HTML
<div class="ayo-home5-product-grid">
    {{widget type="Rokanthemes\\Categorytab\\Block\\CateWidget"
        title=""
        color_box="red-box"
        identify="categorytab_thumb"
        category_id="45,67,71,72,73"
        limit_qty="13"
        show_pager="0"
        slide_row="1"
        slide_limit="6"
        template="categorytab/grid-original.phtml"}}
</div>
HTML;
    }

    private function getHomepageContent(): string
    {
        // O layout do tema ayo_home5 já renderiza toda a homepage via
        // top-home.phtml. Mantemos o conteúdo CMS vazio para não reativar
        // blocos estruturais legados em futuras reprovisões.
        return <<<'HTML'
<!-- Homepage renderizada pelo layout do tema: Magento_Cms::top-home.phtml -->
HTML;
    }

    private function homeCategoryNavVisualContent(): string
    {
            return <<<'HTML'
<div class="awa-category-nav">
    <nav class="awa-category-nav__nav" aria-label="Navegação visual por categorias">
        <ul class="awa-category-nav__grid" role="list">
                <li class="awa-category-nav__item">
    					<a class="awa-category-nav__link" href="{{store url='retrovisores.html'}}" aria-label="Ver Retrovisores">
                        <figure class="awa-category-nav__figure">
                            <span class="awa-category-nav__thumb" aria-hidden="true">
                                <img class="awa-category-nav__img" src="{{media url='wysiwyg/home-categorias/cat-retrovisor.jpg'}}" alt="Retrovisores" loading="lazy" decoding="async" width="104" height="104" />
                            </span>
                            <figcaption class="awa-category-nav__label">Retrovisores</figcaption>
                        </figure>
                    </a>
                </li>

                <li class="awa-category-nav__item">
					<a class="awa-category-nav__link" href="{{store url='guidoes.html'}}" aria-label="Ver Guidões">
                        <figure class="awa-category-nav__figure">
                            <span class="awa-category-nav__thumb" aria-hidden="true">
                                <img class="awa-category-nav__img" src="{{media url='wysiwyg/home-categorias/cat-guidao.jpg'}}" alt="Guidões" loading="lazy" decoding="async" width="104" height="104" />
                            </span>
                            <figcaption class="awa-category-nav__label">Guidões</figcaption>
                        </figure>
                    </a>
                </li>

                <li class="awa-category-nav__item">
					<a class="awa-category-nav__link" href="{{store url='piscas.html'}}" aria-label="Ver Piscas">
                        <figure class="awa-category-nav__figure">
                            <span class="awa-category-nav__thumb" aria-hidden="true">
                                <img class="awa-category-nav__img" src="{{media url='wysiwyg/home-categorias/cat-pisca.jpg'}}" alt="Piscas" loading="lazy" decoding="async" width="104" height="104" />
                            </span>
                            <figcaption class="awa-category-nav__label">Piscas</figcaption>
                        </figure>
                    </a>
                </li>

                <li class="awa-category-nav__item awa-category-nav__item--featured">
					<a class="awa-category-nav__link" href="{{store url='manetes.html'}}" aria-label="Ver Manetes">
                        <figure class="awa-category-nav__figure">
                            <span class="awa-category-nav__thumb" aria-hidden="true">
                                <img class="awa-category-nav__img" src="{{media url='wysiwyg/home-categorias/cat-manete.jpg'}}" alt="Manetes" loading="eager" decoding="async" width="104" height="104" fetchpriority="high" />
                            </span>
                            <figcaption class="awa-category-nav__label">
                                <span class="awa-category-nav__title">Manetes</span>
                                <span class="awa-category-nav__badge" aria-hidden="true">alto giro</span>
                                <span class="awa-category-nav__sub awa-category-nav__sub--featured">Recomendado para revenda</span>
                                <span class="awa-category-nav__cta">Ver agora →</span>
                            </figcaption>
                        </figure>
                    </a>
                </li>

                <li class="awa-category-nav__item">
					<a class="awa-category-nav__link" href="{{store url='suportes.html'}}" aria-label="Ver Suportes">
                        <figure class="awa-category-nav__figure">
                            <span class="awa-category-nav__thumb" aria-hidden="true">
                                <img class="awa-category-nav__img" src="{{media url='wysiwyg/home-categorias/cat-suporte.jpg'}}" alt="Suportes" loading="lazy" decoding="async" width="104" height="104" />
                            </span>
                            <figcaption class="awa-category-nav__label">Suportes <span class="awa-category-nav__sub" aria-hidden="true">Placa/Baú</span></figcaption>
                        </figure>
                    </a>
                </li>

                <li class="awa-category-nav__item awa-category-nav__item--promo">
					<a class="awa-category-nav__link" href="{{store url='super-ofertas.html'}}" aria-label="Ver Ofertas">
                        <figure class="awa-category-nav__figure">
                            <span class="awa-category-nav__thumb" aria-hidden="true">
                                <img class="awa-category-nav__img" src="{{media url='wysiwyg/home-categorias/cat-ofertas.jpg'}}" alt="Ofertas" loading="lazy" decoding="async" width="104" height="104" />
                            </span>
                            <figcaption class="awa-category-nav__label">Ofertas <span class="awa-category-nav__badge awa-category-nav__badge--dark" aria-hidden="true">promo</span></figcaption>
                        </figure>
                    </a>
                </li>
        </ul>
    </nav>
</div>
HTML;
    }



    private function ensurePlaceholderBanners(OutputInterface $output): void
    {
        try {
            $mediaDir = rtrim($this->directoryList->getPath(DirectoryList::MEDIA), '/');
            $sliderDir = $mediaDir . '/slidebanner';
            if (!is_dir($sliderDir)) {
                @mkdir($sliderDir, 0755, true);
            }

            for ($i = 1; $i <= 3; $i++) {
                $file = $sliderDir . "/banner{$i}.svg";
                if (!file_exists($file)) {
                    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1600" height="520">'
                        . '<rect width="100%" height="100%" fill="#f2f2f2"/>'
                        . '<text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" font-size="42" fill="#333">'
                        . "Banner {$i} Placeholder"
                        . '</text></svg>';
                    @file_put_contents($file, $svg);
                }
            }

            $output->writeln(' - Placeholders de banners verificados/criados');
        } catch (\Throwable $e) {
            $output->writeln('<error>   ✗ Falha ao criar placeholders de banners: ' . $e->getMessage() . '</error>');
        }
    }

    private function fixedRightContent(): string
    {
        return <<<HTML
<ul class="fixed-right-ul">
    <li class="scroll-top"><em class="fa fa-arrow-up"></em></li>
    <li class="shooping-cart"><a href="{{store url='checkout/cart'}}"><em class="fa fa-shopping-cart"></em></a></li>
    <li class="my-account"><a href="{{store url='customer/account'}}"><em class="fa fa-user"></em></a></li>
</ul>
HTML;
    }

    private function organizationSchemaContent(): string
    {
        // Organization JSON-LD agora é renderizado exclusivamente por:
        // - Homepage: awa-seo-head.phtml (@graph consolidado)
        // - Outras páginas: SchemaOrg/organization.phtml (via layout XML)
        // Este bloco CMS fica vazio para evitar duplicatas.
        return <<<HTML
<!-- organization_schema: renderizado via layout XML (SchemaOrg module) -->
HTML;
    }

    private function trustBadgesHomepageContent(): string
    {
        return <<<HTML
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

    private function homeTestimonialsContent(): string
    {
        return <<<HTML
<!-- home_testimonials: placeholder — ativar quando houver depoimentos reais -->
HTML;
    }

    private function footerTagsContent(): string
    {
        return <<<HTML
<div class="footer-tags"></div>
HTML;
    }

    private function bannerSidebarProductPageContent(): string
    {
        return <<<HTML
<div class="banner-sidebar-product-page"></div>
HTML;
    }

    private function catalogSidebarAdvContent(): string
    {
        return <<<HTML
<div class="catalog-sidebar-adv"></div>
HTML;
    }

    private function verticalMenuAfterContent(): string
    {
        return <<<HTML
<div class="vertical-menu-after"></div>
HTML;
    }

    private function verticalMenuBeforeContent(): string
    {
        return <<<HTML
<div class="vertical-menu-before"></div>
HTML;
    }

    private function seedSlider(\Symfony\Component\Console\Output\OutputInterface $output): void
    {
        $output->writeln(' - Slider seeding skipped (manual restoration required if needed)');
    }
}
