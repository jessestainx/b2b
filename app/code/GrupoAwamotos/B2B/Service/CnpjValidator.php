<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Service;

/**
 * Canonical CNPJ checksum and formatting. No I/O.
 */
class CnpjValidator
{
    /**
     * Keep digits only.
     */
    public function clean(string $cnpj): string
    {
        return (string) preg_replace('/[^0-9]/', '', $cnpj);
    }

    /**
     * Valida CNPJ usando o algoritmo oficial da Receita Federal.
     */
    public function isValid(string $cnpj): bool
    {
        $cnpj = $this->clean($cnpj);

        if (strlen($cnpj) !== 14) {
            return false;
        }

        if (preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        $sum = 0;
        $weights = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $cnpj[$i] * $weights[$i];
        }
        $remainder = $sum % 11;
        $digit1 = $remainder < 2 ? 0 : 11 - $remainder;
        if ((int) $cnpj[12] !== $digit1) {
            return false;
        }

        $sum = 0;
        $weights = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        for ($i = 0; $i < 13; $i++) {
            $sum += (int) $cnpj[$i] * $weights[$i];
        }
        $remainder = $sum % 11;
        $digit2 = $remainder < 2 ? 0 : 11 - $remainder;

        return (int) $cnpj[13] === $digit2;
    }

    /**
     * Formata CNPJ para exibição: 00.000.000/0000-00
     */
    public function format(string $cnpj): string
    {
        $cnpj = $this->clean($cnpj);
        if (strlen($cnpj) !== 14) {
            return $cnpj;
        }

        return sprintf(
            '%s.%s.%s/%s-%s',
            substr($cnpj, 0, 2),
            substr($cnpj, 2, 3),
            substr($cnpj, 5, 3),
            substr($cnpj, 8, 4),
            substr($cnpj, 12, 2)
        );
    }
}
