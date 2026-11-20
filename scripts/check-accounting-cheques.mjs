import {chromium,expect} from '@playwright/test';
import {readFile} from 'node:fs/promises';
import path from 'node:path';
const manifest=JSON.parse(await readFile('public/build/manifest.json','utf8')),entry=manifest['resources/js/app.ts'];
const browser=await chromium.launch({channel:'msedge',headless:true});
try{
const page=await browser.newPage({viewport:{width:1440,height:1000}}),errors=[];page.on('pageerror',e=>errors.push(e.message));let row=null;
await page.route('**/*',async route=>{const u=new URL(route.request().url()),p=u.pathname,body=route.request().method()==='POST'?route.request().postDataJSON():null;
if(p.startsWith('/build/'))return route.fulfill({body:await readFile(path.resolve('public',p.slice(1))),contentType:p.endsWith('.css')?'text/css':'text/javascript'});
if(p.startsWith('/accounting/api/')){
let data={};
if(p.endsWith('/data'))data={company_id:51,companies:[{id:51,name:'Skyinfinit'}],permissions:['overview.view','cheques.view','cheques.create','cheques.clear','cheques.manage','cheques.bounce','payments.create','payments.reverse'],accounts:[{id:1,code:'01-01-02',name:'Bank',account_type:'Bank',active:true,is_group:false}],book:{currency:'PKR',start_date:'2026-01-01'},can_manage:true,can_delete:true};
else if(p.endsWith('/cheques')&&body){expect(body.invoice_id).toBe(9);expect(body.amount).toBe('1000');row={...body,id:1,party_name:'Test Customer',invoice_number:'SI-0000009',direction:'received',status:'pending',version:1,amount:'1000.00',currency:'PKR',events:[]};data={message:'Cheque registered.'};}
else if(p.endsWith('/actions')){expect(body.action).toBe('clear');row.status='cleared';row.version++;data={message:'Cheque cleared.'};}
else if(p.endsWith('/cheques')){const summary=Object.fromEntries(['issued','received','bounced','cleared'].map(k=>[k,{count:row&&((k==='received'&&row.status==='pending')||k===row.status)?1:0,amount:'1000.00'}]));data={summary,rows:{data:row?[row]:[],last_page:1,total:row?1:0},invoices:[{id:9,number:'SI-0000009',party_name:'Test Customer',kind:'sale',currency:'PKR'}]};}
return route.fulfill({json:data});}
if(p==='/accounting/skyinfinit'){const portal={name:'Judibas',products:[],workspacePath:'/accounting',companyWorkspace:{product:'accounting',id:51,slug:'skyinfinit'},workspaceCompanies:[{id:51,name:'Skyinfinit',slug:'skyinfinit'}],admin:true,administrator:true,validationErrors:[],announcement:{}};return route.fulfill({contentType:'text/html',body:`<html><head><meta name="viewport" content="width=device-width,initial-scale=1">${entry.css.map(c=>`<link rel="stylesheet" href="/build/${c}">`).join('')}</head><body><script>window.portal=${JSON.stringify(portal)}</script><div id="app"></div><script type="module" src="/build/${entry.file}"></script></body></html>`});}
return route.abort();});
await page.goto('http://pdc-preview.test/accounting/skyinfinit#cheques');
await page.getByRole('button',{name:'+ Register cheque',exact:true}).click();
await page.getByRole('combobox',{name:'Posted invoice',exact:true}).click();await page.getByRole('option',{name:/SI-0000009/}).click();await page.getByRole('combobox',{name:'Company bank account',exact:true}).click();await page.getByRole('option',{name:/Bank/}).click();
await page.getByLabel('Cheque number',{exact:true}).fill('TEST-PDC-1');await page.getByLabel('Cheque bank name',{exact:true}).fill('MCB');await page.getByLabel('Amount (PKR)',{exact:true}).fill('1000');await page.getByRole('button',{name:'Register cheque',exact:true}).click();
await expect(page.getByText('TEST-PDC-1',{exact:true})).toBeVisible();await page.getByRole('button',{name:'Clear',exact:true}).click();await page.getByLabel('Reason / bank reference',{exact:true}).fill('Bank clearance confirmed');await page.getByRole('button',{name:'Confirm',exact:true}).click();await expect(page.locator('.ac-finance-table .ac-badge')).toHaveText('cleared');
await page.setViewportSize({width:390,height:844});expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2)).toBe(true);expect(errors).toEqual([]);console.log('PASS: PDC registration, invoice/bank selection, clearance dialog, table state, mobile overflow and runtime checks. APIs mocked.');
}finally{await browser.close();}
