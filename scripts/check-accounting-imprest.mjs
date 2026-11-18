// Browser checks use isolated API fixtures; no financial records are written.
import {chromium,expect} from '@playwright/test';
import {readFile,mkdir} from 'node:fs/promises';
import {resolve,extname} from 'node:path';
const manifest=JSON.parse(await readFile('public/build/manifest.json','utf8')),entry=manifest['resources/js/app.ts'];
let manager=true,fund=null,available='0.00',reserved='0.00',posted='0.00',counter=0;
const journals=new Map(),errors=[],writes=[];
const accounts=[
 {id:1,parent_id:null,code:'01',name:'Assets',root_type:'Asset',account_type:'General',is_group:true,active:true},
 {id:2,parent_id:1,code:'01-01',name:'Current Assets',root_type:'Asset',account_type:'General',is_group:true,active:true},
 {id:3,parent_id:2,code:'01-01-01',name:'Company Cash',root_type:'Asset',account_type:'Cash',is_group:false,active:true},
 {id:4,parent_id:null,code:'05-01-01',name:'Office Expenses',root_type:'Expense',account_type:'Operating Expense',is_group:false,active:true},
 {id:5,parent_id:2,code:'01-01-04',name:'Officer Cash',root_type:'Asset',account_type:'Cash',is_group:false,active:true,imprest_fund:true},
];
const basePermissions=['imprest.view','imprest.create','journals.view','journals.print','cash_payments.view','cash_payments.print','workflow.view'];
const book={currency:'PKR',start_date:'2026-01-01',approval_required:false,approval_types:[]};
const portal={name:'Judibas',products:[],product:{slug:'accounts',name:'Accounting'},employee:{name:'Finance Officer',email:'finance@example.test'},workspacePath:'/accounting',posts:[],validationErrors:[],announcement:{}};
const html=`<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="fixture">${entry.css.map(c=>`<link rel="stylesheet" href="/build/${c}">`).join('')}</head><body><script>window.portal=${JSON.stringify(portal)}</script><div id="app"></div><script type="module" src="/build/${entry.file}"></script></body></html>`;
const browser=await chromium.launch({channel:'msedge',headless:true});
try{
 const page=await browser.newPage({viewport:{width:1440,height:1050}});page.on('pageerror',e=>errors.push(e.message));
 await page.route('**/*',async route=>{
  const url=new URL(route.request().url()),path=url.pathname,body=route.request().method()==='POST'?route.request().postDataJSON():null;
  if(url.hostname!=='imprest-preview.test')return route.abort();
  if(path==='/accounting')return route.fulfill({contentType:'text/html',body:html});
  if(path==='/accounting/api/data')return route.fulfill({json:{company_id:1,companies:[{id:1,name:'Suivillion'}],book,accounts,account_types:{Asset:['Cash','Bank'],Expense:['Operating Expense']},permissions:manager?[...basePermissions,'imprest.manage','imprest.post','journals.reverse','cash_payments.reverse']:basePermissions,role:manager?'manager':'junior_finance_officer',can_manage:true,can_delete:manager}});
  if(path==='/accounting/api/imprest')return route.fulfill({json:{can_manage:manager,can_post:manager,can_create:true,restricted:!manager,officers:[{id:7,name:'Junior Finance Officer',email:'junior@example.test'}],funds:fund?[{...fund,balance:posted,reserved,available}]:[],movements:{data:[...journals.values()].reverse().map(j=>({id:j.id,journal_id:j.id,number:j.number,posting_date:j.posting_date,type:j.imprest.type,void:j.imprest.void,status:j.status,version:j.version,officer_name:'Junior Finance Officer',amount:j.total})),total:journals.size,last_page:1}}});
  if(path==='/accounting/api/imprest/funds'){
   writes.push(body);expect(body.user_id).toBe(7);expect(body.parent_id).toBe(2);expect(body.company_id).toBe(1);
   fund={id:1,account_id:5,user_id:7,name:body.name,officer_name:'Junior Finance Officer',officer_email:'junior@example.test',active:true};
   await new Promise(r=>setTimeout(r,150));return route.fulfill({json:{message:'Fund assigned.',fund_id:1}});
  }
  if(path==='/accounting/api/imprest/allocate'||path==='/accounting/api/imprest/spend'){
   writes.push(body);const funding=path.endsWith('allocate');expect(body.company_id).toBe(1);expect(body.fund_id).toBe(1);
   if(!funding&&Number(body.amount)>Number(available))return route.fulfill({status:422,json:{message:'Insufficient assigned imprest funds.'}});
   const id=++counter,j={id,number:(funding?'IAV-':'PCV-')+String(id).padStart(7,'0'),kind:funding?'general':'cash_payment',posting_date:body.posting_date,version:1,status:'draft',currency:'PKR',total:Number(body.amount).toFixed(2),voucher_person:body.person,remarks:body.remarks,imprest:{type:funding?'funding':'spending',void:false},lines:[{id:1,account_code:funding?'01-01-04':'05-01-01',account_name:funding?'Officer Cash':'Office Expenses',debit:body.amount,credit:'0.00'},{id:2,account_code:funding?'01-01-01':'01-01-04',account_name:funding?'Company Cash':'Officer Cash',debit:'0.00',credit:body.amount}]};
   journals.set(id,j);if(!funding){reserved=j.total;available=(Number(posted)-Number(reserved)).toFixed(2);}
   return route.fulfill({json:{message:'Voucher saved as non-posted.',journal:j}});
  }
  if(path.match(/\/imprest\/\d+\/post$/)){
   const j=journals.get(Number(path.split('/').at(-2)));expect(manager).toBe(true);j.status='posted';j.version++;posted=j.total;available=j.total;
   return route.fulfill({json:{message:'Advance posted.',journal:j}});
  }
  if(/^\/accounting\/api\/imprest\/\d+$/.test(path))return route.fulfill({json:{journal:journals.get(Number(path.split('/').at(-1)))}});
  if(path==='/accounting/api/journals')return route.fulfill({json:{journal:journals.get(Number(url.searchParams.get('id')))}});
  if(path==='/accounting/api/approvals/document')return route.fulfill({json:{enabled:false,approval:null}});
  if(path.startsWith('/accounting/api/notifications'))return route.fulfill({status:403,json:{message:'Disabled in fixture'}});
  if(path.startsWith('/accounting/api/'))return route.fulfill({json:{enabled:false,items:[]}});
  try{return route.fulfill({body:await readFile(resolve('public','.'+path)),contentType:{'.js':'text/javascript','.css':'text/css','.woff2':'font/woff2'}[extname(path)]||'application/octet-stream'});}catch{return route.fulfill({status:404,body:''});}
 });
 await page.goto('http://imprest-preview.test/accounting?company_id=1#imprest');
 await page.getByRole('button',{name:'+ Assign officer fund',exact:true}).click();
 let dialog=page.getByRole('dialog',{name:'Assign officer fund',exact:true});
 await dialog.locator('select').first().selectOption('7');await dialog.getByLabel('Fund name').fill('Suivillion Junior Cash');
 await dialog.getByRole('button',{name:'Assign fund',exact:true}).click();await expect(dialog).toHaveCount(0);
 await page.getByRole('button',{name:'Allocate / top up',exact:true}).click();dialog=page.getByRole('dialog',{name:'Create imprest advance',exact:true});
 await dialog.locator('select').selectOption('3');await dialog.getByLabel('Amount (PKR)',{exact:true}).fill('50000');
 await dialog.getByRole('button',{name:'Save non-posted voucher',exact:true}).click();
 await page.getByRole('button',{name:'Review posting',exact:true}).click();dialog=page.getByRole('dialog',{name:'Post saved imprest voucher',exact:true});
 await dialog.getByRole('button',{name:'Confirm',exact:true}).click();
 await expect(page.locator('.imprest-available dd')).toHaveText('50,000.00');
 manager=false;await page.reload();
 await expect(page.getByRole('button',{name:'+ Assign officer fund',exact:true})).toHaveCount(0);await expect(page.getByRole('button',{name:'Allocate / top up',exact:true})).toHaveCount(0);
 await page.getByRole('button',{name:'+ Expense voucher',exact:true}).click();dialog=page.getByRole('dialog',{name:'Create petty cash expense',exact:true});
 await dialog.locator('select').selectOption('4');await dialog.getByLabel('Amount (PKR)',{exact:true}).fill('60000');
 await dialog.getByRole('button',{name:'Save non-posted voucher',exact:true}).click();await expect(dialog.getByRole('alert')).toContainText('Insufficient assigned imprest funds');
 await dialog.getByLabel('Amount (PKR)',{exact:true}).fill('40000');await dialog.getByRole('button',{name:'Save non-posted voucher',exact:true}).click();
 await expect(page.locator('.imprest-available dd')).toHaveText('10,000.00');await expect(page.getByRole('button',{name:'Review posting',exact:true})).toHaveCount(0);
 await expect(page.getByRole('button',{name:'Cancel draft',exact:true})).toBeVisible();
 await page.getByRole('button',{name:'Close document',exact:true}).click();await page.setViewportSize({width:390,height:844});
 expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
 await mkdir('.preview',{recursive:true});await page.screenshot({path:'.preview/accounting-imprest-mobile.png',fullPage:true});
 await page.setViewportSize({width:1440,height:1050});await page.screenshot({path:'.preview/accounting-imprest-desktop.png',fullPage:true});
 expect(errors).toEqual([]);console.log('PASS: manager fund assignment and 50k allocation/posting; officer-only controls, over-limit alert, 40k reservation, mobile layout, and no browser errors. All financial APIs mocked.');
}finally{await browser.close();}
