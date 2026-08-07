<?php

declare(strict_types=1);

namespace GrupoAwamotos\StoreSetup\Setup\Patch\Data;

use Magento\Cms\Model\BlockFactory;
use Magento\Cms\Model\PageFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Atualiza páginas e blocos institucionais com conteúdo melhorado.
 *
 * Escopo: about-us, store-locator, privacy-policy-cookie-restriction-mode,
 *         customer-service, faq, returns, warranty, shipping.
 * Também cria blocos reutilizáveis (awa_help_hub, awa_cta_b2b, awa_contact_header).
 *
 * SEO: meta title/description, Schema.org (Organization, FAQPage, WebPage).
 * LGPD: privacidade reescrita com resumo, bases legais, direitos, contato DPO.
 * Acessibilidade: ARIA landmarks, hierarquia H1>H2>H3 correta, links descritivos.
 */
class UpdateInstitutionalPages implements DataPatchInterface
{
    private ModuleDataSetupInterface $moduleDataSetup;
    private PageFactory $pageFactory;
    private BlockFactory $blockFactory;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        PageFactory $pageFactory,
        BlockFactory $blockFactory
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->pageFactory = $pageFactory;
        $this->blockFactory = $blockFactory;
    }

    public function apply(): self
    {
        $this->moduleDataSetup->startSetup();

        $this->updatePages();
        $this->createBlocks();

        $this->moduleDataSetup->endSetup();

        return $this;
    }

    /* ================================================================
       PAGES
       ================================================================ */

    private function updatePages(): void
    {
        foreach ($this->getPageDefinitions() as $data) {
            $page = $this->loadOrCreatePage($data['identifier']);
            $page->setTitle($data['title']);
            $page->setContentHeading('');
            $page->setContent($data['content']);
            $page->setPageLayout($data['page_layout']);
            $page->setMetaTitle($data['meta_title']);
            $page->setMetaDescription($data['meta_description']);
            $page->setStores([0]);
            $page->save();
        }
    }

    private function loadOrCreatePage(string $identifier): \Magento\Cms\Model\Page
    {
        $page = $this->pageFactory->create();
        $page->load($identifier, 'identifier');

        if (!$page->getId()) {
            $page->setData([
                'identifier' => $identifier,
                'stores'     => [0],
                'is_active'  => 1,
            ]);
        }

        return $page;
    }

    private function getPageDefinitions(): array
    {
        return [
            [
                'identifier'       => 'about-us',
                'title'            => 'Sobre a AWA Motos',
                'meta_title'       => 'Sobre a AWA Motos — Peças e Acessórios para Motos',
                'meta_description' => 'Conheça o Grupo Awamotos: fabricante e distribuidor de peças e acessórios para motos em Araraquara-SP. Atacado, varejo e programa B2B.',
                'page_layout'      => '1column',
                'content'          => $this->contentAboutUs(),
            ],
            [
                'identifier'       => 'store-locator',
                'title'            => 'Nossa Loja e Retirada',
                'meta_title'       => 'Nossa Loja — Retirada em Araraquara-SP | AWA Motos',
                'meta_description' => 'Visite a AWA Motos em Araraquara-SP ou retire seu pedido. Endereço, horário de funcionamento, como chegar e regras de retirada.',
                'page_layout'      => '1column',
                'content'          => $this->contentStoreLocator(),
            ],
            [
                'identifier'       => 'privacy-policy-cookie-restriction-mode',
                'title'            => 'Política de Privacidade e Cookies',
                'meta_title'       => 'Política de Privacidade e Cookies | AWA Motos',
                'meta_description' => 'Saiba como o Grupo Awamotos coleta, usa e protege seus dados pessoais. Direitos LGPD, cookies e canal do encarregado de dados (DPO).',
                'page_layout'      => '1column',
                'content'          => $this->contentPrivacyPolicy(),
            ],
            [
                'identifier'       => 'customer-service',
                'title'            => 'Central de Atendimento',
                'meta_title'       => 'Central de Atendimento | AWA Motos',
                'meta_description' => 'Fale com a AWA Motos por telefone, e-mail ou WhatsApp. Horário, canais de contato e atalhos para rastreio, trocas, garantia e B2B.',
                'page_layout'      => '1column',
                'content'          => $this->contentCustomerService(),
            ],
            [
                'identifier'       => 'faq',
                'title'            => 'Perguntas Frequentes (FAQ)',
                'meta_title'       => 'Perguntas Frequentes (FAQ) | AWA Motos',
                'meta_description' => 'Respostas sobre pedidos, pagamento, entrega, trocas, garantia e atacado na AWA Motos. Tire suas dúvidas rapidamente.',
                'page_layout'      => '1column',
                'content'          => $this->contentFaq(),
            ],
            [
                'identifier'       => 'returns',
                'title'            => 'Trocas e Devoluções',
                'meta_title'       => 'Trocas e Devoluções | AWA Motos',
                'meta_description' => 'Política de trocas e devoluções da AWA Motos: prazos, condições, passo a passo e exceções. Compre com tranquilidade.',
                'page_layout'      => '1column',
                'content'          => $this->contentReturns(),
            ],
            [
                'identifier'       => 'warranty',
                'title'            => 'Garantia de Produtos',
                'meta_title'       => 'Garantia de Produtos | AWA Motos',
                'meta_description' => 'Saiba como funciona a garantia dos produtos da AWA Motos: prazos, cobertura, como acionar e condições que se aplicam.',
                'page_layout'      => '1column',
                'content'          => $this->contentWarranty(),
            ],
            [
                'identifier'       => 'shipping',
                'title'            => 'Frete e Entrega',
                'meta_title'       => 'Frete e Entrega | AWA Motos',
                'meta_description' => 'Modalidades de frete, prazos por região, frete grátis e condições especiais para B2B. Entrega para todo o Brasil.',
                'page_layout'      => '1column',
                'content'          => $this->contentShipping(),
            ],
        ];
    }

    /* ================================================================
       BLOCKS
       ================================================================ */

    private function createBlocks(): void
    {
        foreach ($this->getBlockDefinitions() as $data) {
            $block = $this->blockFactory->create();
            $block->load($data['identifier'], 'identifier');

            if (!$block->getId()) {
                $block->setData([
                    'identifier' => $data['identifier'],
                    'stores'     => [0],
                    'is_active'  => 1,
                ]);
            }

            $block->setTitle($data['title']);
            $block->setContent($data['content']);
            $block->setIsActive(1);
            $block->setStores([0]);
            $block->save();
        }
    }

    private function getBlockDefinitions(): array
    {
        return [
            [
                'identifier' => 'awa_help_hub',
                'title'      => 'Central de Ajuda — Links Rápidos',
                'content'    => $this->blockHelpHub(),
            ],
            [
                'identifier' => 'awa_cta_b2b',
                'title'      => 'CTA B2B — Cadastro Atacado',
                'content'    => $this->blockCtaB2b(),
            ],
            [
                'identifier' => 'awa_contact_header',
                'title'      => 'Cabeçalho da Página de Contato',
                'content'    => $this->blockContactHeader(),
            ],
        ];
    }

    /* ================================================================
       CONTEÚDO: ABOUT US
       ================================================================ */

    private function contentAboutUs(): string
    {
        return <<<'HTML'
<div class="awa-inst" role="main" aria-label="Sobre a AWA Motos">
  <h1>Sobre a AWA Motos</h1>

  <div class="awa-summary">
    <p><strong>Peças e acessórios para motos com qualidade de fábrica.</strong> O Grupo Awamotos é fabricante e distribuidor de peças, acessórios e equipamentos para motocicletas, atendendo lojas, oficinas, distribuidores e motociclistas de todo o Brasil.</p>
  </div>

  <h2>Quem somos</h2>
  <p>Com sede em Araraquara–SP, o <strong>Grupo Awamotos</strong> atua na fabricação e distribuição de peças e acessórios para motos. Nosso catálogo reúne milhares de itens — de componentes de motor e suspensão a acessórios, capacetes e vestuário — com foco em qualidade, preço justo e entrega rápida.</p>
  <p>Atendemos tanto o <strong>consumidor final (B2C)</strong> quanto <strong>empresas (B2B)</strong>: revendedores, oficinas mecânicas, distribuidores regionais e frotas corporativas.</p>

  <h2>Nossos diferenciais</h2>
  <div class="awa-grid">
    <div class="awa-card">
      <h3>Estoque próprio</h3>
      <p>Centro de distribuição em Araraquara–SP com milhares de itens prontos para envio, garantindo agilidade na entrega.</p>
    </div>
    <div class="awa-card">
      <h3>Atendimento especializado</h3>
      <p>Equipe técnica que entende de motos e ajuda você a encontrar a peça certa para o seu modelo e ano.</p>
    </div>
    <div class="awa-card">
      <h3>Preço de fábrica</h3>
      <p>Como fabricantes e distribuidores, oferecemos preços competitivos direto da fonte, sem intermediários desnecessários.</p>
    </div>
    <div class="awa-card">
      <h3>Programa B2B</h3>
      <p>Condições exclusivas para empresas: descontos de até 20%, crédito, frete diferenciado e gerente de conta dedicado.</p>
    </div>
  </div>

  <h2>Números que importam</h2>
  <ul>
    <li>Milhares de produtos em estoque permanente</li>
    <li>Entregas para todos os estados brasileiros</li>
    <li>Centenas de clientes B2B ativos (revendedores, oficinas, distribuidores)</li>
    <li>Equipe especializada em peças e acessórios para motos</li>
  </ul>

  <h2>Onde estamos</h2>
  <ul>
    <li><strong>Endereço:</strong> {{config path="general/store_information/street_line1"}}, {{config path="general/store_information/street_line2"}} — {{config path="general/store_information/city"}}/SP, CEP {{config path="general/store_information/postcode"}}</li>
    <li><strong>CNPJ:</strong> {{config path="general/store_information/merchant_vat_number"}}</li>
    <li><strong>Telefone:</strong> {{config path="general/store_information/phone"}}</li>
    <li><strong>WhatsApp:</strong> <a href="https://wa.me/5516997367588" target="_blank" rel="noopener">(16) 99736-7588</a></li>
    <li><strong>Horário:</strong> {{config path="general/store_information/hours"}}</li>
  </ul>

  <h2>Compre para sua empresa</h2>
  <div class="awa-cta">
    <h3>Programa B2B — Atacado e Revenda</h3>
    <p>Descontos progressivos, frete CIF, boleto a prazo e atendimento prioritário para empresas com CNPJ ativo.</p>
    <a href="{{store url='atacado/condicoes'}}" class="awa-btn">Conheça as condições</a>
  </div>

  <h2>Links úteis</h2>
  <div class="awa-hub" role="navigation" aria-label="Páginas relacionadas">
    <a href="{{store url='customer-service'}}">Central de Atendimento</a>
    <a href="{{store url='faq'}}">Perguntas Frequentes</a>
    <a href="{{store url='shipping'}}">Frete e Entrega</a>
    <a href="{{store url='returns'}}">Trocas e Devoluções</a>
    <a href="{{store url='warranty'}}">Garantia</a>
    <a href="{{store url='contact'}}">Fale Conosco</a>
  </div>
</div>

<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Organization",
  "name": "Grupo Awamotos",
  "alternateName": "AWA Motos",
  "url": "{{store url=''}}",
  "description": "Fabricante e distribuidor de peças, acessórios e equipamentos para motocicletas.",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "R. Lavineo de Arruda Falcão, 1272, Jardim Cruzeiro do Sul",
    "addressLocality": "Araraquara",
    "addressRegion": "SP",
    "postalCode": "14808-390",
    "addressCountry": "BR"
  },
  "contactPoint": [
    {
      "@type": "ContactPoint",
      "telephone": "+55-16-3301-1890",
      "contactType": "customer service",
      "availableLanguage": "Portuguese",
      "hoursAvailable": {
        "@type": "OpeningHoursSpecification",
        "dayOfWeek": ["Monday","Tuesday","Wednesday","Thursday","Friday"],
        "opens": "09:00",
        "closes": "17:00"
      }
    },
    {
      "@type": "ContactPoint",
      "telephone": "+55-16-99736-7588",
      "contactType": "sales",
      "availableLanguage": "Portuguese"
    }
  ],
  "sameAs": [
    "https://www.instagram.com/awamotos",
    "https://www.facebook.com/awamotos",
    "https://www.youtube.com/@awamotos"
  ],
  "taxID": "51.274.901/0001-02"
}
</script>
HTML;
    }

    /* ================================================================
       CONTEÚDO: STORE LOCATOR
       ================================================================ */

    private function contentStoreLocator(): string
    {
        return <<<'HTML'
<div class="awa-inst" role="main" aria-label="Nossa loja e retirada">
  <h1>Nossa Loja e Retirada</h1>

  <div class="awa-summary">
    <p>Você pode visitar nossa loja física em Araraquara–SP para conhecer os produtos ou retirar pedidos feitos pelo site. Confira abaixo o endereço, horário e as regras de retirada.</p>
  </div>

  <h2>Endereço e contato</h2>
  <div class="awa-grid">
    <div class="awa-card">
      <h3>Localização</h3>
      <p>{{config path="general/store_information/street_line1"}}<br>
      {{config path="general/store_information/street_line2"}}<br>
      {{config path="general/store_information/city"}}/SP — CEP {{config path="general/store_information/postcode"}}</p>
    </div>
    <div class="awa-card">
      <h3>Horário de funcionamento</h3>
      <p>{{config path="general/store_information/hours"}}</p>
      <p><small>Exceto feriados nacionais e regionais.</small></p>
    </div>
    <div class="awa-card">
      <h3>Canais de contato</h3>
      <ul>
        <li><strong>Telefone:</strong> {{config path="general/store_information/phone"}}</li>
        <li><strong>WhatsApp:</strong> <a href="https://wa.me/5516997367588" target="_blank" rel="noopener">(16) 99736-7588</a></li>
        <li><strong>E-mail:</strong> <a href="mailto:awamotos@awamotos.com.br">awamotos@awamotos.com.br</a></li>
      </ul>
    </div>
  </div>

  <h2>Retirada na loja</h2>
  <p>Ao finalizar seu pedido online, você pode escolher a opção <strong>Retirada na Loja</strong> (quando disponível no checkout). Veja como funciona:</p>
  <ol class="awa-steps">
    <li>Faça seu pedido no site e escolha <strong>"Retirar na loja"</strong> como método de envio.</li>
    <li>Aguarde o e-mail de confirmação informando que o pedido está <strong>pronto para retirada</strong>.</li>
    <li>Compareça à loja dentro do horário de funcionamento com um <strong>documento com foto</strong> e o <strong>número do pedido</strong>.</li>
    <li>Se outra pessoa for retirar, ela deve apresentar uma <strong>autorização assinada</strong> e documento próprio.</li>
  </ol>

  <div class="awa-info">
    <p><strong>Prazo de retirada:</strong> o pedido fica disponível para retirada por até <strong>7 dias corridos</strong> após a confirmação. Após esse prazo, entre em contato com nosso <a href="{{store url='customer-service'}}">Atendimento</a>.</p>
  </div>

  <h2>Perguntas rápidas</h2>
  <div class="awa-faq-category">
    <div class="awa-faq-item">
      <h3>Posso comprar diretamente na loja?</h3>
      <p>Sim, atendemos presencialmente. O estoque da loja e do site pode variar; entre em contato antes para confirmar a disponibilidade do item desejado.</p>
    </div>
    <div class="awa-faq-item">
      <h3>A retirada tem custo?</h3>
      <p>Não. A retirada na loja é <strong>gratuita</strong>.</p>
    </div>
    <div class="awa-faq-item">
      <h3>Posso trocar um produto na loja?</h3>
      <p>Sim, desde que dentro do prazo e condições da nossa <a href="{{store url='returns'}}">Política de Trocas e Devoluções</a>.</p>
    </div>
  </div>

  <div class="awa-cta">
    <h3>Dúvidas ou precisa de ajuda?</h3>
    <p>Fale com a gente pelo WhatsApp ou visite a Central de Atendimento.</p>
    <a href="https://wa.me/5516997367588" class="awa-btn" target="_blank" rel="noopener">WhatsApp (16) 99736-7588</a>
  </div>

  <h2>Páginas relacionadas</h2>
  <div class="awa-hub" role="navigation" aria-label="Páginas relacionadas">
    <a href="{{store url='shipping'}}">Frete e Entrega</a>
    <a href="{{store url='customer-service'}}">Atendimento</a>
    <a href="{{store url='contact'}}">Fale Conosco</a>
  </div>
</div>

<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Store",
  "name": "AWA Motos — Loja Araraquara",
  "image": "https://awamotos.com/pub/media/logo/stores/1/logo_161x92_1.png",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "R. Lavineo de Arruda Falcão, 1272, Jardim Cruzeiro do Sul",
    "addressLocality": "Araraquara",
    "addressRegion": "SP",
    "postalCode": "14808-390",
    "addressCountry": "BR"
  },
  "telephone": "+55-16-3301-1890",
  "openingHoursSpecification": {
    "@type": "OpeningHoursSpecification",
    "dayOfWeek": ["Monday","Tuesday","Wednesday","Thursday","Friday"],
    "opens": "09:00",
    "closes": "17:00"
  },
  "url": "{{store url='store-locator'}}"
}
</script>
HTML;
    }

    /* ================================================================
       CONTEÚDO: POLÍTICA DE PRIVACIDADE E COOKIES
       ================================================================ */

    private function contentPrivacyPolicy(): string
    {
        return <<<'HTML'
<div class="awa-inst" role="main" aria-label="Política de Privacidade e Cookies">
  <h1>Política de Privacidade e Cookies</h1>

  <div class="awa-summary">
    <p><strong>Resumo:</strong> Coletamos seus dados cadastrais e de navegação para processar pedidos, melhorar sua experiência e cumprir obrigações legais. Você tem direito de acessar, corrigir e excluir seus dados a qualquer momento. Nosso canal de privacidade é <a href="mailto:awamotos@awamotos.com.br">awamotos@awamotos.com.br</a>.</p>
    <ul>
      <li><strong>O que coletamos:</strong> nome, CPF/CNPJ, e-mail, telefone, endereço, dados de navegação (cookies, IP) e histórico de compras.</li>
      <li><strong>Por quê:</strong> processar pedidos, emitir notas fiscais, entregar produtos, prestar suporte e melhorar o site.</li>
      <li><strong>Base legal:</strong> execução de contrato, obrigação legal, legítimo interesse e consentimento (Art. 7º LGPD).</li>
      <li><strong>Seus direitos:</strong> acesso, correção, exclusão, portabilidade, revogação do consentimento.</li>
      <li><strong>Contato:</strong> <a href="mailto:awamotos@awamotos.com.br">awamotos@awamotos.com.br</a> (prazo de até 15 dias úteis).</li>
    </ul>
  </div>

  <h2>1. Quem somos</h2>
  <p>Esta política é de responsabilidade do <strong>Grupo Awamotos</strong> (AWA Motos), inscrito no CNPJ {{config path="general/store_information/merchant_vat_number"}}, com sede em {{config path="general/store_information/street_line1"}}, {{config path="general/store_information/street_line2"}} — {{config path="general/store_information/city"}}/SP, CEP {{config path="general/store_information/postcode"}}.</p>

  <h2>2. Dados pessoais que coletamos</h2>
  <table class="awa-table">
    <thead>
      <tr>
        <th>Categoria</th>
        <th>Exemplos</th>
        <th>Finalidade</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>Cadastrais</td>
        <td>Nome, CPF/CNPJ, e-mail, telefone, endereço</td>
        <td>Processar pedidos, emitir NF-e, entregar produtos</td>
      </tr>
      <tr>
        <td>Navegação</td>
        <td>Endereço IP, cookies, páginas visitadas, dispositivo</td>
        <td>Melhorar usabilidade, segurança e análise de desempenho</td>
      </tr>
      <tr>
        <td>Compras</td>
        <td>Histórico de pedidos, formas de pagamento utilizadas</td>
        <td>Atendimento pós-venda, garantia, obrigações fiscais</td>
      </tr>
      <tr>
        <td>Empresariais (B2B)</td>
        <td>Razão social, inscrição estadual, contrato social</td>
        <td>Análise de crédito, condições de atacado</td>
      </tr>
    </tbody>
  </table>
  <p>Não coletamos dados sensíveis (origem racial, opinião política, saúde, etc.) salvo quando estritamente necessário e com base legal adequada.</p>

  <h2>3. Bases legais (Art. 7º da LGPD)</h2>
  <ul>
    <li><strong>Execução de contrato:</strong> para processar e entregar seus pedidos.</li>
    <li><strong>Obrigação legal:</strong> para cumprir legislação fiscal e tributária (emissão de NF-e, retenção de dados por 5 anos).</li>
    <li><strong>Legítimo interesse:</strong> para segurança do site, prevenção de fraudes e melhoria dos serviços.</li>
    <li><strong>Consentimento:</strong> para envio de comunicações de marketing e uso de cookies não essenciais. Você pode revogar a qualquer momento.</li>
  </ul>

  <h2>4. Compartilhamento de dados</h2>
  <p>Seus dados podem ser compartilhados com:</p>
  <ul>
    <li><strong>Gateways de pagamento:</strong> para processamento de transações (ex.: PagSeguro, MercadoPago, Pix).</li>
    <li><strong>Transportadoras:</strong> para entrega dos pedidos (Correios, transportadoras parceiras).</li>
    <li><strong>Plataformas de e-mail:</strong> para envio de confirmações e comunicações transacionais.</li>
    <li><strong>Autoridades fiscais:</strong> para cumprimento de obrigações legais (Receita Federal, SEFAZ).</li>
  </ul>
  <p>Não vendemos, alugamos ou compartilhamos seus dados com terceiros para fins de marketing sem seu consentimento expresso.</p>

  <h2>5. Cookies</h2>
  <p>Utilizamos cookies — pequenos arquivos de texto armazenados no seu navegador — para:</p>
  <table class="awa-table">
    <thead>
      <tr>
        <th>Tipo</th>
        <th>Finalidade</th>
        <th>Duração</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>Essenciais</td>
        <td>Carrinho de compras, sessão de login, segurança (CSRF)</td>
        <td>Sessão / até 24h</td>
      </tr>
      <tr>
        <td>Funcionais</td>
        <td>Salvar preferências (idioma, moeda, itens vistos recentemente)</td>
        <td>Até 1 ano</td>
      </tr>
      <tr>
        <td>Analíticos</td>
        <td>Entender como o site é utilizado (páginas mais acessadas, origem do tráfego)</td>
        <td>Até 2 anos</td>
      </tr>
    </tbody>
  </table>
  <p><strong>Como gerenciar cookies:</strong> Você pode bloquear ou excluir cookies nas configurações do seu navegador. Observe que desabilitar cookies essenciais pode impedir o funcionamento correto do site (ex.: carrinho de compras).</p>

  <h2>6. Retenção de dados</h2>
  <ul>
    <li><strong>Dados de compra e fiscais:</strong> mantidos por no mínimo 5 anos (obrigação tributária).</li>
    <li><strong>Dados de conta:</strong> enquanto a conta estiver ativa ou conforme necessário para prestação de serviços.</li>
    <li><strong>Dados de navegação:</strong> até 6 meses (logs de acesso, conforme o Marco Civil da Internet).</li>
    <li>Após esses prazos, os dados são eliminados ou anonimizados.</li>
  </ul>

  <h2>7. Segurança</h2>
  <p>Adotamos medidas técnicas e organizacionais para proteger seus dados, incluindo:</p>
  <ul>
    <li>Conexão criptografada (HTTPS/TLS) em todo o site.</li>
    <li>Armazenamento seguro de senhas (hash com salt).</li>
    <li>Controle de acesso restrito aos dados pessoais.</li>
    <li>Monitoramento de atividades suspeitas.</li>
  </ul>
  <p>Nenhum sistema é 100% inviolável. Em caso de incidente de segurança que possa gerar risco ou dano relevante, notificaremos os titulares e a ANPD conforme determina a LGPD.</p>

  <h2>8. Seus direitos (LGPD)</h2>
  <div class="awa-rights">
    <div class="awa-right">
      <h4>Acesso e confirmação</h4>
      <p>Você pode confirmar se tratamos seus dados e solicitar uma cópia das informações que temos sobre você.</p>
    </div>
    <div class="awa-right">
      <h4>Correção</h4>
      <p>Solicite a correção de dados incompletos, inexatos ou desatualizados.</p>
    </div>
    <div class="awa-right">
      <h4>Exclusão</h4>
      <p>Peça a exclusão dos dados tratados com base no seu consentimento (exceto quando houver obrigação legal de retenção).</p>
    </div>
    <div class="awa-right">
      <h4>Portabilidade</h4>
      <p>Solicite a transferência dos seus dados pessoais a outro fornecedor, em formato estruturado.</p>
    </div>
    <div class="awa-right">
      <h4>Revogação do consentimento</h4>
      <p>Cancele a qualquer momento o consentimento dado para tratamento de dados (ex.: marketing).</p>
    </div>
    <div class="awa-right">
      <h4>Oposição</h4>
      <p>Oponha-se ao tratamento de dados quando realizado sem base legal adequada.</p>
    </div>
  </div>

  <h2>9. Como exercer seus direitos</h2>
  <p>Para exercer qualquer um dos direitos acima:</p>
  <ul>
    <li><strong>E-mail do Encarregado (DPO):</strong> <a href="mailto:awamotos@awamotos.com.br">awamotos@awamotos.com.br</a></li>
    <li><strong>Formulário de contato:</strong> <a href="{{store url='contact'}}">Fale Conosco</a> (selecione o assunto "Privacidade / LGPD")</li>
    <li><strong>Prazo de resposta:</strong> até 15 dias úteis a partir da confirmação da sua identidade.</li>
  </ul>
  <p>Caso não fique satisfeito com a resposta, você pode apresentar reclamação à <strong>Autoridade Nacional de Proteção de Dados (ANPD)</strong>.</p>

  <h2>10. Alterações nesta política</h2>
  <p>Esta política pode ser atualizada periodicamente. A versão vigente estará sempre publicada nesta página com a data da última atualização. Alterações significativas serão comunicadas por e-mail ou aviso no site.</p>
  <p><strong>Última atualização:</strong> Fevereiro de 2026.</p>

  <h2>Páginas relacionadas</h2>
  <div class="awa-hub" role="navigation" aria-label="Páginas relacionadas">
    <a href="{{store url='lgpd'}}">Seus Direitos — LGPD</a>
    <a href="{{store url='terms'}}">Termos de Uso</a>
    <a href="{{store url='contact'}}">Fale Conosco</a>
  </div>
</div>

<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "WebPage",
  "name": "Política de Privacidade e Cookies",
  "description": "Política de Privacidade e uso de Cookies do Grupo Awamotos (AWA Motos).",
  "url": "{{store url='privacy-policy-cookie-restriction-mode'}}",
  "dateModified": "2026-02-01",
  "inLanguage": "pt-BR",
  "isPartOf": {
    "@type": "WebSite",
    "name": "AWA Motos",
    "url": "{{store url=''}}"
  }
}
</script>
HTML;
    }

    /* ================================================================
       CONTEÚDO: CENTRAL DE ATENDIMENTO
       ================================================================ */

    private function contentCustomerService(): string
    {
        return <<<'HTML'
<div class="awa-inst" role="main" aria-label="Central de Atendimento">
  <h1>Central de Atendimento</h1>

  <div class="awa-summary">
    <p>Estamos aqui para ajudar você antes, durante e depois da compra. Escolha o canal de sua preferência ou use os atalhos abaixo para resolver rapidamente.</p>
  </div>

  <h2>Canais de contato</h2>
  <div class="awa-grid">
    <div class="awa-card">
      <h3>Telefone</h3>
      <p><strong>{{config path="general/store_information/phone"}}</strong></p>
      <p>{{config path="general/store_information/hours"}}</p>
    </div>
    <div class="awa-card">
      <h3>WhatsApp</h3>
      <p><a href="https://wa.me/5516997367588" target="_blank" rel="noopener"><strong>(16) 99736-7588</strong></a></p>
      <p>Atendimento em horário comercial.</p>
    </div>
    <div class="awa-card">
      <h3>E-mail</h3>
      <p><a href="mailto:awamotos@awamotos.com.br"><strong>awamotos@awamotos.com.br</strong></a></p>
      <p>Respondemos em até 1 dia útil.</p>
    </div>
    <div class="awa-card">
      <h3>Formulário</h3>
      <p><a href="{{store url='contact'}}"><strong>Fale Conosco</strong></a></p>
      <p>Preencha o formulário e retornaremos por e-mail.</p>
    </div>
  </div>

  <h2>Resolva rapidamente</h2>
  <div class="awa-hub" role="navigation" aria-label="Atalhos de atendimento">
    <a href="{{store url='sales/guest/form'}}">Rastrear pedido</a>
    <a href="{{store url='returns'}}">Trocas e devoluções</a>
    <a href="{{store url='warranty'}}">Garantia</a>
    <a href="{{store url='shipping'}}">Frete e entrega</a>
    <a href="{{store url='faq'}}">Perguntas frequentes</a>
    <a href="{{store url='atacado/condicoes'}}">Atacado / B2B</a>
  </div>

  <h2>Clientes B2B / Atacado</h2>
  <div class="awa-b2b">
    <p>Se você é <strong>revendedor, oficina ou distribuidor</strong>, temos um canal dedicado:</p>
    <ul>
      <li><strong>E-mail B2B:</strong> <a href="mailto:awamotos@awamotos.com.br">awamotos@awamotos.com.br</a></li>
      <li><strong>WhatsApp Comercial:</strong> <a href="https://wa.me/5516997367588" target="_blank" rel="noopener">(16) 99736-7588</a></li>
      <li><strong>Cotação personalizada:</strong> <a href="{{store url='b2b/quote/index'}}">Solicitar cotação</a></li>
    </ul>
    <p><a href="{{store url='atacado/condicoes'}}">Conheça as condições de atacado</a></p>
  </div>

  <h2>Horário de atendimento</h2>
  <table class="awa-table">
    <thead>
      <tr><th>Canal</th><th>Dias</th><th>Horário</th></tr>
    </thead>
    <tbody>
      <tr><td>Telefone</td><td>Segunda a sexta</td><td>2ª a 5ª: 8h às 18h; sexta: 8h às 17h</td></tr>
      <tr><td>WhatsApp</td><td>Segunda a sexta</td><td>2ª a 5ª: 8h às 18h; sexta: 8h às 17h</td></tr>
      <tr><td>E-mail / Formulário</td><td>24 horas</td><td>Resposta em até 1 dia útil</td></tr>
    </tbody>
  </table>

  <div class="awa-cta">
    <h3>Não encontrou o que procurava?</h3>
    <p>Envie sua dúvida pelo nosso formulário e responderemos o mais rápido possível.</p>
    <a href="{{store url='contact'}}" class="awa-btn">Fale Conosco</a>
  </div>
</div>

<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "ContactPage",
  "name": "Central de Atendimento — AWA Motos",
  "url": "{{store url='customer-service'}}",
  "mainEntity": {
    "@type": "Organization",
    "name": "Grupo Awamotos",
    "telephone": "+55-16-3301-1890",
    "email": "awamotos@awamotos.com.br",
    "contactPoint": {
      "@type": "ContactPoint",
      "telephone": "+55-16-3301-1890",
      "contactType": "customer service",
      "availableLanguage": "Portuguese"
    }
  }
}
</script>
HTML;
    }

    /* ================================================================
       CONTEÚDO: FAQ
       ================================================================ */

    private function contentFaq(): string
    {
        // Dados para Schema.org FAQPage (mesmo conteúdo das perguntas abaixo)
        $faqSchema = json_encode([
            '@context' => 'https://schema.org',
            '@type'    => 'FAQPage',
            'mainEntity' => [
                $this->faqItem('Como acompanho meu pedido?', 'Acesse "Minha Conta" e vá em "Meus Pedidos". Se comprou como visitante, use a página Rastrear Pedido informando e-mail e número do pedido.'),
                $this->faqItem('Posso alterar ou cancelar um pedido depois de confirmado?', 'Sim, desde que o pedido ainda não tenha sido faturado ou despachado. Entre em contato pelo WhatsApp (16) 99736-7588 ou e-mail awamotos@awamotos.com.br o mais rápido possível.'),
                $this->faqItem('Quais formas de pagamento são aceitas?', 'Aceitamos Pix, boleto bancário e pagamento à vista. Condições a prazo dependem de análise financeira — consulte um de nossos atendentes.'),
                $this->faqItem('O pagamento por Pix é seguro?', 'Sim. O Pix é regulamentado pelo Banco Central e nosso QR Code é gerado pelo gateway de pagamento com chave vinculada ao CNPJ da empresa.'),
                $this->faqItem('Qual o prazo de entrega?', 'Depende da região e da modalidade escolhida. PAC: 5 a 15 dias úteis. SEDEX: 1 a 5 dias úteis. Retirada na loja: após confirmação do pagamento. Consulte a página Frete e Entrega para detalhes.'),
                $this->faqItem('Vocês entregam em todo o Brasil?', 'Sim, enviamos para todos os estados brasileiros pelos Correios e por transportadoras parceiras.'),
                $this->faqItem('Como funciona a troca ou devolução?', 'Você tem até 7 dias corridos após o recebimento para desistir da compra (direito de arrependimento). Em caso de defeito, o prazo é de 30 dias. Consulte nossa página de Trocas e Devoluções para o passo a passo.'),
                $this->faqItem('Quem paga o frete da devolução?', 'Em caso de arrependimento, o frete de retorno é por conta do comprador. Em caso de defeito ou erro no envio, o Grupo Awamotos arca com o frete.'),
                $this->faqItem('Qual a garantia dos produtos?', 'Todos os produtos têm garantia legal de 30 dias (CDC). Alguns itens possuem garantia estendida do fabricante. Consulte a página de Garantia para saber como acionar.'),
                $this->faqItem('Como faço para comprar no atacado?', 'É necessário ter CNPJ ativo e se cadastrar no programa B2B. Após aprovação (até 24h úteis), você acessa preços diferenciados, condições de pagamento especiais e frete CIF. Saiba mais em Condições para Atacado.'),
                $this->faqItem('Qual o pedido mínimo para atacado?', 'Primeiro pedido: R$ 1.500,00. Frete grátis em pedidos acima de R$ 1.500,00.'),
                $this->faqItem('Como escolho a peça certa para minha moto?', 'Use o campo de busca informando o modelo e ano da moto, ou o código da peça. Se tiver dúvida, entre em contato pelo WhatsApp (16) 99736-7588 com o modelo, ano e a peça que procura.'),
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return <<<HTML
<div class="awa-inst" role="main" aria-label="Perguntas Frequentes">
  <h1>Perguntas Frequentes (FAQ)</h1>

  <div class="awa-summary">
    <p>Reunimos as dúvidas mais comuns dos nossos clientes. Se não encontrar sua resposta aqui, fale com a gente pela <a href="{{store url='customer-service'}}">Central de Atendimento</a> ou <a href="https://wa.me/5516997367588" target="_blank" rel="noopener">WhatsApp (16) 99736-7588</a>.</p>
  </div>

  <!-- PEDIDOS -->
  <h2>Pedidos</h2>
  <div class="awa-faq-category">
    <div class="awa-faq-item">
      <h3>Como acompanho meu pedido?</h3>
      <p>Acesse <a href="{{store url='customer/account'}}">"Minha Conta"</a> e vá em "Meus Pedidos". Se comprou como visitante, use a página <a href="{{store url='sales/guest/form'}}">Rastrear Pedido</a> informando e-mail e número do pedido.</p>
    </div>
    <div class="awa-faq-item">
      <h3>Posso alterar ou cancelar um pedido depois de confirmado?</h3>
      <p>Sim, desde que o pedido ainda não tenha sido faturado ou despachado. Entre em contato pelo <a href="https://wa.me/5516997367588" target="_blank" rel="noopener">WhatsApp (16) 99736-7588</a> ou e-mail <a href="mailto:awamotos@awamotos.com.br">awamotos@awamotos.com.br</a> o mais rápido possível.</p>
    </div>
    <div class="awa-faq-item">
      <h3>Como escolho a peça certa para minha moto?</h3>
      <p>Use o campo de busca informando o modelo e ano da moto, ou o código da peça. Se tiver dúvida, fale conosco pelo <a href="https://wa.me/5516997367588" target="_blank" rel="noopener">WhatsApp</a> com o modelo, ano e a peça que procura — nossa equipe técnica ajuda a encontrar.</p>
    </div>
  </div>

  <!-- PAGAMENTO -->
  <h2>Pagamento</h2>
  <div class="awa-faq-category">
    <div class="awa-faq-item">
      <h3>Quais formas de pagamento são aceitas?</h3>
      <p>Aceitamos <strong>Pix</strong>, <strong>boleto bancário</strong> e <strong>pagamento à vista</strong>. Condições a <strong>prazo</strong> dependem de análise financeira — consulte um de nossos atendentes.</p>
    </div>
    <div class="awa-faq-item">
      <h3>O pagamento por Pix é seguro?</h3>
      <p>Sim. O Pix é regulamentado pelo Banco Central e nosso QR Code é gerado pelo gateway de pagamento com chave vinculada ao CNPJ da empresa.</p>
    </div>
    <div class="awa-faq-item">
      <h3>O boleto vence em quantos dias?</h3>
      <p>O boleto tem vencimento em 3 dias úteis. Após o vencimento, o pedido é cancelado automaticamente. Se precisar de um novo boleto, faça um novo pedido.</p>
    </div>
  </div>

  <!-- ENTREGA -->
  <h2>Entrega</h2>
  <div class="awa-faq-category">
    <div class="awa-faq-item">
      <h3>Qual o prazo de entrega?</h3>
      <p>Depende da região e da modalidade escolhida. <strong>PAC:</strong> 5 a 15 dias úteis. <strong>SEDEX:</strong> 1 a 5 dias úteis. <strong>Retirada na loja:</strong> após confirmação do pagamento. Consulte a página <a href="{{store url='shipping'}}">Frete e Entrega</a> para prazos detalhados por região.</p>
    </div>
    <div class="awa-faq-item">
      <h3>Vocês entregam em todo o Brasil?</h3>
      <p>Sim, enviamos para todos os estados brasileiros pelos Correios e por transportadoras parceiras.</p>
    </div>
    <div class="awa-faq-item">
      <h3>Como consigo frete grátis?</h3>
      <p>Frete grátis para Sul e Sudeste em compras acima de R$ 299. Para as demais regiões, acima de R$ 499. Clientes B2B têm frete grátis acima de R$ 1.500. Veja todos os detalhes em <a href="{{store url='shipping'}}">Frete e Entrega</a>.</p>
    </div>
  </div>

  <!-- TROCAS E DEVOLUÇÕES -->
  <h2>Trocas e Devoluções</h2>
  <div class="awa-faq-category">
    <div class="awa-faq-item">
      <h3>Como funciona a troca ou devolução?</h3>
      <p>Você tem até <strong>7 dias corridos</strong> após o recebimento para desistir da compra (direito de arrependimento). Em caso de defeito, o prazo é de <strong>30 dias</strong>. Consulte a <a href="{{store url='returns'}}">Política de Trocas e Devoluções</a> para o passo a passo.</p>
    </div>
    <div class="awa-faq-item">
      <h3>Quem paga o frete da devolução?</h3>
      <p>Em caso de <strong>arrependimento</strong>, o frete de retorno é por conta do comprador. Em caso de <strong>defeito ou erro no envio</strong>, o Grupo Awamotos arca com o frete.</p>
    </div>
  </div>

  <!-- GARANTIA -->
  <h2>Garantia</h2>
  <div class="awa-faq-category">
    <div class="awa-faq-item">
      <h3>Qual a garantia dos produtos?</h3>
      <p>Todos os produtos têm <strong>garantia legal de 30 dias</strong> conforme o Código de Defesa do Consumidor. Alguns itens possuem garantia estendida do fabricante. Consulte a <a href="{{store url='warranty'}}">página de Garantia</a> para saber como acionar.</p>
    </div>
    <div class="awa-faq-item">
      <h3>Preciso guardar a nota fiscal?</h3>
      <p>Sim. A nota fiscal é o comprovante de compra e é necessária para acionar a garantia. Você recebe a NF-e por e-mail e pode consultá-la em <a href="{{store url='customer/account'}}">"Minha Conta"</a>.</p>
    </div>
  </div>

  <!-- B2B / ATACADO -->
  <h2>Atacado / B2B</h2>
  <div class="awa-faq-category">
    <div class="awa-faq-item">
      <h3>Como faço para comprar no atacado?</h3>
      <p>É necessário ter CNPJ ativo e <a href="{{store url='b2b/register'}}">se cadastrar no programa B2B</a>. Após aprovação (até 24h úteis), você acessa preços diferenciados, condições de pagamento especiais e frete CIF. Saiba mais em <a href="{{store url='atacado/condicoes'}}">Condições para Atacado</a>.</p>
    </div>
    <div class="awa-faq-item">
      <h3>Qual o pedido mínimo para atacado?</h3>
      <p>Primeiro pedido: <strong>R$ 1.500,00</strong>. Frete grátis em pedidos acima de R$ 1.500,00.</p>
    </div>
    <div class="awa-faq-item">
      <h3>Como solicito uma cotação personalizada?</h3>
      <p>Use o <a href="{{store url='b2b/quote/index'}}">formulário de cotação</a> ou envie sua lista de peças para <a href="mailto:awamotos@awamotos.com.br">awamotos@awamotos.com.br</a>. Respondemos em até 1 dia útil.</p>
    </div>
  </div>

  <div class="awa-cta">
    <h3>Não encontrou sua resposta?</h3>
    <p>Fale diretamente com nosso time de atendimento.</p>
    <a href="https://wa.me/5516997367588" class="awa-btn" target="_blank" rel="noopener">WhatsApp (16) 99736-7588</a>
  </div>
</div>

<script type="application/ld+json">
{$faqSchema}
</script>
HTML;
    }

    /**
     * Helper para montar itens do Schema FAQPage.
     */
    private function faqItem(string $question, string $answer): array
    {
        return [
            '@type'          => 'Question',
            'name'           => $question,
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text'  => $answer,
            ],
        ];
    }

    /* ================================================================
       CONTEÚDO: TROCAS E DEVOLUÇÕES
       ================================================================ */

    private function contentReturns(): string
    {
        return <<<'HTML'
<div class="awa-inst" role="main" aria-label="Trocas e Devoluções">
  <h1>Trocas e Devoluções</h1>

  <div class="awa-summary">
    <p>Queremos que você compre com tranquilidade. Se precisar trocar ou devolver um produto, veja abaixo os prazos, condições e o passo a passo para solicitar.</p>
  </div>

  <h2>Prazos</h2>
  <table class="awa-table">
    <thead>
      <tr>
        <th>Situação</th>
        <th>Prazo</th>
        <th>Base legal</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>Arrependimento (desistência)</td>
        <td>Até 7 dias corridos após o recebimento</td>
        <td>Art. 49 do CDC</td>
      </tr>
      <tr>
        <td>Defeito aparente</td>
        <td>Até 30 dias após o recebimento</td>
        <td>Art. 26 do CDC</td>
      </tr>
      <tr>
        <td>Defeito oculto</td>
        <td>Até 30 dias após a constatação do defeito</td>
        <td>Art. 26, §3º do CDC</td>
      </tr>
      <tr>
        <td>Produto errado ou com avaria no transporte</td>
        <td>Até 7 dias corridos após o recebimento</td>
        <td>CDC + Política interna</td>
      </tr>
    </tbody>
  </table>

  <h2>Condições para troca ou devolução</h2>
  <ul>
    <li>O produto deve estar em sua <strong>embalagem original</strong>, sem sinais de uso, instalação ou danos.</li>
    <li>Todos os acessórios, manuais e brindes devem ser devolvidos junto.</li>
    <li>A <strong>nota fiscal</strong> (ou número do pedido) é obrigatória para dar início ao processo.</li>
    <li>Produtos instalados ou com sinais de uso podem ter a troca recusada (exceto defeito comprovado).</li>
  </ul>

  <h2>Passo a passo</h2>
  <ol class="awa-steps">
    <li>Entre em contato pelo <a href="{{store url='customer-service'}}">Atendimento</a> ou <a href="https://wa.me/5516997367588" target="_blank" rel="noopener">WhatsApp (16) 99736-7588</a> informando o <strong>número do pedido</strong> e o <strong>motivo</strong> da troca/devolução.</li>
    <li>Nossa equipe analisa a solicitação e envia as <strong>instruções de envio</strong> (código de postagem ou endereço para remessa).</li>
    <li>Embale o produto com segurança e envie no <strong>prazo indicado</strong>.</li>
    <li>Após o recebimento e análise, realizamos a <strong>troca</strong> ou o <strong>reembolso</strong> em até <strong>10 dias úteis</strong>.</li>
  </ol>

  <h2>Frete de devolução</h2>
  <div class="awa-info">
    <ul>
      <li><strong>Arrependimento:</strong> frete de retorno por conta do comprador.</li>
      <li><strong>Defeito, produto errado ou avaria no transporte:</strong> frete de retorno por conta do Grupo Awamotos.</li>
    </ul>
  </div>

  <h2>Reembolso</h2>
  <ul>
    <li><strong>Cartão de crédito:</strong> estorno na fatura em até 2 faturas seguintes (depende da operadora).</li>
    <li><strong>Boleto / Pix:</strong> reembolso via transferência bancária em até 10 dias úteis após a aprovação.</li>
    <li>Em caso de troca, o novo produto é enviado após a aprovação da devolução.</li>
  </ul>

  <h2>Exceções</h2>
  <ul>
    <li>Produtos sob medida ou personalizados não são passíveis de troca por arrependimento.</li>
    <li>Peças elétricas instaladas que apresentem dano por instalação incorreta podem não ser cobertas.</li>
    <li>Capacetes e equipamentos de proteção têm restrições de troca por higiene, exceto defeito de fabricação.</li>
  </ul>

  <div class="awa-cta">
    <h3>Precisa iniciar uma troca ou devolução?</h3>
    <p>Fale com nosso atendimento — resolveremos da maneira mais ágil possível.</p>
    <a href="{{store url='customer-service'}}" class="awa-btn">Central de Atendimento</a>
  </div>

  <h2>Páginas relacionadas</h2>
  <div class="awa-hub" role="navigation" aria-label="Páginas relacionadas">
    <a href="{{store url='warranty'}}">Garantia</a>
    <a href="{{store url='shipping'}}">Frete e Entrega</a>
    <a href="{{store url='faq'}}">Perguntas Frequentes</a>
  </div>
</div>
HTML;
    }

    /* ================================================================
       CONTEÚDO: GARANTIA
       ================================================================ */

    private function contentWarranty(): string
    {
        return <<<'HTML'
<div class="awa-inst" role="main" aria-label="Garantia de Produtos">
  <h1>Garantia de Produtos</h1>

  <div class="awa-summary">
    <p>Todos os produtos vendidos pela AWA Motos possuem <strong>garantia legal</strong> conforme o Código de Defesa do Consumidor. Alguns itens também contam com garantia adicional do fabricante. Veja abaixo como funciona.</p>
  </div>

  <h2>Tipos de garantia</h2>
  <table class="awa-table">
    <thead>
      <tr>
        <th>Tipo</th>
        <th>Prazo</th>
        <th>Cobertura</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>Garantia legal (CDC)</td>
        <td>30 dias (produtos não duráveis) / 90 dias (produtos duráveis)</td>
        <td>Defeitos de fabricação</td>
      </tr>
      <tr>
        <td>Garantia do fabricante</td>
        <td>Variável (consulte a embalagem ou manual do produto)</td>
        <td>Conforme termos do fabricante</td>
      </tr>
    </tbody>
  </table>
  <p><small>O prazo de garantia legal é contado a partir da entrega do produto. Para defeitos ocultos, o prazo inicia na data da constatação do defeito.</small></p>

  <h2>Como acionar a garantia</h2>
  <ol class="awa-steps">
    <li>Entre em contato com nosso <a href="{{store url='customer-service'}}">Atendimento</a> informando o <strong>número do pedido</strong> e uma <strong>descrição do problema</strong> (fotos ou vídeos ajudam na análise).</li>
    <li>Nossa equipe técnica avalia a solicitação e orienta sobre o <strong>envio do produto</strong> (se necessário).</li>
    <li>Após recebimento e análise técnica (até 30 dias conforme o CDC), informamos o resultado: <strong>reparo</strong>, <strong>troca</strong> ou <strong>reembolso</strong>.</li>
    <li>Se o produto for reparado ou substituído, devolvemos no mesmo endereço de entrega original, sem custo adicional.</li>
  </ol>

  <h2>O que é coberto</h2>
  <ul>
    <li>Defeitos de fabricação que impeçam o uso normal do produto.</li>
    <li>Falhas de material comprovadas por análise técnica.</li>
    <li>Divergência entre o produto recebido e a descrição no site.</li>
  </ul>

  <h2>O que NÃO é coberto</h2>
  <ul>
    <li>Desgaste natural pelo uso (pastilhas de freio, pneus, correias, etc.).</li>
    <li>Danos causados por <strong>instalação incorreta</strong> (recomendamos sempre um mecânico qualificado).</li>
    <li>Uso em desacordo com as especificações do fabricante.</li>
    <li>Modificações ou reparos realizados por terceiros não autorizados.</li>
    <li>Danos por acidentes, quedas, exposição a produtos químicos ou intempéries.</li>
  </ul>

  <div class="awa-info">
    <p><strong>Dica importante:</strong> Guarde sempre a <strong>nota fiscal</strong> e, quando possível, a <strong>embalagem original</strong>. Esses documentos são necessários para acionar a garantia.</p>
  </div>

  <h2>Prazos de resolução</h2>
  <ul>
    <li><strong>Resposta inicial:</strong> até 2 dias úteis após o contato.</li>
    <li><strong>Análise técnica:</strong> até 30 dias corridos após o recebimento do produto (Art. 18, §1º do CDC).</li>
    <li>Se a solução não for possível no prazo de 30 dias, você pode optar por: substituição do produto, restituição do valor ou abatimento proporcional do preço.</li>
  </ul>

  <div class="awa-cta">
    <h3>Precisa acionar a garantia?</h3>
    <p>Entre em contato com nosso atendimento e resolveremos o mais rápido possível.</p>
    <a href="{{store url='customer-service'}}" class="awa-btn">Central de Atendimento</a>
  </div>

  <h2>Páginas relacionadas</h2>
  <div class="awa-hub" role="navigation" aria-label="Páginas relacionadas">
    <a href="{{store url='returns'}}">Trocas e Devoluções</a>
    <a href="{{store url='faq'}}">Perguntas Frequentes</a>
    <a href="{{store url='shipping'}}">Frete e Entrega</a>
  </div>
</div>
HTML;
    }

    /* ================================================================
       CONTEÚDO: FRETE E ENTREGA
       ================================================================ */

    private function contentShipping(): string
    {
        return <<<'HTML'
<div class="awa-inst" role="main" aria-label="Frete e Entrega">

  <div class="awa-summary">
    <p>Enviamos para todo o Brasil. Confira abaixo as condições de frete grátis e as opções para clientes B2B.</p>
  </div>

  <h2>Frete grátis</h2>
  <table class="awa-table">
    <thead>
      <tr>
        <th>Tipo de cliente</th>
        <th>Valor mínimo do pedido</th>
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
        <td>Todo o Brasil</td>
      </tr>
      <tr>
        <td>B2B / Atacado</td>
        <td>R$ 1.500,00</td>
        <td>Todo o Brasil</td>
      </tr>
    </tbody>
  </table>

  <h2>Frete para clientes B2B</h2>
  <div class="awa-b2b">
    <p>Clientes cadastrados no programa B2B contam com condições especiais de frete:</p>
    <ul>
      <li><strong>Frete CIF (por nossa conta):</strong> em pedidos acima de R$ 1.500,00 para todo o Brasil.</li>
      <li><strong>Frete FOB (por conta do cliente):</strong> com valores negociados junto a transportadoras parceiras.</li>
      <li><strong>Transportadora preferencial:</strong> você pode usar sua própria transportadora de confiança.</li>
    </ul>
    <p><a href="{{store url='atacado/condicoes'}}">Conheça o programa B2B e condições de atacado</a></p>
  </div>

  <h2>Rastreamento</h2>
  <ul>
    <li>Após o despacho, você recebe o <strong>código de rastreamento por e-mail</strong>.</li>
    <li>Acompanhe em <a href="{{store url='customer/account'}}">"Minha Conta" &gt; "Meus Pedidos"</a>.</li>
    <li>Comprou como visitante? Use a página <a href="{{store url='sales/guest/form'}}">Rastrear Pedido</a>.</li>
  </ul>

  <h2>Informações importantes</h2>
  <ul>
    <li>Produtos volumosos ou pesados podem ter frete calculado separadamente.</li>
    <li>Em caso de ausência no endereço, a transportadora pode realizar novas tentativas de entrega conforme a política do serviço contratado.</li>
    <li>Confira o produto no ato da entrega. Se houver avaria na embalagem, recuse e entre em contato conosco.</li>
    <li>O prazo de entrega não inclui fins de semana e feriados.</li>
  </ul>

  <div class="awa-cta">
    <h3>Dúvidas sobre frete ou entrega?</h3>
    <p>Fale com a gente — estamos prontos para ajudar.</p>
    <a href="{{store url='customer-service'}}" class="awa-btn">Central de Atendimento</a>
  </div>

  <h2>Páginas relacionadas</h2>
  <div class="awa-hub" role="navigation" aria-label="Páginas relacionadas">
    <a href="{{store url='store-locator'}}">Retirada na loja</a>
    <a href="{{store url='returns'}}">Trocas e Devoluções</a>
    <a href="{{store url='faq'}}">Perguntas Frequentes</a>
    <a href="{{store url='atacado/condicoes'}}">Atacado / B2B</a>
  </div>
</div>
HTML;
    }

    /* ================================================================
       BLOCOS REUTILIZÁVEIS
       ================================================================ */

    private function blockHelpHub(): string
    {
        return <<<'HTML'
<div class="awa-inst">
  <h2>Central de Ajuda</h2>
  <div class="awa-hub" role="navigation" aria-label="Links rápidos da Central de Ajuda">
    <a href="{{store url='sales/guest/form'}}">Rastrear pedido</a>
    <a href="{{store url='returns'}}">Trocas e devoluções</a>
    <a href="{{store url='warranty'}}">Garantia</a>
    <a href="{{store url='shipping'}}">Frete e entrega</a>
    <a href="{{store url='faq'}}">Perguntas frequentes</a>
    <a href="{{store url='atacado/condicoes'}}">Atacado / B2B</a>
  </div>
</div>
HTML;
    }

    private function blockCtaB2b(): string
    {
        return <<<'HTML'
<div class="awa-inst">
  <div class="awa-cta">
    <h3>Programa B2B — Atacado e Revenda</h3>
    <p>Descontos de até 20%, frete CIF, boleto a prazo e atendimento prioritário para empresas com CNPJ.</p>
    <a href="{{store url='atacado/condicoes'}}" class="awa-btn">Conheça as condições</a>
  </div>
</div>
HTML;
    }

    private function blockContactHeader(): string
    {
        return <<<'HTML'
<div class="awa-contact-header">
  <p>Preencha o formulário abaixo e responderemos em até <strong>1 dia útil</strong>. Se preferir, use um dos nossos canais diretos:</p>
  <div class="awa-contact-channels">
    <div class="awa-contact-channel">
      <strong>Telefone</strong>
      <span>{{config path="general/store_information/phone"}}</span>
    </div>
    <div class="awa-contact-channel">
      <strong>WhatsApp</strong>
      <a href="https://wa.me/5516997367588" target="_blank" rel="noopener">(16) 99736-7588</a>
    </div>
    <div class="awa-contact-channel">
      <strong>E-mail</strong>
      <a href="mailto:awamotos@awamotos.com.br">awamotos@awamotos.com.br</a>
    </div>
  </div>
  <p><small>Horário de atendimento: {{config path="general/store_information/hours"}}. E-mails e formulários enviados fora do expediente serão respondidos no próximo dia útil.</small></p>
</div>

<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "ContactPage",
  "name": "Fale Conosco — AWA Motos",
  "url": "{{store url='contact'}}",
  "mainEntity": {
    "@type": "Organization",
    "name": "Grupo Awamotos",
    "telephone": "+55-16-3301-1890",
    "email": "awamotos@awamotos.com.br"
  }
}
</script>
HTML;
    }

    /* ================================================================
       INTERFACE
       ================================================================ */

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
