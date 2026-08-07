import { chromium } from 'playwright';
import fs from 'fs';
const LOG='/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-7d58c7.log';
const nd=(o)=>fs.appendFileSync(LOG,JSON.stringify({sessionId:'7d58c7',timestamp:Date.now(),...o})+'\n');
const browser=await chromium.launch({headless:true,args:['--disable-dev-shm-usage','--no-sandbox']});
const page=await browser.newPage({viewport:{width:1310,height:900}});
await page.goto('https://awamotos.com/catalogsearch/result/?q=retro',{waitUntil:'domcontentloaded',timeout:60000});
await page.waitForTimeout(2000);
const data=await page.evaluate(()=>{
  const els=[...document.querySelectorAll('.block.filter')];
  return {
    count:els.length,
    entries:els.map((el,i)=>{const r=el.getBoundingClientRect();const cs=getComputedStyle(el);return {i,top:Math.round(r.top),left:Math.round(r.left),w:Math.round(r.width),h:Math.round(r.height),disp:cs.display,pos:cs.position,parent:el.parentElement?.className||null,html:(el.textContent||'').trim().slice(0,90)};}),
    filterTitles:[...document.querySelectorAll('.block.filter .filter-title, .filter-title')].length,
    layeredBlocks:[...document.querySelectorAll('#layered-ajax-filter-block')].length
  };
});
nd({runId:'filter-multi',hypothesisId:'G2',location:'summary',message:'filter nodes count',data});
console.log(JSON.stringify(data,null,2));
await browser.close();
