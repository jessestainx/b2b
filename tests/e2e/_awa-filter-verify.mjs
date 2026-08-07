import { chromium } from 'playwright';
import fs from 'fs';
const LOG='/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-7d58c7.log';
const nd=(o)=>fs.appendFileSync(LOG,JSON.stringify({sessionId:'7d58c7',timestamp:Date.now(),...o})+'\n');
const browser = await chromium.launch({headless:true,args:['--disable-dev-shm-usage','--no-sandbox']});
const page = await browser.newPage({viewport:{width:1310,height:900}});
const pageErrors=[];
page.on('pageerror',e=>pageErrors.push(String(e)));
await page.goto('https://awamotos.com/catalogsearch/result/?q=retro',{waitUntil:'domcontentloaded',timeout:60000});
await page.waitForTimeout(1800);

async function geom(label){
  return await page.evaluate((label)=>{
    function g(sel){const el=document.querySelector(sel); if(!el) return null; const cs=getComputedStyle(el),r=el.getBoundingClientRect();
      return {sel,top:Math.round(r.top),h:Math.round(r.height),pos:cs.position,cssTop:cs.top,overflowY:cs.overflowY,maxH:cs.maxHeight,transform:cs.transform};}
    return {
      label,
      scrollY:Math.round(window.scrollY),
      bodySticky:document.body.classList.contains('awa-header-is-sticky'),
      col:g('.col-xs-12.col-sm-3.col-md-3.col-lg-2'),
      filter:g('.block.filter'),
      header:g('.header-wrapper-sticky')
    };
  },label);
}

const a=await geom('initial');
await page.mouse.wheel(0,950);
await page.waitForTimeout(1200);
const b=await geom('after-scroll-1k');
await page.mouse.wheel(0,1600);
await page.waitForTimeout(1200);
const c=await geom('after-scroll-2.6k');

const data={a,b,c,pageErrors,ok:b.col && b.col.top>=0 && c.col && c.col.top>=0};
nd({runId:'filter-sticky-verify',hypothesisId:'G1',location:'summary',message:'sidebar sticky geometry',data});
console.log(JSON.stringify(data,null,2));
await browser.close();
