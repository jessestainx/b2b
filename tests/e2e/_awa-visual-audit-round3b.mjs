import { chromium } from 'playwright';
import fs from 'fs';
const LOG='/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-7d58c7.log';
const nd=(o)=>fs.appendFileSync(LOG,JSON.stringify({sessionId:'7d58c7',timestamp:Date.now(),...o})+'\n');
const EN=/\b(Add to Cart|Sign In|Log In|Login|Shop Now|Continue Shopping|My Cart|Checkout|Place Order|Create an Account|Forgot Your Password|Customer Login|Email Address|Submit|Shipping|Payment|Coupon Code|Logout|Register)\b/;
const pages=[
  {id:'home', url:'https://awamotos.com/'},
  {id:'search', url:'https://awamotos.com/catalogsearch/result/?q=filtro'},
  {id:'category', url:'https://awamotos.com/retrovisores.html'},
  {id:'cart', url:'https://awamotos.com/checkout/cart/'},
  {id:'login', url:'https://awamotos.com/b2b/account/login/'},
  {id:'forgot', url:'https://awamotos.com/b2b/account/forgotpassword/'},
  {id:'brands', url:'https://awamotos.com/nossas-marcas'},
  {id:'cms404', url:'https://awamotos.com/pagina-que-nao-existe-awa-404'},
];
const viewports=[{name:'tablet',width:768,height:1024},{name:'mobile',width:390,height:844}];
const browser=await chromium.launch({headless:true,args:['--disable-dev-shm-usage','--no-sandbox']});
const results=[];
for(const vp of viewports){
  const page=await browser.newPage({viewport:{width:vp.width,height:vp.height}});
  page.setDefaultNavigationTimeout(35000);
  for(const p of pages){
    try{
      const resp=await page.goto(p.url,{waitUntil:'domcontentloaded',timeout:35000});
      await page.waitForTimeout(900);
      const data=await page.evaluate((src)=>{
        const re=new RegExp(src,'i');
        const english=[]; const seen=new Set();
        const walker=document.createTreeWalker(document.body,NodeFilter.SHOW_TEXT);
        let node;
        while((node=walker.nextNode())){
          const t=(node.textContent||'').replace(/\s+/g,' ').trim();
          if(!t||t.length>100) continue;
          const el=node.parentElement; if(!el) continue;
          if(['SCRIPT','STYLE','NOSCRIPT','SVG'].includes(el.tagName)) continue;
          const cs=getComputedStyle(el);
          if(cs.display==='none'||cs.visibility==='hidden') continue;
          const r=el.getBoundingClientRect(); if(r.width<1||r.height<1) continue;
          if(!re.test(t)) continue;
          if(/finalização|pedido B2B|WhatsApp|certificado/i.test(t)) continue;
          const k=t.toLowerCase(); if(seen.has(k)) continue; seen.add(k);
          english.push(t); if(english.length>=12) break;
        }
        const docW=document.documentElement.clientWidth;
        const overflow=[];
        document.querySelectorAll('header,main,footer,.page-wrapper,.columns,.column.main,.products,.b2b-login-card,.awa-cart-empty').forEach(el=>{
          const r=el.getBoundingClientRect();
          if(r.right>docW+4||r.left<-4) overflow.push((el.className||el.tagName).toString().slice(0,70));
        });
        // sticky column check on category
        const col=document.querySelector('.columns.layout-2-col > [class*="col-sm-3"], .columns.layout-2-col > .sidebar-main-1');
        let stickyCol=null;
        if(col){ const cs=getComputedStyle(col); stickyCol={pos:cs.position, top:cs.top, maxH:cs.maxHeight, ovY:cs.overflowY}; }
        const main=document.querySelector('#maincontent');
        const mr=main?main.getBoundingClientRect():null;
        return {english, overflow:overflow.slice(0,6), stickyCol, mainH:mr?Math.round(mr.height):null, mainW:mr?Math.round(mr.width):null};
      }, EN.source);
      const row={page:p.id,vp:vp.name,status:resp?.status(),...data};
      results.push(row);
      nd({runId:'visual-r3b',hypothesisId:'A1',location:`${p.id}:${vp.name}`,message:'audit',data:row});
      console.log(JSON.stringify({page:p.id,vp:vp.name,status:row.status,en:row.english.length,overflow:row.overflow.length,enSample:row.english,sticky:row.stickyCol},null,2));
    }catch(e){
      nd({runId:'visual-r3b',hypothesisId:'A1',location:`${p.id}:${vp.name}`,message:'fail',data:{error:String(e)}});
      console.error('FAIL',p.id,vp.name,e.message||e);
    }
  }
  await page.close();
}
await browser.close();
const summary={total:results.length,withEn:results.filter(r=>r.english.length),withOverflow:results.filter(r=>r.overflow.length)};
nd({runId:'visual-r3b',hypothesisId:'A1',location:'summary',message:'done',data:{total:summary.total,enPages:summary.withEn.map(r=>({p:r.page,vp:r.vp,en:r.english})),ovPages:summary.withOverflow.map(r=>({p:r.page,vp:r.vp,ov:r.overflow}))}});
console.log('SUMMARY',JSON.stringify({total:summary.total,enCount:summary.withEn.length,ovCount:summary.withOverflow.length,en:summary.withEn.map(r=>({p:r.page,vp:r.vp,en:r.english})),ov:summary.withOverflow.map(r=>({p:r.page,vp:r.vp,ov:r.overflow}))},null,2));
