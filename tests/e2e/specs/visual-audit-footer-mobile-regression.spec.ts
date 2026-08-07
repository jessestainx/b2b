import { test, expect } from '@playwright/test';

const baseUrl = process.env.PLAYWRIGHT_BASE_URL || 'https://awamotos.com';

function colorLightness(value: string): number | null {
  const oklch = value.match(/oklch\(\s*([\d.]+)/i);
  if (oklch) return Number(oklch[1]);

  const rgb = value.match(/rgba?\(\s*([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)/i);
  if (!rgb) return null;
  const channels = rgb.slice(1, 4).map(channel => {
    const normalized = Number(channel) / 255;
    return normalized <= 0.04045
      ? normalized / 12.92
      : Math.pow((normalized + 0.055) / 1.055, 2.4);
  });
  return 0.2126 * channels[0] + 0.7152 * channels[1] + 0.0722 * channels[2];
}

test.use({ viewport: { width: 390, height: 844 } });

test('mobile footer contains its content and keeps readable accordion titles', async ({ page }) => {
  await page.goto(baseUrl, { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(3000);

  const result = await page.evaluate(() => {
    const footer = document.querySelector<HTMLElement>('.page-footer');
    const toggle = document.querySelector<HTMLElement>('.page-footer .awa-footer-section__toggle');
    if (!footer || !toggle) throw new Error('Footer or mobile footer toggle not found');

    const footerRect = footer.getBoundingClientRect();
    const descendantBottom = Math.max(
      ...[...footer.querySelectorAll<HTMLElement>('*')]
        .filter(el => {
          const style = getComputedStyle(el);
          const rect = el.getBoundingClientRect();
          return style.display !== 'none' && style.visibility !== 'hidden' && rect.height > 0;
        })
        .map(el => el.getBoundingClientRect().bottom)
    );

    const footerStyle = getComputedStyle(footer);
    const toggleStyle = getComputedStyle(toggle);
    return {
      footerBottom: footerRect.bottom,
      descendantBottom,
      footerBackground: footerStyle.backgroundColor,
      toggleColor: toggleStyle.color
    };
  });

  expect(result.footerBottom).toBeGreaterThanOrEqual(result.descendantBottom - 1);

  const backgroundL = colorLightness(result.footerBackground);
  const textL = colorLightness(result.toggleColor);
  expect(backgroundL).not.toBeNull();
  expect(textL).not.toBeNull();
  expect(Math.abs(backgroundL! - textL!)).toBeGreaterThanOrEqual(0.35);
});
