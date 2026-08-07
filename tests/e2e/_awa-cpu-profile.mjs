import { chromium } from 'playwright';
import fs from 'fs';
const LOG='/home/jessessh/htdocs/srv1113343.hstgr.cloud/.cursor/debug-7d58c7.log';
const nd=(o)=>fs.appendFileSync(LOG,JSON.stringify({sessionId:'7d58c7',timestamp:Date.now(),...o})+'\n');

const browser=await chromium.launch({headless:true,args:['--disable-dev-shm-usage','--no-sandbox']});
const page=await browser.newPage({viewport:{width:1124,height:900}});
const client=await page.context().newCDPSession(page);
await client.send('Profiler.enable');

await page.goto('https://awamotos.com/catalogsearch/result/?q=retro',{waitUntil:'domcontentloaded',timeout:60000});
await page.waitForTimeout(1800);
await page.locator('[data-role="awa-vertical-menu-trigger"]').click({force:true});
await page.waitForTimeout(400);

await client.send('Profiler.start');
await page.locator('#search').click({force:true});
await page.locator('#search').type('retro',{delay:30});
await page.waitForTimeout(2200);
const { profile } = await client.send('Profiler.stop');

const nodeById = new Map(profile.nodes.map(n=>[n.id,n]));
const selfTimeByNode = new Map();
const samples = profile.samples || [];
const deltas = profile.timeDeltas || [];
for (let i=0;i<samples.length;i++) {
  const id=samples[i];
  const d=(deltas[i]||0)/1000; // ms
  selfTimeByNode.set(id,(selfTimeByNode.get(id)||0)+d);
}

const agg = new Map();
for (const [id,ms] of selfTimeByNode) {
  const node=nodeById.get(id);
  if (!node || !node.callFrame) continue;
  const cf=node.callFrame;
  const url=cf.url||'(native)';
  const fn=cf.functionName||'(anonymous)';
  const key=`${url}::${fn}`;
  agg.set(key,(agg.get(key)||0)+ms);
}
const top=[...agg.entries()].sort((a,b)=>b[1]-a[1]).slice(0,30).map(([k,ms])=>({key:k,ms:Math.round(ms)}));

const topByUrl = new Map();
for (const [k,ms] of agg) {
  const url = k.split('::')[0];
  topByUrl.set(url,(topByUrl.get(url)||0)+ms);
}
const urls=[...topByUrl.entries()].sort((a,b)=>b[1]-a[1]).slice(0,20).map(([url,ms])=>({url,ms:Math.round(ms)}));

const payload={samples:samples.length,topFunctions:top,topUrls:urls};
nd({runId:'cpu-profile',hypothesisId:'P1',location:'cdp:profiler',message:'cpu hotspots during search typing',data:payload});
console.log(JSON.stringify(payload,null,2));
await browser.close();
