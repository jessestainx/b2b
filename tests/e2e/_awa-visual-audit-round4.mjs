import { chromium } from 'playwright';
import fs from 'fs';
const LOG='/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-7d58c7.log';
const nd=(o)=>fs.appendFileSync(LOG,JSON.stringify({sessionId:'7d58c7',timestamp:Date.now(),...o})+'\n');

const EN=/\b(Add to Cart|Sign In|Log In|Login|Shop Now|Continue Shopping|My Cart|Checkout|Place Order|Create an Account|Forgot Your Password|Customer Login|Email Address|Submit|Shipping|Payment|Coupon Code|Logout|Register|Quick view|Add to Wishlist|Add to Compare|Clear All|First Name|Last Name|Street Address|Personal Information|Password Strength|Additional Address|Zip\/Postal|In stock|Out of stock|Sort By|Filter By|Show|per page|Back|Edit|Delete|Phone|Country|City|State|Actions|Confirm Password|No Password|Address Information|Sign-in Information|Sign Up for Newsletter)\b/;

const pages=[
  {id:'home', url:'https://awamotos.com/'},
  {id:'pdp', url:'https://awamotos.com/ret-biz-100-cr-redondo-universal-2220.html'},
  {id:'category', url:'https://awamotos.com/retrovisores.html'},
  {id:'search', url:'https://awamotos.com/catalogsearch/result/?q=retrovisor'},
  {id:'cart', url:'https://awamotos.com/checkout/cart/'},
  {id:'login_b2b', url:'https://awamotos.com/b2b/account/login/'},
  {id:'login_cust', url:'https://awamotos.com/customer/account/login/'},
  {id:'register_b2b', url:'https://awamotos.com/b2b/register/'},
  {id:'forgot', url:'https://awamotos.com/b2b/account/forgotpassword/'},
  {id:'brands', url:'https://awamotos.com/nossas-marcas'},
  {id:'wishlist', url:'https://awamotos.com/wishlist/'},
  {id:'compare', url:'https://awamotos.com/catalog/product_compare/'},
  {id:'cms404', url:'https://awamotos.com/pagina-que-nao-existe-awa-404'},
];
const viewports=[
  {name:'desktop', width:1366, height:900},
  {name:'mobile', width:390, height:844},
];

const browser=await chromium.launch({headless:true,args:['--disable-dev-shm-usage','--no-sandbox']});
const results=[];

for(const vp of viewports){
  const page=await browser.newPage({viewport:{width:vp.width,height:vp.height}});
  page.setDefaultNavigationTimeout(40000);
  for(const p of pages){
    try{
      const resp=await page.goto(p.url,{waitUntil:'domcontentloaded',timeout:40000});
      await page.waitForTimeout(1100);
      // collect visible EN + title/aria attrs that are EN
      const data=await page.evaluate((src)=>{
        const re=new RegExp(src,'i');
        const english=[]; const seen=new Set();
        const push=(t,meta)=>{
          const k=t.toLowerCase(); if(seen.has(k)) return; seen.add(k);
          english.push({text:t,...meta});
        };
        const walker=document.createTreeWalker(document.body,NodeFilter.SHOW_TEXT);
        let node;
        while((node=walker.nextNode())){
          const t=(node.textContent||'').replace(/\s+/g,' ').trim();
          if(!t||t.length<2||t.length>110) continue;
          const el=node.parentElement; if(!el) continue;
          if(['SCRIPT','STYLE','NOSCRIPT','SVG','PATH','TEXTAREA','CODE'].includes(el.tagName)) continue;
          const cs=getComputedStyle(el);
          if(cs.display==='none'||cs.visibility==='hidden'||Number(cs.opacity)===0) continue;
          const r=el.getBoundingClientRect(); if(r.width<1||r.height<1) continue;
          if(!re.test(t)) continue;
          if(/finalização|pedido B2B|WhatsApp|certificado digital na/i.test(t)) continue;
          // false positives: COD, SKU alone
          if(/^(COD:|SKU)\b/i.test(t)) continue;
          push(t,{via:'text',tag:el.tagName,cls:(el.className||'').toString().slice(0,60)});
          if(english.length>=25) break;
        }
        // title/aria-label on interactive
        document.querySelectorAll('a[title],button[title],a[aria-label],button[aria-label]').forEach(el=>{
          const cs=getComputedStyle(el);
          if(cs.display==='none'||cs.visibility==='hidden') return;
          const r=el.getBoundingClientRect(); if(r.width<1||r.height<1) return;
          for(const attr of ['title','aria-label']){
            const v=(el.getAttribute(attr)||'').trim();
            if(v && re.test(v) && !/finalização|pedido B2B/i.test(v)) push(v,{via:attr,tag:el.tagName,cls:(el.className||'').toString().slice(0,50)});
          }
        });
        const docW=document.documentElement.clientWidth;
        const overflow=[];
        document.querySelectorAll('header,main,footer,.page-wrapper,.columns,.column.main,.product-info-main,.products,.cart-container,.b2b-login-card,.gallery-placeholder').forEach(el=>{
          const r=el.getBoundingClientRect();
          if(r.right>docW+4||r.left<-4) overflow.push({cls:(el.className||el.tagName).toString().slice(0,70),left:Math.round(r.left),right:Math.round(r.right)});
        });
        // spacing: overlapping sticky ATC / fab
        const sticky=document.querySelector('.awa-pdp-sticky-cta, .awa-pdp-sticky-atc');
        const fab=document.querySelector('.awa-back-to-top, .cookie-consent, #btn-cookie-allow, .block-static-block.cookie');
        let collisions=[];
        if(sticky){
          const sr=sticky.getBoundingClientRect();
          const scs=getComputedStyle(sticky);
          if(scs.display!=='none' && sr.height>0){
            collisions.push({el:'sticky-atc', bottom:Math.round(sr.bottom), top:Math.round(sr.top), h:Math.round(sr.height), pos:scs.position});
          }
        }
        const main=document.querySelector('#maincontent,.page-main');
        const mr=main?main.getBoundingClientRect():null;
        // PLP filter clear text
        const clear=document.querySelector('.filter-clear, .action.clear');
        const clearText=clear?(clear.textContent||'').replace(/\s+/g,' ').trim():null;
        return {english:english.slice(0,20), overflow:overflow.slice(0,8), collisions, clearText, mainH:mr?Math.round(mr.height):null, mainW:mr?Math.round(mr.width):null, title:document.title};
      }, EN.source);
      const row={page:p.id,vp:vp.name,status:resp?.status(),...data};
      results.push(row);
      nd({runId:'visual-r4',hypothesisId:'A1',location:`${p.id}:${vp.name}`,message:'page audit',data:{status:row.status,en:row.english.length,overflow:row.overflow.length,enSample:row.english.slice(0,12),overflowSample:row.overflow.slice(0,5),clearText:row.clearText,collisions:row.collisions,mainW:row.mainW,mainH:row.mainH}});
      console.log(JSON.stringify({page:p.id,vp:vp.name,status:row.status,en:row.english.length,overflow:row.overflow.length,enSample:row.english.slice(0,8).map(e=>e.text+'@'+e.via),clearText:row.clearText,collisions:row.collisions},null,2));
    }catch(e){
      nd({runId:'visual-r4',hypothesisId:'A1',location:`${p.id}:${vp.name}`,message:'fail',data:{error:String(e)}});
      console.error('FAIL',p.id,vp.name,e.message||e);
    }
  }
  await page.close();
}
await browser.close();
const summary={
  total:results.length,
  withEn:results.filter(r=>r.english?.length).map(r=>({page:r.page,vp:r.vp,en:r.english.map(e=>e.text+'@'+e.via)})),
  withOverflow:results.filter(r=>r.overflow?.length).map(r=>({page:r.page,vp:r.vp,ov:r.overflow})),
};
nd({runId:'visual-r4',hypothesisId:'A1',location:'summary',message:'done',data:summary});
fs.writeFileSync('/tmp/awa-visual-r4.json',JSON.stringify({results,summary},null,2));
console.log('SUMMARY',JSON.stringify(summary,null,2));
