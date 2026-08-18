<?php

declare(strict_types=1);

namespace GrupoAwamotos\HelpCenter\Setup\Patch\Data;

use GrupoAwamotos\HelpCenter\Api\Data\CategoryInterface;
use GrupoAwamotos\HelpCenter\Model\CategoryFactory;
use GrupoAwamotos\HelpCenter\Model\CategoryRepository;
use GrupoAwamotos\HelpCenter\Model\TopicFactory;
use GrupoAwamotos\HelpCenter\Model\TopicRepository;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Seed inicial da Central de Ajuda AWA Motos.
 * Migrado do FAQ legado e do manual de atendentes.
 */
class InstallHelpTopics implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly CategoryFactory $categoryFactory,
        private readonly CategoryRepository $categoryRepository,
        private readonly TopicFactory $topicFactory,
        private readonly TopicRepository $topicRepository,
    ) {
    }

    public function apply(): self
    {
        $this->moduleDataSetup->startSetup();

        foreach ($this->getData() as $categoryData) {
            $category = $this->categoryFactory->create();
            $category->setName($categoryData['name']);
            $category->setDescription($categoryData['description'] ?? null);
            $category->setSortOrder((int) ($categoryData['sort_order'] ?? 0));
            $category->setAudience($categoryData['audience'] ?? 'all');
            $category->setStatus(1);
            $this->categoryRepository->save($category);
            $categoryId = (int) $category->getCategoryId();

            foreach ($categoryData['topics'] ?? [] as $order => $topicData) {
                $topic = $this->topicFactory->create();
                $topic->setCategoryId($categoryId);
                $topic->setTitle($topicData['title']);
                $topic->setSummary($topicData['summary'] ?? null);
                $topic->setContent($topicData['content'] ?? null);
                $topic->setKeywords($topicData['keywords'] ?? null);
                $topic->setAudience($topicData['audience'] ?? $categoryData['audience'] ?? 'all');
                $topic->setRoutePattern($topicData['route_pattern'] ?? null);
                $topic->setSortOrder($order + 1);
                $topic->setStatus(1);
                $topic->setTourSteps($topicData['tour_steps'] ?? null);
                $this->topicRepository->save($topic);
            }
        }

        $this->moduleDataSetup->endSetup();
        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getData(): array
    {
        return [
            [
                'name'        => 'Encontrando Produtos e Pecas',
                'description' => 'Como buscar, filtrar e verificar compatibilidade.',
                'sort_order'  => 10,
                'audience'    => CategoryInterface::AUDIENCE_ALL,
                'topics'      => [
                    [
                        'title'    => 'Como buscar pecas por nome ou SKU',
                        'summary'  => 'Use a barra de busca no topo da loja para pesquisar por nome, codigo ou SKU.',
                        'keywords' => 'busca, pesquisa, SKU, codigo, encontrar',
                        'content'  => '<p>Use a <strong>barra de busca</strong> no topo. Pesquise por nome (ex.: manete freio CG 160), SKU ou palavras-chave.</p>',
                    ],
                    [
                        'title'    => 'Como verificar compatibilidade de pecas',
                        'summary'  => 'Consulte a secao Compatibilidade na pagina do produto ou use os filtros de modelo e ano.',
                        'keywords' => 'compatibilidade, modelo, ano, moto, fitment',
                        'content'  => '<p>Na pagina do produto, veja a secao <strong>Compatibilidade</strong>. Nos filtros laterais selecione Marca, Modelo e Ano para ver apenas pecas compativeis.</p>',
                    ],
                ],
            ],
            [
                'name'        => 'Pedidos, Pagamentos e Entregas',
                'description' => 'Como comprar, formas de pagamento e prazo de entrega.',
                'sort_order'  => 20,
                'audience'    => CategoryInterface::AUDIENCE_CUSTOMER,
                'topics'      => [
                    [
                        'title'    => 'Como fazer um pedido',
                        'summary'  => 'Adicione ao carrinho, informe endereco, escolha frete e confirme.',
                        'keywords' => 'pedido, comprar, carrinho, checkout',
                        'content'  => '<p>Adicione o produto ao carrinho, clique em <strong>Finalizar Compra</strong>, informe endereco e forma de pagamento. Voce recebera confirmacao por e-mail.</p>',
                    ],
                    [
                        'title'    => 'Formas de pagamento aceitas',
                        'summary'  => 'Cartao de credito ate 12x, boleto, Pix e transferencia.',
                        'keywords' => 'pagamento, cartao, boleto, pix, parcelamento',
                        'content'  => '<p>Aceitamos: <strong>Cartao de credito</strong> ate 12x, <strong>Boleto bancario</strong>, <strong>Pix</strong> e <strong>Transferencia</strong>. Clientes B2B tambem tem faturamento a prazo.</p>',
                    ],
                    [
                        'title'    => 'Como rastrear meu pedido',
                        'summary'  => 'Acesse Minha Conta > Meus Pedidos ou use o codigo de rastreio do e-mail.',
                        'keywords' => 'rastrear, pedido, entrega, codigo',
                        'content'  => '<p>Em <strong>Minha Conta &gt; Meus Pedidos</strong>, clique no pedido e veja o codigo de rastreio na aba Entrega. Ou pergunte para a assistente IA: Qual o status do meu pedido?</p>',
                    ],
                    [
                        'title'    => 'Politica de trocas e devolucoes',
                        'summary'  => '7 dias para arrependimento, 90 dias para defeito de fabricacao.',
                        'keywords' => 'troca, devolucao, garantia, arrependimento',
                        'content'  => '<p>Voce tem <strong>7 dias corridos</strong> para devolucao por arrependimento e <strong>90 dias</strong> para defeito de fabricacao. Solicite em Minha Conta &gt; Meus Pedidos &gt; Solicitar Devolucao.</p>',
                    ],
                ],
            ],
            [
                'name'        => 'Conta B2B e Cadastro',
                'description' => 'Cadastro CNPJ, aprovacao, precos e credito B2B.',
                'sort_order'  => 30,
                'audience'    => CategoryInterface::AUDIENCE_B2B,
                'topics'      => [
                    [
                        'title'    => 'Como criar uma conta B2B',
                        'summary'  => 'Cadastre seu CNPJ e aguarde aprovacao em ate 24h uteis.',
                        'keywords' => 'cnpj, cadastro, b2b, empresa, aprovacao',
                        'content'  => '<p>Acesse <strong>Cadastro B2B</strong>, informe o CNPJ (dados preenchidos automaticamente via Receita Federal) e aguarde aprovacao em ate 24 horas uteis. Voce recebera confirmacao por e-mail.</p>',
                    ],
                    [
                        'title'    => 'Precos e condicoes especiais para B2B',
                        'summary'  => 'Tabela de precos exclusiva, faturamento a prazo e cotacoes para grandes volumes.',
                        'keywords' => 'preco, desconto, tabela, faturamento, prazo',
                        'content'  => '<p>Apos aprovacao: tabela de precos exclusiva, faturamento a prazo conforme limite de credito, cotacoes para grandes volumes e sugestoes de recompra inteligentes.</p>',
                    ],
                ],
            ],
            [
                'name'        => 'Painel Comercial — Primeiros Passos',
                'description' => 'Como navegar e usar o cockpit comercial.',
                'sort_order'  => 40,
                'audience'    => CategoryInterface::AUDIENCE_SELLER,
                'topics'      => [
                    [
                        'title'         => 'Acessando o painel comercial',
                        'summary'       => 'Acesse via menu Admin > AWA Comercial.',
                        'keywords'      => 'painel, cockpit, acesso, login',
                        'route_pattern' => 'awa_commercial_*',
                        'content'       => '<p>Faca login no Admin e clique em <strong>AWA Comercial</strong> no menu. O painel exibe seus KPIs, carteira e tarefas.</p>',
                        'tour_steps'    => '[{"element":".awa-commercial-navigation","title":"Navegacao do Cockpit","description":"Barra de abas para navegar entre as secoes do cockpit."}]',
                    ],
                    [
                        'title'         => 'Gerenciando minha carteira de clientes',
                        'summary'       => 'Na aba Minha Carteira, veja todos os clientes B2B atribuidos a voce.',
                        'keywords'      => 'carteira, clientes, B2B, atendimento',
                        'route_pattern' => 'awa_commercial_commercialportfolio_*',
                        'content'       => '<p>Acesse <strong>Minha Carteira</strong>. Filtre por status, ultima compra ou inatividade. Clique no cliente para ver a Ficha 360.</p>',
                    ],
                    [
                        'title'         => 'Consultando a Ficha 360 do cliente',
                        'summary'       => 'Historico de compras, pedidos pendentes, dados ERP, tarefas e contatos.',
                        'keywords'      => 'ficha, 360, historico, cliente, ERP',
                        'route_pattern' => 'awa_commercial_commercialcustomer360_*',
                        'content'       => '<p>A Ficha 360 consolida: dados cadastrais, historico de compras, limite de credito, sugestoes de recompra ERP, registro de contatos e tarefas abertas.</p>',
                    ],
                    [
                        'title'         => 'Carrinhos abandonados',
                        'summary'       => 'Clientes B2B com produtos no carrinho sem finalizar nos ultimos 7 dias.',
                        'keywords'      => 'carrinho, abandonado, follow-up',
                        'route_pattern' => 'awa_commercial_commercialabandonedcart_*',
                        'content'       => '<p>A aba <strong>Carrinhos Abandonados</strong> lista carrinhos da sua carteira nao finalizados nos ultimos 7 dias. Registre contato, crie tarefa ou envie link por WhatsApp.</p>',
                    ],
                    [
                        'title'         => 'Clientes inativos e sugestoes de recompra',
                        'summary'       => 'Clientes sem compra ha 60+ dias com sugestoes baseadas no historico ERP.',
                        'keywords'      => 'inativo, recompra, sugestao, ERP',
                        'route_pattern' => 'awa_commercial_commercialinactive_*',
                        'content'       => '<p>Em <strong>Clientes Parados</strong> veja clientes sem compra ha 60+ dias. Em <strong>Sugestoes de Recompra</strong> veja quais pecas o cliente costuma comprar e esta na epoca de repor.</p>',
                    ],
                    [
                        'title'         => 'Acompanhando metas e relatorios',
                        'summary'       => 'Meta mensal, percentual atingido, evolucao diaria e ranking da equipe.',
                        'keywords'      => 'metas, relatorio, ranking, desempenho',
                        'route_pattern' => 'awa_commercial_commercialgoal_*',
                        'content'       => '<p>A aba <strong>Metas</strong> exibe meta mensal, percentual atingido, evolucao diaria e ranking na equipe. Em <strong>Relatorios</strong> exporte em CSV.</p>',
                    ],
                    [
                        'title'         => 'Gerenciando cotacoes B2B',
                        'summary'       => 'Visualize, responda e aprove cotacoes pendentes dos seus clientes.',
                        'keywords'      => 'cotacao, orcamento, B2B, negociacao, preco',
                        'route_pattern' => 'awa_commercial_*',
                        'content'       => '<p>Acesse <code>B2B &rarr; Cotacoes</code> para ver cotacoes pendentes:</p><ol><li>Clique na cotacao para <strong>visualizar os itens</strong>, quantidades e valores.</li><li><strong>Responda</strong> com os precos negociados e prazo de entrega.</li><li>O cliente recebe notificacao por e-mail automaticamente.</li></ol>',
                        'tour_steps'    => '[{"element":"[href*=\'b2b_quote\']","title":"Menu Cotacoes","description":"Acesse aqui todas as cotacoes dos seus clientes."}]',
                    ],
                    [
                        'title'         => 'Acompanhando pedidos dos clientes',
                        'summary'       => 'Monitore status de pedidos da sua carteira em Vendas > Pedidos.',
                        'keywords'      => 'pedido, status, rastreamento, processando, enviado',
                        'route_pattern' => 'awa_commercial_*',
                        'content'       => '<p>Va em <code>Vendas &rarr; Pedidos</code> e use o filtro por <strong>nome do cliente</strong> ou <strong>numero do pedido</strong>. Status: <strong>Pendente</strong>, <strong>Processando</strong>, <strong>Enviado</strong> ou <strong>Completo</strong>.</p><p><em>Dica:</em> Pedidos aprovados pelo ERP aparecem como "Processing". Pedidos com acao manual: <code>B2B &rarr; Aprovacao de Pedidos</code>.</p>',
                    ],
                    [
                        'title'         => 'Tarefas e pendencias do dia',
                        'summary'       => 'Veja suas tarefas abertas, prazos e pendencias de clientes no cockpit.',
                        'keywords'      => 'tarefa, pendencia, agenda, prazo, follow-up',
                        'route_pattern' => 'awa_commercial_commercialtask_*',
                        'content'       => '<p>A aba <strong>Tarefas</strong> mostra pendencias vinculadas a voce: follow-ups programados, clientes aguardando retorno e alertas de prazo. Clique em uma tarefa para registrar o resultado ou reagendar.</p>',
                    ],
                ],
            ],
            [
                'name'        => 'Gestao da Equipe Comercial',
                'description' => 'Relatorios consolidados e distribuicao de carteiras (supervisoras).',
                'sort_order'  => 50,
                'audience'    => CategoryInterface::AUDIENCE_SUPERVISOR,
                'topics'      => [
                    [
                        'title'         => 'Visualizando o painel da equipe',
                        'summary'       => 'Painel consolidado com KPIs de todos os atendentes.',
                        'keywords'      => 'equipe, supervisor, painel, KPI',
                        'route_pattern' => 'awa_commercial_commercialdashboard_*',
                        'content'       => '<p>No cockpit, supervisoras veem o <strong>Painel da Equipe</strong>: faturamento total e por atendente, metas, ranking e clientes inativos por carteira.</p>',
                    ],
                    [
                        'title'    => 'Redistribuindo carteiras e permissoes',
                        'summary'  => 'A redistribuicao de carteiras e feita pelo Admin em B2B > Atendentes.',
                        'keywords' => 'permissao, carteira, atribuicao',
                        'content'  => '<p>A redistribuicao e feita pelo Administrador em Admin &gt; B2B &gt; Atendentes. Documente os clientes a mover e encaminhe ao administrador.</p>',
                    ],
                ],
            ],
        ];
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
