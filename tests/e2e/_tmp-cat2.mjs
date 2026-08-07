import { chromium } from 'playwright';
import fs from 'fs';
const LOG='/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-d5387e.log';
const w=(p)=>fs.appendFileSync(LOG,JSON.stringify({sessionId:'d5387e',timestamp:Date.now(),runId:'catalogo-audit2',...p})+'\n');
const browser=await chromium.launch({headless:true,args:['--disable-dev-shm-usage','--no-sandbox','--single-process']});
const page=await browser.newPage({viewport:{width:1440,height:900}});
const errors=[], failed=[];
page.on('pageerror', e=>errors.push(String(e.message||e).slice(0,180)));
page.on('console', m=>{ if(m.type()==='error') errors.push(m.text().slice(0,180)); });
page.on('response', r=>{ const u=r.url(); if(r.status()>=400) failed.push({s:r.status(),u:u.slice(-90)}); });
const resp=await page.goto('https://awamotos.com/catalogo',{waitUntil:'domcontentloaded',timeout:45000});
await page.waitForTimeout(2000);
const data=await page.evaluate(()=>{
  const frame=document.querySelector('iframe.awa-catalogo-page__frame, .awa-catalogo-page__frame, iframe');
  const hero=document.querySelector('.awa-catalogo-page__hero');
  const section=document.querySelector('.awa-catalogo-page');
  const pick=el=>{ if(!el) return null; const r=el.getBoundingClientRect(); const s=getComputedStyle(el); return {cls:String(el.className).slice(0,60),w:Math.round(r.width),h:Math.round(r.height),top:Math.round(r.top),display:s.display,visibility:s.visibility,opacity:s.opacity,src:el.src||null}; };
  return {
    statusOk: true,
    title: document.title,
    h1: (document.querySelector('#awa-catalogo-title')?.textContent||'').trim(),
    section: pick(section),
    hero: pick(hero),
    frame: pick(frame),
    cover: pick(document.querySelector('.awa-catalogo-page__cover')),
    overflowX: document.documentElement.scrollWidth - window.innerWidth,
    bodyCls: document.body.className
  };
});
await page.screenshot({path:'/tmp/catalogo-desk.png'});
w({hypothesisId:'CAT1',location:'catalogo:desk',message:'light audit',data:{status:resp.status(),errors:errors.slice(0,8),failed:failed.slice(0,12),...data}});
console.log(JSON.stringify({status:resp.status(),errors,failed:failed.slice(0,12),...data},null,2));

// mobile
await page.setViewportSize({width:390,height:844});
await page.reload({waitUntil:'domcontentloaded',timeout:45000});
await page.waitForTimeout(2000);
const mob=await page.evaluate(()=>{
  const frame=document.querySelector('iframe.awa-catalogo-page__frame, iframe');
  const r=frame?.getBoundingClientRect();
  return {
    frame:{w:r?Math.round(r.width):0,h:r?Math.round(r.height):0,display:frame?getComputedStyle(frame).display:null},
    overflowX: document.documentElement.scrollWidth - window.innerWidth,
    heroH: Math.round(document.querySelector('.awa-catalogo-page__hero')?.getBoundingClientRect().height||0)
  };
});
await page.screenshot({path:'/tmp/catalogo-mobile.png'});
w({hypothesisId:'CAT2',location:'catalogo:mobile',message:'mobile',data:mob});
console.log('MOB', JSON.stringify(mob));
await browser.close();
