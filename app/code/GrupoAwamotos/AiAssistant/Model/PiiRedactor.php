<?php

declare(strict_types=1);

namespace GrupoAwamotos\AiAssistant\Model;

/**
 * Redacts personal and secret data before LLM calls and persistence.
 */
class PiiRedactor
{
    private const PLACEHOLDER_EMAIL = '[email]';
    private const PLACEHOLDER_CPF = '[cpf]';
    private const PLACEHOLDER_CNPJ = '[cnpj]';
    private const PLACEHOLDER_PHONE = '[telefone]';
    private const PLACEHOLDER_CARD = '[cartao]';
    private const PLACEHOLDER_SECRET = '[redigido]';

    /**
     * @var list<string>
     */
    private const SECRET_LABELS = [
        'senha',
        'password',
        'passwd',
        'token',
        'api_key',
        'apikey',
        'secret',
        'cvv',
        'cvc',
        'cid',
        'form_key',
        'authorization',
        'bearer',
    ];

    public function containsSecret(string $text): bool
    {
        $normalized = mb_strtolower($text);
        foreach (self::SECRET_LABELS as $label) {
            if (preg_match('/\b' . preg_quote($label, '/') . '\b/u', $normalized) === 1) {
                return true;
            }
        }

        if (preg_match('/\b\d{4}[ -]\d{4}[ -]\d{4}[ -]\d{1,7}\b/', $text) === 1) {
            return true;
        }

        return false;
    }

    public function redact(string $text): string
    {
        if ($text === '') {
            return $text;
        }

        $redacted = $text;
        $redacted = preg_replace(
            '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/iu',
            self::PLACEHOLDER_EMAIL,
            $redacted
        ) ?? $redacted;
        $redacted = preg_replace(
            '/\b\d{2}\.?\d{3}\.?\d{3}\/?\d{4}-?\d{2}\b/',
            self::PLACEHOLDER_CNPJ,
            $redacted
        ) ?? $redacted;
        $redacted = preg_replace(
            '/\b\d{3}\.\d{3}\.\d{3}-?\d{2}\b/',
            self::PLACEHOLDER_CPF,
            $redacted
        ) ?? $redacted;
        $redacted = preg_replace(
            '/\b\d{4}[ -]\d{4}[ -]\d{4}[ -]\d{1,7}\b/',
            self::PLACEHOLDER_CARD,
            $redacted
        ) ?? $redacted;
        $redacted = preg_replace(
            '/(?:\+55\s*)?\(?\d{2}\)?\s*9?\d{4}-?\d{4}\b/',
            self::PLACEHOLDER_PHONE,
            $redacted
        ) ?? $redacted;
        $redacted = preg_replace(
            '/\b(?:' . implode('|', array_map(static fn (string $l): string => preg_quote($l, '/'), self::SECRET_LABELS))
            . ')\b\s*[:=]\s*\S+/iu',
            self::PLACEHOLDER_SECRET,
            $redacted
        ) ?? $redacted;

        return $redacted;
    }
}
