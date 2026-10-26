import {chromium,expect} from '@playwright/test';
import assert from 'node:assert/strict';
import {mkdir} from 'node:fs/promises';
const origin='http://127.0.0.1:8000',browser=await chromium.launch({channel:'msedge',headless:true});
try{
 const page=await browser.newPage({viewport:{width:1440,height:1000},serviceWorkers:'block'}),errors=[];
 page.on('pageerror',e=>errors.push(e.message));
 await page.addInitScript(()=>{const original=HTMLAnchorElement.prototype.click;HTMLAnchorElement.prototype.click=function(){if(this.download){window.__accountingDownload=this.download;return;}return original.call(this);};});
 let role='manager',journal=null,invoice=null,hasBook=true;
 const accounts=[{id:11,code:'1110',name:'Cash',root_type:'Asset',account_type:'Cash'},{id:12,code:'1130',name:'Receivable',root_type:'Asset',account_type:'Receivable'},{id:13,code:'2110',name:'Payable',root_type:'Liability',account_type:'Payable'},{id:14,code:'4100',name:'Service revenue',root_type:'Income',account_type:'Sales'},{id:15,code:'5110',name:'Office costs',root_type:'Expense',account_type:'Operating Expense'}].map(a=>({...a,version:1,company_id:51,parent_id:null,is_group:false,is_system:false,active:true}));
 const paged=data=>({data,total:data.length,current_page:1,last_page:1});
 await page.route(/\/accounting\/api\//,async route=>{
  const url=new URL(route.request().url()),path=url.pathname.replace('/accounting/api/',''),write=route.request().method()==='POST',body=write?route.request().postDataJSON():null;
  let data={};
  if(path==='data')data={companies:[{id:51,name:'Demo books'}],company_id:51,role,can_manage:role!=='viewer',can_delete:role==='manager',book:hasBook?{version:1,currency:'PKR',start_date:'2026-01-01',locked_through:null}:null,accounts,account_types:{Asset:['General','Cash','Receivable'],Liability:['General','Payable'],Equity:['General'],Income:['General','Sales'],Expense:['General','Operating Expense']}};
  else if(path==='parties')data=paged([{id:21,kind:'customer',name:'Demo customer',active:true,version:1}]);
  else if(path==='journals'&&!write)data=url.searchParams.has('id')?{journal}:paged(journal?[journal]:[]);
  else if(path==='journals'&&write){await new Promise(r=>setTimeout(r,250));journal={...body,id:1,number:'JV-0000001',version:1,status:'draft',total:'100.00'};data={message:'Journal saved.',journal};}
  else if(path==='journals/1/post'){journal={...journal,status:'posted',version:2};data={message:'Journal posted.',journal};}
  else if(path==='invoices'&&!write)data=url.searchParams.has('id')?{invoice}:paged(invoice?[invoice]:[]);
  else if(path==='invoices'&&write){invoice={...body,id:1,number:'SI-0000001',party_name:'Demo customer',status:'draft',version:1,net:'250.00',tax:'0.00',total:'250.00',paid:'0.00',outstanding:'250.00',payments:[]};data={message:'Invoice saved.',invoice};}
  else if(path==='invoices/1/post'){invoice={...invoice,status:'posted',version:2};data={message:'Invoice posted.',invoice};}
  else if(path==='invoices/1/payments'){invoice={...invoice,paid:'100.00',outstanding:'150.00',payments:[{id:1,number:'PAY-0000001',version:1,posting_date:body.posting_date,amount:body.amount,reference:body.reference,status:'posted'}]};data={message:'Payment recorded.',invoice};}
  else if(path==='reports'){
   if(url.searchParams.has('export')){await new Promise(r=>setTimeout(r,250));await route.fulfill({contentType:'text/csv',body:'Account,Debit,Credit\nCash,100.00,0.00'});return;}
   data={currency:'PKR',report:url.searchParams.get('report'),rows:[],opening:'0.00',closing:'100.00',debit:'100.00',credit:'0.00',entries:paged([]),summary:{outstanding:'150.00',current:'150.00',days_1_30:'0.00',days_31_60:'0.00',days_61_90:'0.00',over_90:'0.00',debit:'100.00',credit:'100.00',closing_difference:'0.00',profit:'100.00',income:'100.00',expense:'0.00',assets:'100.00',liabilities:'0.00',equity:'0.00',accumulated_earnings:'100.00',balance_difference:'0.00'}};
  }else if(path==='audit')data=paged([]);
  await route.fulfill({json:data});
 });
 await page.route(/\/accounting(?:\?.*)?$/,async route=>{const html=await page.request.get(origin+'/products/accounts');await route.fulfill({contentType:'text/html',body:await html.text()});});
 await page.goto(origin+'/accounting#journals',{waitUntil:'networkidle'});
 await page.getByRole('button',{name:'Create journal',exact:false}).click();
 await page.getByLabel('Description',{exact:true}).fill('Browser test revenue');
 const d1=page.getByLabel('Debit line 1',{exact:true}),c1=page.getByLabel('Credit line 1',{exact:true});
 await expect(d1).toBeEnabled();await expect(c1).toBeEnabled();await d1.click();await d1.pressSequentially('58439');await expect(d1).toHaveValue('58439');await d1.pressSequentially('abc');await expect(d1).toHaveValue('58439');await d1.pressSequentially('.25');await expect(d1).toHaveValue('58439.25');await d1.pressSequentially('6');await expect(d1).toHaveValue('58439.25');await d1.fill('0.00');await d1.fill('50.00');await expect(c1).toBeDisabled();await d1.fill('');await expect(c1).toBeEnabled();await c1.fill('25.00');await expect(d1).toBeDisabled();await c1.fill('0.00');await expect(d1).toBeEnabled();await expect(c1).toBeEnabled();
 await page.getByLabel('Account line 1',{exact:true}).selectOption('11');await page.getByLabel('Debit line 1',{exact:true}).fill('100.00');
 await page.getByLabel('Account line 2',{exact:true}).selectOption('14');await page.getByLabel('Credit line 2',{exact:true}).fill('100.00');
 await page.getByRole('button',{name:'Save draft',exact:true}).click();await expect(page.getByRole('button',{name:'Saving...',exact:true})).toBeDisabled();
 await expect(page.getByRole('button',{name:'Review posting',exact:true})).toBeVisible();
 await page.getByLabel('Description',{exact:true}).fill('Unsaved change');await page.getByRole('button',{name:'Review posting',exact:true}).click();
 await expect(page.getByRole('alert')).toContainText('Save the draft changes');
 await page.getByLabel('Description',{exact:true}).fill('Browser test revenue');await page.getByRole('button',{name:'Review posting',exact:true}).click();
 await page.getByRole('button',{name:'Confirm posting saved entry',exact:true}).click();await expect(page.getByRole('button',{name:'Reverse entry',exact:true})).toBeVisible();
 await expect(page.getByLabel('Description',{exact:true})).toBeDisabled();await page.getByRole('button',{name:'Close document',exact:true}).click();
 await page.getByRole('navigation',{name:'Accounting navigation'}).getByRole('button',{name:'Sales invoices',exact:true}).click();
 await page.getByRole('button',{name:'Create invoice',exact:false}).click();
 await page.getByLabel('Customer',{exact:true}).selectOption('21');await page.getByLabel('Description line 1',{exact:true}).fill('Design services');await page.getByLabel('Account line 1',{exact:true}).selectOption('14');await page.getByLabel('Quantity line 1',{exact:true}).fill('2.50');await page.getByLabel('Rate line 1',{exact:true}).fill('100.00');
 await page.getByRole('button',{name:'Save invoice draft',exact:true}).click();await page.getByRole('button',{name:'Review posting',exact:true}).click();await page.getByRole('button',{name:'Confirm posting invoice',exact:true}).click();
 await page.getByRole('button',{name:'Receive payment',exact:true}).click();await page.getByLabel('Amount',{exact:true}).fill('100.00');await page.getByLabel('Payment reference',{exact:true}).fill('BANK-TEST');await page.getByRole('button',{name:'Confirm and post payment',exact:true}).click();
 await expect(page.locator('.ac-document')).toContainText('150.00');await expect(page.locator('.ac-document')).toContainText('PAY-0000001');await page.getByRole('button',{name:'Close document',exact:true}).click();
 await page.getByRole('navigation',{name:'Accounting navigation'}).getByRole('button',{name:'Trial Balance',exact:true}).click();await expect(page.getByRole('heading',{name:'Trial Balance',exact:true,level:2})).toBeVisible();
 await page.getByRole('button',{name:'Download CSV',exact:true}).click();await expect(page.getByRole('button',{name:'Exporting...',exact:true})).toBeDisabled();await expect.poll(()=>page.evaluate(()=>window.__accountingDownload)).toBe('accounting-trial.csv');
 await page.getByRole('navigation',{name:'Accounting navigation'}).getByRole('button',{name:'Balance Sheet',exact:true}).click();await expect(page.getByRole('heading',{name:'Statement of Financial Position',exact:true})).toBeVisible();await expect(page.locator('.ac-statement')).toContainText('Total liabilities and equity');
 await mkdir('.preview',{recursive:true});await page.screenshot({path:'.preview/accounting-ledger-desktop.png',fullPage:true});
 await page.setViewportSize({width:390,height:844});assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);await page.screenshot({path:'.preview/accounting-ledger-mobile.png',fullPage:true});
 hasBook=false;await page.goto(origin+'/accounting#purchases',{waitUntil:'networkidle'});await page.reload({waitUntil:'networkidle'});await page.getByRole('button',{name:'Create invoice',exact:false}).click();await expect(page.locator('.ac-document')).toBeVisible();await expect(page.locator('.ac-document')).toContainText('Company books are not set up yet');await expect(page.getByRole('button',{name:'Save invoice draft',exact:true})).toBeDisabled();await page.getByRole('button',{name:'Open Company books',exact:true}).click();await expect(page.getByRole('heading',{name:'Company books',exact:true})).toBeVisible();
 hasBook=true;role='viewer';await page.goto(origin+'/accounting#journals',{waitUntil:'networkidle'});await expect(page.getByRole('button',{name:'Create journal',exact:false})).toHaveCount(0);
 assert.deepEqual(errors,[]);console.log('Accounting browser checks passed: journal save/post, unsaved-change protection, invoice posting, partial payment, CSV loading indicator, responsive layout and viewer restrictions.');
}finally{await browser.close();}
