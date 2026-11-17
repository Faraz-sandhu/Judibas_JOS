// Isolated invitation UI checks using the production bundle. No real invitations.
import {chromium,expect} from '@playwright/test';
import {readFile} from 'node:fs/promises';
import {resolve,extname} from 'node:path';
const manifest=JSON.parse(await readFile('public/build/manifest.json','utf8')),entry=manifest['resources/js/app.ts'];
const portal={name:'Judibas',tagline:'Workspace',products:[],posts:[],admin:true,administrator:true,settingsPage:true,validationErrors:[],announcement:{}};
const html=`<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="fixture">${entry.css.map(c=>`<link rel="stylesheet" href="/build/${c}">`).join('')}</head><body><script>window.portal=${JSON.stringify(portal)}</script><div id="app"></div><script type="module" src="/build/${entry.file}"></script></body></html>`;
const products=[{slug:'projects',name:'PMS',workspace:'/pms'},{slug:'accounts',name:'Accounting',workspace:'/accounting'},{slug:'communication',name:'Communication',workspace:'/communication'}];
const data={products,companies:[{id:1,name:'Skyinfinit'},{id:2,name:'Bedizen'},{id:3,name:'Other company'}],product_company_ids:{projects:[1],accounts:[2]},groups:[],users:[],blogs:[],pages:[],pms_roles:[],pms_departments:[],pms_tasks:[],accounting_roles:[],canteen_levels:[],daymark_departments:[],daymark_roles:[],daymark_managers:[],announcement:{}};
const browser=await chromium.launch({channel:'msedge',headless:true}),errors=[];
try{
 const context=await browser.newContext({serviceWorkers:'block',viewport:{width:1366,height:1000}});
 await context.route('**/*',async route=>{
  const {hostname,pathname:path}=new URL(route.request().url());
  if(hostname!=='invitation-preview.test')return route.abort();
  if(path==='/admin')return route.fulfill({contentType:'text/html',body:html});
  if(path==='/admin/api')return route.fulfill({json:data});
  if(path==='/admin/api/invitations')return route.fulfill({json:{invitations:[]}});
  if(path.includes('/api/'))return route.fulfill({json:{items:[],unread:0,enabled:false}});
  try{const file=resolve('public','.'+path);return route.fulfill({body:await readFile(file),contentType:{'.js':'text/javascript','.css':'text/css','.woff2':'font/woff2','.svg':'image/svg+xml'}[extname(file)]||'application/octet-stream'});}catch{return route.fulfill({status:404,body:''});}
 });
 const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));
 await page.goto('http://invitation-preview.test/admin#invitations');
 await page.getByRole('button',{name:'Invite employee',exact:true}).click();
 const dialog=page.getByRole('dialog');await expect(dialog).toBeVisible();
 const companies=dialog.locator('.admin-access-fields > fieldset').filter({has:page.locator('legend').filter({hasText:/^Companies/})});
 await expect(companies.getByRole('checkbox')).toHaveCount(0);
 const product=name=>dialog.locator('.admin-product-options label').filter({hasText:name}).getByRole('checkbox');
 await product('PMS').check();
 await expect(companies.getByRole('checkbox')).toHaveCount(1);
 await companies.getByRole('checkbox',{name:'Skyinfinit',exact:true}).check();
 await product('Accounting').check();
 await expect(companies.getByRole('checkbox')).toHaveCount(2);
 await product('PMS').uncheck();
 await expect(companies.getByRole('checkbox')).toHaveCount(1);
 await expect(companies.locator('legend')).toContainText('0 selected');
 await product('Communication').check();
 await expect(companies.getByRole('checkbox')).toHaveCount(3);
 await companies.getByRole('checkbox',{name:'Other company',exact:true}).check();
 await product('Communication').uncheck();
 await expect(companies.getByRole('checkbox')).toHaveCount(1);
 await expect(companies.locator('legend')).toContainText('0 selected');
 await page.setViewportSize({width:390,height:844});
 expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2)).toBe(true);
 expect(errors).toEqual([]);
 console.log('PASS: invitation product/company filtering, combined products, universal access, stale selection cleanup and mobile layout. No database writes.');
}finally{await browser.close();}
