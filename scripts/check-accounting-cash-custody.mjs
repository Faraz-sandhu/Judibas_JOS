import {chromium,expect} from '@playwright/test';
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import {resolve,extname} from 'node:path';
const manifest=JSON.parse(await readFile('public/build/manifest.json','utf8')),entry=manifest['resources/js/app.ts'];
const portal={name:'Judibas',tagline:'Workspace',logo:null,products:[],product:null,page:null,workspacePath:'/accounting',admin:true,administrator:true,superAdmin:true,settingsPage:false,productsPage:false,loginPage:false,employee:null,posts:[],announcement:{title:'',text:'',url:''},error:null,success:null,validationErrors:[]};
const html='<!doctype html><html><head><meta charset="utf-8">'+entry.css.map(c=>'<link rel="stylesheet" href="/build/'+c+'">').join('')+'</head><body><script>window.portal='+JSON.stringify(portal)+'</script><div id="app"></div><script type="module" src="/build/'+entry.file+'"></script></body></html>';
const accounts=[{id:11,name:'Cash',code:'01-01',root_type:'Asset',account_type:'Cash'},{id:12,name:'XYZ Customer',code:'01-02-01',root_type:'Asset',account_type:'Receivable',party_kind:'customer'},{id:13,name:'Generic receivables',code:'01-02',root_type:'Asset',account_type:'Receivable'}].map(a=>({...a,company_id:51,active:true,is_group:false,version:1}));
let superUser=true,attached=false,received=false;
const journal={id:101,number:'JV-PREVIEW-0000001',posting_date:'2026-11-26',kind:'contra',status:'draft',version:2,remarks:'Saved record',currency:'PKR',exchange_rate:'1.000000',total:'100.00',lines:[{account_id:11,debit:'100.00',credit:'0.00'},{account_id:12,debit:'0.00',credit:'100.00'}]};
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
   if(path==='data')data={company_id:51,companies:[{id:51,name:'Preview company'}],is_super_admin:superUser,role:'manager',can_manage:true,can_delete:true,permissions:['journals.view','journals.create','journals.update','journals.post','chart.create','workflow.view','journals.print'],book:{currency:'PKR',start_date:'2026-01-01'},accounts,account_types:{}};
   if(path==='journals'&&route.request().method()==='GET')data=u.searchParams.has('id')?{journal}:{data:[journal],total:1,current_page:1,last_page:1};
   if(path==='journals'&&route.request().method()==='POST')return route.fulfill({status:422,json:{message:'Example validation error for alert preview.'}});
   if(path==='journals/101/payment-proofs'){
    if(route.request().method()==='POST'){attached=true;data={message:'Payment proof attached'};}else data={required:true,has_current:attached,can_upload:true,is_super_admin:true,proofs:attached?[{id:401,original_name:'payment.pdf',current:true,uploaded_name:'Super Admin',created_at:'2026-11-27T10:00:00Z',url:'/accounting/api/payment-proofs/401?company_id=51'}]:[]};
   }
   if(path==='cash-custody')data={can_create:true,can_manage:true,users:[{id:1,name:'Ahmed'}],custodians:[{id:1,user_id:1,account_id:11,name:'Ahmed',code:'01-01-03',account_name:'Ahmed Cash',balance:'250000.00',active:true}],transfers:{data:[{id:1,journal_id:101,number:'CCV-PREVIEW-0000001',status:'draft',version:2,posting_date:'2026-11-27',source_name:'Ahmed Cash',destination_name:'Head Office Cash',receiver_name:'Super Admin',amount:'250000.00',can_receive:!received,received_at:received?'2026-11-27T12:00:00':null}],last_page:1,total:1},activity:{data:[],last_page:1,total:0}};
   if(path==='cash-custody/101/receive'){received=true;data={message:'Physical receipt confirmed.'};}
   if(path==='approvals/document')data={enabled:false};
   if(path==='realtime/config')data={enabled:false};
   return route.fulfill({json:data});
  }
  return route.fulfill({contentType:'text/html',body:html});
 });
 const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));
 await page.goto('http://accounting-preview.test/accounting?company_id=51#cash-custody');
 await expect(page.getByRole('heading',{name:'Cash Custody Vouchers',exact:true})).toBeVisible();
 await expect(page.locator('.custody-balance')).toHaveText('250,000.00');
 await page.getByRole('button',{name:'Confirm receipt',exact:true}).click();
 await page.getByPlaceholder('Cash counted and received in full').fill('Cash counted and received.');
 await page.getByRole('button',{name:'Confirm cash received',exact:true}).click();
 await expect(page.getByText('Receipt confirmed',{exact:true})).toBeVisible();
 await page.getByRole('button',{name:'View',exact:true}).click();
 await expect(page.getByRole('button',{name:'Post custody voucher',exact:true})).toBeDisabled();
 await page.locator('.ac-payment-proofs input[type=file]').setInputFiles({name:'payment.pdf',mimeType:'application/pdf',buffer:Buffer.from('%PDF-1.7\nMock payment proof')});
 await page.getByRole('button',{name:'Attach payment proof',exact:true}).click();
 await expect(page.getByRole('button',{name:'Post custody voucher',exact:true})).toBeEnabled();
 await expect(page.getByRole('link',{name:'Print custody voucher',exact:true})).toBeVisible();
 await page.getByRole('button',{name:'Close document',exact:true}).click();
 await page.getByRole('button',{name:'+ Assign cash custodian',exact:true}).click();
 await expect(page.getByPlaceholder('Cash held by Ahmed')).toBeVisible();
 assert.equal(received,true);assert.deepEqual(errors,[]);
 console.log('Cash custody screen, receiving confirmation, proof guard and assignment form browser checks passed.');
}finally{await browser.close();}
