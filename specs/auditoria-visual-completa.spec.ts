import { test, expect } from '@playwright/test';

const BASE_URL = process.env.AWA_BASE_URL || 'https://awamotos.com';

const PAGES_AUDIT = [
  {
    url: '/',
    name: 'home',
    description: 'Página inicial'
  },
  {
    url: '/bauletos.html',
    name: 'plp-categoria',
    description: 'PLP - Categoria de peças'
  },
  {
    url: '/capacete-fechado-helt-tt501-dark-rider-verde-militar.html',
    name: 'pdp-produto', 
    description: 'PDP - Página de produto'
  }
];

const VIEWPORTS_AUDIT = [
  { name: 'mobile', width: 375, height: 812 },
  { name: 'desktop', width: 1440, height: 900 }
];

test.describe('Auditoria Visual Completa AWA Motos', () => {
  for (const viewport of VIEWPORTS_AUDIT) {
    test.describe(`Viewport ${viewport.name} (${viewport.width}x${viewport.height})`, () => {
      test.use({ viewport });

      for (const pageInfo of PAGES_AUDIT) {
        test(`${pageInfo.name} - Screenshot e análise DOM`, async ({ page }) => {
          console.log(`🔍 Auditando: ${pageInfo.description} - ${viewport.name}`);
          
          // Navegar para a página
          await page.goto(`${BASE_URL}${pageInfo.url}`, {
            waitUntil: 'networkidle',
            timeout: 60000
          });

          // Aguardar carregamento completo
          await page.waitForTimeout(3000);

          // Screenshot principal
          await page.screenshot({
            path: `shots/audit-${pageInfo.name}-${viewport.name}.png`,
            fullPage: true
          });

          // Análise de contraste e elementos críticos
          const auditResults = await page.evaluate(() => {
            // Função para calcular contraste
            function getContrast(foreground: string, background: string) {
              const rgb1 = getRGB(foreground);
              const rgb2 = getRGB(background);
              
              const l1 = getLuminance(rgb1) + 0.05;
              const l2 = getLuminance(rgb2) + 0.05;
              
              return Math.max(l1, l2) / Math.min(l1, l2);
            }

            function getRGB(color: string) {
              const div = document.createElement('div');
              div.style.color = color;
              document.body.appendChild(div);
              const computedColor = getComputedStyle(div).color;
              document.body.removeChild(div);
              
              const match = computedColor.match(/rgb\\((\\d+),\\s*(\\d+),\\s*(\\d+)\\)/);
              return match ? [parseInt(match[1]), parseInt(match[2]), parseInt(match[3])] : [0, 0, 0];
            }

            function getLuminance([r, g, b]: number[]) {
              const [rs, gs, bs] = [r, g, b].map(c => {
                c = c / 255;
                return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
              });
              return 0.2126 * rs + 0.7152 * gs + 0.0722 * bs;
            }

            // Verificar design tokens
            const rootStyle = getComputedStyle(document.documentElement);
            const designTokens = {
              primaryRed: rootStyle.getPropertyValue('--awa-red').trim(),
              primaryColor: rootStyle.getPropertyValue('--awa-primary').trim(),
              gapXs: rootStyle.getPropertyValue('--awa-gap-xs').trim(),
              gapLg: rootStyle.getPropertyValue('--awa-gap-lg').trim(),
            };

            // Verificar elementos críticos
            const criticalElements = {
              hasHeader: !!document.querySelector('header, [role="banner"]'),
              hasFooter: !!document.querySelector('footer, [role="contentinfo"]'),
              hasNavigation: !!document.querySelector('nav, [role="navigation"]'),
              hasMainContent: !!document.querySelector('main, [role="main"], #maincontent'),
            };

            // Verificar CLS potencial
            const imagesWithoutDimensions = Array.from(document.querySelectorAll('img')).filter(img => {
              const computedStyle = getComputedStyle(img);
              return !img.width || !img.height || (!computedStyle.width || !computedStyle.height);
            }).length;

            // Verificar overflow horizontal
            const hasHorizontalOverflow = document.documentElement.scrollWidth > document.documentElement.clientWidth + 2;

            return {
              designTokens,
              criticalElements,
              imagesWithoutDimensions,
              hasHorizontalOverflow,
              url: window.location.href,
              title: document.title
            };
          });

          // Log dos resultados para análise posterior
          console.log(`📊 Resultados da auditoria ${pageInfo.name}-${viewport.name}:`, {
            designTokens: auditResults.designTokens,
            criticalElements: auditResults.criticalElements,
            imagesWithoutDimensions: auditResults.imagesWithoutDimensions,
            hasHorizontalOverflow: auditResults.hasHorizontalOverflow
          });

          // Verificações básicas
          expect(auditResults.hasHorizontalOverflow).toBe(false);
          expect(auditResults.criticalElements.hasHeader).toBe(true);
          expect(auditResults.criticalElements.hasMainContent).toBe(true);
        });
      }
    });
  }
});