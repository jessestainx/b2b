import { chromium } from 'playwright';
import fs from 'fs';
const LOG='/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-7d58c7.log';
const nd=(o)=>fs.appendFileSync(LOG,JSON.stringify({sessionId:'7d58c7',timestamp:Date.now(),...o})+'\n');
const browser = await chromium.launch({headless:true,args:['--disable-dev-shm-usage','--no-sandbox']});
const page = await browser.newPage({viewport:{width:1310,height:900}});
page.on('pageerror',e=>nd({runId:'geom',hypothesisId:'G0',location:'pageerror',message:String(e)}));
await page.goto('https://awamotos.com/catalogsearch/result/?q=retro',{waitUntil:'domcontentloaded',timeout:60000});
await page.waitForTimeout(2000);

async function snap(label){
  const data = await page.evaluate((label)=>{
    function pick(sel){ const el=document.querySelector(sel); if(!el) return null;
      const cs=getComputedStyle(el), r=el.getBoundingClientRect();
      return {
        sel, cls:el.className, id:el.id||null,
        rect:{top:Math.round(r.top),left:Math.round(r.left),w:Math.round(r.width),h:Math.round(r.height)},
        pos:cs.position, top:cs.top, transform:cs.transform, overflow:cs.overflow,
        z:cs.zIndex, disp:cs.display, vis:cs.visibility
      };
    }
    const body=document.body;
    return {
      label,
      scrollY:window.scrollY,
      bodyClasses: body.className,
      picks:[
        pick('.columns.layout.layout-2-col.row'),
        pick('.col-xs-12.col-sm-3.col-md-3.col-lg-2'),
        pick('.sidebar-main-1'),
        pick('.sidebar-main'),
        pick('.block.filter'),
        pick('.block.filter .block-content.filter-content'),
        pick('#layered-ajax-filter-block'),
        pick('.header-wrapper-sticky')
      ]
    };
  }, label);
  nd({runId:'filter-geom',hypothesisId:'G1',location:'snapshot',message:label,data});
  return data;
}

const s1=await snap('initial');
await page.mouse.wheel(0,900);
await page.waitForTimeout(1200);
const s2=await snap('after-scroll');
console.log(JSON.stringify({s1,s2},null,2));
await browser.close();
