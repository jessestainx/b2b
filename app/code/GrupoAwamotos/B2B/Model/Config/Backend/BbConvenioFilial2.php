<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

/**
 * Sanitiza e valida o convenio Banco do Brasil da filial 2.
 *
 * Regras atuais (escopo validado da Fase 4):
 * - apenas digitos
 * - comprimento fixo de 7 digitos
 */
class BbConvenioFilial2 extends Value
{
    private const CONVENIO_LENGTH = 7;

    public function beforeSave(): self
    {
        $sanitized = self::sanitizeConvenio((string) $this->getValue());

        if (!self::isValidConvenio($sanitized)) {
            throw new LocalizedException(
                __('O convênio Banco do Brasil da Filial 2 deve conter exatamente %1 dígitos.', self::CONVENIO_LENGTH)
            );
        }

        $this->setValue($sanitized);

        /** @var self $result */
        $result = parent::beforeSave();

        return $result;
    }

    public static function sanitizeConvenio(string $value): string
    {
        return preg_replace('/\D+/', '', trim($value)) ?? '';
    }

    public static function isValidConvenio(string $value): bool
    {
        return strlen($value) === self::CONVENIO_LENGTH;
    }
}
