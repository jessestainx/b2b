/**
 * Renderizador de codigo de barras Interleaved 2-of-5 (ITF) para boletos bancarios.
 *
 * Implementacao propria (sem biblioteca externa) do padrao ITF usado no codigo de barras
 * de 44 digitos do boleto FEBRABAN. Tabela de larguras (N=estreita, W=larga) por digito
 * e o padrao amplamente documentado do ITF.
 *
 * A linha digitavel (texto) e a fonte de verdade validada para pagamento -- este grafico
 * e uma representacao visual adicional.
 */
define([], function () {
    'use strict';

    var PATTERNS = {
        '0': 'NNWWN',
        '1': 'WNNNW',
        '2': 'NWNNW',
        '3': 'WWNNN',
        '4': 'NNWNW',
        '5': 'WNWNN',
        '6': 'NWWNN',
        '7': 'NNNWW',
        '8': 'WNNWN',
        '9': 'NWNWN'
    };

    var NARROW = 1;
    var WIDE = 3;

    /**
     * Renderiza o codigo de barras ITF dentro do elemento informado.
     *
     * @param {string} digits Sequencia numerica (deve ter comprimento par)
     * @param {HTMLElement} container Elemento onde os elementos de barra serao inseridos
     * @param {number} [unitPx] Largura em pixels de uma unidade "estreita"
     * @param {number} [heightPx] Altura em pixels das barras
     */
    function render(digits, container, unitPx, heightPx) {
        digits = String(digits).replace(/\D/g, '');
        unitPx = unitPx || 2;
        heightPx = heightPx || 60;

        if (digits.length % 2 !== 0) {
            digits = '0' + digits;
        }

        container.innerHTML = '';
        container.style.display = 'flex';
        container.style.alignItems = 'flex-end';
        container.style.height = heightPx + 'px';

        function addBar(widthUnits, isBar) {
            var el = document.createElement('span');
            el.style.display = 'inline-block';
            el.style.width = (widthUnits * unitPx) + 'px';
            el.style.height = '100%';
            el.style.background = isBar ? '#000' : 'transparent';
            container.appendChild(el);
        }

        // Start pattern: bar-space-bar-space, todas estreitas
        addBar(NARROW, true);
        addBar(NARROW, false);
        addBar(NARROW, true);
        addBar(NARROW, false);

        for (var i = 0; i < digits.length; i += 2) {
            var barDigit = digits[i];
            var spaceDigit = digits[i + 1];
            var barPattern = PATTERNS[barDigit];
            var spacePattern = PATTERNS[spaceDigit];

            if (!barPattern || !spacePattern) {
                continue;
            }

            for (var p = 0; p < 5; p++) {
                addBar(barPattern[p] === 'W' ? WIDE : NARROW, true);
                addBar(spacePattern[p] === 'W' ? WIDE : NARROW, false);
            }
        }

        // Stop pattern: barra larga, espaco estreito, barra estreita
        addBar(WIDE, true);
        addBar(NARROW, false);
        addBar(NARROW, true);
    }

    return {
        render: render
    };
});
