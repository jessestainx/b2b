import { chromium } from 'playwright';
import fs from 'fs';
const LOG='/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-7d58c7.log';
const nd=(o)=>fs.appendFileSync(LOG,JSON.stringify({sessionId:'7d58c7',timestamp:Date.now(),...o})+'\n');
const browser=await chromium.launch({headless:true,args:['--disable-dev-shm-usage','--no-sandbox']});

async function snap(page, label){
  return await page.evaluate((label)=>{
    const pick=(sel)=>{
      const el=document.querySelector(sel);
      if(!el) return null;
      const r=el.getBoundingClientRect();
      const cs=getComputedStyle(el);
      return {sel,top:Math.round(r.top),left:Math.round(r.left),w:Math.round(r.width),h:Math.round(r.height),pos:cs.position,display:cs.display,overflow:cs.overflow,maxH:cs.maxHeight,text:(el.innerText||'').replace(/\s+/g,' ').trim().slice(0,80)};
    };
    // find Login-like nodes
    const loginNodes=[];
    document.querySelectorAll('a,button,span,strong,label').forEach(el=>{
      const t=(el.textContent||'').replace(/\s+/g,' ').trim();
      if(/^Login$/i.test(t) || /^Sign In$/i.test(t) || /^Log in$/i.test(t)){
        const r=el.getBoundingClientRect();
        loginNodes.push({tag:el.tagName,cls:(el.className||'').toString().slice(0,100),href:el.getAttribute('href'),text:t,top:Math.round(r.top),left:Math.round(r.left),w:Math.round(r.width),h:Math.round(r.height),visible:r.width>0&&r.height>0});
      }
    });
    return {
      label,
      url:location.href,
      body:document.body.className,
      loginNodes,
      main:pick('#maincontent'),
      header:pick('.awa-site-header'),
      hero:pick('.banner-slider, .content-top-home, .awa-hero, .top-home-content'),
      columns:pick('.columns'),
      filterCol:pick('.col-xs-12.col-sm-3.col-md-3.col-lg-2, .sidebar.sidebar-main'),
      filter:pick('.block.filter'),
      filterTitle:pick('.filter-title, .block.filter .block-title'),
      navInner:pick('.awa-nav-bar__inner'),
      pageTitle:pick('.page-title-wrapper, h1.page-title'),
      breadcrumb:pick('.breadcrumbs, .nav-breadcrumb'),
      productGrid:pick('.products-grid, .product-items, ul.products'),
      viewport:{w:innerWidth,h:innerHeight},
      scrollY:Math.round(scrollY)
    };
  }, label);
}

const page=await browser.newPage();

// 1) Login text desktop home
await page.setViewportSize({width:1366,height:900});
await page.goto('https://awamotos.com/',{waitUntil:'domcontentloaded',timeout:60000});
await page.waitForTimeout(2000);
const home=await snap(page,'home-desktop');
nd({runId:'visual-deep',hypothesisId:'V2',location:'home-desktop',message:'login + main geometry',data:home});

// 2) search tablet filter sticky
await page.setViewportSize({width:768,height:1024});
await page.goto('https://awamotos.com/catalogsearch/result/?q=retro',{waitUntil:'domcontentloaded',timeout:60000});
await page.waitForTimeout(2000);
const search0=await snap(page,'search-tablet-top');
await page.evaluate(()=>window.scrollTo(0,600));
await page.waitForTimeout(500);
const search1=await snap(page,'search-tablet-scroll600');
await page.evaluate(()=>window.scrollTo(0,1200));
await page.waitForTimeout(500);
const search2=await snap(page,'search-tablet-scroll1200');
nd({runId:'visual-deep',hypothesisId:'V3',location:'search-tablet',message:'filter sticky sequence',data:{search0,search1,search2}});

// 3) category tablet
await page.goto('https://awamotos.com/retrovisores.html',{waitUntil:'domcontentloaded',timeout:60000});
await page.waitForTimeout(2000);
await page.evaluate(()=>window.scrollTo(0,800));
await page.waitForTimeout(400);
const cat=await snap(page,'category-tablet-scroll');
nd({runId:'visual-deep',hypothesisId:'V3',location:'category-tablet',message:'filter sticky category',data:cat});

console.log(JSON.stringify({homeLogin:home.loginNodes,homeMain:home.main,homeHero:home.hero,search0Filter:search0.filter,search1Filter:search1.filter,search2Filter:search2.filter,catFilter:cat.filter,catFilterCol:cat.filterCol},null,2));
await browser.close();
