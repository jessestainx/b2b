<?php

declare(strict_types=1);

namespace GrupoAwamotos\CatalogFix\Model;

/**
 * Normaliza nomes vindos do ERP: Title Case (ALL CAPS) + acentuação PT-BR.
 */
class ErpProductNameNormalizer
{
    /**
     * Palavras que permanecem em maiúsculas (marcas, séries, siglas).
     *
     * @var string[]
     */
    private const FORCE_UPPERCASE = [
        'AWA',
        'CBX',
        'BIZ',
        'NXR',
        'XRE',
        'YBR',
        'GSX',
        'CRF',
        'XTZ',
        'PCX',
        'LED',
        'ABS',
        'EFI',
        'POP',
    ];

    /**
     * Preposições e artigos PT-BR em minúsculas (exceto início).
     *
     * @var string[]
     */
    private const LOWERCASE_WORDS = [
        'de', 'do', 'da', 'dos', 'das',
        'em', 'no', 'na', 'nos', 'nas',
        'ao', 'os', 'as', 'um', 'ou',
        'com', 'sem', 'por',
    ];

    /**
     * Abreviações ERP no início do nome → palavra completa PT-BR.
     * Chave: abreviação em maiúsculas exatamente como no ERP (com ponto).
     * Valor: forma por extenso para exibição (Title Case).
     *
     * @var array<string, string>
     */
    private const PREFIX_EXPANSION = [
        'RET.' => 'Retrovisor',
        'SUP.' => 'Suporte',
    ];

    /**
     * Termos ERP sem acento → forma correta (chave sempre minúscula).
     *
     * @var array<string, string>
     */
    private const ACCENT_MAP = [
        'carcaca' => 'carcaça',
        'carcacas' => 'carcaças',
        'acrilico' => 'acrílico',
        'acrilicos' => 'acrílicos',
        'guidao' => 'guidão',
        'guidoes' => 'guidões',
        'aluminio' => 'alumínio',
        'peca' => 'peça',
        'pecas' => 'peças',
        'valvula' => 'válvula',
        'valvulas' => 'válvulas',
        'oleo' => 'óleo',
        'oleos' => 'óleos',
        'ignicao' => 'ignição',
        'suspensao' => 'suspensão',
        'plastico' => 'plástico',
        'plasticos' => 'plásticos',
        'eletrica' => 'elétrica',
        'eletrico' => 'elétrico',
        'eletricos' => 'elétricos',
        'mecanica' => 'mecânica',
        'mecanico' => 'mecânico',
        'manutencao' => 'manutenção',
        'direcao' => 'direção',
        'transmissao' => 'transmissão',
        'correcao' => 'correção',
        'revisao' => 'revisão',
        'injecao' => 'injeção',
        'vedacao' => 'vedação',
        'fixacao' => 'fixação',
        'conexao' => 'conexão',
        'pressao' => 'pressão',
        'ventilacao' => 'ventilação',
    ];

    public function normalize(?string $name): ?string
    {
        if ($name === null || $name === '') {
            return $name;
        }

        $normalized = $name;
        if ($name === mb_strtoupper($name, 'UTF-8')) {
            $normalized = $this->expandPrefixAbbreviation($name);
            $normalized = $this->toTitleCase($normalized);
        }

        return $this->applyAccentFixes($normalized);
    }

    /**
     * Expande abreviações ERP no início do nome (ex: "RET. BIZ" → "Retrovisor BIZ").
     * Só atua quando o nome está em ALL CAPS (vindo do ERP).
     */
    private function expandPrefixAbbreviation(string $name): string
    {
        foreach (self::PREFIX_EXPANSION as $abbrev => $expanded) {
            $prefix = $abbrev . ' ';
            if (str_starts_with($name, $prefix)) {
                return mb_strtoupper($expanded, 'UTF-8') . ' ' . mb_substr($name, mb_strlen($prefix, 'UTF-8'), null, 'UTF-8');
            }
        }
        return $name;
    }

    private function toTitleCase(string $name): string
    {
        $result = mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');

        $lowercaseMap = array_flip(self::LOWERCASE_WORDS);
        $result = (string) preg_replace_callback(
            '/\b([a-zA-ZÀ-ÿ]{1,2})\b/u',
            static function (array $matches) use ($lowercaseMap): string {
                $lower = mb_strtolower($matches[1], 'UTF-8');
                if (isset($lowercaseMap[$lower])) {
                    return $lower;
                }

                return mb_strtoupper($matches[1], 'UTF-8');
            },
            $result
        );

        $threeLetterLower = array_filter(
            self::LOWERCASE_WORDS,
            static fn (string $word): bool => mb_strlen($word) === 3
        );
        $result = (string) preg_replace_callback(
            '/\b(' . implode('|', $threeLetterLower) . ')\b/iu',
            static fn (array $matches): string => mb_strtolower($matches[1], 'UTF-8'),
            $result
        );

        $result = mb_strtoupper(mb_substr($result, 0, 1, 'UTF-8'), 'UTF-8')
            . mb_substr($result, 1, null, 'UTF-8');

        $pattern = '/\b(' . implode('|', array_map(
            static fn (string $word): string => preg_quote(mb_convert_case($word, MB_CASE_TITLE, 'UTF-8'), '/'),
            self::FORCE_UPPERCASE
        )) . ')\b/u';

        $result = (string) preg_replace_callback(
            $pattern,
            static fn (array $matches): string => mb_strtoupper($matches[1], 'UTF-8'),
            $result
        );

        $result = (string) preg_replace_callback(
            '/\b([a-zA-Z]{1,3})\/([a-zA-Z]{1,3})\b/',
            static fn (array $matches): string => mb_strtoupper($matches[1] . '/' . $matches[2], 'UTF-8'),
            $result
        );

        return $result;
    }

    private function applyAccentFixes(string $name): string
    {
        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($name, \Normalizer::FORM_C);
            if (is_string($normalized)) {
                $name = $normalized;
            }
        }

        foreach (self::ACCENT_MAP as $from => $to) {
            $pattern = '/\b' . preg_quote($from, '/') . '\b/iu';
            $name = (string) preg_replace_callback(
                $pattern,
                fn (array $matches): string => $this->matchReplacementCase($matches[0], $to),
                $name
            );
        }

        return $name;
    }

    private function matchReplacementCase(string $matched, string $replacement): string
    {
        if ($matched === mb_strtoupper($matched, 'UTF-8')) {
            return mb_strtoupper($replacement, 'UTF-8');
        }

        if ($matched === mb_strtolower($matched, 'UTF-8')) {
            return mb_strtolower($replacement, 'UTF-8');
        }

        return mb_convert_case($replacement, MB_CASE_TITLE, 'UTF-8');
    }
}
