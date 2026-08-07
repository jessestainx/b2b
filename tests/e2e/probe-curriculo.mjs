import { chromium } from 'playwright';

const browser = await chromium.launch({
  headless: true,
  executablePath: '/usr/bin/google-chrome',
  args: ['--no-sandbox', '--disable-dev-shm-usage', '--disable-gpu']
});
const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
const errors = [];
const consoleMsgs = [];
page.on('pageerror', e => errors.push(String(e)));
page.on('console', m => {
  if (['error','warning'].includes(m.type())) consoleMsgs.push({type:m.type(), text:m.text().slice(0,300)});
});
const failed = [];
page.on('response', r => {
  const u = r.url();
  if (r.status() >= 400 && (u.includes('Curriculo') || u.includes('curriculo') || u.includes('mage/') || u.includes('jquery') || u.includes('require'))) {
    failed.push({status:r.status(), url:u.slice(0,200)});
  }
});

await page.goto('https://awamotos.com/trabalhe-conosco/', { waitUntil: 'domcontentloaded', timeout: 90000 });
await page.waitForFunction(() => typeof window.jQuery !== 'undefined' || document.readyState === 'complete', { timeout: 30000 }).catch(()=>{});
await page.waitForTimeout(4000);

const data = await page.evaluate(() => {
  const progress = document.querySelector('#progress-percent')?.textContent;
  const filled = document.querySelector('#filled-fields')?.textContent;
  const total = document.querySelector('#total-fields')?.textContent;
  const form = document.querySelector('#curriculo-form');
  let hasWidget = false, hasEnh = false, widgetErr = null, allDataKeys = [];
  try {
    if (window.jQuery && form) {
      const d = jQuery(form).data();
      allDataKeys = Object.keys(d || {});
      hasWidget = !!(d['ayo-curriculoProgress'] || d.ayoCurriculoProgress);
      hasEnh = !!(d['ayo-formEnhancements'] || d.ayoFormEnhancements);
      // Magento widget data keys often camelCase without hyphen
      for (const k of allDataKeys) {
        if (/curriculo/i.test(k)) hasWidget = true;
        if (/formEnhancement/i.test(k)) hasEnh = true;
      }
    }
  } catch (e) { widgetErr = String(e); }
  const inputCount = form ? form.querySelectorAll('input:not([type=hidden]), select, textarea').length : 0;
  const progressEl = document.querySelector('.curriculo-progress');
  const progressStyles = progressEl ? {
    display: getComputedStyle(progressEl).display,
    visibility: getComputedStyle(progressEl).visibility,
    height: Math.round(progressEl.getBoundingClientRect().height),
    width: Math.round(progressEl.getBoundingClientRect().width)
  } : null;
  const wrapper = document.querySelector('.curriculo-wrapper');
  const wrapperBox = wrapper?.getBoundingClientRect();
  const wrapperCS = wrapper ? {
    maxWidth: getComputedStyle(wrapper).maxWidth,
    width: getComputedStyle(wrapper).width,
    margin: getComputedStyle(wrapper).margin,
    padding: getComputedStyle(wrapper).padding
  } : null;
  const map = window.require?.s?.contexts?._?.config?.map?.['*'] || null;
  const main = document.querySelector('.column.main');
  return {
    progress, filled, total, hasWidget, hasEnh, widgetErr, allDataKeys, inputCount,
    formAction: form?.action || null,
    requireDefined: typeof window.require,
    jqueryDefined: typeof window.jQuery,
    wrapperBox: wrapperBox ? {w: Math.round(wrapperBox.width), h: Math.round(wrapperBox.height), top: Math.round(wrapperBox.top)} : null,
    wrapperCS, progressStyles,
    mapCurriculo: map ? {curriculoProgress: map.curriculoProgress, formEnhancements: map.formEnhancements} : null,
    bodyClasses: document.body.className.slice(0,250),
    mainWidth: main ? Math.round(main.getBoundingClientRect().width) : null
  };
});

await page.locator('#name').fill('Teste Debug');
await page.waitForTimeout(1000);
const afterFill = await page.evaluate(() => ({
  progress: document.querySelector('#progress-percent')?.textContent,
  filled: document.querySelector('#filled-fields')?.textContent,
  total: document.querySelector('#total-fields')?.textContent,
}));

await page.screenshot({ path: '/tmp/trabalhe-conosco.png', fullPage: false });
console.log(JSON.stringify({data, afterFill, errors: errors.slice(0,15), consoleMsgs: consoleMsgs.slice(0,25), failed}, null, 2));
await browser.close();
