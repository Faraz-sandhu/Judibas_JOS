import {chromium,expect} from '@playwright/test';
import assert from 'node:assert/strict';
import {mkdir} from 'node:fs/promises';
const browser=await chromium.launch({channel:'msedge',headless:true});
try{
 const fixtures=[['communication','Communication','/communication'],['projects','PMS','/pms'],['canteen','Canteen','/canteen'],['accounts','Accounting','/accounting'],['daymark','Daymark','/daymark'],['cv-bank','CV Bank','/cv-bank']];
 for(const scenario of ['admin','employee','many']){
  const context=await browser.newContext({viewport:{width:1440,height:1000}});
  await context.addInitScript(({scenario,fixtures})=>{
   let snapshot;
   Object.defineProperty(window,'portal',{configurable:true,get:()=>snapshot,set:value=>{
    const apps=scenario==='employee'?fixtures.slice(0,2):scenario==='many'?[...fixtures,...Array.from({length:24},(_,i)=>['demo-'+i,'Additional application '+(i+1),'/products/demo-'+i])]:fixtures;
    snapshot={...value,admin:scenario!=='employee',administrator:scenario!=='employee',employee:scenario==='employee'?{name:'Demo employee',email:'demo@example.test'}:null,products:apps.map(([slug,name,workspace])=>({slug,name,workspace,category:'Business Apps',description:'Demo',features:[]}))};
   }});
  },{scenario,fixtures});
  const page=await context.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto('http://127.0.0.1:8000/products',{waitUntil:'networkidle'});
  const menu=page.locator('.account-menu'),summary=menu.locator('summary'),grid=menu.locator('.account-app-grid');
  await summary.click();await expect(menu).toHaveAttribute('open','');
  await expect(grid.locator('a')).toHaveCount(scenario==='employee'?2:scenario==='many'?31:7);
  await expect(menu.getByRole('link',{name:'Administration',exact:false})).toHaveCount(scenario==='employee'?0:1);
  for(const width of [1440,718,390,320]){
   await page.setViewportSize({width,height:900});
   const boxes=await grid.locator('a').evaluateAll(links=>links.slice(0,3).map(a=>{const r=a.getBoundingClientRect();return {x:r.x,y:r.y,width:r.width};}));
   if(width>340){assert.ok(Math.abs(boxes[0].y-boxes[1].y)<2);assert.ok(boxes[1].x>boxes[0].x);if(boxes.length>2)assert.ok(boxes[2].y>boxes[0].y);}else assert.ok(boxes[1].y>boxes[0].y);
   const bounds=await menu.locator('.account-dropdown').boundingBox();assert.ok(bounds.x>=0&&bounds.x+bounds.width<=width);
  }
  if(scenario==='many')assert.ok(await grid.evaluate(el=>el.scrollHeight>el.clientHeight));
  await page.setViewportSize({width:718,height:900});
  await menu.getByRole('button',{name:'Dark',exact:true}).click();await expect(page.locator('html')).toHaveAttribute('data-theme','dark');await menu.getByRole('button',{name:'Light',exact:true}).click();
  if(scenario==='admin'){await mkdir('.preview',{recursive:true});await page.screenshot({path:'.preview/account-app-grid.png'});}
  await page.keyboard.press('Escape');await expect(menu).not.toHaveAttribute('open','');await expect(summary).toBeFocused();await summary.click();await page.mouse.click(40,850);await expect(menu).not.toHaveAttribute('open','');assert.deepEqual(errors,[]);
  await context.close();
 }
 console.log('Application switcher passed: two-column layout, narrow-screen fallback, many-product scrolling, assigned-app fixtures, appearance and dismissal.');
}finally{await browser.close();}
