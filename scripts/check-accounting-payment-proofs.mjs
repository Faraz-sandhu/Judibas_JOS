import {chromium,expect} from '@playwright/test';
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import {resolve,extname} from 'node:path';
const manifest=JSON.parse(await readFile('public/build/manifest.json','utf8')),entry=manifest['resources/js/app.ts'];
const portal={name:'Judibas',tagline:'Workspace',logo:null,products:[],product:null,page:null,workspacePath:'/accounting',admin:true,administrator:true,superAdmin:true,settingsPage:false,productsPage:false,loginPage:false,employee:null,posts:[],announcement:{title:'',text:'',url:''},error:null,success:null,validationErrors:[]};
const html='<!doctype html><html><head><meta charset="utf-8">'+entry.css.map(c=>'<link rel="stylesheet" href="/build/'+c+'">').join('')+'</head><body><script>window.portal='+JSON.stringify(portal)+'</script><div id="app"></div><script type="module" src="/build/'+entry.file+'"></script></body></html>';
const accounts=[{id:11,name:'Cash',code:'01-01',root_type:'Asset',account_type:'Cash'},{id:12,name:'XYZ Customer',code:'01-02-01',root_type:'Asset',account_type:'Receivable',party_kind:'customer'},{id:13,name:'Generic receivables',code:'01-02',root_type:'Asset',account_type:'Receivable'}].map(a=>({...a,company_id:51,active:true,is_group:false,version:1}));
let superUser=true,attached=false;
const journal={id:101,number:'JV-PREVIEW-0000001',posting_date:'2026-11-26',kind:'general',status:'draft',version:2,remarks:'Saved record',currency:'PKR',exchange_rate:'1.000000',total:'100.00',lines:[{account_id:11,debit:'100.00',credit:'0.00'},{account_id:12,debit:'0.00',credit:'100.00'}]};
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
   if(path==='data')data={company_id:51,companies:[{id:51,name:'Preview company'}],is_super_admin:superUser,role:'manager',can_manage:true,can_delete:true,permissions:['journals.view','journals.create','journals.update','journals.post'],book:{currency:'PKR',start_date:'2026-01-01'},accounts,account_types:{}};
   if(path==='journals'&&route.request().method()==='GET')data=u.searchParams.has('id')?{journal}:{data:[journal],total:1,current_page:1,last_page:1};
   if(path==='journals'&&route.request().method()==='POST')return route.fulfill({status:422,json:{message:'Example validation error for alert preview.'}});
   if(path==='journals/101/payment-proofs'){
    if(route.request().method()==='POST'){attached=true;data={message:'Payment proof attached'};}else data={required:true,has_current:attached,can_upload:true,is_super_admin:true,proofs:attached?[{id:401,original_name:'payment.pdf',current:true,uploaded_name:'Super Admin',created_at:'2026-11-27T10:00:00Z',url:'/accounting/api/payment-proofs/401?company_id=51'}]:[]};
   }
   if(path==='realtime/config')data={enabled:false};
   return route.fulfill({json:data});
  }
  return route.fulfill({contentType:'text/html',body:html});
 });
 const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));
 await page.goto('http://accounting-preview.test/accounting?company_id=51#journals');
 await page.getByRole('button',{name:'Edit draft',exact:true}).first().click();
 await expect(page.locator('.ac-payment-proofs')).toContainText('Required');
 await expect(page.getByRole('button',{name:'Review posting',exact:true})).toBeDisabled();
 await expect(page.getByRole('button',{name:'Confirm posting',exact:true})).toHaveCount(0);
 await page.locator('.ac-payment-proofs input[type=file]').setInputFiles({name:'payment.pdf',mimeType:'application/pdf',buffer:Buffer.from('%PDF-1.7\nMock payment proof')});
 await page.getByRole('button',{name:'Attach payment proof',exact:true}).click();
 await expect(page.locator('.ac-payment-proofs')).toContainText('Attached');
 await expect(page.getByRole('link',{name:'View proof',exact:true})).toBeVisible();
 await page.getByRole('button',{name:'Review posting',exact:true}).click();
 await expect(page.locator('.ac-post-confirm')).toBeVisible();
 assert.equal(attached,true);assert.deepEqual(errors,[]);console.log('Payment proof upload, required state and posting guard browser checks passed.');
}finally{await browser.close();}
