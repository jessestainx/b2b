import { test, expect } from '@playwright/test';

const baseUrl = process.env.PLAYWRIGHT_BASE_URL || 'https://awamotos.com';

test.use({ viewport: { width: 390, height: 844 } });

test('mobile footer contains its content and keeps readable accordion titles', async ({ page }) => {
  await page.goto(baseUrl, { waitUntil: 'domcontentloaded' });
  await page.locator('.page-footer .awa-footer-section__toggle').first().waitFor({ state: 'visible' });
  await page.locator('.page-footer').scrollIntoViewIfNeeded();
  await page.waitForLoadState('load');
  await page.waitForFunction(() => {
    const footer = document.querySelector<HTMLElement>('.page-footer');
    return footer && footer.scrollHeight <= footer.clientHeight + 1;
  }, undefined, { timeout: 15_000 });

  const result = await page.evaluate(async () => {
    await document.fonts?.ready;
    await new Promise<void>(resolve => requestAnimationFrame(() => requestAnimationFrame(() => resolve())));

    const footer = document.querySelector<HTMLElement>('.page-footer');
    const toggle = document.querySelector<HTMLElement>('.page-footer .awa-footer-section__toggle');
    if (!footer || !toggle) throw new Error('Footer or mobile footer toggle not found');

    const toRgb = (color: string): [number, number, number] => {
      const canvas = document.createElement('canvas');
      canvas.width = 1;
      canvas.height = 1;
      const context = canvas.getContext('2d');
      if (!context) throw new Error('Canvas 2D context unavailable');
      context.fillStyle = color;
      context.fillRect(0, 0, 1, 1);
      const data = context.getImageData(0, 0, 1, 1).data;
      return [data[0], data[1], data[2]];
    };
    const luminance = (rgb: [number, number, number]): number => {
      const [red, green, blue] = rgb.map(channel => {
        const value = channel / 255;
        return value <= 0.04045 ? value / 12.92 : Math.pow((value + 0.055) / 1.055, 2.4);
      });
      return 0.2126 * red + 0.7152 * green + 0.0722 * blue;
    };

    const footerStyle = getComputedStyle(footer);
    const toggleStyle = getComputedStyle(toggle);
    const backgroundLuminance = luminance(toRgb(footerStyle.backgroundColor));
    const textLuminance = luminance(toRgb(toggleStyle.color));
    const contrastRatio = (Math.max(backgroundLuminance, textLuminance) + 0.05)
      / (Math.min(backgroundLuminance, textLuminance) + 0.05);

    return {
      footerScrollHeight: footer.scrollHeight,
      footerClientHeight: footer.clientHeight,
      contrastRatio
    };
  });

  expect(result.footerScrollHeight).toBeLessThanOrEqual(result.footerClientHeight + 1);
  expect(result.contrastRatio).toBeGreaterThanOrEqual(4.5);
});
