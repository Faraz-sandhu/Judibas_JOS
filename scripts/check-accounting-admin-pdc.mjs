import {chromium,expect} from '@playwright/test';
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import {resolve,extname} from 'node:path';
const manifest=JSON.parse(await readFile('public/build/manifest.json','utf8')),entry=manifest['resources/js/app.ts'];
const portal={name:'Judibas',tagline:'Workspace',logo:null,products:[],product:null,page:null,workspacePath:'/accounting',admin:true,administrator:true,superAdmin:true,settingsPage:false,productsPage:false,loginPage:false,employee:null,posts:[],announcement:{title:'',text:'',url:''},error:null,success:null,validationErrors:[]};
const html='<!doctype html><html><head><meta charset="utf-8">'+entry.css.map(c=>'<link rel="stylesheet" href="/build/'+c+'">').join('')+'</head><body><script>window.portal='+JSON.stringify(portal)+'</script><div id="app"></div><script type="module" src="/build/'+entry.file+'"></script></body></html>';
const accounts=[{id:11,name:'Cash',code:'01-01',root_type:'Asset',account_type:'Bank'},{id:12,name:'XYZ Customer',code:'01-02-01',root_type:'Asset',account_type:'Receivable',party_kind:'customer'},{id:13,name:'Generic receivables',code:'01-02',root_type:'Asset',account_type:'Receivable'}].map(a=>({...a,company_id:51,active:true,is_group:false,version:1}));
let captured=null;
const cheque={id:201,version:2,invoice_id:301,bank_account_id:11,direction:'received',cheque_number:'CH-TEST',drawee_bank:'MCB',cheque_date:'2026-11-26',recorded_date:'2026-11-26',amount:'100.00',amount_minor:10000,currency:'PKR',status:'cleared',party_name:'XYZ Customer',invoice_number:'SI-TEST',events:[]};
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
   if(path==='data')data={company_id:51,companies:[{id:51,name:'Preview company'}],is_super_admin:true,role:'manager',can_manage:true,can_delete:true,permissions:['cheques.view','cheques.manage','cheques.create'],book:{currency:'PKR',start_date:'2026-01-01'},accounts,account_types:{}};
   if(path==='journals'&&route.request().method()==='GET')data={data:[],total:0,current_page:1,last_page:1};
   if(path==='journals'&&route.request().method()==='POST')return route.fulfill({status:422,json:{message:'Example validation error for alert preview.'}});
   if(path==='cheques')data={rows:{data:[cheque],total:1,current_page:1,last_page:1},summary:Object.fromEntries(['issued','received','bounced','cleared'].map(k=>[k,{count:0,amount:'0.00'}])),invoices:[{id:301,number:'SI-TEST',party_name:'XYZ Customer',kind:'sale',currency:'PKR'}]};
   if(path==='admin-records/cheque/201')data={message:'Review correction',context:{form:{...cheque,reason:route.request().postDataJSON().reason}}};
   if(path==='admin-cheques/201'){captured=route.request().postDataJSON();data={message:'Cheque corrected'};}
   if(path==='realtime/config')data={enabled:false};
   return route.fulfill({json:data});
  }
  return route.fulfill({contentType:'text/html',body:html});
 });
 const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));
 await page.goto('http://accounting-preview.test/accounting?company_id=51#cheques');
 await page.getByRole('button',{name:'Edit record',exact:true}).click();
 await page.getByPlaceholder('Explain this correction or removal').fill('Correct the cheque amount.');
 await page.getByRole('button',{name:'Continue to edit',exact:true}).click();
 await expect(page.getByRole('heading',{name:'Edit post-dated cheque',exact:true})).toBeVisible();
 await expect(page.getByLabel('Recorded date', {exact:true})).toHaveAttribute('readonly','');
 await page.getByLabel('Amount (PKR)',{exact:true}).fill('75.00');
 await page.getByRole('button',{name:'Save cheque correction',exact:true}).click();
 await expect(page.getByRole('heading',{name:'Edit post-dated cheque',exact:true})).toHaveCount(0);
 assert.equal(captured.amount,'75.00');assert.equal(captured.version,2);assert.equal(captured.invoice_id,301);assert.deepEqual(errors,[]);
 console.log('PDC correction form, preserved registration date and versioned save payload passed.');
}finally{await browser.close();}
