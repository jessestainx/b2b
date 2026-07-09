<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Order;

final class TrackingUrlResolver
{
    /**
     * @return array{url: string|null, carrier_label: string}
     */
    public function resolve(string $trackingCode, string $carrierName = ''): array
    {
        $trackingCode = trim($trackingCode);
        $carrierKey = $this->normalizeCarrierKey($carrierName);

        if ($trackingCode === '') {
            return ['url' => null, 'carrier_label' => $carrierName !== '' ? $carrierName : 'Transportadora'];
        }

        $url = match ($carrierKey) {
            'correios' => 'https://rastreamento.correios.com.br/app/index.php?objeto=' . rawurlencode($trackingCode),
            'jadlog' => 'https://www.jadlog.com.br/siteInstitucional/tracking.jad?cte=' . rawurlencode($trackingCode),
            'totalexpress' => 'https://www.totalexpress.com.br/rastreamento?codigo=' . rawurlencode($trackingCode),
            default => null,
        };

        return [
            'url' => $url,
            'carrier_label' => $carrierName !== '' ? $carrierName : 'Transportadora',
        ];
    }

    private function normalizeCarrierKey(string $carrierName): string
    {
        $carrierName = strtolower(trim($carrierName));

        if ($carrierName === '') {
            return 'custom';
        }

        if (str_contains($carrierName, 'correios') || str_contains($carrierName, 'sedex') || str_contains($carrierName, 'pac')) {
            return 'correios';
        }

        if (str_contains($carrierName, 'jadlog')) {
            return 'jadlog';
        }

        if (str_contains($carrierName, 'total express') || str_contains($carrierName, 'totalexpress')) {
            return 'totalexpress';
        }

        return 'custom';
    }
}
