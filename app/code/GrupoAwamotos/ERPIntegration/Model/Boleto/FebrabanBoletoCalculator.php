<?php

declare(strict_types=1);

namespace GrupoAwamotos\ERPIntegration\Model\Boleto;

/**
 * Calculadora do padrao FEBRABAN de codigo de barras / linha digitavel de boleto bancario.
 *
 * Algoritmo puro (sem I/O, sem consulta a banco de dados) -- validado byte-a-byte contra um
 * boleto REAL emitido pelo Sectra (Banco do Brasil, carteira 017, FILIAL=2/Boomerang,
 * FN_RECEBER.CODIGO=247091, vencimento 2026-08-06, valor R$ 651,61):
 *
 *   Linha digitavel esperada (do PDF): 00190.00009 02467.742009 00003.599172 6 15300000065161
 *   Linha digitavel calculada por esta classe: idem (100% identica).
 *
 * Ver app/code/GrupoAwamotos/B2B/BOLETOS_NFE_IMPLEMENTACAO.md secao Fase 4 para o
 * detalhamento completo da validacao.
 *
 * IMPORTANTE: o "campo livre" (25 digitos) montado em buildCampoLivreBancoBrasil() e
 * especifico do layout usado pelo Sectra para Banco do Brasil (codigo 001) + carteira 017.
 * NAO deve ser usado para outros bancos/carteiras sem nova validacao contra um boleto real.
 */
class FebrabanBoletoCalculator
{
    private const FATOR_VENCIMENTO_BASE_DATE = '2025-02-22';
    private const FATOR_VENCIMENTO_BASE_VALUE = 1000;
    private const CONVENIO_LENGTH = 7;
    private const NOSSO_NUMERO_LENGTH = 10;

    /**
     * Monta o campo livre (25 digitos) no layout Banco do Brasil usado pelo Sectra para
     * carteira "017" (Cobrancao Simples com Registro), validado contra boleto real.
     *
     * @param string $convenio Codigo de convenio da filial (validado: "2467742" para FILIAL=2)
     * @param string $nossoNumero Numero do titulo (FN_RECEBER.NROBOLETO), sera zero-padded a 10 digitos
     * @param string $carteira Codigo de carteira (ex.: "017"); usa-se os 2 ultimos digitos
     */
    public function buildCampoLivreBancoBrasil(string $convenio, string $nossoNumero, string $carteira): string
    {
        $convenioDigits = $this->sanitizeDigits($convenio);
        if (strlen($convenioDigits) !== self::CONVENIO_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('Convenio Banco do Brasil deve ter %d digitos.', self::CONVENIO_LENGTH)
            );
        }

        $nossoNumeroDigits = $this->sanitizeDigits($nossoNumero);
        if ($nossoNumeroDigits === '' || strlen($nossoNumeroDigits) > self::NOSSO_NUMERO_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('Nosso numero deve ter de 1 a %d digitos.', self::NOSSO_NUMERO_LENGTH)
            );
        }

        $carteiraDigits = $this->sanitizeDigits($carteira);
        if ($carteiraDigits === '') {
            throw new \InvalidArgumentException('Carteira deve conter apenas digitos.');
        }

        $nossoNumeroPad = str_pad($nossoNumeroDigits, self::NOSSO_NUMERO_LENGTH, '0', STR_PAD_LEFT);
        $carteira2 = substr(str_pad($carteiraDigits, 2, '0', STR_PAD_LEFT), -2);

        return '000000' . $convenioDigits . $nossoNumeroPad . $carteira2;
    }

    /**
     * Fator de vencimento (4 digitos) na base "nova" adotada pelo FEBRABAN desde 22/02/2025
     * (fator reiniciado em 1000 apos a base antiga de 07/10/1997 esgotar o intervalo util).
     */
    public function fatorVencimento(\DateTimeInterface $vencimento): int
    {
        $base = new \DateTimeImmutable(self::FATOR_VENCIMENTO_BASE_DATE);
        $venc = \DateTimeImmutable::createFromInterface($vencimento)->setTime(0, 0, 0);
        $dias = (int) $base->diff($venc)->format('%r%a');

        return self::FATOR_VENCIMENTO_BASE_VALUE + $dias;
    }

    /**
     * Monta o codigo de barras (44 digitos) e a linha digitavel (com pontuacao) a partir
     * de banco, moeda, vencimento, valor e campo livre (25 digitos ja montados).
     *
     * @return array{barcode: string, linha_digitavel: string}
     */
    public function build(string $banco, string $moeda, \DateTimeInterface $vencimento, float $valor, string $campoLivre25): array
    {
        $bancoDigits = $this->sanitizeDigits($banco);
        if (strlen($bancoDigits) !== 3) {
            throw new \InvalidArgumentException('Codigo do banco deve ter exatamente 3 digitos.');
        }

        $moedaDigits = $this->sanitizeDigits($moeda);
        if (strlen($moedaDigits) !== 1) {
            throw new \InvalidArgumentException('Codigo da moeda deve ter exatamente 1 digito.');
        }

        if ($valor < 0) {
            throw new \InvalidArgumentException('Valor do boleto nao pode ser negativo.');
        }

        $campoLivreDigits = $this->sanitizeDigits($campoLivre25);
        if (strlen($campoLivreDigits) !== 25) {
            throw new \InvalidArgumentException('Campo livre deve ter exatamente 25 digitos numericos.');
        }

        $fator = str_pad((string) $this->fatorVencimento($vencimento), 4, '0', STR_PAD_LEFT);
        $valorCentavos = str_pad((string) (int) round($valor * 100), 10, '0', STR_PAD_LEFT);

        $semDv = $bancoDigits . $moedaDigits . $fator . $valorCentavos . $campoLivreDigits;
        $dvGeral = (string) $this->mod11DvGeral($semDv);

        $barcode = $bancoDigits . $moedaDigits . $dvGeral . $fator . $valorCentavos . $campoLivreDigits;

        $campo1Base = substr($barcode, 0, 4) . substr($campoLivreDigits, 0, 5);
        $campo2Base = substr($campoLivreDigits, 5, 10);
        $campo3Base = substr($campoLivreDigits, 15, 10);

        $campo1 = substr($campo1Base, 0, 5) . '.' . substr($campo1Base, 5) . $this->mod10Dac($campo1Base);
        $campo2 = substr($campo2Base, 0, 5) . '.' . substr($campo2Base, 5) . $this->mod10Dac($campo2Base);
        $campo3 = substr($campo3Base, 0, 5) . '.' . substr($campo3Base, 5) . $this->mod10Dac($campo3Base);
        $campo5 = $fator . $valorCentavos;

        $linhaDigitavel = "{$campo1} {$campo2} {$campo3} {$dvGeral} {$campo5}";

        return [
            'barcode' => $barcode,
            'linha_digitavel' => $linhaDigitavel,
        ];
    }

    /**
     * Digito verificador de campo (Modulo 10 / Luhn), usado nos 3 primeiros campos da
     * linha digitavel (auto-verificacao de cada bloco).
     */
    private function mod10Dac(string $digits): int
    {
        $sum = 0;
        $peso = 2;

        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $prod = ((int) $digits[$i]) * $peso;
            if ($prod > 9) {
                $prod = intdiv($prod, 10) + ($prod % 10);
            }
            $sum += $prod;
            $peso = $peso === 2 ? 1 : 2;
        }

        $resto = $sum % 10;

        return $resto === 0 ? 0 : 10 - $resto;
    }

    /**
     * Digito verificador geral do codigo de barras (Modulo 11), pesos ciclicos 2-9 da
     * direita para a esquerda.
     */
    private function mod11DvGeral(string $digits43): int
    {
        $sum = 0;
        $peso = 2;

        for ($i = strlen($digits43) - 1; $i >= 0; $i--) {
            $sum += ((int) $digits43[$i]) * $peso;
            $peso = $peso === 9 ? 2 : $peso + 1;
        }

        $resto = $sum % 11;

        if ($resto === 0 || $resto === 1) {
            return 1;
        }

        return 11 - $resto;
    }

    private function sanitizeDigits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }
}
