/**
 * Auditoria visual profunda — implantação do plano Fase 0/1 (sem alterações funcionais)
 * - inventário de páginas por área
 * - validação por viewport: 390x844, 768x1024, 1366x768, 1920x1080
 * - links, botões, formulários, layout/overflow e erros JS
 * - screenshot full-page e JSON consolidado por severidade
 */
import { test, type Page, type Response } from '@playwright/test';
import fs from 'fs';
import path from 'path';
import {
  hasNoOverflow,
  isVisible,
  COMMON,
  navigateTo,
  dismissCookie,
} from '../helpers/visual-audit.helpers';
import {
  collectConsoleErrors,
  collectNetworkErrors,
  filter404s,
  filter500s,
  filterCriticalJsErrors,
  checkOverflow,
  findBrokenImages,
  getViewportLabel,
} from '../helpers/deep-audit.helpers';

type Severity = 'P0' | 'P1' | 'P2' | 'P3';
type Phase = 'FASE_0' | 'FASE_1' | 'FASE_2' | 'INFRA';

type DeviceViewport = { label: string; width: number; height: number };

interface RouteTarget {
  label: string;
  path: string;
  expectStatus?: number;
  expectNotStatus?: number;
  expectRoot?: boolean;
  expectedSelector?: string;
  criticalSelectors?: string[];
  optionalSelectors?: string[];
}

interface Issue {
  phase: Phase;
  severity: Severity;
  route: string;
  viewport: string;
  component: string;
  step: string;
  description: string;
  evidence: string;
}

interface LinkSample {
  selector: string;
  href: string;
  text: string;
  scope: 'header' | 'main' | 'footer' | 'other';
}

interface ButtonSample {
  tag: string;
  selector: string;
  width: number;
  height: number;
  x: number;
  y: number;
}

const BASE_URL = process.env.AWA_BASE_URL || 'https://awamotos.com';
const TARGET_ROUTES = (process.env.IMPECCABLE_TARGET_ROUTES || '').split(',').map((value) => value.trim()).filter(Boolean);
const TARGET_VIEWPORTS = (process.env.IMPECCABLE_TARGET_VIEWPORTS || '').split(',').map((value) => value.trim()).filter(Boolean);
const SAVE_SCREENSHOTS = process.env.IMPECCABLE_KEEP_SCREENSHOTS !== '0';
const REPORT_DIR = path.join(__dirname, '../reports');
const SCREENSHOT_DIR = path.join(__dirname, '../screenshots/impeccable-visual-deep-audit');
const REPORT_FILE = path.join(REPORT_DIR, 'impeccable-visual-deep-audit.json');

const VIEWPORTS: DeviceViewport[] = [
  { label: '390x844', width: 390, height: 844 },
  { label: '768x1024', width: 768, height: 1024 },
  { label: '1366x768', width: 1366, height: 768 },
  { label: '1920x1080', width: 1920, height: 1080 },
];

const ROUTES: RouteTarget[] = [
  {
    label: 'home',
    path: '/',
    expectedSelector: '.page-wrapper, .page-main, #maincontent, .cms-index-index',
    criticalSelectors: [COMMON.header, COMMON.minicart],
    optionalSelectors: ['.navigation, .nav-sections'],
    expectRoot: true,
  },
  {
    label: 'categoria-bagageiros',
    path: '/bagageiros.html',
    expectedSelector: '.toolbar-products, .catalog-category-view',
    criticalSelectors: [COMMON.search, '#layered-filter-block, .filter-options'],
  },
  {
    label: 'categoria-filtro-de-oleo',
    path: '/filtro-de-oleo.html',
    expectedSelector: '.toolbar-products, .catalog-category-view, #maincontent',
    criticalSelectors: [COMMON.search, '.product-item'],
  },
  {
    label: 'search-retrovisor',
    path: '/catalogsearch/result/?q=retrovisor',
    expectedSelector: '.search.results, .products.wrapper',
    criticalSelectors: [COMMON.search, '.item-product, .product-item'],
  },
  {
    label: 'pdp-bagageiro',
    path: '/bagageiro-titan-125-modelo-05-08-fan-125-modelo-07-08-fumacado-3039.html',
    expectedSelector: '.product-info-main, .product.media',
    criticalSelectors: ['#product-addtocart-button, .action.tocart', '.price-box', '.product-info-main h1'],
  },
  {
    label: 'cart',
    path: '/checkout/cart/',
    expectedSelector: '.cart-container, .checkout-cart-index',
    criticalSelectors: ['.cart.main.actions, .action.primary', '.items', '.total'],
  },
  {
    label: 'checkout',
    path: '/checkout/',
    expectedSelector: '.checkout-container, .opc-wrapper, .checkout-index-index',
    optionalSelectors: ['#shipping, .checkout-shipping-address, .payment-methods'],
    expectStatus: 200,
  },
  {
    label: 'b2b-login',
    path: '/b2b/account/login/',
    expectedSelector: '#b2b-login-shell, form, #b2b-email, #b2b-pass',
    criticalSelectors: ['#b2b-btn-entrar, .b2b-btn-entrar, button[type="submit"]'],
    expectStatus: 200,
  },
  {
    label: 'b2b-dashboard',
    path: '/b2b/account/dashboard/',
    expectedSelector: '#customer-account-dashboard, .block-dashboard-info',
    expectStatus: 200,
    optionalSelectors: ['.b2b-dashboard', '.page-title'],
    expectNotStatus: 404,
  },
  {
    label: 'b2b-shoppinglist',
    path: '/b2b/shoppinglist/',
    expectedSelector: '.b2b-shoppinglist, .column.main',
    expectStatus: 200,
    expectNotStatus: 404,
  },
  {
    label: 'b2b-quote',
    path: '/b2b/quote/',
    expectedSelector: '.main-column, .column.main',
    expectStatus: 200,
    expectNotStatus: 404,
  },
  {
    label: 'b2b-reorder-history',
    path: '/b2b/reorder/history/',
    expectedSelector: '.page-main .column.main, .reorder',
    expectStatus: 200,
    expectNotStatus: 404,
  },
  {
    label: 'sales-order-history',
    path: '/sales/order/history/',
    expectedSelector: '.orders-history, .orders-quickview, .table-order-items',
    expectStatus: 200,
    expectNotStatus: 404,
  },
  {
    label: 'customer-address',
    path: '/customer/address/',
    expectedSelector: '.page-title, .form-address-edit, .field',
    expectStatus: 200,
    expectNotStatus: 404,
  },
  {
    label: 'institucional-parceiro',
    path: '/seja-nosso-parceiro',
    expectedSelector: '.page-title, main, #maincontent',
    optionalSelectors: ['.breadcrumbs', '.page-content'],
  },
  {
    label: 'institucional-termos',
    path: '/privacy-policy-cookie-restriction-mode',
    expectedSelector: '.page-title, .policy',
    optionalSelectors: ['.cms-content', '.page-main'],
  },
  {
    label: 'faq',
    path: '/faq',
    expectedSelector: '.page-title, .faq, #maincontent',
    optionalSelectors: ['.page-main'],
  },
  {
    label: 'customer-service',
    path: '/customer-service',
    expectedSelector: '.page-title, #maincontent, .page-main',
    optionalSelectors: ['.contact-form', '.page-wrapper'],
  },
  {
    label: '404',
    path: '/pagina-inexistente-audit-2026-06',
    expectStatus: 404,
    expectNotStatus: 200,
    expectedSelector: '.page-title, .page-main, .error-page',
  },
];

const ROUTES_TO_AUDIT = TARGET_ROUTES.length
  ? ROUTES.filter((route) => TARGET_ROUTES.includes(route.label) || TARGET_ROUTES.includes(route.path))
  : ROUTES;

const VIEWPORTS_TO_AUDIT = TARGET_VIEWPORTS.length
  ? VIEWPORTS.filter((viewport) => TARGET_VIEWPORTS.includes(viewport.label))
  : VIEWPORTS;

const issues: Issue[] = [];

function addIssue(issue: Issue): void {
  issues.push(issue);
  console.warn(`[${issue.severity}] ${issue.route} [${issue.viewport}] ${issue.component} / ${issue.step}: ${issue.description}`);
}

function toSafeFileName(value: string): string {
  return value
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-zA-Z0-9_-]+/g, '-')
    .replace(/-+/g, '-')
    .replace(/^-|-$/g, '')
    .toLowerCase();
}

function ensureDir() {
  fs.mkdirSync(REPORT_DIR, { recursive: true });
  fs.mkdirSync(SCREENSHOT_DIR, { recursive: true });
}

async function takeFullScreenshot(page: Page, route: RouteTarget, viewport: DeviceViewport): Promise<string> {
  if (!SAVE_SCREENSHOTS) {
    return `${toSafeFileName(route.label)}-${viewport.label}.png`;
  }
  ensureDir();
  const fileName = `${toSafeFileName(route.label)}-${viewport.label}.png`;
  const filePath = path.join(SCREENSHOT_DIR, fileName);
  await page.screenshot({ path: filePath, fullPage: true, timeout: 60_000 }).catch(() => null);
  return filePath;
}

async function openRoute(page: Page, route: RouteTarget): Promise<{ responseStatus: number | null; finalUrl: string }>
{
  const response = await page.goto(`${BASE_URL}${route.path}`, { waitUntil: 'domcontentloaded', timeout: 80_000 }).catch(() => null);
  await page.waitForLoadState('domcontentloaded').catch(() => {});
  await dismissCookie(page);
  return {
    responseStatus: response ? response.status() : null,
    finalUrl: page.url(),
  };
}

async function collectOverflowNodes(page: Page): Promise<string[]> {
  const entries = await page.evaluate(() => {
    const vw = window.innerWidth;
    const out: string[] = [];
    const limit = 8;
    const all = Array.from(document.querySelectorAll('*'));
    for (const el of all) {
      const rect = el.getBoundingClientRect();
      if (rect.width === 0 || rect.height === 0) continue;
      const style = window.getComputedStyle(el);
      if (style.visibility === 'hidden' || style.display === 'none') continue;
      if (rect.right > vw + 6 || rect.left < -6) {
        const tag = el.tagName.toLowerCase();
        const id = el.id ? `#${el.id}` : '';
        const cls = (el.className && typeof el.className === 'string') ? `.${el.className.trim().split(/\s+/).slice(0, 2).join('.')}` : '';
        out.push(`${tag}${id}${cls}`);
      }
      if (out.length >= limit) break;
    }
    return out;
  });
  return entries;
}

async function collectLinkCandidates(page: Page): Promise<LinkSample[]> {
  return page.evaluate(() => {
    const byScope = (scope: 'header' | 'main' | 'footer' | 'other', selector: string): HTMLAnchorElement[] => {
      const nodes = Array.from(document.querySelectorAll(selector)) as HTMLAnchorElement[];
      return nodes.filter((node) => node instanceof HTMLAnchorElement && !!node.getAttribute('href')).slice(0, 12);
    };

    const toSample = (scope: LinkSample['scope'], el: HTMLAnchorElement) => ({
      selector: (el.id ? `#${el.id}` : (el.className ? `.${String(el.className).split(/\s+/).join('.').slice(0, 80)}` : el.tagName.toLowerCase())),
      href: el.getAttribute('href') || '',
      text: (el.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 90),
      scope,
    });

    const headerNodes = byScope('header', 'header a[href]');
    const footerNodes = byScope('footer', 'footer a[href]');
    const mainNodes = byScope('main', 'main a[href]');
    const extraNodes = byScope('other', '[class*=nav] a[href], .pagebuilder a[href], .breadcrumbs a[href], .product-item a[href]');

    const map = new Map<string, LinkSample>();
    [...headerNodes, ...mainNodes, ...footerNodes, ...extraNodes].forEach((a) => {
      const href = a.getAttribute('href') || '';
      if (!href || href.startsWith('javascript:') || href.startsWith('#')) return;
      const key = `${href}|${(a.textContent || '').trim()}`;
      if (!map.has(key)) {
        map.set(key, toSample('other', a));
      }
    });

    const ordered: LinkSample[] = [];
    const add = (scope: LinkSample['scope'], nodes: HTMLAnchorElement[]) => {
      for (const a of nodes) {
        const href = a.getAttribute('href') || '';
        const key = `${href}|${(a.textContent || '').trim()}`;
        const sample = map.get(key);
        if (!sample) continue;
        ordered.push({ ...sample, scope });
        if (ordered.length >= 16) break;
      }
    };

    add('header', headerNodes);
    add('main', mainNodes);
    add('footer', footerNodes);
    add('other', extraNodes);

    return ordered.slice(0, 16);
  });
}

function isHttpInternal(url: string): boolean {
  try {
    const parsed = new URL(url, BASE_URL);
    const base = new URL(BASE_URL);
    return parsed.protocol.startsWith('http') && parsed.hostname === base.hostname;
  } catch {
    return false;
  }
}

async function auditLinks(route: RouteTarget, viewport: DeviceViewport, page: Page): Promise<void> {
  const links = await collectLinkCandidates(page);
  if (!links.length) {
    addIssue({
      phase: 'FASE_1',
      severity: 'P3',
      route: route.label,
      viewport: viewport.label,
      component: 'Links',
      step: 'coleta',
      description: 'Não foi possível coletar âncoras visíveis nesta página/viewport',
      evidence: `${route.path}`,
    });
    return;
  }

  let checked = 0;
  for (const link of links.slice(0, 10)) {
    const href = new URL(link.href, BASE_URL).toString();
    if (!isHttpInternal(href)) {
      continue;
    }
    checked += 1;
    const response = await page.request.get(href, { timeout: 20_000, failOnStatusCode: false }).catch(() => null);
    const status = response?.status() ?? 0;
    if (status >= 400) {
      addIssue({
        phase: 'FASE_1',
        severity: status >= 500 ? 'P1' : 'P2',
        route: route.label,
        viewport: viewport.label,
        component: 'Links',
        step: `status-${status}`,
        description: `Link ${route.path} retorna HTTP ${status}`,
        evidence: `${link.scope} | ${link.text || '(sem texto)'} | ${href}`,
      });
      continue;
    }

    if (link.scope === 'header') {
      // Verifica estado pós-clique em 1 link de cabeçalho por viewport.
      const current = page.url();
      const clicked = await page.evaluate<string[]>((hrefToCheck) => {
        const href = String(hrefToCheck);
        const target = Array.from(document.querySelectorAll('a[href]')).find((a) => a instanceof HTMLAnchorElement && (a.getAttribute('href') || '').startsWith(href) );
        if (!target) return [];
        const text = (target.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 80);
        const selector = target.getAttribute('href') || '';
        return [selector, text];
      }, link.href);
      const sameHostHref = clicked.length ? new URL(link.href, BASE_URL).toString() : href;
      if (sameHostHref && sameHostHref !== current && sameHostHref !== `${BASE_URL}/`) {
        await page.goto(sameHostHref, { timeout: 30_000, waitUntil: 'domcontentloaded' }).catch(() => null);
        await page.waitForTimeout(800);
        const urlAfter = page.url();
        const contentVisible = await isVisible(page, '.page-wrapper, main, #maincontent, .page-main', 6_000);
        if (!contentVisible) {
          addIssue({
            phase: 'FASE_1',
            severity: 'P2',
            route: route.label,
            viewport: viewport.label,
            component: 'Links',
            step: 'post-click',
            description: 'Estado visual pós-clique sem área principal visível',
            evidence: `${route.path} -> ${urlAfter}`,
          });
        }
        await page.goBack({ waitUntil: 'domcontentloaded' }).catch(() => {});
        await page.waitForTimeout(500);
      }
    }
  }

  if (!checked) {
    addIssue({
      phase: 'FASE_1',
      severity: 'P3',
      route: route.label,
      viewport: viewport.label,
      component: 'Links',
      step: 'validacao',
      description: 'Nenhum link interno válido detectado para validação de status nesta rota/viewport',
      evidence: route.path,
    });
  }
}

async function auditButtons(page: Page, route: RouteTarget, viewport: DeviceViewport): Promise<void> {
  const smallTargets = await page.evaluate(() => {
    const out: ButtonSample[] = [];
    const selectors = 'a, button, [role="button"], input[type="button"], input[type="submit"]';
    const nodes = Array.from(document.querySelectorAll(selectors));

    for (const el of nodes) {
      const html = el as HTMLElement;
      const style = window.getComputedStyle(html);
      if (!html.offsetParent || style.visibility === 'hidden' || style.display === 'none' || parseFloat(style.opacity) === 0) {
        continue;
      }
      const rect = html.getBoundingClientRect();
      if (rect.width < 1 || rect.height < 1) {
        continue;
      }
      if (rect.width < 44 || rect.height < 44) {
        const cls = (html.className && typeof html.className === 'string') ? html.className.trim().split(/\s+/).slice(0, 2).join('.') : '';
        out.push({
          tag: html.tagName.toLowerCase(),
          selector: `.${cls || html.tagName.toLowerCase()}`,
          width: Math.round(rect.width),
          height: Math.round(rect.height),
          x: Math.round(rect.x),
          y: Math.round(rect.y),
        });
      }
      if (out.length >= 25) break;
    }

    return out;
  });

  if (smallTargets.length === 0) {
    return;
  }

  const top = smallTargets.slice(0, 8);
  addIssue({
    phase: 'FASE_1',
    severity: 'P1',
    route: route.label,
    viewport: viewport.label,
    component: 'Botões',
    step: 'touch-target',
    description: `${smallTargets.length} alvo(s) com dimensão abaixo de 44x44px detectados`,
    evidence: top.map((item) => `${item.selector} ${item.width}x${item.height}`).join(' | '),
  });
}

function hasMeaningfulLabel(input: HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement): boolean {
  const id = input.id;
  if (!id) {
    return false;
  }
  const label = document.querySelector(`label[for="${id}"]`);
  if (label && label.textContent && label.textContent.trim().length > 0) {
    return true;
  }
  if (input.getAttribute('aria-label')) return true;
  if (input.getAttribute('aria-labelledby')) return true;
  if (input.getAttribute('placeholder')) return true;
  return false;
}

async function auditForms(page: Page, route: RouteTarget, viewport: DeviceViewport): Promise<void> {
  const formData = await page.evaluate(() => {
    const forms = Array.from(document.querySelectorAll('form'));
    const summary: Array<{
      index: number;
      action: string;
      fields: number;
      requiredWithoutLabel: number;
      requiredFields: number;
      disabledFields: number;
      hasErrorsNow: boolean;
    }> = [];

    for (const form of forms) {
      const fields = Array.from(form.querySelectorAll('input, select, textarea'));
      const required = fields.filter((field) =>
        field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement
      );
      const requiredWithoutLabel = required.filter((field) => {
        if (!(field instanceof HTMLInputElement || field instanceof HTMLSelectElement || field instanceof HTMLTextAreaElement)) {
          return false;
        }
        if (!field.required) {
          return false;
        }
        return !((field.id && document.querySelector(`label[for="${field.id}"]`)) ||
          field.getAttribute('aria-label') ||
          field.getAttribute('aria-labelledby') ||
          field.getAttribute('placeholder'));
      }).length;

      const disabledFields = required.filter((field) => field instanceof HTMLInputElement && (field.disabled || field.readOnly)).length;

      const hasError = !!form.querySelector('.mage-error, .error, .message-error, .field-error, .validation-advice');
      summary.push({
        index: summary.length,
        action: (form.getAttribute('action') || '').slice(0, 200),
        fields: fields.length,
        requiredWithoutLabel,
        requiredFields: required.length,
        disabledFields,
        hasErrorsNow: hasError,
      });
    }

    return summary;
  });

  if (!formData.length) {
    return;
  }

  const totalRequiredWithoutLabel = formData.reduce((acc, item) => acc + item.requiredWithoutLabel, 0);
  if (totalRequiredWithoutLabel > 0) {
    addIssue({
      phase: 'FASE_1',
      severity: 'P2',
      route: route.label,
      viewport: viewport.label,
      component: 'Formulários',
      step: 'labels',
      description: `${totalRequiredWithoutLabel} campo(s) obrigatório(s) sem label/aria-label`,
      evidence: formData.map((item) => `form[${item.index}] action=${item.action || 'N/A'}`).join(' | '),
    });
  }

  const noActionForm = formData.find((item) => !item.action && item.fields > 0);
  if (noActionForm) {
    addIssue({
      phase: 'FASE_2',
      severity: 'P3',
      route: route.label,
      viewport: viewport.label,
      component: 'Formulários',
      step: 'action',
      description: 'Formulário sem atributo action',
      evidence: `form[${noActionForm.index}]`,
    });
  }
}

async function auditLayout(route: RouteTarget, viewport: DeviceViewport, page: Page): Promise<void> {
  const overflow = await checkOverflow(page);
  const noOverflow = await hasNoOverflow(page);
  if (!noOverflow || overflow.hasOverflow) {
    const offenders = await collectOverflowNodes(page);
    addIssue({
      phase: 'FASE_1',
      severity: overflow.diff > 90 || offenders.length > 8 ? 'P1' : 'P2',
      route: route.label,
      viewport: viewport.label,
      component: 'Layout',
      step: 'overflow',
      description: `scrollWidth excede viewport (${overflow.diff}px de diferença)`,
      evidence: offenders.length ? offenders.join(' | ') : 'overflow-node sem detecção',
    });
  }

  const metrics = await page.evaluate(() => {
    const header = document.querySelector('header, .awa-site-header') as HTMLElement | null;
    const main = document.querySelector('main, #maincontent, .columns, .page-main') as HTMLElement | null;
    const footer = document.querySelector('footer, .page-footer') as HTMLElement | null;
    return {
      hasHeader: !!header,
      hasFooter: !!footer,
      hasMain: !!main,
      bodyHeight: Math.round(document.body ? document.body.scrollHeight : 0),
      headerHeight: header ? Math.round(header.getBoundingClientRect().height) : 0,
      mainHeight: main ? Math.round(main.getBoundingClientRect().height) : 0,
      footerHeight: footer ? Math.round(footer.getBoundingClientRect().height) : 0,
      contentVisible: !!(main || footer),
    };
  });

  if (!metrics.hasHeader) {
    addIssue({
      phase: 'FASE_1',
      severity: 'P1',
      route: route.label,
      viewport: viewport.label,
      component: 'Header',
      step: 'presenca',
      description: 'Header principal não localizado na página',
      evidence: route.path,
    });
  }

  if (!metrics.hasFooter && route.label !== 'b2b-login' && route.label !== '404') {
    addIssue({
      phase: 'FASE_1',
      severity: 'P2',
      route: route.label,
      viewport: viewport.label,
      component: 'Footer',
      step: 'presenca',
      description: 'Footer não localizado na página',
      evidence: route.path,
    });
  }

  if (route.expectedSelector) {
    const hasExpected = await isVisible(page, route.expectedSelector, 8_000);
    if (!hasExpected && route.label !== '404' && route.label !== 'checkout') {
      addIssue({
        phase: 'FASE_1',
        severity: 'P2',
        route: route.label,
        viewport: viewport.label,
        component: 'Template',
        step: 'estrutura',
        description: `Template não apresentou seletor esperado: ${route.expectedSelector}`,
        evidence: route.path,
      });
    }
  }

  if (route.expectRoot && page.url() !== `${BASE_URL}/` && !page.url().endsWith('/')) {
    addIssue({
      phase: 'FASE_1',
      severity: 'P1',
      route: route.label,
      viewport: viewport.label,
      component: 'Navegação',
      step: 'URL',
      description: `Home não retornou rota raiz (${page.url()})`,
      evidence: route.path,
    });
  }

  if (viewport.label === '390x844' && metrics.headerHeight > 190 && route.path !== '/') {
    addIssue({
      phase: 'FASE_1',
      severity: 'P2',
      route: route.label,
      viewport: viewport.label,
      component: 'Header',
      step: 'altura',
      description: `Header elevado em mobile (${metrics.headerHeight}px)`,
      evidence: `height=${metrics.headerHeight} path=${route.path}`,
    });
  }

  if (route.expectedSelector && !route.label.startsWith('institucional') && route.expectedSelector.includes('#') && !metrics.hasMain) {
    addIssue({
      phase: 'FASE_1',
      severity: 'P1',
      route: route.label,
      viewport: viewport.label,
      component: 'Conteúdo',
      step: 'main',
      description: 'Bloco main/maincontent indisponível ou com altura zero',
      evidence: route.path,
    });
  }

  const brokenImages = await findBrokenImages(page);
  if (brokenImages.length > 0) {
    addIssue({
      phase: 'FASE_2',
      severity: 'P2',
      route: route.label,
      viewport: viewport.label,
      component: 'Mídia',
      step: 'images',
      description: `${brokenImages.length} imagem(ns) não carregou com largura natural zero`,
      evidence: brokenImages.slice(0, 5).join(' | '),
    });
  }
}

function resolveRouteSeverity(route: RouteTarget, status: number | null): Severity {
  if (status === null) return 'P0';
  if (route.expectStatus === 404 && status !== 404) return 'P2';
  if ((route.expectNotStatus && status === route.expectNotStatus) || (route.expectStatus && status !== route.expectStatus)) return 'P1';
  if (status >= 500) return 'P1';
  if (status >= 400) return 'P2';
  return 'P2';
}

async function reportRoute(route: RouteTarget, viewport: DeviceViewport, page: Page, responseStatus: number | null, consoleErrors: ReturnType<typeof collectConsoleErrors>, networkErrors: ReturnType<typeof collectNetworkErrors>): Promise<void> {
  const filteredNetwork = [...filter404s(networkErrors), ...filter500s(networkErrors)];
  const criticalJs = filterCriticalJsErrors(consoleErrors);

  const criticalJsCount = criticalJs.length;
  const networkCount = filteredNetwork.length;

  if (responseStatus !== null) {
    if (route.expectStatus && responseStatus !== route.expectStatus) {
      addIssue({
        phase: 'FASE_1',
        severity: resolveRouteSeverity(route, responseStatus),
        route: route.label,
        viewport: viewport.label,
        component: 'HTTP',
        step: 'status',
        description: `Status esperado ${route.expectStatus}, recebido ${responseStatus}`,
        evidence: `final=${page.url()}`,
      });
    }

    if (route.expectNotStatus && responseStatus === route.expectNotStatus && route.path.includes('/404')) {
      addIssue({
        phase: 'FASE_1',
        severity: 'P1',
        route: route.label,
        viewport: viewport.label,
        component: 'HTTP',
        step: 'status',
        description: `A rota 404 retornou status ${responseStatus}`,
        evidence: route.path,
      });
    }

    if (responseStatus >= 500) {
      addIssue({
        phase: 'FASE_1',
        severity: 'P0',
        route: route.label,
        viewport: viewport.label,
        component: 'HTTP',
        step: 'server-error',
        description: 'Resposta de erro 5xx na rota',
        evidence: `status=${responseStatus}`,
      });
    }
  }

  if (criticalJsCount > 0) {
    addIssue({
      phase: 'FASE_1',
      severity: criticalJsCount >= 3 ? 'P1' : 'P2',
      route: route.label,
      viewport: viewport.label,
      component: 'JS',
      step: 'pageerror',
      description: `${criticalJsCount} erro(s) JS crítico(s) capturado(s)`,
      evidence: criticalJs.slice(0, 3).map((item) => item.text).join(' | '),
    });
  }

  if (networkCount > 0) {
    const first = filteredNetwork[0];
    addIssue({
      phase: 'FASE_1',
      severity: first.status === 500 ? 'P1' : 'P2',
      route: route.label,
      viewport: viewport.label,
      component: 'Rede',
      step: `HTTP_${first.status}`,
      description: 'Falha de rede em requisição carregada pela página',
      evidence: first.url,
    });
  }
}

async function captureRouteAudit(route: RouteTarget, viewport: DeviceViewport, page: Page): Promise<void> {
  const consoleErrors = collectConsoleErrors(page);
  const networkErrors = collectNetworkErrors(page);

  await page.setViewportSize({ width: viewport.width, height: viewport.height });
  const open = await openRoute(page, route);
  await page.waitForTimeout(1800);

  const screenshot = await takeFullScreenshot(page, route, viewport);

  if (open.responseStatus === null) {
    addIssue({
      phase: 'FASE_0',
      severity: 'P0',
      route: route.label,
      viewport: viewport.label,
      component: 'Navegação',
      step: 'goto',
      description: 'Não foi possível obter resposta HTTP (falha de carregamento)',
      evidence: screenshot,
    });
    return;
  }

  await reportRoute(route, viewport, page, open.responseStatus, consoleErrors, networkErrors);
  await auditLayout(route, viewport, page);
  await auditLinks(route, viewport, page);
  await auditButtons(page, route, viewport);
  await auditForms(page, route, viewport);

  if (route.path === '/checkout/' && !page.url().includes('/checkout')) {
    addIssue({
      phase: 'FASE_1',
      severity: 'P1',
      route: route.label,
      viewport: viewport.label,
      component: 'Checkout',
      step: 'redirect',
      description: 'Checkout não manteve rota esperada após navegação',
      evidence: `final=${page.url()}`,
    });
  }
}

test.describe('Impeccable — auditoria visual profunda (Fase 0/1)', () => {
  test.setTimeout(900_000);

  test('Preparar evidências de auditoria cross-viewport', async ({ page }) => {
    const total = ROUTES_TO_AUDIT.length * VIEWPORTS_TO_AUDIT.length;
    if (!total) {
      addIssue({
        phase: 'FASE_0',
        severity: 'P3',
        route: 'na',
        viewport: 'na',
        component: 'Configuração',
        step: 'filtro',
        description: 'Nenhuma rota/viewport válida para auditoria com filtro atual',
        evidence: `IMPECCABLE_TARGET_ROUTES=${process.env.IMPECCABLE_TARGET_ROUTES || '(vazio)'}, IMPECCABLE_TARGET_VIEWPORTS=${process.env.IMPECCABLE_TARGET_VIEWPORTS || '(vazio)'}`,
      });
      return;
    }

    for (const route of ROUTES_TO_AUDIT) {
      for (const viewport of VIEWPORTS_TO_AUDIT) {
        await test.step(`${route.label} — ${viewport.label}`, async () => {
          try {
            await captureRouteAudit(route, viewport, page);
          } catch (error: unknown) {
            const message = error instanceof Error ? error.message : String(error);
            await takeFullScreenshot(page, route, viewport);
            addIssue({
              phase: 'FASE_1',
              severity: 'P1',
              route: route.label,
              viewport: viewport.label,
              component: 'Execução',
              step: 'falha-na-captura',
              description: 'Erro durante a captura da rota/viewport no passo da auditoria',
              evidence: message,
            });
          }
        });
      }
    }
  });
});

test.afterAll(() => {
  ensureDir();
  const bySeverity = {
    P0: issues.filter((item) => item.severity === 'P0').length,
    P1: issues.filter((item) => item.severity === 'P1').length,
    P2: issues.filter((item) => item.severity === 'P2').length,
    P3: issues.filter((item) => item.severity === 'P3').length,
  };
  const payload = {
    generatedAt: new Date().toISOString(),
    baseUrl: BASE_URL,
    viewports: VIEWPORTS_TO_AUDIT,
    routes: ROUTES_TO_AUDIT.map(({ label, path }) => ({ label, path })),
    totalIssues: issues.length,
    bySeverity,
    issues,
  };
  fs.writeFileSync(REPORT_FILE, JSON.stringify(payload, null, 2));
  console.log('\n════════ AUDIT IMPERCCABLE: VISUAL DEEP AUDIT ════════');
  console.log(`Relatório gravado em: ${REPORT_FILE}`);
  console.log(`Total de issues: ${issues.length} (P0=${bySeverity.P0}, P1=${bySeverity.P1}, P2=${bySeverity.P2}, P3=${bySeverity.P3})`);
  console.log(`Snapshots: ${SCREENSHOT_DIR}`);
  if (!issues.length) {
    console.log('✅ Sem problemas críticos registrados pela etapa de auditoria.');
  }
});
