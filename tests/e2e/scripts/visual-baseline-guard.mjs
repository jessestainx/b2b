import fs from 'node:fs';
import path from 'node:path';

const root = process.cwd();
const args = process.argv.slice(2);

function getArgValue(flag, fallback = '') {
  const eq = args.find((arg) => arg.startsWith(`${flag}=`));
  if (eq) return eq.slice(flag.length + 1);
  const idx = args.indexOf(flag);
  if (idx >= 0 && args[idx + 1] && !args[idx + 1].startsWith('--')) return args[idx + 1];
  return fallback;
}

function hasFlag(flag) {
  return args.includes(flag);
}

const scope = getArgValue('--scope', 'mcp'); // mcp | all
const strict = hasFlag('--strict') || scope === 'mcp';
const maxDetails = Number.parseInt(getArgValue('--max-details', '40'), 10) || 40;

const reportsDir = path.join(root, 'reports');
const reportFile = path.join(reportsDir, 'visual-baseline-guard.json');

function ensureDir(dirPath) {
  fs.mkdirSync(dirPath, { recursive: true });
}

function readText(filePath) {
  try {
    return fs.readFileSync(filePath, 'utf8');
  } catch {
    return '';
  }
}

function unique(values) {
  return [...new Set(values)];
}

function collectMcpSlugs() {
  const helperPath = path.join(root, 'helpers', 'mcp-visual-ops.helpers.ts');
  const text = readText(helperPath);
  const slugs = [];
  const regex = /slug:\s*'([^']+)'/g;
  let match;
  while ((match = regex.exec(text)) !== null) {
    slugs.push(match[1]);
  }
  return unique(slugs);
}

function collectMcpProjects() {
  const configFiles = [
    path.join(root, 'pw-mcp-visual.config.ts'),
    path.join(root, 'pw-mcp-visual.safe.config.ts'),
  ];

  const projects = [];
  const regex = /name:\s*'([^']+)'/g;

  for (const file of configFiles) {
    const text = readText(file);
    let match;
    while ((match = regex.exec(text)) !== null) {
      projects.push(match[1]);
    }
  }

  return unique(projects).sort();
}

function checkMcpBaselines() {
  const updateMode = process.env.MCP_VISUAL_UPDATE_BASELINE === '1';
  const slugs = collectMcpSlugs();
  const projects = collectMcpProjects();
  const baseDir = path.join(root, 'snapshots', 'mcp-visual-baseline');
  const issues = [];

  for (const project of projects) {
    for (const slug of slugs) {
      const expected = path.join(baseDir, project, `${slug}.png`);
      if (!fs.existsSync(expected)) {
        issues.push({
          type: 'mcp-baseline-missing',
          project,
          slug,
          expected: path.relative(root, expected),
          severity: updateMode ? 'minor' : 'major',
        });
      }
    }
  }

  return {
    check: 'mcp',
    updateMode,
    slugs,
    projects,
    expectedTotal: slugs.length * projects.length,
    missing: issues,
  };
}

function listFilesRec(dirPath, extension = '.ts') {
  const out = [];

  function walk(current) {
    const entries = fs.readdirSync(current, { withFileTypes: true });
    for (const entry of entries) {
      const full = path.join(current, entry.name);
      if (entry.isDirectory()) walk(full);
      else if (entry.isFile() && full.endsWith(extension)) out.push(full);
    }
  }

  if (fs.existsSync(dirPath)) walk(dirPath);
  return out;
}

function checkPlaywrightSnapshots() {
  const specsRoot = path.join(root, 'specs');
  const snapshotRoot = path.join(root, 'snapshots'); // snapshotDir configurado nos pw-*.config.ts
  const files = listFilesRec(specsRoot, '.ts');
  const issues = [];
  let specsWithScreenshotAssertion = 0;

  for (const filePath of files) {
    const text = readText(filePath);
    if (!text.includes('toHaveScreenshot(')) continue;

    specsWithScreenshotAssertion += 1;

    // Playwright default: <spec>-snapshots ao lado da spec.
    // Configs AWA (snapshotDir): snapshots/<relativo-a-specs>-snapshots.
    const candidates = [
      `${filePath}-snapshots`,
      path.join(snapshotRoot, `${path.relative(specsRoot, filePath)}-snapshots`),
    ];

    const snapshotDir = candidates.find((candidate) => fs.existsSync(candidate));
    const pngCount = snapshotDir
      ? listFilesRec(snapshotDir, '.png').length + listFilesRec(snapshotDir, '.webp').length
      : 0;

    if (!snapshotDir || pngCount === 0) {
      issues.push({
        type: 'playwright-snapshot-missing',
        spec: path.relative(root, filePath),
        snapshotDir: path.relative(root, candidates[1]),
        pngCount,
        severity: 'major',
      });
    }
  }

  return {
    check: 'playwright-snapshots',
    specsWithScreenshotAssertion,
    missing: issues,
  };
}

function main() {
  ensureDir(reportsDir);

  const mcp = checkMcpBaselines();
  const checks = [mcp];
  if (scope === 'all') checks.push(checkPlaywrightSnapshots());

  const issues = checks.flatMap((c) => c.missing || []);
  const blockingIssues = issues.filter((i) => i.severity !== 'minor');

  const summary = {
    generatedAt: new Date().toISOString(),
    scope,
    strict,
    checks,
    totals: {
      issues: issues.length,
      blockingIssues: blockingIssues.length,
    },
  };

  fs.writeFileSync(reportFile, JSON.stringify(summary, null, 2), 'utf8');

  console.log('=== Visual Baseline Guard ===');
  console.log(`Scope: ${scope}`);
  console.log(`Strict: ${strict ? 'yes' : 'no'}`);
  console.log(`Issues: ${issues.length} (blocking: ${blockingIssues.length})`);
  console.log(`Report: ${path.relative(root, reportFile)}`);

  if (issues.length > 0) {
    console.log('--- Details (first entries) ---');
    for (const issue of issues.slice(0, maxDetails)) {
      if (issue.type === 'mcp-baseline-missing') {
        console.log(`[${issue.severity}] MCP baseline missing: ${issue.project}/${issue.slug} -> ${issue.expected}`);
      } else if (issue.type === 'playwright-snapshot-missing') {
        console.log(`[${issue.severity}] Snapshot missing: ${issue.spec} -> ${issue.snapshotDir}`);
      }
    }
  }

  if (strict && blockingIssues.length > 0) {
    console.error('Baseline guard failed: blocking baseline issues found.');
    process.exit(1);
  }

  console.log('Baseline guard finished.');
}

main();
