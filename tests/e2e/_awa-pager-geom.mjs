import { chromium } from 'playwright';
import fs from 'fs';
const LOG='/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-7d58c7.log';
const nd=(o)=>fs.appendFileSync(LOG,JSON.stringify({sessionId:'7d58c7',timestamp:Date.now(),...o})+'\n');

async function check(url,label){
  const browser=await chromium.launch({headless:true,args:['--disable-dev-shm-usage','--no-sandbox']});
  const page=await browser.newPage({viewport:{width:1310,height:900}});
  await page.goto(url,{waitUntil:'domcontentloaded',timeout:60000});
  await page.waitForTimeout(1500);
  const data=await page.evaluate((label)=>{
    function rect(sel){const el=document.querySelector(sel); if(!el) return null; const r=el.getBoundingClientRect(); const cs=getComputedStyle(el); return {sel,top:Math.round(r.top),left:Math.round(r.left),w:Math.round(r.width),h:Math.round(r.height),pos:cs.position,order:cs.order,display:cs.display};}
    const grid=rect('.wrapper.grid.products-grid');
    const bottom=rect('.sort-pagi-bar.sort-pagi-bar-bottom');
    const row=rect('.search.result > .row');
    const bottomCol=rect('.search.result > .row > .col-md-12.col-sm-12.col-xs-12');
    return {label,scrollY:Math.round(window.scrollY),grid,bottom,row,bottomCol,htmlOrderBottomAfterGrid:(()=>{
      const g=document.querySelector('.wrapper.grid.products-grid');
      const b=document.querySelector('.sort-pagi-bar.sort-pagi-bar-bottom');
      if(!g||!b||!g.parentElement) return null;
      return !!(g.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING);
    })()};
  },label);
  nd({runId:'pager-geom',hypothesisId:'P1',location:'summary',message:label,data});
  console.log(JSON.stringify(data,null,2));
  await browser.close();
}

await check('https://awamotos.com/catalogsearch/result/?q=retro','p1');
await check('https://awamotos.com/catalogsearch/result/index/?p=4&q=retro','p4');
