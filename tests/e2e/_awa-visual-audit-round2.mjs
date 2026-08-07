import { chromium } from 'playwright';
import fs from 'fs';
const LOG='/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-7d58c7.log';
const nd=(o)=>fs.appendFileSync(LOG,JSON.stringify({sessionId:'7d58c7',timestamp:Date.now(),...o})+'\n');

const EN_WORDS=/\b(Add to Cart|Sign In|Log In|Login|Shop Now|View Details|Continue Shopping|Out of stock|In stock|Sort By|Filter By|My Cart|Checkout|Place Order|Update Cart|Remove Item|Wishlist|Compare|Subscribe|Newsletter|Loading\.\.\.|Please wait|No results found|Sorry|Error|Success|Close|Open Menu|Create an Account|Forgot Your Password|Customer Login|Email|Password|Submit|Back|Next|Previous|Shipping|Payment|Order Total|Grand Total|Subtotal|Discount|Coupon Code|Apply|Clear All|Show More|Show Less|Related Products|Upsell|Recently Viewed|Home|Sale|New|Hot|All Categories|Search|Account|My Account|Logout|Log Out|Register|Qty|SKU|Price|Quantity|Items)\b/;

const pages=[
  {id:'home', url:'https://awamotos.com/'},
  {id:'search', url:'https://awamotos.com/catalogsearch/result/?q=retro'},
  {id:'category', url:'https://awamotos.com/retrovisores.html'},
  {id:'cart', url:'https://awamotos.com/checkout/cart/'},
  {id:'login', url:'https://awamotos.com/b2b/account/login/'},
  {id:'register', url:'https://awamotos.com/b2b/register/'},
  {id:'forgot', url:'https://awamotos.com/b2b/account/forgotpassword/'},
  {id:'brands', url:'https://awamotos.com/brand.html'},
  {id:'contact', url:'https://awamotos.com/contact/'},
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
  for(const p of pages){
    try{
      const resp=await page.goto(p.url,{waitUntil:'domcontentloaded',timeout:90000});
      await page.waitForTimeout(1800);
      const data=await page.evaluate(({EN})=>{
        const re=new RegExp(EN,'i');
        const english=[];
        const walker=document.createTreeWalker(document.body,NodeFilter.SHOW_TEXT);
        let node; const seen=new Set();
        while((node=walker.nextNode())){
          const t=(node.textContent||'').replace(/\s+/g,' ').trim();
          if(!t||t.length<2||t.length>100) continue;
          const el=node.parentElement; if(!el) continue;
          if(['SCRIPT','STYLE','NOSCRIPT','SVG','PATH','TEXTAREA'].includes(el.tagName)) continue;
          const cs=getComputedStyle(el);
          if(cs.display==='none'||cs.visibility==='hidden'||cs.opacity==='0') continue;
          const r=el.getBoundingClientRect();
          if(r.width<1||r.height<1) continue;
          if(!re.test(t)) continue;
          // skip brand/tech tokens already PT mixed
          if(/^(AWA|Honda|Yamaha|Suzuki|Kawasaki|COD:|SKU|CNPJ|CPF)/i.test(t)) continue;
          if(/certificado digital|checkout e no cadastro/i.test(t)) continue;
          const key=t.toLowerCase();
          if(seen.has(key)) continue; seen.add(key);
          english.push({text:t, tag:el.tagName, cls:(el.className||'').toString().slice(0,70), top:Math.round(r.top)});
          if(english.length>=25) break;
        }
        const docW=document.documentElement.clientWidth;
        const overflow=[];
        document.querySelectorAll('header, main, footer, .page-wrapper, .columns, .cart-container, .checkout-container, .form-login, .form.create').forEach(el=>{
          const r=el.getBoundingClientRect();
          if(r.right>docW+3||r.left<-3) overflow.push({cls:(el.className||el.tagName).toString().slice(0,80), left:Math.round(r.left), right:Math.round(r.right)});
        });
        const main=document.querySelector('#maincontent, .page-main, .cart-container, .column.main');
        const mr=main?main.getBoundingClientRect():null;
        return {
          title:document.title,
          body:(document.body.className||'').split(/\s+/).slice(0,12),
          english,
          overflow:overflow.slice(0,10),
          mainH:mr?Math.round(mr.height):null,
          mainTop:mr?Math.round(mr.top):null
        };
      },{EN:EN_WORDS.source});
      const row={page:p.id, vp:vp.name, status:resp?.status(), ...data};
      results.push(row);
      nd({runId:'visual-r2',hypothesisId:'A1',location:`${p.id}:${vp.name}`,message:'page audit',data:{status:row.status,en:row.english.length,overflow:row.overflow.length,enSample:row.english.slice(0,12),overflowSample:row.overflow.slice(0,5),mainH:row.mainH,mainTop:row.mainTop,title:row.title}});
      console.log(JSON.stringify({page:p.id,vp:vp.name,status:row.status,en:row.english.length,overflow:row.overflow.length,enSample:row.english.slice(0,8).map(e=>e.text),mainH:row.mainH},null,2));
    }catch(e){
      nd({runId:'visual-r2',hypothesisId:'A1',location:`${p.id}:${vp.name}`,message:'audit failed',data:{error:String(e)}});
      console.error(p.id,vp.name,e.message||e);
    }
  }
  await page.close();
}
await browser.close();
fs.writeFileSync('/tmp/awa-visual-r2.json',JSON.stringify(results,null,2));
console.log('done',results.length);
