import {chromium,expect} from '@playwright/test';
import assert from 'node:assert/strict';
const origin='http://127.0.0.1:8000',browser=await chromium.launch({channel:'msedge',headless:true});
try {
 const context=await browser.newContext({serviceWorkers:'block',viewport:{width:1440,height:1000}});
 await context.addInitScript(()=>{let portal;Object.defineProperty(window,'portal',{configurable:true,get(){return portal;},set(value){const id=new URL(location.href).searchParams.get('workspace_company')||(location.pathname.endsWith('/skyinfinit')?'101':location.pathname.endsWith('/cruxlo')?'102':'all');portal={...value,workspacePath:'/pms',workspaceCompanies:[{id:101,name:'Skyinfinit',slug:'skyinfinit'},{id:102,name:'Cruxlo',slug:'cruxlo'}],admin:true,administrator:true,pmsBootstrap:{permissions:['dashboard-view','project-view','client-view','report-view','summary-view'],user_id:null,name:'Scope Tester',branding:{},company_context:id==='all'?null:{id:Number(id),name:id==='101'?'Skyinfinit':'Cruxlo'}}};}});});
 const pages=[await context.newPage(),await context.newPage()],requests=[],errors=[];
 for(const page of pages){
  page.on('pageerror',e=>errors.push(e.message));
  await page.route(/\/pms(?:\/(?:skyinfinit|cruxlo|all))?(?:\?.*)?$/,async route=>{const response=await page.request.get(origin+'/products/projects');await route.fulfill({contentType:'text/html',body:await response.text()});});
  await page.route(/\/pms\/api(?:\/|$)/,async route=>{
   const request=route.request(),url=new URL(request.url()),company=request.headers()['x-pms-company'];requests.push({page:pages.indexOf(page),company,path:url.pathname});const name=company==='101'?'Skyinfinit':'Cruxlo';let body={};
   if(url.pathname.endsWith('/dashboard/bundle'))body={data:{ongoingProjectCount:1,dashboardCharts:{},dashboardInsights:{workload:[],deadlines:[],deadline_total:0}},table:[]};
   else if(url.pathname.endsWith('/clients'))body={companies:[{id:1,name}],clients:[{id:1,company_id:1,name:name+' Client',company:{name},projects_count:1,status:true}]};
   else if(url.pathname.endsWith('/reports'))body={data:{projects:[],users:[],departments:[],clients:[],companies:[{id:1,name}]}};
   else if(url.pathname.endsWith('/reports/overview'))body={summary:{total:1,completed:0,overdue:0,dueSoon:0,loggedSeconds:0,progress:0},departments:[]};
   else if(url.pathname.endsWith('/reports/data'))body={data:[],recordsFiltered:0};
   else if(url.pathname.endsWith('/realtime/config'))body={enabled:false};
   else if(url.pathname.endsWith('/notifications'))body={items:[],unread:0};
   else if(url.pathname.endsWith('/work-logs'))body=request.headers()['x-requested-with']==='XMLHttpRequest'?{data:[]}:{data:{projects:[],users:[]}};
   await route.fulfill({json:body});
  });
 }
 await pages[0].goto(origin+'/pms/skyinfinit',{waitUntil:'networkidle'});await expect(pages[0].locator('.pms-company-context')).toContainText('Skyinfinit');await expect(pages[0].locator('a[href*="section=clients"]')).toHaveAttribute('href',/\/pms\/skyinfinit\?section=clients/);
 await pages[1].goto(origin+'/pms/cruxlo',{waitUntil:'networkidle'});await expect(pages[1].locator('.pms-company-context')).toContainText('Cruxlo');
 for(const [index,page]of pages.entries()){
  const id=index===0?'101':'102';await page.locator('.pms-sidebar').getByRole('button',{name:'Clients',exact:true}).click();await expect(page.locator('.pms-content')).toContainText(index===0?'Skyinfinit Client':'Cruxlo Client');assert.equal(new URL(page.url()).pathname,index===0?'/pms/skyinfinit':'/pms/cruxlo');
  await page.locator('.pms-sidebar').getByRole('button',{name:'Reports',exact:true}).click();await expect(page.locator('.pms-report-table-card')).toBeVisible();await expect(page.locator('a[href*="/reports/export/csv"]')).toHaveAttribute('href',new RegExp('workspace_company='+id));
 }
 await pages[0].reload({waitUntil:'networkidle'});await expect(pages[0].locator('.pms-company-context')).toContainText('Skyinfinit');
 assert.ok(requests.length>8);assert.ok(requests.every(r=>r.company===(r.page===0?'101':'102')),JSON.stringify(requests));assert.deepEqual(errors,[]);console.log('PASS: company header on every API request, independent tabs, sidebar navigation, reload, dashboard links and export scope.');
}finally{await browser.close();}
