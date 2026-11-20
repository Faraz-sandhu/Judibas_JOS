import {chromium,expect} from '@playwright/test';
import {readFile} from 'node:fs/promises';
import path from 'node:path';
const manifest=JSON.parse(await readFile('public/build/manifest.json','utf8')),entry=manifest['resources/js/app.ts'];
const browser=await chromium.launch({channel:'msedge',headless:true});
try{
const page=await browser.newPage({viewport:{width:1440,height:1000}}),errors=[];page.on('pageerror',e=>errors.push(e.message));let row=null,chequeQuery=null;
await page.route('**/*',async route=>{const u=new URL(route.request().url()),p=u.pathname,body=route.request().method()==='POST'?route.request().postDataJSON():null;
if(p.startsWith('/build/'))return route.fulfill({body:await readFile(path.resolve('public',p.slice(1))),contentType:p.endsWith('.css')?'text/css':'text/javascript'});
if(p.startsWith('/accounting/api/')){
let data={};
if(p.endsWith('/data'))data={company_id:51,companies:[{id:51,name:'Skyinfinit'}],permissions:['sales.view','purchases.view','journals.view','reports.trial','reports.cashflow','reports.profit','reports.receivables','reports.payables','reports.export','overview.view','cheques.view','cheques.create','cheques.clear','cheques.manage','cheques.bounce','payments.create','payments.reverse'],accounts:[{id:1,code:'01-01-02',name:'Bank',account_type:'Bank',active:true,is_group:false}],book:{currency:'PKR',start_date:'2026-01-01'},can_manage:true,can_delete:true};
else if(p.endsWith('/reports')){data=u.searchParams.get('report')==='cashflow'?{report:'cashflow',currency:'PKR',from:u.searchParams.get('from'),to:u.searchParams.get('to'),summary:{opening:'10000.00',inflow:'5000.00',outflow:'2000.00',net:'3000.00',closing:'13000.00'},activities:{operating:'3000.00',investing:'0.00',financing:'0.00',review:'0.00'},review_count:0,accounts:[{id:1,code:'01-01-02',name:'Bank',type:'Bank',balance:'13000.00'}],rows:[{id:1,number:'JV-0000001',date:'2026-11-20',description:'Test receipt',category:'operating',inflow:'5000.00',outflow:'0.00'}]}:{summary:{income:'5000.00',expense:'2000.00',outstanding:'1000.00'}};}
else if(p.endsWith('/cheques')&&body){expect(body.invoice_id).toBe(9);expect(body.amount).toBe('1000');row={...body,id:1,party_name:'Test Customer',invoice_number:'SI-0000009',direction:'received',status:'pending',version:1,amount:'1000.00',currency:'PKR',events:[]};data={message:'Cheque registered.'};}
else if(p.endsWith('/actions')){expect(body.action).toBe('clear');row.status='cleared';row.version++;data={message:'Cheque cleared.'};}
else if(p.endsWith('/cheques')){chequeQuery=Object.fromEntries(u.searchParams);const summary=Object.fromEntries(['issued','received','bounced','cleared'].map(k=>[k,{count:row&&((k==='received'&&row.status==='pending')||k===row.status)?1:0,amount:'1000.00'}]));data={summary,rows:{data:row?[row]:[],last_page:1,total:row?1:0},invoices:[{id:9,number:'SI-0000009',party_name:'Test Customer',kind:'sale',currency:'PKR'}]};}
return route.fulfill({json:data});}
if(p==='/accounting/skyinfinit'){const portal={name:'Judibas',products:[],workspacePath:'/accounting',companyWorkspace:{product:'accounting',id:51,slug:'skyinfinit'},workspaceCompanies:[{id:51,name:'Skyinfinit',slug:'skyinfinit'}],admin:true,administrator:true,validationErrors:[],announcement:{}};return route.fulfill({contentType:'text/html',body:`<html><head><meta name="viewport" content="width=device-width,initial-scale=1">${entry.css.map(c=>`<link rel="stylesheet" href="/build/${c}">`).join('')}</head><body><script>window.portal=${JSON.stringify(portal)}</script><div id="app"></div><script type="module" src="/build/${entry.file}"></script></body></html>`});}
return route.abort();});
await page.goto('http://pdc-preview.test/accounting/skyinfinit#overview');
await expect(page.getByRole('heading',{name:'Cash flow statement',exact:true})).toBeVisible();
expect(chequeQuery.from).toBeTruthy();expect(chequeQuery.to).toBeTruthy();
await expect(page.locator('.ac-cash-closing')).toContainText('13,000.00');
await page.getByRole('button',{name:'View movements',exact:true}).click();await expect(page.getByText('JV-0000001',{exact:true})).toBeVisible();
await page.getByRole('button',{name:'Update overview',exact:true}).click();await expect(page.getByRole('heading',{name:'Cash flow statement',exact:true})).toBeVisible();
const shortcut=page.locator('.ac-dashboard-shortcuts');await expect(shortcut.locator('.ac-shortcut-arrow')).toHaveCount(4);expect(await shortcut.innerText()).not.toContain('?');const sw=await shortcut.boundingBox(),mw=await page.locator('.ac-main').boundingBox();expect(Math.abs(sw.width-mw.width)).toBeLessThanOrEqual(2);await page.screenshot({path:'.preview/accounting-overview-cashflow-desktop.png',fullPage:true});
await page.setViewportSize({width:390,height:844});expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2)).toBe(true);await page.screenshot({path:'.preview/accounting-overview-cashflow-mobile.png',fullPage:true});await expect(page.locator('.ac-financial-dashboard')).not.toContainText('\uFFFD');await page.getByRole('button',{name:/PDC cleared/}).click();await expect(page.getByRole('heading',{name:'Cheque Register / PDC',exact:true})).toBeVisible();await expect(page.getByLabel('From',{exact:true})).toHaveValue(chequeQuery.from);await expect(page.getByLabel('To',{exact:true})).toHaveValue(chequeQuery.to);expect(errors).toEqual([]);console.log('PASS: dashboard cash reconciliation, period refresh, movement details, PDC widgets and mobile layout. APIs mocked.');
}finally{await browser.close();}
