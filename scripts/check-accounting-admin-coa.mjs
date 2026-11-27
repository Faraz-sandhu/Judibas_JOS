import {chromium,expect} from '@playwright/test';
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import {resolve,extname} from 'node:path';
const manifest=JSON.parse(await readFile('public/build/manifest.json','utf8')),entry=manifest['resources/js/app.ts'];
const portal={name:'Judibas',tagline:'Workspace',logo:null,products:[],product:null,page:null,workspacePath:'/accounting',admin:true,administrator:true,superAdmin:true,settingsPage:false,productsPage:false,loginPage:false,employee:null,posts:[],announcement:{title:'',text:'',url:''},error:null,success:null,validationErrors:[]};
const html='<!doctype html><html><head><meta charset="utf-8">'+entry.css.map(c=>'<link rel="stylesheet" href="/build/'+c+'">').join('')+'</head><body><script>window.portal='+JSON.stringify(portal)+'</script><div id="app"></div><script type="module" src="/build/'+entry.file+'"></script></body></html>';
let actual=true;
const accounts=[{id:11,name:'Cash',code:'01-01',root_type:'Asset',account_type:'Cash'},{id:12,name:'XYZ Customer',code:'01-02-01',root_type:'Asset',account_type:'Receivable',party_kind:'customer'},{id:13,name:'Generic receivables',code:'01-02',root_type:'Asset',account_type:'Receivable'}].map(a=>({...a,company_id:51,parent_id:null,active:true,is_group:false,version:1}));
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
   if(path==='data')data={company_id:51,companies:[{id:51,name:'Preview company'}],is_super_admin:actual,role:'manager',can_manage:true,can_delete:true,permissions:['chart.view'],book:{currency:'PKR',start_date:'2026-01-01'},accounts:accounts.map(a=>({...a,debit:actual?'150000.00':'0.00',credit:'0.00',balance:actual?'150000.00':(a.id===11?'50000.00':'0.00'),balance_side:actual?'Dr':'',actual_balance_visible:actual,display_balance_visible:actual||a.id===11,balance_basis:!actual&&a.id===11?'disbursement':'ledger',disbursement_amount:a.id===11?'50000.00':'0.00'})),account_types:{}};
   if(path==='journals'&&route.request().method()==='GET')data={data:[],total:0,current_page:1,last_page:1};
   if(path==='journals'&&route.request().method()==='POST')return route.fulfill({status:422,json:{message:'Example validation error for alert preview.'}});
   if(path==='realtime/config')data={enabled:false};
   return route.fulfill({json:data});
  }
  return route.fulfill({contentType:'text/html',body:html});
 });
 const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));
 await page.goto('http://accounting-preview.test/accounting?company_id=51#chart');
 let row=page.locator('[data-account-id="11"]');await row.locator('.ac-account').click();
 await page.getByRole('button',{name:'Edit account',exact:true}).click();
 await expect(page.getByRole('heading',{name:'Super Admin: edit account'})).toBeVisible();
 await expect(page.getByRole('button',{name:'Save account',exact:true})).toBeDisabled();
 await page.getByRole('button',{name:'Cancel',exact:true}).click();
 await page.getByRole('button',{name:'Delete account',exact:true}).click();
 await expect(page.getByText('Remove this account and all children',{exact:false})).toBeVisible();
 await expect(page.getByRole('button',{name:'Confirm deletion',exact:true})).toBeDisabled();
 actual=false;await page.reload();row=page.locator('[data-account-id="11"]');await row.locator('.ac-account').click();
 await expect(page.getByRole('button',{name:'Delete account',exact:true})).toHaveCount(0);
 assert.deepEqual(errors,[]);console.log('COA Super Admin edit/archive controls, reason requirement and non-super visibility passed.');
}finally{await browser.close();}
