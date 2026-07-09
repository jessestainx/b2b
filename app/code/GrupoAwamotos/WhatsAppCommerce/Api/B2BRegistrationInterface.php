<?php

declare(strict_types=1);

namespace GrupoAwamotos\WhatsAppCommerce\Api;

/**
 * WhatsApp B2B Registration API
 *
 * Allows B2B customers to register via WhatsApp by providing CNPJ.
 * Validates CNPJ via ReceitaWS, creates lead in Magento, notifies B2B team.
 */
interface B2BRegistrationInterface
{
    /**
     * Validate CNPJ and return company data from ReceitaWS
     *
     * @param string $cnpj CNPJ with or without formatting
     * @return mixed[] Company data (razao_social, nome_fantasia, situacao, etc.)
     */
    public function validateCnpj(string $cnpj): array;

    /**
     * Register a B2B lead from WhatsApp
     *
     * Creates a pending B2B customer in Magento with the provided data.
     * Notifies the B2B team via WhatsApp.
     *
     * @param string $cnpj CNPJ
     * @param string $phone WhatsApp phone number (with country code)
     * @param string $contactName Contact person name
     * @param string|null $email Contact email (optional)
     * @param string|null $segment Business segment (motopecas, oficina, revendedor, outro)
     * @return mixed[] Registration result (success, message, customer_id if created)
     */
    public function register(
        string $cnpj,
        string $phone,
        string $contactName,
        ?string $email = null,
        ?string $segment = null
    ): array;

    /**
     * Check B2B registration status by phone or CNPJ
     *
     * @param string $identifier Phone number or CNPJ
     * @return mixed[] Status (not_found, pending, approved, rejected) with details
     */
    public function checkStatus(string $identifier): array;
}
