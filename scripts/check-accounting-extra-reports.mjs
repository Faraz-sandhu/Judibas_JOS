import {chromium,expect} from '@playwright/test';import {readFile} from 'node:fs/promises';import path from 'node:path';
const manifest=JSON.parse(await readFile('public/build/manifest.json','utf8')),entry=manifest['resources/js/app.ts'];const browser=await chromium.launch({channel:'msedge',headless:true});
try{
 const page=await browser.newPage({viewport:{width:1440,height:1000}}),errors=[];page.on('pageerror',e=>errors.push(e.message));let item=null;
 const groups=['items','sales','purchases','journals','cash_payments','cash_receipts','bank_payments','bank_receipts','expense_vouchers','income_vouchers'];const permissions=[...groups.flatMap(g=>[g+'.view',g+'.create',g+'.update']),'reports.ledger','reports.receivables','reports.payables','reports.export'];
 const accounts=[{id:11,code:'04-01',name:'Service Revenue',root_type:'Income',account_type:'Sales'},{id:12,code:'05-01',name:'Outsourcing',root_type:'Expense',account_type:'General'},{id:13,code:'01-01',name:'Cash',root_type:'Asset',account_type:'Cash'},{id:14,code:'01-02',name:'Bank',root_type:'Asset',account_type:'Bank'},{id:15,code:'01-03',name:'Client A',root_type:'Asset',account_type:'Receivable'}].map(a=>({...a,active:true,is_group:false}));
 await page.route('**/*',async route=>{const u=new URL(route.request().url()),p=u.pathname,body=route.request().method()==='POST'?route.request().postDataJSON():null;
 if(p.startsWith('/build/'))return route.fulfill({body:await readFile(path.resolve('public',p.slice(1))),contentType:p.endsWith('.css')?'text/css':'text/javascript'});
 if(p.startsWith('/accounting/api/')){let d={};if(p.endsWith('/data'))d={company_id:51,companies:[{id:51,name:'Skyinfinit'}],permissions,accounts,book:{currency:'PKR',start_date:'2026-01-01',approval_required:false},can_manage:true,can_delete:true};
 else if(p.endsWith('/reports'))d={currency:'PKR',opening:'0.00',debit:'1000.00',credit:'0.00',closing:'1000.00',total:'1000.00',summary:{outstanding:'1000.00',current:'1000.00',days_1_30:'0.00',days_31_60:'0.00',days_61_90:'0.00',over_90:'0.00'},rows:[{id:1,number:'SI-TEST',party_name:'Test customer',due_date:'2026-12-01',overdue_days:0,outstanding:'1000.00'}],entries:{data:[{id:1,number:'JV-TEST',posting_date:'2026-11-23',code:'01-01',name:'Cash',debit:'1000.00',credit:'0.00',balance:'1000.00',total:'1000.00',party_name:'Test customer'}],total:1,last_page:1}};
 else if(p.endsWith('/report-parties'))d={data:[]};
 else if(p.endsWith('/items')&&body){expect(body.code).toBe('DEV-TEST');item={...body,id:1,version:1,sale_rate:'1500.00',purchase_rate:'900.00'};d={message:'Item saved.'};}
 else if(p.endsWith('/items'))d={data:item?[item]:[],current_page:1,last_page:1,total:item?1:0};
 else if(p.endsWith('/parties'))d={data:[{id:1,name:'Client A',active:true,account_id:15}]};
 else if(p.endsWith('/invoices')||p.endsWith('/journals'))d={data:[],last_page:1,total:0};
 return route.fulfill({json:d});}
 const portal={name:'Judibas',products:[],workspacePath:'/accounting',companyWorkspace:{product:'accounting',id:51,slug:'skyinfinit'},workspaceCompanies:[{id:51,name:'Skyinfinit',slug:'skyinfinit'}],admin:true,administrator:true,validationErrors:[],announcement:{}};
 return route.fulfill({contentType:'text/html',body:`<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">${entry.css.map(c=>`<link rel="stylesheet" href="/build/${c}">`).join('')}</head><body><script>window.portal=${JSON.stringify(portal)}</script><div id="app"></div><script type="module" src="/build/${entry.file}"></script></body></html>`});});

 for(const section of ['customer-ledger','supplier-ledger','expense-report','income-report','sales-report','cash-book','bank-book','aged-receivables','aged-payables']){
 await page.goto('http://reports-preview.test/accounting/skyinfinit#'+section);
 await expect(page.locator('.ar-statement')).toBeVisible();await expect(page.locator('.ar-summary article').first()).toBeVisible();
 }
 await page.screenshot({path:'.preview/accounting-reports-desktop.png',fullPage:true});
 await page.setViewportSize({width:390,height:844});await expect(page.locator('.ar-statement')).toBeVisible();
 expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1)).toBeTruthy();
 await page.screenshot({path:'.preview/accounting-reports-mobile.png',fullPage:true});expect(errors).toEqual([]);
 console.log('PASS: all nine report layouts, summary cards, mobile overflow and no runtime errors. APIs mocked.');
}finally{await browser.close();}
