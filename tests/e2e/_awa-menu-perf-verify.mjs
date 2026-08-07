import { chromium } from 'playwright';
import fs from 'fs';
const LOG='/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-7d58c7.log';
const nd=(o)=>fs.appendFileSync(LOG,JSON.stringify({sessionId:'7d58c7',timestamp:Date.now(),...o})+'\n');
const browser=await chromium.launch({headless:true,args:['--disable-dev-shm-usage','--no-sandbox']});
const page=await browser.newPage({viewport:{width:1124,height:900}});
await page.goto('https://awamotos.com/catalogsearch/result/?q=retro',{waitUntil:'domcontentloaded',timeout:60000});
await page.waitForTimeout(1800);
await page.evaluate(() => {
  window.__awaLongTasks = [];
  try {
    new PerformanceObserver((list)=>{
      list.getEntries().forEach((e)=>window.__awaLongTasks.push({name:e.name,duration:Math.round(e.duration),start:Math.round(e.startTime)}));
    }).observe({entryTypes:['longtask']});
  } catch(_){}
});

async function snap(label){
  return await page.evaluate((label)=>{
    const navInner=document.querySelector('.awa-nav-bar__inner');
    const main=document.querySelector('#maincontent');
    const trig=document.querySelector('[data-role="awa-vertical-menu-trigger"]');
    const panel=document.querySelector('[data-role="awa-vertical-menu-panel"]');
    const rMain=main?main.getBoundingClientRect():null;
    const rNav=navInner?navInner.getBoundingClientRect():null;
    return {
      label,
      mainTop:rMain?Math.round(rMain.top):null,
      navTop:rNav?Math.round(rNav.top):null,
      navH:rNav?Math.round(rNav.height):null,
      triggerExpanded:trig?trig.getAttribute('aria-expanded'):null,
      panelState:panel?panel.getAttribute('data-awa-menu-state'):null,
      navInline:navInner?{
        height:navInner.style.getPropertyValue('height')||null,
        minHeight:navInner.style.getPropertyValue('min-height')||null,
        maxHeight:navInner.style.getPropertyValue('max-height')||null,
        overflow:navInner.style.getPropertyValue('overflow')||null
      }:null
    };
  },label);
}

const before=await snap('before-open');
await page.locator('[data-role="awa-vertical-menu-trigger"]').click({force:true});
await page.waitForTimeout(450);
const opened=await snap('after-open');

const t0=Date.now();
await page.locator('#search').click({force:true});
await page.locator('#search').type('retro',{delay:30});
await page.waitForTimeout(2200);
const typingMs=Date.now()-t0;

const afterType=await snap('after-type');
const longTasks=await page.evaluate(()=>window.__awaLongTasks||[]);
const dbgEvents=await page.evaluate(()=>window.__awaDbgSearchEvents||[]);
const summarize={
  before,opened,afterType,typingMs,
  longTasksCount: longTasks.length,
  longTasksTail: longTasks.slice(-20),
  dbgCount: dbgEvents.length,
  dbgTail: dbgEvents.slice(-20).map(e=>({h:e.hypothesisId,loc:e.location,msg:e.message,data:e.data}))
};
nd({runId:'evidence-autocomplete',hypothesisId:'A5-A6',location:'summary',message:'autocomplete instrumentation evidence',data:summarize});
for (const ev of dbgEvents.slice(-60)) {
  nd({runId:ev.runId||'autocomplete-run',hypothesisId:ev.hypothesisId||'A?',location:ev.location||'unknown',message:ev.message||'event',data:ev.data||{}});
}
console.log(JSON.stringify(summarize,null,2));
await browser.close();
