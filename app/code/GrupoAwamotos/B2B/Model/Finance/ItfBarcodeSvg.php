<?php

declare(strict_types=1);

namespace GrupoAwamotos\B2B\Model\Finance;

/**
 * Renderiza codigo de barras ITF (Interleaved 2-of-5) como SVG.
 *
 * Usado na pagina de impressao do boleto para nao depender de RequireJS/mage-init
 * (o iframe do modal de impressao pode nao completar o bootstrap Magento a tempo).
 */
class ItfBarcodeSvg
{
    private const PATTERNS = [
        '0' => 'NNWWN',
        '1' => 'WNNNW',
        '2' => 'NWNNW',
        '3' => 'WWNNN',
        '4' => 'NNWNW',
        '5' => 'WNWNN',
        '6' => 'NWWNN',
        '7' => 'NNNWW',
        '8' => 'WNNWN',
        '9' => 'NWNWN',
    ];

    private const NARROW = 1;
    private const WIDE = 3;

    /**
     * @return string SVG markup seguro para output HTML
     */
    public function render(string $digits, int $unitPx = 2, int $heightPx = 60): string
    {
        $digits = preg_replace('/\D+/', '', $digits) ?? '';
        if ($digits === '') {
            return '';
        }

        if (strlen($digits) % 2 !== 0) {
            $digits = '0' . $digits;
        }

        $bars = [];
        $this->pushBar($bars, self::NARROW, true);
        $this->pushBar($bars, self::NARROW, false);
        $this->pushBar($bars, self::NARROW, true);
        $this->pushBar($bars, self::NARROW, false);

        $len = strlen($digits);
        for ($i = 0; $i < $len; $i += 2) {
            $barPattern = self::PATTERNS[$digits[$i]] ?? null;
            $spacePattern = self::PATTERNS[$digits[$i + 1]] ?? null;
            if ($barPattern === null || $spacePattern === null) {
                continue;
            }
            for ($p = 0; $p < 5; $p++) {
                $this->pushBar($bars, $barPattern[$p] === 'W' ? self::WIDE : self::NARROW, true);
                $this->pushBar($bars, $spacePattern[$p] === 'W' ? self::WIDE : self::NARROW, false);
            }
        }

        $this->pushBar($bars, self::WIDE, true);
        $this->pushBar($bars, self::NARROW, false);
        $this->pushBar($bars, self::NARROW, true);

        $totalUnits = 0;
        foreach ($bars as $bar) {
            $totalUnits += $bar['units'];
        }
        $width = max(1, $totalUnits * $unitPx);

        $rects = [];
        $x = 0;
        foreach ($bars as $bar) {
            $w = $bar['units'] * $unitPx;
            if ($bar['black']) {
                $rects[] = sprintf(
                    '<rect x="%d" y="0" width="%d" height="%d" fill="#000"/>',
                    $x,
                    $w,
                    $heightPx
                );
            }
            $x += $w;
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Codigo de barras"'
            . ' width="%d" height="%d" viewBox="0 0 %d %d"'
            . ' style="display:block;width:100%%;max-width:%dpx;height:auto;background:#fff">'
            . '%s</svg>',
            $width,
            $heightPx,
            $width,
            $heightPx,
            $width,
            implode('', $rects)
        );
    }

    /**
     * @param array<int, array{units: int, black: bool}> $bars
     */
    private function pushBar(array &$bars, int $units, bool $black): void
    {
        $bars[] = ['units' => $units, 'black' => $black];
    }
}
