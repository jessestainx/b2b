#!/usr/bin/env bash
# setup-chrome-debug-mcp.sh — DESATIVADO (projeto usa somente MCP nativo do Cursor)
echo "chrome-devtools-mcp foi removido deste projeto."
echo "Use cursor-ide-browser (nativo) ou: cd tests/e2e && npx playwright test"
echo "Diagnóstico: scripts/mcp-performance.sh status"
exit 0

# --- legado abaixo (não executado) ---

NODE_BIN="/usr/bin/node"
MCP_BIN="/usr/lib/node_modules/chrome-devtools-mcp/build/src/bin/chrome-devtools-mcp.js"
CDP_URL="${CDP_URL:-http://127.0.0.1:9222}"
OK=0
FAIL=0

pass() { echo "[OK]   $*"; ((OK++)) || true; }
fail() { echo "[FAIL] $*"; ((FAIL++)) || true; }

echo "=== AWA Motos — Chrome DevTools MCP ==="
echo

if [[ -x "$NODE_BIN" ]]; then
  ver=$("$NODE_BIN" -v)
  pass "Node $ver ($NODE_BIN)"
else
  fail "Node não encontrado em $NODE_BIN"
fi

if [[ -f "$MCP_BIN" ]]; then
  ver=$(grep '"version"' /usr/lib/node_modules/chrome-devtools-mcp/package.json | head -1 | sed 's/[^0-9.]//g')
  pass "chrome-devtools-mcp v$ver"
else
  fail "chrome-devtools-mcp não instalado — rode: sudo npm install -g chrome-devtools-mcp@latest"
fi

if command -v playwright-mcp >/dev/null 2>&1; then
  pass "playwright-mcp $(command -v playwright-mcp)"
else
  fail "playwright-mcp não encontrado no PATH"
fi

if curl -sf --connect-timeout 3 "${CDP_URL}/json/version" >/dev/null; then
  browser=$(curl -s "${CDP_URL}/json/version" | grep -o '"Browser": "[^"]*"' | cut -d'"' -f4)
  pass "CDP ativo em ${CDP_URL} — $browser"
else
  pass "CDP parado (esperado no perfil LEVE — scripts/mcp-performance.sh heavy-off)"
  echo "       Para QA visual use Playwright: scripts/mcp-performance.sh heavy-on"
  echo "       CDP externo opcional: sudo systemctl start chrome-cdp-9222.service"
fi

pages=$(curl -sf --connect-timeout 3 "${CDP_URL}/json/list" 2>/dev/null | "$NODE_BIN" -e "let d='';process.stdin.on('data',c=>d+=c);process.stdin.on('end',()=>{try{console.log(JSON.parse(d).length)}catch{console.log(0)}})" 2>/dev/null || echo "0")
pass "Abas abertas via CDP: ${pages}"

echo
if (( FAIL == 0 )); then
  echo "Tudo pronto. Reinicie os MCP servers no Cursor (Settings → MCP → Reload)."
  exit 0
fi

echo "$FAIL verificação(ões) falharam. Corrija acima e rode novamente."
exit 1
