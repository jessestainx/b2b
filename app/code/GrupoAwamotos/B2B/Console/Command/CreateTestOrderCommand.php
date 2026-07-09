<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Console\Command;

use GrupoAwamotos\B2B\Model\B2bCustomerFactory;
use GrupoAwamotos\B2B\Model\ResourceModel\B2bCustomer as B2bCustomerResource;
use GrupoAwamotos\B2B\Model\ResourceModel\B2bCustomer\CollectionFactory as B2bCollectionFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote\AddressFactory as QuoteAddressFactory;
use Magento\Quote\Model\QuoteFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Cria um pedido de teste no Magento para um cliente B2B.
 * O pedido NÃO é enviado ao Sectra — o usuário faz a importação manual no ERP.
 *
 * Uso:
 *   php bin/magento b2b:create-test-order --erp-code=19107
 *   php bin/magento b2b:create-test-order --erp-code=19107 --sku=MOTO-001,MOTO-002
 *   php bin/magento b2b:create-test-order --erp-code=19107 --qty=2
 */
class CreateTestOrderCommand extends Command
{
    private const OPT_ERP_CODE = 'erp-code';
    private const OPT_SKU      = 'sku';
    private const OPT_QTY      = 'qty';

    public function __construct(
        private readonly B2bCustomerFactory $b2bCustomerFactory,
        private readonly B2bCustomerResource $b2bCustomerResource,
        private readonly B2bCollectionFactory $b2bCollectionFactory,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly QuoteFactory $quoteFactory,
        private readonly QuoteAddressFactory $quoteAddressFactory,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly CartManagementInterface $cartManagement,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly ResourceConnection $resourceConnection,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('b2b:create-test-order')
             ->setDescription('Cria pedido de teste no Magento para um cliente B2B (sem enviar ao Sectra).')
             ->addOption(self::OPT_ERP_CODE, null, InputOption::VALUE_REQUIRED, 'Código ERP do cliente (ex: 19107)')
             ->addOption(self::OPT_SKU, null, InputOption::VALUE_OPTIONAL, 'SKUs separados por vírgula. Se omitido usa os primeiros produtos disponíveis.')
             ->addOption(self::OPT_QTY, null, InputOption::VALUE_OPTIONAL, 'Quantidade por item', '1');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $erpCode = trim((string) $input->getOption(self::OPT_ERP_CODE));
        $skuList = trim((string) $input->getOption(self::OPT_SKU));
        $qty     = max(1, (int) $input->getOption(self::OPT_QTY));

        if ($erpCode === '') {
            $output->writeln('<error>Informe: --erp-code=19107</error>');
            return Command::FAILURE;
        }

        $output->writeln('');
        $output->writeln("<info>╔══════════════════════════════════════════════════════╗</info>");
        $output->writeln("<info>║  AWA Motos — Criar Pedido de Teste B2B (ERP: $erpCode)</info>");
        $output->writeln("<info>╚══════════════════════════════════════════════════════╝</info>");

        // ── 1. Localiza o cliente B2B ────────────────────────────────────────────
        $output->writeln("\n<comment>[ 1. Cliente B2B ]</comment>");

        $col = $this->b2bCollectionFactory->create();
        $col->addFieldToFilter('cod_erp', $erpCode);
        $b2b = $col->getFirstItem();

        if (!$b2b->getId()) {
            $output->writeln("  <error>Cliente com cod_erp=$erpCode não encontrado no Magento.</error>");
            $output->writeln("  Execute primeiro: php bin/magento erp:import-customer --erp-code=$erpCode");
            return Command::FAILURE;
        }

        if (!$b2b->getCustomerId()) {
            $output->writeln("  <error>O registro B2B (ID {$b2b->getId()}) não tem conta Magento vinculada.</error>");
            $output->writeln("  Execute: php bin/magento erp:import-customer --erp-code=$erpCode para vincular.");
            return Command::FAILURE;
        }

        $customerId = (int) $b2b->getCustomerId();
        $output->writeln("  Razão Social : <info>{$b2b->getRazaoSocial()}</info>");
        $output->writeln("  B2B ID       : {$b2b->getId()}");
        $output->writeln("  Customer ID  : $customerId");
        $output->writeln("  E-mail       : {$b2b->getEmail()}");

        $customer = $this->customerRepository->getById($customerId);

        // ── 2. Produtos ──────────────────────────────────────────────────────────
        $output->writeln("\n<comment>[ 2. Produtos ]</comment>");

        $products = [];
        if ($skuList !== '') {
            foreach (array_filter(array_map('trim', explode(',', $skuList))) as $sku) {
                try {
                    $products[] = $this->productRepository->get($sku);
                    $output->writeln("  ✓ SKU: $sku");
                } catch (NoSuchEntityException) {
                    $output->writeln("  <comment>SKU não encontrado: $sku (ignorado)</comment>");
                }
            }
        }

        // Se nenhum SKU fornecido, usa os primeiros produtos simples disponíveis
        if (empty($products)) {
            $output->writeln("  Nenhum SKU especificado — buscando produtos simples disponíveis...");
            $products = $this->findFirstSimpleProducts(3);
            if (empty($products)) {
                $output->writeln("  <error>Nenhum produto simples encontrado no catálogo.</error>");
                $output->writeln("  Forneça SKUs via: --sku=MOTO-001,MOTO-002");
                return Command::FAILURE;
            }
            foreach ($products as $p) {
                $output->writeln("  ✓ SKU: {$p->getSku()} — {$p->getName()}");
            }
        }

        if (empty($products)) {
            $output->writeln("  <error>Nenhum produto válido para adicionar ao pedido.</error>");
            return Command::FAILURE;
        }

        // ── 3. Cria o pedido via Quote ───────────────────────────────────────────
        $output->writeln("\n<comment>[ 3. Criando pedido ]</comment>");

        try {
            $store = $this->storeManager->getDefaultStoreView();

            // Cria quote
            $quote = $this->quoteFactory->create();
            $quote->setStoreId((int) $store->getId());
            $quote->assignCustomer($customer);
            $quote->setIsActive(true);

            // Adiciona produtos
            foreach ($products as $product) {
                $quote->addProduct($product, $qty);
            }

            // Endereço baseado nos dados B2B
            [$firstName, $lastName] = $this->splitName($b2b->getContactName() ?: $b2b->getRazaoSocial());
            $addressData = [
                'firstname'    => $firstName,
                'lastname'     => $lastName,
                'company'      => $b2b->getRazaoSocial(),
                'street'       => ['Rua Teste B2B, 100'],
                'city'         => 'São Paulo',
                'country_id'   => 'BR',
                'region'       => 'SP',
                'region_id'    => 508, // SP
                'postcode'     => '01310-100',
                'telephone'    => $b2b->getPhone() ?: '(11) 99999-9999',
                'email'        => $b2b->getEmail(),
                'save_in_address_book' => 0,
            ];

            $billingAddress = $this->quoteAddressFactory->create()->addData($addressData);
            $quote->setBillingAddress($billingAddress);

            $shippingAddress = $this->quoteAddressFactory->create()->addData($addressData);
            $shippingAddress->setCollectShippingRates(true)->collectShippingRates();

            // Tenta métodos de envio em ordem de preferência
            $shippingMethod = $this->pickShippingMethod($shippingAddress);
            $shippingAddress->setShippingMethod($shippingMethod);
            $quote->setShippingAddress($shippingAddress);

            // Pagamento: checkmo (cheque/dinheiro — sem gateway)
            $quote->setPaymentMethod('checkmo');
            $quote->setInventoryProcessed(false);
            $quote->getPayment()->setMethod('checkmo');

            $this->cartRepository->save($quote);
            $quote->collectTotals();
            $this->cartRepository->save($quote);

            $orderId = $this->cartManagement->placeOrder($quote->getId());
            $order   = $this->orderRepository->get($orderId);

            $output->writeln("  <info>✓ Pedido criado com sucesso!</info>");
            $output->writeln('');

            $t = new Table($output);
            $t->setHeaders(['Campo', 'Valor']);
            $t->addRows([
                ['Order ID (interno)',  $orderId],
                ['Increment ID',        $order->getIncrementId()],
                ['Status',              $order->getStatus()],
                ['Total',               'R$ ' . number_format((float) $order->getGrandTotal(), 2, ',', '.')],
                ['Pagamento',           'Cheque/Transferência (checkmo)'],
                ['Envio',               $shippingMethod],
                ['Cliente (Magento)',   $order->getCustomerEmail()],
            ]);
            $t->render();

            $output->writeln('');
            $output->writeln('<info>Pedido pronto para importação manual no Sectra.</info>');
            $output->writeln("Acesse no admin: Vendas → Pedidos → #{$order->getIncrementId()}");
            $output->writeln('');
        } catch (\Throwable $e) {
            $output->writeln("  <error>Falha ao criar pedido: " . $e->getMessage() . "</error>");
            $output->writeln("  <comment>Detalhes: " . get_class($e) . "</comment>");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function findFirstSimpleProducts(int $limit): array
    {
        $connection = $this->resourceConnection->getConnection();
        $productEntityTable = $this->resourceConnection->getTableName('catalog_product_entity');
        $stockItemTable = $this->resourceConnection->getTableName('cataloginventory_stock_item');

        $select = $connection->select()
            ->from(['e' => $productEntityTable], ['sku'])
            ->joinInner(['si' => $stockItemTable], 'si.product_id = e.entity_id AND si.is_in_stock = 1', [])
            ->where('e.type_id = ?', 'simple')
            ->order('e.entity_id ASC')
            ->limit($limit);

        $rows = $connection->fetchAll($select);

        $products = [];
        foreach ($rows as $row) {
            try {
                $products[] = $this->productRepository->get($row['sku']);
            } catch (NoSuchEntityException) {
                continue;
            }
        }

        return $products;
    }

    private function pickShippingMethod(\Magento\Quote\Model\Quote\Address $address): string
    {
        $rates = $address->getAllShippingRates();
        $preferred = ['freeshipping_freeshipping', 'flatrate_flatrate', 'tablerate_bestway'];

        foreach ($preferred as $code) {
            foreach ($rates as $rate) {
                if ($rate->getCode() === $code) {
                    return $code;
                }
            }
        }

        // Usa o primeiro disponível
        if (!empty($rates)) {
            return $rates[0]->getCode();
        }

        return 'flatrate_flatrate';
    }

    /** @return array{0:string,1:string} */
    private function splitName(string $fullName): array
    {
        $parts = explode(' ', trim($fullName), 2);
        return [$parts[0] ?: 'Cliente', $parts[1] ?? 'B2B'];
    }
}
