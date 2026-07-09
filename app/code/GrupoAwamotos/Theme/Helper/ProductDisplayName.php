<?php

declare(strict_types=1);

namespace GrupoAwamotos\Theme\Helper;

/**
 * Normaliza nomes de produto com entidades HTML duplamente codificadas.
 */
class ProductDisplayName
{
    /**
     * @param string|null $name
     */
    public static function normalize(?string $name): string
    {
        $normalized = trim(html_entity_decode((string) $name, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        for ($i = 0; $i < 3; $i++) {
            $decoded = html_entity_decode($normalized, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded === $normalized) {
                break;
            }
            $normalized = $decoded;
        }

        return trim($normalized);
    }
}
