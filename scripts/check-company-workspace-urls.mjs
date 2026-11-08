import {chromium,expect} from '@playwright/test';
import assert from 'node:assert/strict';
const origin='http://127.0.0.1:8000',browser=await chromium.launch({channel:'msedge',headless:true});
try{
 const context=await browser.newContext({serviceWorkers:'block',viewport:{width:1440,height:1000}}),companies=[{id:101,name:'Skyinfinit',slug:'skyinfinit'},{id:102,name:'Cruxlo',slug:'cruxlo'}];
 await context.addInitScript(({companies})=>{let portal;Object.defineProperty(window,'portal',{get(){return portal},set(value){const product=location.pathname.split('/')[1];portal={...value,workspacePath:['accounting','canteen','cv-bank','daymark'].includes(product)?'/'+product:value.workspacePath,workspaceCompanies:companies,companyWorkspace:{product,id:101,slug:'skyinfinit'},admin:true,administrator:true};}});},{companies});
 const page=await context.newPage(),errors=[],calls=[];page.on('pageerror',e=>errors.push(e.message));
 await page.route(/\/accounting(?:\/(?:skyinfinit|cruxlo|all))?(?:\?.*)?$/,async route=>{const r=await page.request.get(origin+'/products/accounts');await route.fulfill({contentType:'text/html',body:await r.text()});});
 await page.route(/\/accounting\/api\//,async route=>{const url=new URL(route.request().url()),path=url.pathname.slice('/accounting/api/'.length);calls.push({path,id:url.searchParams.get('company_id'),group:url.searchParams.get('workspace_group')});let d={};
  if(path==='data')d={can_view_all:true,all_companies:url.searchParams.get('company_id')==='all',company_id:url.searchParams.get('company_id')==='all'?null:Number(url.searchParams.get('company_id')||101),companies,permissions:['chart.view','books.view','journals.view'],accounts:[],account_types:{},can_manage:false,can_delete:false,role:'viewer',book:null};
  if(path==='all-companies')d={mode:url.searchParams.get('section')==='journals'?'records':'combined',company_count:1,totals:[],records:{data:[],total:0,current_page:1,last_page:1}};
  if(path==='journals')d={data:[],total:0,current_page:1,last_page:1};
  await route.fulfill({json:d});
 });
 await page.goto(origin+'/accounting/skyinfinit#chart',{waitUntil:'networkidle'});await expect(page.locator('.ac-sidebar')).toBeVisible();await expect(page.getByRole('dialog',{name:'Choose a company'})).toHaveCount(0);assert.ok(calls.some(c=>c.path==='data'&&c.id==='101'));assert.equal(new URL(page.url()).pathname,'/accounting/skyinfinit');assert.equal(new URL(page.url()).searchParams.has('company_id'),false);
 await page.reload({waitUntil:'networkidle'});await expect(page.locator('.ac-sidebar')).toBeVisible();assert.equal(new URL(page.url()).pathname,'/accounting/skyinfinit');
 await page.goto(origin+'/accounting?company_id=102#chart',{waitUntil:'networkidle'});await expect.poll(()=>new URL(page.url()).pathname).toBe('/accounting/cruxlo');assert.equal(new URL(page.url()).hash,'#chart');assert.equal(new URL(page.url()).searchParams.has('company_id'),false);assert.ok(calls.some(c=>c.path==='data'&&c.id==='102'));assert.deepEqual(errors,[]);

 const start=calls.length;await page.goto(origin+'/accounting/all?workspace_group=ecommerce#chart',{waitUntil:'networkidle'});await expect(page.locator('.ac-all-companies')).toBeVisible();
 await page.getByRole('navigation',{name:'Accounting navigation'}).getByRole('button',{name:'Journal entries',exact:true}).click();await expect(page.locator('.ac-all-companies')).toBeVisible();
 await page.reload({waitUntil:'networkidle'});await expect(page.locator('.ac-all-companies')).toBeVisible();assert.equal(new URL(page.url()).searchParams.get('workspace_group'),'ecommerce');const grouped=calls.slice(start).filter(c=>['data','all-companies'].includes(c.path));assert.ok(grouped.length>=4);assert.ok(grouped.every(c=>c.group==='ecommerce'),JSON.stringify(grouped));
 await page.route(/\/(?:cv-bank|canteen|daymark)\/skyinfinit(?:\?.*)?$/,async route=>{const root=new URL(route.request().url()).pathname.split('/')[1];const response=await page.request.get(origin+'/products/'+(root==='cv-bank'?'cv-bank':root));await route.fulfill({contentType:'text/html',body:await response.text()});});
 await page.route(/\/cv-bank\/data/,route=>route.fulfill({json:{company_id:101,company_name:'Skyinfinit',companies,company_page:null,can_manage_companies:false,can_edit_careers:false,can_assign_editors:false,jobs:[],departments:[],applications:[]}}));
 await page.route(/\/canteen\/api\//,route=>{const url=new URL(route.request().url());calls.push({path:url.pathname,id:url.searchParams.get('company_id')});return route.fulfill({json:url.pathname.endsWith('/bootstrap')?{companies:companies.map(c=>({...c,role:'employee'}))}:url.pathname.endsWith('/catalog')?{items:[],levels:[],categories:[],employees:[],profile:null,settings:{ordering_enabled:true},allowances:[]}:{data:[],total:0}});});
 await page.route(/\/pms\/api\/realtime\/config/,route=>route.fulfill({json:{enabled:false}}));
 await page.route(/\/daymark\/api\//,route=>{const url=new URL(route.request().url());return route.fulfill({json:url.pathname.endsWith('/data')?{viewer:{id:0,name:'Super Admin',role:'super_admin'},permissions:['view_reports','view_companies'],companies,users:[],reports:[],departments:[],audit:[],action_items:[],assignments:[],custom_roles:[],role_matrix:{abilities:{},roles:[]}}:{enabled:false}});});
 for(const [root,selector]of [['cv-bank','.cv-workspace'],['canteen','.canteen-workspace'],['daymark','.dm-workspace']]){
  await page.goto(origin+'/'+root+'/skyinfinit',{waitUntil:'networkidle'});await expect(page.locator(selector)).toBeVisible();assert.equal(new URL(page.url()).pathname,'/'+root+'/skyinfinit');assert.equal(new URL(page.url()).searchParams.has('company_id'),false);
 }
 assert.ok(calls.some(c=>c.path==='/canteen/api/catalog'&&c.id==='101'));assert.deepEqual(errors,[]);
 console.log('PASS: Accounting, CV Bank, Canteen and Daymark company URLs render their workspaces; Accounting/Canteen API IDs, reload, legacy URL normalization and Accounting All group persistence verified.');
}finally{await browser.close();}
