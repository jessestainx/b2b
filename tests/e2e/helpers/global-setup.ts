import { execSync } from 'child_process';
import { resolveTargetEnv, resolveBaseUrl } from './resolve-base-url';

/**
 * Global setup — valida TARGET_ENV e mata browsers órfãos.
 */
export default async function globalSetup(): Promise<void> {
  resolveTargetEnv('global-setup');
  resolveBaseUrl('global-setup');

  try {
    execSync(
      'pkill -9 -x "chrome-headless-shell" 2>/dev/null || true; '
      + 'pkill -9 -x "firefox" 2>/dev/null || true; '
      + 'pkill -9 -x "firefox-bin" 2>/dev/null || true; '
      + 'pkill -9 -x "firefox-esr" 2>/dev/null || true; '
      + 'pkill -9 -x "google-chrome" 2>/dev/null || true; '
      + 'pkill -9 -x "chromium" 2>/dev/null || true; '
      + 'pkill -9 -x "chromium-browser" 2>/dev/null || true; '
      + 'pkill -9 -f "Web Content" 2>/dev/null || true',
      { shell: '/bin/bash', stdio: 'ignore' },
    );
    await new Promise(resolve => setTimeout(resolve, 500));
  } catch {
    // Ignore — no leftover processes is fine
  }
}
