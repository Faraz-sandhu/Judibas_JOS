// Production bundle, isolated API responses; never writes application data.
import {chromium,expect} from '@playwright/test';
import {readFile} from 'node:fs/promises';
import {resolve,extname} from 'node:path';
const manifest=JSON.parse(await readFile('public/build/manifest.json','utf8'));
const entry=manifest['resources/js/app.ts'];
const portal={name:'Judibas',workspacePath:'/communication',products:[],posts:[],admin:true,administrator:true,superAdmin:true,validationErrors:[],announcement:{}};
const html=`<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="fixture">${entry.css.map(c=>`<link rel="stylesheet" href="/build/${c}">`).join('')}</head><body><script>window.portal=${JSON.stringify(portal)}</script><div id="app"></div><script type="module" src="/build/${entry.file}"></script></body></html>`;
const person={id:2,name:'Preview employee',email:'preview@example.test'};
const chat={id:1,kind:'direct',name:person.name,members:[person],preview:'Hello',unread:0,last_message_at:null};
const snapshot={admin:true,manager:true,profile:null,company_id:null,user_id:null,people:[person],review_people:[person],companies:[{id:1,name:'Preview company'}],conversations:[chat],stories:[],viewed_user:null,calling_enabled:false,call_history_enabled:false};
const browser=await chromium.launch({channel:'msedge',headless:true});
const errors=[];
try{
 const context=await browser.newContext({viewport:{width:1366,height:900},serviceWorkers:'block'});
 await context.route('**/*',async route=>{
  const {hostname,pathname:path}=new URL(route.request().url());
  if(hostname!=='motion-preview.test')return route.abort();
  if(path==='/communication')return route.fulfill({contentType:'text/html',body:html});
  if(path.startsWith('/communication/api')){
   let data={};
   if(path==='/communication/api')data=snapshot;
   else if(path.endsWith('/presence'))data={states:{},preferences:{}};
   else if(path.endsWith('/realtime/config'))data={enabled:false};
   else if(path.endsWith('/protection'))data={enabled:false};
   else if(path.endsWith('/messages'))data={messages:[{id:1,sender_id:2,sender_name:person.name,body:'Animation preview',created_at:'2026-11-17T10:00:00Z',reactions:[]}],pinned:[],has_more:false};
   else if(path.endsWith('/shared'))data={items:[],members:[person],has_more:false};
   return route.fulfill({json:data});
  }
  try{const file=resolve('public','.'+path);return route.fulfill({body:await readFile(file),contentType:{'.js':'text/javascript','.css':'text/css','.svg':'image/svg+xml','.woff2':'font/woff2'}[extname(file)]||'application/octet-stream'});}catch{return route.fulfill({status:404,body:''});}
 });
 const page=await context.newPage();page.on('pageerror',error=>errors.push(error.message));page.on('console',m=>{if(m.type()==='error'&&/TypeError|ReferenceError|Unhandled/.test(m.text()))errors.push(m.text());});
 await page.goto('http://motion-preview.test/communication');
 await page.getByRole('button',{name:'Send announcement',exact:true}).click();
 await expect(page.getByRole('dialog')).toBeVisible();
 await page.waitForTimeout(300);
 await page.getByRole('button',{name:'Close',exact:true}).click();
 await expect(page.locator('.comm-modal-backdrop')).toHaveClass(/product-dialog-leave-active/);
 await expect(page.getByRole('dialog')).toHaveCount(0);
 await page.locator('.comm-conversations>button').first().click();
 await expect(page.locator('.comm-message')).toContainText('Animation preview');
 await page.getByRole('button',{name:'Open chat profile',exact:true}).click();
 await expect(page.locator('.comm-detail-panel')).toBeVisible();
 await page.emulateMedia({reducedMotion:'reduce'});
 await page.getByRole('button',{name:'Send announcement',exact:true}).click();
 await expect(page.getByRole('dialog')).toBeVisible();
 expect(await page.getByRole('dialog').evaluate(el=>getComputedStyle(el.parentElement).transitionDuration)).toBe('0s');
 await page.getByRole('button',{name:'Close',exact:true}).click();
 await expect(page.getByRole('dialog')).toHaveCount(0);
 await page.setViewportSize({width:390,height:844});
 expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2)).toBe(true);
 expect(errors).toEqual([]);
 console.log('PASS: Communication dialog enter/leave, chat opening, details panel, reduced motion, mobile overflow and runtime checks. No database writes.');
}finally{await browser.close();}
