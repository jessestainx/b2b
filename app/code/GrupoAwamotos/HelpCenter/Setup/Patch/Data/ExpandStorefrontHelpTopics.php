<?php

declare(strict_types=1);

namespace GrupoAwamotos\HelpCenter\Setup\Patch\Data;

use GrupoAwamotos\HelpCenter\Api\Data\CategoryInterface;
use GrupoAwamotos\HelpCenter\Api\Data\TopicInterface;
use GrupoAwamotos\HelpCenter\Api\TopicRepositoryInterface;
use GrupoAwamotos\HelpCenter\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use GrupoAwamotos\HelpCenter\Model\ResourceModel\Topic\CollectionFactory as TopicCollectionFactory;
use GrupoAwamotos\HelpCenter\Model\TopicFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Complements the public Help Center hub with CMS-aligned topics.
 *
 * Source of truth: institutional pages formas-pagamento, prazo-entrega,
 * shipping, trocas-devolucoes and enabled checkout methods (Pix + A Combinar).
 * Idempotent by normalized topic title.
 */
class ExpandStorefrontHelpTopics implements DataPatchInterface
{
    /** @var array<string, CategoryInterface> */
    private array $categoriesByName = [];

    /** @var array<string, TopicInterface> */
    private array $topicsByTitle = [];

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly TopicFactory $topicFactory,
        private readonly TopicRepositoryInterface $topicRepository,
        private readonly CategoryCollectionFactory $categoryCollectionFactory,
        private readonly TopicCollectionFactory $topicCollectionFactory,
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->startSetup();
        $this->loadMaps();
        foreach ($this->getTopicSpecs() as $spec) {
            $this->ensureTopic($spec);
        }
        $this->moduleDataSetup->endSetup();

        return $this;
    }

    public static function getDependencies(): array
    {
        return [NormalizeStorefrontHelpHub::class];
    }

    public function getAliases(): array
    {
        return [];
    }

    /**
     * @param array{
     *     titles: string[],
     *     category: string,
     *     audience: string,
     *     sort_order: int,
     *     summary: string,
     *     keywords: string,
     *     content: string
     * } $spec
     */
    private function ensureTopic(array $spec): void
    {
        $category = $this->categoriesByName[$this->normalizeName($spec['category'])] ?? null;
        $categoryId = $category?->getCategoryId();
        if ($categoryId === null) {
            return;
        }

        $canonical = $spec['titles'][0];
        $topic = $this->findTopic($spec['titles']);
        $isNew = $topic === null;
        if ($isNew) {
            $topic = $this->topicFactory->create();
            $topic->setStatus(1);
        }

        if (!$this->topicNeedsSave($topic, $spec, $categoryId, $isNew)) {
            return;
        }

        $oldTitle = $topic->getTitle();
        $topic->setTitle($canonical);
        $topic->setSummary($spec['summary']);
        $topic->setKeywords($spec['keywords']);
        $topic->setContent($spec['content']);
        $topic->setCategoryId($categoryId);
        $topic->setAudience($spec['audience']);
        $topic->setSortOrder($spec['sort_order']);
        $topic->setStatus(1);
        $this->topicRepository->save($topic);

        if ($oldTitle !== '') {
            unset($this->topicsByTitle[$this->normalizeName($oldTitle)]);
        }
        $this->topicsByTitle[$this->normalizeName($canonical)] = $topic;
    }

    /**
     * @param array{titles: string[], summary: string, keywords: string, content: string, audience: string, sort_order: int} $spec
     */
    private function topicNeedsSave(TopicInterface $topic, array $spec, int $categoryId, bool $isNew): bool
    {
        if ($isNew) {
            return true;
        }

        return $topic->getTitle() !== $spec['titles'][0]
            || (string) $topic->getSummary() !== $spec['summary']
            || (string) $topic->getKeywords() !== $spec['keywords']
            || (string) $topic->getContent() !== $spec['content']
            || (int) $topic->getCategoryId() !== $categoryId
            || $topic->getAudience() !== $spec['audience']
            || $topic->getSortOrder() !== $spec['sort_order']
            || $topic->getStatus() !== 1;
    }

    /**
     * @param string[] $titles
     */
    private function findTopic(array $titles): ?TopicInterface
    {
        foreach ($titles as $title) {
            $topic = $this->topicsByTitle[$this->normalizeName($title)] ?? null;
            if ($topic instanceof TopicInterface) {
                return $topic;
            }
        }

        return null;
    }

    private function loadMaps(): void
    {
        $this->categoriesByName = [];
        foreach ($this->categoryCollectionFactory->create() as $category) {
            $this->categoriesByName[$this->normalizeName($category->getName())] = $category;
        }

        $this->topicsByTitle = [];
        foreach ($this->topicCollectionFactory->create() as $topic) {
            $this->topicsByTitle[$this->normalizeName($topic->getTitle())] = $topic;
        }
    }

    private function normalizeName(string $name): string
    {
        $name = mb_strtolower(trim($name));

        return strtr($name, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a',
            'é' => 'e', 'ê' => 'e',
            'í' => 'i',
            'ó' => 'o', 'ô' => 'o', 'õ' => 'o',
            'ú' => 'u', 'ü' => 'u',
            'ç' => 'c',
        ]);
    }

    /**
     * @return list<array{
     *     titles: string[],
     *     category: string,
     *     audience: string,
     *     sort_order: int,
     *     summary: string,
     *     keywords: string,
     *     content: string
     * }>
     */
    private function getTopicSpecs(): array
    {
        $all = CategoryInterface::AUDIENCE_ALL;
        $b2b = CategoryInterface::AUDIENCE_B2B;

        return [
            [
                'titles' => ['Como criar uma conta B2B'],
                'category' => 'Cadastro',
                'audience' => $all,
                'sort_order' => 1,
                'summary' => 'Cadastre o CNPJ e aguarde a aprovação em até 24 horas úteis.',
                'keywords' => 'cnpj, cadastro, b2b, empresa, aprovação, conta',
                'content' => '<p>Acesse <a href="/b2b/register">Cadastro B2B</a>, informe o CNPJ (os dados da Receita Federal preenchem automaticamente) e envie o pedido.</p><p>A aprovação ocorre em até <strong>24 horas úteis</strong>. Você recebe a confirmação por e-mail. Depois disso, entre com o mesmo e-mail e senha cadastrados.</p>',
            ],
            [
                'titles' => ['Como acessar a conta ou recuperar a senha', 'Esqueci minha senha'],
                'category' => 'Cadastro',
                'audience' => $all,
                'sort_order' => 2,
                'summary' => 'Entre pelo login da loja. Se esqueceu a senha, use “Esqueci minha senha”.',
                'keywords' => 'login, senha, acesso, conta, esqueci, recuperar',
                'content' => '<p>Use <a href="/customer/account/login">Minha Conta</a> ou o login B2B. Em “Esqueci minha senha”, informe o e-mail cadastrado e abra o link enviado.</p><p>Se o e-mail não chegar, confira o spam. Ainda com problema, fale no WhatsApp <a href="https://wa.me/5516997367588">(16) 99736-7588</a> ou em <a href="mailto:awamotos@awamotos.com.br">awamotos@awamotos.com.br</a>.</p>',
            ],
            [
                'titles' => ['Preços e condições especiais para B2B', 'Precos e condicoes especiais para B2B'],
                'category' => 'Cadastro',
                'audience' => $b2b,
                'sort_order' => 3,
                'summary' => 'Tabela exclusiva, faturamento a prazo e cotações para volume.',
                'keywords' => 'preço, desconto, tabela, faturamento, prazo, b2b, cotação',
                'content' => '<p>Com o cadastro B2B aprovado você passa a ver a tabela da sua empresa, pode faturar a prazo conforme o limite de crédito e solicitar cotações para volumes maiores.</p><p>Condições a prazo passam por <strong>análise financeira</strong>. Detalhes também em <a href="/formas-pagamento">Formas de Pagamento</a>.</p>',
            ],
            [
                'titles' => ['Como buscar peças por nome ou SKU', 'Como buscar pecas por nome ou SKU'],
                'category' => 'Produtos',
                'audience' => $all,
                'sort_order' => 1,
                'summary' => 'Use a busca do topo por nome, código ou SKU.',
                'keywords' => 'busca, pesquisa, SKU, código, encontrar, peça',
                'content' => '<p>Use a <strong>barra de busca</strong> no topo. Pesquise por nome (ex.: manete freio CG 160), SKU ou palavras-chave. Você também pode perguntar à assistente da loja.</p>',
            ],
            [
                'titles' => ['Como verificar compatibilidade de peças', 'Como verificar compatibilidade de pecas'],
                'category' => 'Produtos',
                'audience' => $all,
                'sort_order' => 2,
                'summary' => 'Veja Compatibilidade na página do produto ou filtre por marca, modelo e ano.',
                'keywords' => 'compatibilidade, modelo, ano, moto, fitment, filtro',
                'content' => '<p>Na página do produto, abra a seção <strong>Compatibilidade</strong>. Nos filtros da listagem, selecione marca, modelo e ano para ver só peças compatíveis.</p>',
            ],
            [
                'titles' => ['Como fazer um pedido'],
                'category' => 'Pedidos',
                'audience' => $all,
                'sort_order' => 1,
                'summary' => 'Carrinho, endereço, frete, pagamento e confirmação por e-mail.',
                'keywords' => 'pedido, comprar, carrinho, checkout, finalizar',
                'content' => '<p>Adicione a peça ao carrinho, clique em <strong>Finalizar compra</strong>, confira o endereço, o frete calculado no CEP e a forma de pagamento. Após a confirmação você recebe e-mail com o número do pedido.</p><p>Clientes B2B aprovados compram logados, com o preço da tabela da empresa.</p>',
            ],
            [
                'titles' => ['Posso alterar ou cancelar um pedido?'],
                'category' => 'Pedidos',
                'audience' => $all,
                'sort_order' => 2,
                'summary' => 'Fale com o atendimento o quanto antes. Pedido em separação pode não ser cancelado.',
                'keywords' => 'cancelar, alterar, pedido, mudar endereço, desistir',
                'content' => '<p>Não há cancelamento automático no site. Entre em contato com o número do pedido no WhatsApp <a href="https://wa.me/5516997367588">(16) 99736-7588</a>, no telefone <a href="tel:1633011890">(16) 3301-1890</a> ou em <a href="mailto:awamotos@awamotos.com.br">awamotos@awamotos.com.br</a>.</p><p>Se o pagamento já confirmou e o pedido entrou em separação, a alteração pode não ser possível.</p>',
            ],
            [
                'titles' => ['Formas de pagamento aceitas'],
                'category' => 'Pagamento',
                'audience' => $all,
                'sort_order' => 1,
                'summary' => 'No checkout: Pix. Boleto, à vista e prazo sob análise — veja a página institucional.',
                'keywords' => 'pagamento, pix, boleto, prazo, a combinar, checkout',
                'content' => '<p>No checkout da loja as opções ativas são <strong>Pix</strong> e <strong>A Combinar</strong>. Boleto, liquidação à vista e faturamento a prazo seguem a política publicada em <a href="/formas-pagamento">Formas de Pagamento</a> e passam por atendimento quando necessário.</p><p>Não oferecemos cartão de crédito no checkout. Dúvidas: WhatsApp <a href="https://wa.me/5516997367588">(16) 99736-7588</a>.</p>',
            ],
            [
                'titles' => ['Como pagar com Pix'],
                'category' => 'Pagamento',
                'audience' => $all,
                'sort_order' => 2,
                'summary' => 'Pix à vista, 24h, sem taxa extra. O pedido segue após a confirmação.',
                'keywords' => 'pix, pagamento instantâneo, qr code, transferência',
                'content' => '<p>No checkout escolha <strong>PIX - Pagamento Instantâneo</strong>. O pagamento é à vista, 24h, sem taxa extra da loja. Depois da confirmação o pedido entra em processamento.</p><p>Detalhes em <a href="/formas-pagamento">Formas de Pagamento</a>.</p>',
            ],
            [
                'titles' => ['Pagamento a prazo para empresas'],
                'category' => 'Pagamento',
                'audience' => $b2b,
                'sort_order' => 3,
                'summary' => 'Prazo sujeito à análise financeira do cadastro B2B.',
                'keywords' => 'prazo, faturamento, crédito, boleto, análise, b2b',
                'content' => '<p>Condições a prazo dependem de <strong>análise financeira</strong> do cadastro B2B. Consulte o atendente da sua carteira ou o WhatsApp <a href="https://wa.me/5516997367588">(16) 99736-7588</a>.</p><p>Política completa: <a href="/formas-pagamento">Formas de Pagamento</a>.</p>',
            ],
            [
                'titles' => ['Quais são os prazos de entrega?'],
                'category' => 'Entrega',
                'audience' => $all,
                'sort_order' => 1,
                'summary' => 'Após o pagamento, separação em até 24h úteis. O prazo no destino varia pela região.',
                'keywords' => 'prazo, entrega, dias úteis, correios, transportadora, cep',
                'content' => '<p>Enviamos para todo o Brasil. O frete e o prazo saem na página do produto e no carrinho, pelo CEP. Depois da confirmação do pagamento, o pedido é separado em até <strong>24 horas úteis</strong> e você recebe o código de rastreio por e-mail.</p><p>Estimativas após a postagem (úteis, sem fim de semana/feriado): SP 3–7 dias; Sul/Sudeste 5–10; Centro-Oeste 7–12; Nordeste 10–15; Norte 12–20. Podem variar. Tabela em <a href="/prazo-entrega">Prazos de Entrega</a>.</p>',
            ],
            [
                'titles' => ['Como funciona o frete grátis?'],
                'category' => 'Entrega',
                'audience' => $all,
                'sort_order' => 2,
                'summary' => 'PF: R$ 299 Sul/Sudeste ou R$ 499 Brasil. B2B: R$ 1.500 Brasil.',
                'keywords' => 'frete grátis, cif, fob, 299, 499, 1500, b2b',
                'content' => '<p>Condições publicadas em <a href="/shipping">Frete e Entrega</a>:</p><ul><li>Pessoa física: a partir de <strong>R$ 299</strong> para Sul e Sudeste, ou <strong>R$ 499</strong> para todo o Brasil.</li><li>B2B / atacado: a partir de <strong>R$ 1.500</strong> para todo o Brasil (CIF). FOB e transportadora do cliente também existem no programa B2B.</li></ul><p>Itens volumosos ou pesados podem ter frete à parte. Confira o cálculo no carrinho.</p>',
            ],
            [
                'titles' => ['Como rastrear meu pedido'],
                'category' => 'Entrega',
                'audience' => $all,
                'sort_order' => 3,
                'summary' => 'Código por e-mail e em Minha Conta. Visitante usa Rastrear Pedido.',
                'keywords' => 'rastrear, rastreio, correios, código, status, entrega',
                'content' => '<p>Depois do despacho o código vai por e-mail. Logado: <a href="/customer/account">Minha Conta</a> → Meus Pedidos. Visitante: <a href="/sales/guest/form">Rastrear Pedido</a>. Correios: <a href="https://www.correios.com.br/rastreamento" target="_blank" rel="noopener">rastreamento</a>.</p><p>Na entrega, confira a embalagem. Se houver avaria, recuse e fale conosco.</p>',
            ],
            [
                'titles' => ['Política de trocas e devoluções', 'Politica de trocas e devolucoes'],
                'category' => 'Devolução',
                'audience' => $all,
                'sort_order' => 1,
                'summary' => '7 dias corridos após o recebimento, produto sem uso e com nota fiscal.',
                'keywords' => 'troca, devolução, arrependimento, CDC, 7 dias, política',
                'content' => '<p>Você tem <strong>7 dias corridos</strong> após o recebimento para troca ou devolução por arrependimento, conforme o CDC. O produto precisa estar na embalagem original, sem uso/instalação, com acessórios e nota fiscal.</p><p>Não aceitamos troca de item instalado, sem embalagem, fora do prazo (exceto defeito) nem peça de desgaste já usada (pastilha, pneu usado etc.). Texto completo: <a href="/trocas-devolucoes">Trocas e Devoluções</a>.</p>',
            ],
            [
                'titles' => ['Como solicitar troca ou devolução'],
                'category' => 'Devolução',
                'audience' => $all,
                'sort_order' => 2,
                'summary' => 'Fale com o atendimento, informe o pedido e aguarde a autorização de envio.',
                'keywords' => 'solicitar, troca, devolução, reembolso, whatsapp, RMA',
                'content' => '<p>Não há botão de devolução automática no pedido. Entre em contato com número do pedido, produto e motivo:</p><ul><li>WhatsApp <a href="https://wa.me/5516997367588">(16) 99736-7588</a></li><li>E-mail <a href="mailto:awamotos@awamotos.com.br">awamotos@awamotos.com.br</a></li><li>Telefone <a href="tel:1633011890">(16) 3301-1890</a></li></ul><p>Aguarde a autorização e as instruções de envio. Depois da conferência, o reembolso sai em até <strong>10 dias úteis</strong> (Pix/boleto na conta informada; cartão, se houver, via estorno da operadora).</p>',
            ],
            [
                'titles' => ['Garantia e produto com defeito'],
                'category' => 'Devolução',
                'audience' => $all,
                'sort_order' => 3,
                'summary' => '30 dias para não duráveis e 90 dias para duráveis, além da garantia do fabricante.',
                'keywords' => 'garantia, defeito, fabricação, 30 dias, 90 dias, vício',
                'content' => '<p>Defeito de fabricação: <strong>30 dias</strong> para produto não durável e <strong>90 dias</strong> para durável, além da garantia legal do fabricante. Envie foto ou vídeo do defeito para agilizar a análise.</p><p>Abra o chamado pelos canais de <a href="/trocas-devolucoes">Trocas e Devoluções</a> ou pela assistente da loja.</p>',
            ],
        ];
    }
}
