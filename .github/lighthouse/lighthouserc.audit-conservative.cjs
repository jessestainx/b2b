/** @type {import('@lhci/cli').LhciConfig} */
module.exports = {
  ci: {
    collect: {
      urls: [
        process.env.LHCI_BASE_URL + '/',
        process.env.LHCI_BASE_URL + '/bagageiros.html',
        process.env.LHCI_BASE_URL + '/b2b/account/login/',
      ],
      numberOfRuns: parseInt(process.env.LHCI_RUNS || '1', 10),
      settings: {
        formFactor: 'desktop',
        preset: 'desktop',
        throttlingMethod: 'simulate',
        chromeFlags: '--headless=new --no-sandbox --disable-dev-shm-usage',
        skipAudits: ['uses-http2'],
      },
    },
    assert: {
      assertions: {
        'categories:performance': ['warn', { minScore: 0.35 }],
        'categories:accessibility': ['warn', { minScore: 0.80 }],
        'categories:best-practices': ['warn', { minScore: 0.70 }],
        'categories:seo': ['warn', { minScore: 0.80 }],
        'first-contentful-paint': ['warn', { maxNumericValue: 4000 }],
        'largest-contentful-paint': ['warn', { maxNumericValue: 6000 }],
        'cumulative-layout-shift': ['warn', { maxNumericValue: 0.25 }],
        'total-blocking-time': ['warn', { maxNumericValue: 800 }],
      },
    },
    upload: {
      target: 'filesystem',
      outputDir: '.lighthouseci/audit-conservative',
    },
  },
};
