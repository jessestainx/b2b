#!/usr/bin/env bash
# audit-repo-readonly.sh — inventário e checagens estáticas. Nunca altera o repositório.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
OUT_DIR="${AUDIT_OUT_DIR:-$ROOT_DIR/artifacts/audit-readonly}"
mkdir -p "$OUT_DIR"
umask 077

log() { printf '%s\n' "$*"; }

{
  log "# Relatório de auditoria somente leitura"
  log ""
  log "- gerado_em: $(date -u +%Y-%m-%dT%H:%M:%SZ)"
  log "- root: \`$ROOT_DIR\`"
  log "- hostname: \`$(hostname)\`"
  log "- git_branch: \`$(git -C "$ROOT_DIR" rev-parse --abbrev-ref HEAD 2>/dev/null || echo n/d)\`"
  log "- git_commit: \`$(git -C "$ROOT_DIR" rev-parse --short HEAD 2>/dev/null || echo n/d)\`"
  log "- modo: leitura (sem deploy, sem cache Magento, sem SSH)"
  log ""
} | tee "$OUT_DIR/00-header.md"

{
  log "# Workflows GitHub Actions"
  log ""
  if [[ -d "$ROOT_DIR/.github/workflows" ]]; then
    find "$ROOT_DIR/.github/workflows" -maxdepth 1 -type f \( -name '*.yml' -o -name '*.yaml' \) | sort | while read -r wf; do
      rel="${wf#"$ROOT_DIR"/}"
      name="$(grep -E '^name:' "$wf" | head -1 | sed 's/^name:[[:space:]]*//')"
      triggers="$(python3 -c '
import re, sys
text = open(sys.argv[1], encoding="utf-8", errors="replace").read().splitlines()
keys = []
on = False
for line in text:
    if re.match(r"^on:\s*$", line):
        on = True
        continue
    if on:
        if line and not line.startswith(" ") and not line.startswith("\t"):
            break
        m = re.match(r"^  ([A-Za-z0-9_]+):", line)
        if m:
            keys.append(m.group(1))
print(", ".join(keys) or "n/d")
' "$wf")"
      log "- \`$rel\` — ${name:-sem nome} — on: ${triggers}"
    done
  else
    log "- nenhum workflow encontrado"
  fi
  log ""
} | tee "$OUT_DIR/01-workflows.md"

{
  log "# Módulos customizados (module.xml)"
  log ""
  if [[ -d "$ROOT_DIR/app/code/GrupoAwamotos" ]]; then
    find "$ROOT_DIR/app/code/GrupoAwamotos" -path '*/etc/module.xml' -type f | sort | while read -r xml; do
      rel="${xml#"$ROOT_DIR"/}"
      mod="$(python3 -c '
import re, sys
text = open(sys.argv[1], encoding="utf-8", errors="replace").read()
m = re.search(r"name=\"([^\"]+)\"", text)
print(m.group(1) if m else "n/d")
' "$xml" 2>/dev/null || true)"
      log "- \`$rel\` — ${mod:-n/d}"
    done
  else
    log "- app/code/GrupoAwamotos ausente neste checkout"
  fi
  log ""
} | tee "$OUT_DIR/02-modules.md"

{
  log "# Varredura de padrões de segredo (caminho + tipo, sem trecho)"
  log ""
  log "Ignora vendor, node_modules, pub/static, var, generated, .git, env.php, auth.json, .env."
  log ""
  python3 -c '
import os, re, sys
root, out = sys.argv[1], sys.argv[2]
patterns = [
    ("github_pat", re.compile(r"ghp_[A-Za-z0-9]{20,}")),
    ("github_oauth", re.compile(r"gho_[A-Za-z0-9]{20,}")),
    ("aws_access_key", re.compile(r"AKIA[0-9A-Z]{16}")),
    ("private_key_header", re.compile(r"-----BEGIN (RSA |OPENSSH |EC )?PRIVATE KEY-----")),
    ("slack_token", re.compile(r"xox[baprs]-[A-Za-z0-9-]{10,}")),
]
skip_parts = ("/vendor/", "/node_modules/", "/pub/static/", "/var/", "/generated/", "/.git/", "/dev/tests/")
skip_names = {"env.php", "auth.json", ".git-credentials", ".env"}
hits = []
for dirpath, dirnames, filenames in os.walk(root):
    dirnames[:] = [d for d in dirnames if d not in {".git", "vendor", "node_modules", "generated"}]
    rel_dir = os.path.relpath(dirpath, root)
    probe = "/" + rel_dir.replace("\\", "/") + "/"
    if any(p in probe for p in skip_parts):
        continue
    for name in filenames:
        if name in skip_names:
            continue
        path = os.path.join(dirpath, name)
        rel = os.path.relpath(path, root)
        if any(p in "/" + rel.replace("\\", "/") for p in skip_parts):
            continue
        try:
            if os.path.getsize(path) > 2_000_000:
                continue
            data = open(path, "rb").read().decode("utf-8", errors="ignore")
        except OSError:
            continue
        for label, rx in patterns:
            if rx.search(data):
                hits.append((rel, label))
with open(out, "w", encoding="utf-8") as fh:
    fh.write("path\tpattern\n")
    for rel, label in hits:
        fh.write("%s\t%s\n" % (rel, label))
print("ocorrencias: %d" % len(hits))
for rel, label in hits[:50]:
    print("- `%s` — %s" % (rel, label))
if len(hits) > 50:
    print("- … +%d (ver TSV)" % (len(hits) - 50))
if not hits:
    print("- nenhuma ocorrência nos padrões rastreados")
' "$ROOT_DIR" "$OUT_DIR/03-secret-patterns.tsv"
  log ""
} | tee "$OUT_DIR/03-secret-scan.md"

{
  log "# Composer"
  log ""
  if [[ -f "$ROOT_DIR/composer.json" ]]; then
    if (cd "$ROOT_DIR" && composer validate --no-check-all --no-interaction --quiet); then
      log "- composer.json: válido"
    else
      log "- composer.json: validação falhou"
    fi
  else
    log "- composer.json ausente"
  fi
  log ""
} | tee "$OUT_DIR/04-composer.md"

cat "$OUT_DIR"/00-header.md "$OUT_DIR"/01-workflows.md "$OUT_DIR"/02-modules.md "$OUT_DIR"/03-secret-scan.md "$OUT_DIR"/04-composer.md > "$OUT_DIR/REPORT.md"
log "Relatório consolidado: $OUT_DIR/REPORT.md"
