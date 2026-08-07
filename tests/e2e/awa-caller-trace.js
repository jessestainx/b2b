const { chromium } = require('playwright');
const fs = require('fs');

(async () => {
  const logPath = '/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-ec818a.log';
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1430, height: 900 } });

  await page.addInitScript(() => {
    window.__awaStyleCalls = [];
    function logCall(kind, el, detail) {
      if (!el || !el.matches?.('[data-role="awa-vertical-menu-trigger"], .awa-vmenu-trigger-text')) return;
      window.__awaStyleCalls.push({ kind, detail, stack: (new Error()).stack.split('\n').slice(1, 8).join(' | ') });
    }
    const origSetAttr = Element.prototype.setAttribute;
    Element.prototype.setAttribute = function (name, value) {
      if (name === 'style') logCall('setAttribute-style', this, value);
      return origSetAttr.call(this, name, value);
    };
    const origSetProperty = CSSStyleDeclaration.prototype.setProperty;
    CSSStyleDeclaration.prototype.setProperty = function (prop, value, priority) {
      const el = this.ownerElement;
      if (el) logCall('setProperty', el, prop + '=' + value);
      return origSetProperty.call(this, prop, value, priority);
    };
    const cssTextDesc = Object.getOwnPropertyDescriptor(CSSStyleDeclaration.prototype, 'cssText');
    if (cssTextDesc && cssTextDesc.set) {
      Object.defineProperty(CSSStyleDeclaration.prototype, 'cssText', {
        ...cssTextDesc,
        set: function (v) {
          const el = this.ownerElement;
          if (el) logCall('cssText', el, v);
          return cssTextDesc.set.call(this, v);
        },
      });
    }
    ['fontSize', 'fontWeight'].forEach((prop) => {
      const desc = Object.getOwnPropertyDescriptor(CSSStyleDeclaration.prototype, prop);
      if (!desc || !desc.set) return;
      Object.defineProperty(CSSStyleDeclaration.prototype, prop, {
        ...desc,
        set: function (v) {
          const el = this.ownerElement;
          if (el) logCall('direct-' + prop, el, String(v));
          return desc.set.call(this, v);
        },
      });
    });
  });

  await page.goto('https://awamotos.com/bauletos.html?caller-trace=' + Date.now(), { waitUntil: 'networkidle', timeout: 60000 });
  await page.waitForTimeout(3000);

  const data = await page.evaluate(() => ({ calls: window.__awaStyleCalls || [] }));
  fs.appendFileSync(logPath, JSON.stringify({ sessionId: 'ec818a', runId: 'caller-trace', hypothesisId: 'M5', location: 'playwright:caller-trace', message: 'Who sets trigger inline styles', data, timestamp: Date.now() }) + '\n');
  console.log(JSON.stringify(data, null, 2));
  await browser.close();
})();
