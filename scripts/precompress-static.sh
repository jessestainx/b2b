#!/usr/bin/env bash
# =============================================================================
# precompress-static.sh — Pré-comprimir assets estáticos com Brotli + Gzip
# =============================================================================
# Gera arquivos .br (Brotli nível 11) e .gz (Gzip nível 9) para CSS/JS/SVG
# para uso com brotli_static on e gzip_static on no Nginx.
#
# Uso: bash scripts/precompress-static.sh [--pub-only|--check]
#   (padrão) / --pub-only: comprime apenas pub/static (pós-deploy)
#   --check: valida sidecars em pub e ausência de .br/.gz no tema (somente leitura)
#
# Requer: brotli (apt install brotli)
# =============================================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

# Mínimo de bytes para justificar pré-compressão
MIN_SIZE=1024

# Diretórios fonte
THEME_CSS="$PROJECT_ROOT/app/design/frontend/AWA_Custom/ayo_home5_child/web/css"
THEME_JS="$PROJECT_ROOT/app/design/frontend/AWA_Custom/ayo_home5_child/web/js"
THEME_FONTS="$PROJECT_ROOT/app/design/frontend/AWA_Custom/ayo_home5_child/web/fonts"

# Diretório público
PUB_STATIC="$PROJECT_ROOT/pub/static/frontend/AWA_Custom/ayo_home5_child/pt_BR"

# User path (para symlinks do Magento developer mode)
USER_PATH="/home/user/htdocs/srv1113343.hstgr.cloud"

PUB_ONLY=false
if [[ "${1:-}" == "--check" ]]; then
  # Validação somente leitura (Fase 5) — não comprime.
  exec bash "$(dirname "$0")/check-awa-static-sidecars.sh" --check
fi
[[ "${1:-}" == "--pub-only" ]] && PUB_ONLY=true
[[ "${1:-}" == "" ]] && PUB_ONLY=true  # default seguro: nunca escrever no tema

compress_file() {
    local f="$1"
    local sz
    sz=$(wc -c < "$f")
    if [[ $sz -lt $MIN_SIZE ]]; then
        return
    fi
    brotli -f -q 11 -o "${f}.br" "$f"
    gzip -f -k -9 "$f"
    local br_sz gz_sz pct
    br_sz=$(wc -c < "${f}.br")
    gz_sz=$(wc -c < "${f}.gz")
    pct=$((br_sz * 100 / sz))
    echo "  $(basename "$f"): ${sz}B → br:${br_sz}B (${pct}%) gz:${gz_sz}B"
}

create_pub_symlinks() {
    local src_dir="$1"
    local pub_dir="$2"
    local user_src_dir="$3"
    local count=0

    [[ -d "$pub_dir" ]] || return

    for ext in br gz; do
        for f in "$src_dir"/*."$ext"; do
            [[ -f "$f" ]] || continue
            local base
            base=$(basename "$f")
            ln -sf "$user_src_dir/$base" "$pub_dir/$base"
            count=$((count + 1))
        done
    done
    echo "  → $count symlinks em $(basename "$pub_dir")/"
}

echo "=== AWA Motos — Pré-compressão Estática ==="
echo ""

if [[ "$PUB_ONLY" == true ]]; then
    echo "[pub/static] Comprimindo CSS/JS em pub/static..."
    # Busca todos os .js e .css (excluindo os próprios archivos comprimidos)
    # e recomprime se: .br não existe OU .js é mais novo que .br OU .js é mais novo que .gz
    find "$PUB_STATIC" \( -name '*.css' -o -name '*.js' \) -size "+${MIN_SIZE}c" \
         -not -name '*.min.js.br' -not -name '*.min.js.gz' \
         -not -name '*.css.br' -not -name '*.css.gz' \
         -not -name '*.js.br' -not -name '*.js.gz' | while read -r f; do
        [[ -L "$f" ]] && continue  # pular symlinks
        # Recomprimir se: compressed não existe OU o .js/.css é mais novo que o compressed
        if [[ ! -f "${f}.br" ]] || [[ ! -f "${f}.gz" ]] || \
           [[ "$f" -nt "${f}.br" ]] || [[ "$f" -nt "${f}.gz" ]]; then
            compress_file "$f"
        fi
    done
    echo "Pronto!"
    exit 0
fi

# NOTA: O modo padrão (sem --pub-only) era usado para comprimir no diretório fonte
# e criar symlinks em pub/static. Isso causava mismatches de SRI quando os .js
# eram atualizados sem regenerar os .gz.
# SOLUÇÃO: Sempre use --pub-only APÓS setup:static-content:deploy.
echo "AVISO: Modo padrão redirecionando para --pub-only (compressão direto em pub/static)."
echo "Os arquivos fonte em app/design/ NÃO devem conter .gz/.br."
echo ""

echo "[1/3] Comprimindo JS em pub/static..."
find "$PUB_STATIC/js" -name '*.js' -size "+${MIN_SIZE}c" \
     -not -name '*.js.br' -not -name '*.js.gz' 2>/dev/null | while read -r f; do
    [[ -L "$f" ]] && continue
    if [[ ! -f "${f}.br" ]] || [[ ! -f "${f}.gz" ]] || \
       [[ "$f" -nt "${f}.br" ]] || [[ "$f" -nt "${f}.gz" ]]; then
        compress_file "$f"
    fi
done

echo ""
echo "[2/3] Comprimindo CSS em pub/static..."
find "$PUB_STATIC/css" -name '*.css' -size "+${MIN_SIZE}c" \
     -not -name '*.css.br' -not -name '*.css.gz' 2>/dev/null | while read -r f; do
    [[ -L "$f" ]] && continue
    if [[ ! -f "${f}.br" ]] || [[ ! -f "${f}.gz" ]] || \
       [[ "$f" -nt "${f}.br" ]] || [[ "$f" -nt "${f}.gz" ]]; then
        compress_file "$f"
    fi
done

echo ""
echo "[3/3] Concluído."
echo ""
echo "=== Pré-compressão concluída ==="
echo "Nginx serve automaticamente via brotli_static on / gzip_static on"
