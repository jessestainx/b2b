import { chromium } from 'playwright';
import fs from 'fs';
const LOG='/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-7d58c7.log';
const nd=(o)=>fs.appendFileSync(LOG,JSON.stringify({sessionId:'7d58c7',timestamp:Date.now(),...o})+'\n');

const EN_WORDS=/\b(Add to Cart|Sign In|Log In|Login|Shop Now|View Details|Continue Shopping|Out of stock|In stock|Sort By|Filter By|My Cart|Checkout|Place Order|Update Cart|Remove Item|Wishlist|Compare|Subscribe|Newsletter|Loading\.\.\.|Please wait|No results found|Create an Account|Forgot Your Password|Customer Login|Email Address|Submit|Shipping|Payment|Order Total|Grand Total|Coupon Code|Clear All|Show More|Show Less|Related Products|Recently Viewed|Logout|Log Out|Register|Quantity|Items)\b/;

const pages=[
  {id:'home', url:'https://awamotos.com/'},
  {id:'search', url:'https://awamotos.com/catalogsearch/result/?q=retro'},
  {id:'category', url:'https://awamotos.com/retrovisores.html'},
  {id:'pdp', url:'https://awamotos.com/catalogsearch/result/?q=012345'},
  {id:'cart', url:'https://awamotos.com/checkout/cart/'},
  {id:'login', url:'https://awamotos.com/b2b/account/login/'},
  {id:'register', url:'https://awamotos.com/b2b/register/'},
  {id:'forgot', url:'https://awamotos.com/b2b/account/forgotpassword/'},
  {id:'brands', url:'https://awamotos.com/nossas-marcas'},
  {id:'contact', url:'https://awamotos.com/contact/'},
  {id:'cms404', url:'https://awamotos.com/pagina-que-nao-existe-awa-404'},
];
const viewports=[
  {name:'desktop', width:1366, height:900},
  {name:'tablet', width:768, height:1024},
  {name:'mobile', width:390, height:844},
];

const browser=await chromium.launch({headless:true,args:['--disable-dev-shm-usage','--no-sandbox']});
const results=[];

for(const vp of viewports){
  const page=await browser.newPage({viewport:{width:vp.width,height:vp.height}});
  page.setDefaultTimeout(45000);
  for(const p of pages){
    try{
      const resp=await page.goto(p.url,{waitUntil:'domcontentloaded',timeout:60000});
      await page.waitForTimeout(1200);
      // if search page, try first product link for pdp only when id=pdp
      if(p.id==='pdp'){
        const href=await page.evaluate(()=>{
          const a=document.querySelector('.product-item-link, .product-item-info a, a.product.photo');
          return a?a.href:null;
        });
        if(href){
          await page.goto(href,{waitUntil:'domcontentloaded',timeout:60000});
          await page.waitForTimeout(1200);
        }
      }
      const data=await page.evaluate(({EN})=>{
        const re=new RegExp(EN,'i');
        const english=[];
        const walker=document.createTreeWalker(document.body,NodeFilter.SHOW_TEXT);
        let node; const seen=new Set();
        while((node=walker.nextNode())){
          const t=(node.textContent||'').replace(/\s+/g,' ').trim();
          if(!t||t.length<2||t.length>120) continue;
          const el=node.parentElement; if(!el) continue;
          if(['SCRIPT','STYLE','NOSCRIPT','SVG','PATH','TEXTAREA','CODE'].includes(el.tagName)) continue;
          const cs=getComputedStyle(el);
          if(cs.display==='none'||cs.visibility==='hidden'||cs.opacity==='0') continue;
          const r=el.getBoundingClientRect();
          if(r.width<1||r.height<1) continue;
          if(!re.test(t)) continue;
          // skip mixed PT that only contain brand/tech or false positives
          if(/certificado digital|finalização|pedido B2B|WhatsApp/i.test(t)) continue;
          if(/\b(SKU|CNPJ|CPF|COD:)\b/i.test(t) && !/\b(Login|Checkout|Sign In)\b/i.test(t)) continue;
          const key=t.toLowerCase();
          if(seen.has(key)) continue; seen.add(key);
          english.push({text:t, tag:el.tagName, cls:(el.className||'').toString().slice(0,70)});
          if(english.length>=20) break;
        }
        const docW=document.documentElement.clientWidth;
        const overflow=[];
        document.querySelectorAll('header, main, footer, .page-wrapper, .columns, .cart-container, .column.main, .products, .sidebar, .filter-options, .b2b-login-card, .awa-cart-empty').forEach(el=>{
          const r=el.getBoundingClientRect();
          if(r.right>docW+4||r.left<-4) overflow.push({tag:el.tagName, cls:(el.className||'').toString().slice(0,90), left:Math.round(r.left), right:Math.round(r.right), docW});
        });
        // spacing / util: check main content width usage
        const main=document.querySelector('#maincontent, .page-main, .column.main');
        const mr=main?main.getBoundingClientRect():null;
        const gaps=[];
        if(mr && docW>=992){
          const sidePad=Math.round(mr.left);
          if(sidePad>120) gaps.push({issue:'large-side-pad', sidePad, mainW:Math.round(mr.width), docW});
        }
        // filter sticky sanity on category/search desktop/tablet
        const filter=document.querySelector('.sidebar.sidebar-main, .block.filter, #layered-filter-block');
        let filterInfo=null;
        if(filter){
          const fs=getComputedStyle(filter);
          const fr=filter.getBoundingClientRect();
          filterInfo={pos:fs.position, top:fs.top, maxH:fs.maxHeight, ov:fs.overflowY, h:Math.round(fr.height), topPx:Math.round(fr.top)};
        }
        return {
          title:document.title,
          url:location.pathname+location.search,
          english,
          overflow:overflow.slice(0,8),
          gaps,
          filterInfo,
          mainH:mr?Math.round(mr.height):null,
          mainW:mr?Math.round(mr.width):null,
          mainTop:mr?Math.round(mr.top):null
        };
      },{EN:EN_WORDS.source});
      const row={page:p.id, vp:vp.name, status:resp?.status(), ...data};
      results.push(row);
      nd({runId:'visual-r3',hypothesisId:'A1',location:`${p.id}:${vp.name}`,message:'page audit',data:{status:row.status,en:row.english.length,overflow:row.overflow.length,gaps:row.gaps,enSample:row.english.slice(0,10),filterInfo:row.filterInfo,mainH:row.mainH,mainW:row.mainW,url:row.url}});
      console.log(JSON.stringify({page:p.id,vp:vp.name,status:row.status,en:row.english.length,overflow:row.overflow.length,enSample:row.english.slice(0,6).map(e=>e.text),gaps:row.gaps,filter:row.filterInfo&&{pos:row.filterInfo.pos,top:row.filterInfo.top},mainW:row.mainW},null,2));
    }catch(e){
      nd({runId:'visual-r3',hypothesisId:'A1',location:`${p.id}:${vp.name}`,message:'audit failed',data:{error:String(e)}});
      console.error('FAIL',p.id,vp.name,e.message||e);
    }
  }
  await page.close();
}
await browser.close();
fs.writeFileSync('/tmp/awa-visual-r3.json',JSON.stringify(results,null,2));
const summary={
  total:results.length,
  withEn:results.filter(r=>r.english?.length).map(r=>({page:r.page,vp:r.vp,en:r.english.map(e=>e.text)})),
  withOverflow:results.filter(r=>r.overflow?.length).map(r=>({page:r.page,vp:r.vp,overflow:r.overflow})),
  withGaps:results.filter(r=>r.gaps?.length).map(r=>({page:r.page,vp:r.vp,gaps:r.gaps})),
};
nd({runId:'visual-r3',hypothesisId:'A1',location:'summary',message:'audit complete',data:summary});
console.log('SUMMARY',JSON.stringify(summary,null,2));
