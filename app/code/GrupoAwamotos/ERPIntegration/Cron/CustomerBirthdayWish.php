<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Cron;

use GrupoAwamotos\ERPIntegration\Helper\Data as Helper;
use GrupoAwamotos\ERPIntegration\Model\WhatsApp\ZApiClient;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

/**
 * Send WhatsApp birthday wishes to customers with opt-in.
 *
 * Runs daily at 9:00 AM. Sends a celebratory message with an optional
 * birthday coupon code.
 */
class CustomerBirthdayWish
{
    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly ZApiClient $zapiClient,
        private readonly Helper $helper,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function execute(): void
    {
        if (!$this->helper->isWhatsAppEnabled() || !$this->helper->isBirthdayWishEnabled()) {
            return;
        }

        try {
            $customers = $this->getBirthdayCustomers();

            if (empty($customers)) {
                $this->logger->debug('[BirthdayWish] No customers with birthday today');
                return;
            }

            $couponCode = $this->helper->getBirthdayCouponCode();
            $sent = 0;
            $failed = 0;

            foreach ($customers as $customer) {
                try {
                    $phone = $customer['phone'];
                    $name = $customer['name'];

                    if (empty($phone)) {
                        continue;
                    }

                    $message = $this->buildMessage($name, $couponCode);
                    $result = $this->zapiClient->sendTextMessage($phone, $message);

                    if ($result !== null) {
                        $sent++;
                        $this->logger->info('[BirthdayWish] Sent to ' . $this->maskPhone($phone));
                    } else {
                        $failed++;
                    }
                } catch (\Exception $e) {
                    $failed++;
                    $this->logger->warning('[BirthdayWish] Send failed: ' . $e->getMessage());
                }
            }

            $this->logger->info('[BirthdayWish] Completed', [
                'total' => count($customers),
                'sent' => $sent,
                'failed' => $failed,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('[BirthdayWish] Cron error: ' . $e->getMessage());
        }
    }

    /**
     * Get customers whose birthday is today and have WhatsApp opt-in.
     *
     * @return array<int, array{name: string, phone: string}>
     */
    private function getBirthdayCustomers(): array
    {
        $connection = $this->resource->getConnection();
        $today = date('m-d');

        $dobAttrId = $this->getAttributeId($connection, 'dob');
        $optinAttrId = $this->getAttributeId($connection, 'whatsapp_optin');

        if ($dobAttrId === 0 || $optinAttrId === 0) {
            return [];
        }

        $select = $connection->select()
            ->from(
                ['ce' => $this->resource->getTableName('customer_entity')],
                ['customer_id' => 'ce.entity_id']
            )
            ->join(
                ['cev_dob' => $this->resource->getTableName('customer_entity_datetime')],
                'cev_dob.entity_id = ce.entity_id AND cev_dob.attribute_id = ' . $dobAttrId,
                []
            )
            ->join(
                ['cev_opt' => $this->resource->getTableName('customer_entity_int')],
                'cev_opt.entity_id = ce.entity_id AND cev_opt.attribute_id = ' . $optinAttrId,
                []
            )
            ->joinLeft(
                ['ca' => $this->resource->getTableName('customer_address_entity')],
                'ca.entity_id = ce.default_billing',
                ['phone' => 'ca.telephone']
            )
            ->where('DATE_FORMAT(cev_dob.value, "%m-%d") = ?', $today)
            ->where('cev_opt.value = ?', '1')
            ->where('ce.is_active = ?', 1)
            ->group('ce.entity_id');

        $rows = $connection->fetchAll($select);

        $result = [];
        foreach ($rows as $row) {
            $phone = $this->normalizePhone($row['phone'] ?? '');
            if (empty($phone)) {
                continue;
            }

            try {
                $customer = $this->customerRepository->getById((int) $row['customer_id']);
                $name = $customer->getFirstname() ?: 'Cliente';
            } catch (\Exception $e) {
                $name = 'Cliente';
            }

            $result[] = [
                'name' => $name,
                'phone' => $phone,
            ];
        }

        return $result;
    }

    private function buildMessage(string $name, ?string $couponCode): string
    {
        $storeName = $this->helper->getStoreName();

        $message = "🎉 Parabens, {$name}! 🎂\n\n";
        $message .= "A *{$storeName}* deseja um dia incrivel e muitas aventuras na estrada! 🏍️💨\n\n";

        if ($couponCode) {
            $message .= "Seu presente de aniversario:\n";
            $message .= "Cupom: *{$couponCode}*\n";
            $message .= "Aproveite o desconto exclusivo! 🎁\n\n";
        }

        $message .= "Que seu ano seja repleto de boas rotas! 🛣️";

        return $message;
    }

    private function getAttributeId(\Magento\Framework\DB\Adapter\AdapterInterface $connection, string $code): int
    {
        return (int) $connection->fetchOne(
            $connection->select()
                ->from($this->resource->getTableName('eav_attribute'), ['attribute_id'])
                ->where('attribute_code = ?', $code)
                ->where('entity_type_id = ?', 1)
                ->limit(1)
        );
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        if (strlen($digits) <= 8) {
            return '';
        }
        if (strlen($digits) <= 11 && !str_starts_with($digits, '55')) {
            $digits = '55' . $digits;
        }
        return $digits;
    }

    private function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';
        if (strlen($digits) <= 4) {
            return '****';
        }
        return substr($digits, 0, 4) . str_repeat('*', strlen($digits) - 8) . substr($digits, -4);
    }
}
