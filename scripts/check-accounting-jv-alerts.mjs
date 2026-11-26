import {chromium,expect} from '@playwright/test';
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import {resolve,extname} from 'node:path';
const manifest=JSON.parse(await readFile('public/build/manifest.json','utf8')),entry=manifest['resources/js/app.ts'];
const portal={name:'Judibas',tagline:'Workspace',logo:null,products:[],product:null,page:null,workspacePath:'/accounting',admin:true,administrator:true,superAdmin:true,settingsPage:false,productsPage:false,loginPage:false,employee:null,posts:[],announcement:{title:'',text:'',url:''},error:null,success:null,validationErrors:[]};
const html='<!doctype html><html><head><meta charset="utf-8">'+entry.css.map(c=>'<link rel="stylesheet" href="/build/'+c+'">').join('')+'</head><body><script>window.portal='+JSON.stringify(portal)+'</script><div id="app"></div><script type="module" src="/build/'+entry.file+'"></script></body></html>';
const accounts=[{id:11,name:'Cash',code:'01-01',root_type:'Asset',account_type:'Cash'},{id:12,name:'XYZ Customer',code:'01-02-01',root_type:'Asset',account_type:'Receivable',party_kind:'customer'},{id:13,name:'Generic receivables',code:'01-02',root_type:'Asset',account_type:'Receivable'}].map(a=>({...a,company_id:51,active:true,is_group:false,version:1}));
const browser=await chromium.launch({channel:'msedge',headless:true});
try{
 const context=await browser.newContext({serviceWorkers:'block',viewport:{width:1440,height:1000}}),errors=[];
 await context.route('**/*',async route=>{
  const u=new URL(route.request().url());
  if(u.hostname!=='accounting-preview.test')return route.abort();
  if(u.pathname.startsWith('/build/')){
   try{return route.fulfill({body:await readFile(resolve('public','.'+u.pathname)),contentType:extname(u.pathname)==='.css'?'text/css':'application/javascript'});}catch{return route.abort();}
  }
  if(u.pathname.startsWith('/accounting/api/')){
   const path=u.pathname.slice('/accounting/api/'.length);let data={};
   if(path==='data')data={company_id:51,companies:[{id:51,name:'Preview company'}],is_super_admin:true,role:'manager',can_manage:true,can_delete:true,permissions:['journals.view','journals.create','journals.update','journals.post'],book:{currency:'PKR',start_date:'2026-01-01'},accounts,account_types:{}};
   if(path==='journals'&&route.request().method()==='GET')data={data:[],total:0,current_page:1,last_page:1};
   if(path==='journals'&&route.request().method()==='POST')return route.fulfill({status:422,json:{message:'Example validation error for alert preview.'}});
   if(path==='realtime/config')data={enabled:false};
   return route.fulfill({json:data});
  }
  return route.fulfill({contentType:'text/html',body:html});
 });
 const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));
 await page.goto('http://accounting-preview.test/accounting?company_id=51#journals');
 await page.getByRole('button',{name:'Create journal',exact:false}).click();
 const first=page.locator('select[aria-label="Account line 1"]'),second=page.locator('select[aria-label="Account line 2"]');
 const options=await first.locator('option').allTextContents();assert(options.some(v=>v.includes('XYZ Customer')));assert(options.some(v=>v.includes('Generic receivables')));
 await first.selectOption('11',{force:true});await second.selectOption('12',{force:true});
 await expect(page.locator('select[aria-label^="Customer or supplier line"]')).toHaveCount(0);
 const money=page.locator('.ac-money-input');await money.nth(0).fill('1000');await money.nth(3).fill('1000');
 await page.getByRole('button',{name:'Save draft',exact:true}).click();
 const alert=page.locator('.ac-feedback-toast');await expect(alert).toBeVisible();await expect(alert).toContainText('Example validation error');
 await expect(page.locator('.ac-document .admin-error')).toBeHidden();
 const box=await alert.boundingBox();assert(box.x>800,'Toast should appear on the right');
 await expect(alert).toHaveCount(0,{timeout:7000});assert.deepEqual(errors,[]);
 console.log('JV linked-account options, no party dropdown, right-side error toast and five-second dismissal passed.');
}finally{await browser.close();}
