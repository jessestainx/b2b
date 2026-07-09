import type { BrowserContext, Page, Route } from '@playwright/test';

const BLOCKED_HOST_PATTERNS = [
  /(^|\.)googletagmanager\.com$/i,
  /(^|\.)google-analytics\.com$/i,
  /(^|\.)analytics\.google\.com$/i,
  /(^|\.)doubleclick\.net$/i,
  /(^|\.)facebook\.net$/i,
  /(^|\.)facebook\.com$/i,
  /(^|\.)hotjar\.com$/i,
  /(^|\.)clarity\.ms$/i,
  /(^|\.)tiktok\.com$/i,
  /(^|\.)criteo\.com$/i,
  /(^|\.)taboola\.com$/i,
  /(^|\.)tawk\.to$/i,
  /(^|\.)zopim\.com$/i,
  /(^|\.)intercom\.io$/i,
  /(^|\.)intercomcdn\.com$/i,
  /(^|\.)chatwoot\.com$/i,
  /(^|\.)onesignal\.com$/i,
  /(^|\.)rdstation\.com\.br$/i,
];

const BLOCKED_RESOURCE_TYPES = new Set([
  'script',
  'xhr',
  'fetch',
  'eventsource',
  'websocket',
  'other',
]);

type Routable = BrowserContext | Page;

function shouldBlock(route: Route): boolean {
  if (process.env.PLAYWRIGHT_BLOCK_THIRD_PARTY === '0') {
    return false;
  }

  const request = route.request();
  let hostname = '';
  try {
    hostname = new URL(request.url()).hostname;
  } catch {
    return false;
  }

  return (
    BLOCKED_RESOURCE_TYPES.has(request.resourceType()) &&
    BLOCKED_HOST_PATTERNS.some((pattern) => pattern.test(hostname))
  );
}

export async function blockOptionalThirdParty(target: Routable): Promise<void> {
  await target.route('**/*', async (route) => {
    if (shouldBlock(route)) {
      await route.abort('blockedbyclient');
      return;
    }

    await route.continue();
  });
}
