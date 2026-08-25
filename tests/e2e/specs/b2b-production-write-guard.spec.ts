import { test, expect } from '@playwright/test';
import { installProductionWriteGuard } from '../helpers/production-write-guard';

test.describe('B2B production write guard', () => {
  test('bloqueia POST em produção quando TARGET_ENV não é piloto', async ({ page }) => {
    const env = String(process.env.TARGET_ENV || '');
    test.skip(env === 'pilot', 'Guard de escrita não se aplica ao modo piloto');

    installProductionWriteGuard(page, { taggedPilot: false });

    if (env !== 'production_readonly') {
      test.skip(true, 'Exercita o interceptor somente contra production_readonly');
    }

    let blocked = false;
    try {
      await page.evaluate(async () => {
        await fetch('/b2b/register/save', { method: 'POST', body: 'probe=1' });
      });
    } catch (error) {
      blocked = String(error).includes('Bloqueio de escrita em produção');
    }

    expect(blocked || env !== 'production_readonly').toBeTruthy();
  });
});
