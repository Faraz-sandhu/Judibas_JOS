// Isolated invitation UI checks using the production bundle. No real invitations.
import {chromium,expect} from '@playwright/test';
import {readFile} from 'node:fs/promises';
import {resolve,extname} from 'node:path';
const manifest=JSON.parse(await readFile('public/build/manifest.json','utf8')),entry=manifest['resources/js/app.ts'];
const portal={name:'Judibas',tagline:'Workspace',products:[],posts:[],admin:true,administrator:true,settingsPage:true,validationErrors:[],announcement:{}};
const html=`<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="fixture">${entry.css.map(c=>`<link rel="stylesheet" href="/build/${c}">`).join('')}</head><body><script>window.portal=${JSON.stringify(portal)}</script><div id="app"></div><script type="module" src="/build/${entry.file}"></script></body></html>`;
const products=[{slug:'projects',name:'PMS',workspace:'/pms'},{slug:'accounts',name:'Accounting',workspace:'/accounting'},{slug:'communication',name:'Communication',workspace:'/communication'}];
const data={products,companies:[{id:1,name:'Skyinfinit'},{id:2,name:'Bedizen'},{id:3,name:'Other company'}],product_company_ids:{projects:[1],accounts:[2]},groups:[],users:[],blogs:[],pages:[],pms_roles:[],pms_departments:[],pms_tasks:[],accounting_roles:[],canteen_levels:[],daymark_departments:[],daymark_roles:[],daymark_managers:[],announcement:{}};
let submitted=null,attempts=0;
data.products.push({slug:'cv-bank',name:'CV Bank',workspace:'/cv-bank'});
data.pms_roles=[{id:1,role_name:'Employee',role_key:'employee'},{id:2,role_name:'Project Manager',role_key:'project_manager'}];
data.pms_departments=[{id:1,dept_name:'Development'},{id:2,dept_name:'Design'}];
data.accounting_roles=[{slug:'viewer',name:'Viewer',active:true}];
const browser=await chromium.launch({channel:'msedge',headless:true}),errors=[];
try{
 const context=await browser.newContext({serviceWorkers:'block',viewport:{width:1366,height:1000}});
 await context.route('**/*',async route=>{
  const {hostname,pathname:path}=new URL(route.request().url());
  if(hostname!=='invitation-preview.test')return route.abort();
  if(path==='/admin')return route.fulfill({contentType:'text/html',body:html});
  if(path==='/admin/api')return route.fulfill({json:data});
  if(path==='/admin/api/invitations'){
   if(route.request().method()==='POST'){
    submitted=route.request().postDataJSON();attempts++;
    await new Promise(resolve=>setTimeout(resolve,350));
    if(attempts===1)return route.fulfill({status:422,json:{message:'Check access',errors:{email:['Please review the invitation email.']}}});
    return route.fulfill({status:201,json:{message:'Invitation created.',invitation_url:'https://example.test/invitations/fixture'}});
   }
   return route.fulfill({json:{invitations:[]}});
  }
  if(path.includes('/api/'))return route.fulfill({json:{items:[],unread:0,enabled:false}});
  try{const file=resolve('public','.'+path);return route.fulfill({body:await readFile(file),contentType:{'.js':'text/javascript','.css':'text/css','.woff2':'font/woff2','.svg':'image/svg+xml'}[extname(file)]||'application/octet-stream'});}catch{return route.fulfill({status:404,body:''});}
 });
 const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));
 await page.goto('http://invitation-preview.test/admin#invitations');
 await page.getByRole('button',{name:'Invite employee',exact:true}).click();
 const dialog=page.getByRole('dialog',{name:'Invite to Judibas'});
 await expect(dialog).toContainText('Who are you inviting?');
 await expect(dialog.getByRole('button',{name:/Use current|old form/i})).toHaveCount(0);
 await dialog.getByLabel('Full name').fill('Ayesha Khan');
 await dialog.getByLabel('Email address').fill('ayesha@example.test');
 await dialog.getByRole('button',{name:'Choose product access',exact:false}).click();
 await dialog.getByRole('checkbox',{name:'Include PMS',exact:true}).check();
 const pms=dialog.getByRole('region',{name:'PMS access settings'});
 await pms.getByRole('checkbox',{name:'Skyinfinit',exact:true}).check();
 await pms.getByRole('checkbox',{name:'Employee',exact:true}).check();
 await pms.getByRole('checkbox',{name:'Development',exact:true}).check();
 await dialog.getByRole('checkbox',{name:'Include Communication',exact:true}).check();
 await expect(pms).toHaveCount(0);
 const comm=dialog.getByRole('region',{name:'Communication access settings'});
 await comm.getByRole('button',{name:/Select all/}).click();
 await dialog.getByRole('checkbox',{name:'Include CV Bank',exact:true}).check();
 await dialog.getByRole('region',{name:'CV Bank access settings'}).getByRole('checkbox',{name:'Bedizen',exact:true}).check();
 await dialog.getByRole('button',{name:'Configure PMS',exact:true}).click();
 await expect(pms.getByRole('checkbox',{name:'Skyinfinit',exact:true})).toBeChecked();
 await expect(pms.getByRole('checkbox')).toHaveCount(5);
 expect(await dialog.locator('.invite-product-name').first().evaluate(el=>getComputedStyle(el).flexDirection)).toBe('row');
 expect(await pms.locator('.invite-choice-scroll label').first().evaluate(el=>getComputedStyle(el).flexDirection)).toBe('row');
 const row=dialog.locator('.invite-product-row').filter({has:page.getByRole('checkbox',{name:'Include Communication',exact:true})});
 await expect(row.locator('.invite-company-summary')).toHaveText('All 3 companies');
 await page.screenshot({path:'.preview/invitation-design-desktop.png',fullPage:true});
 await page.evaluate(()=>{document.documentElement.dataset.theme='dark';localStorage.setItem('judibas-theme-v2','dark');});
 await page.screenshot({path:'.preview/invitation-design-dark.png',fullPage:true});
 await page.setViewportSize({width:390,height:844});
 expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2)).toBe(true);
 await page.screenshot({path:'.preview/invitation-design-mobile.png',fullPage:true});
 await dialog.getByRole('button',{name:'Review invitation',exact:false}).click();
 await expect(dialog).toContainText('Review product access');
 const review=dialog.locator('.invite-review-products article').filter({hasText:'PMS'});
 await expect(review).toContainText('Skyinfinit');
 await expect(review).toContainText('Employee');
 await expect(review).toContainText('Development');
 await expect(review).not.toContainText('Bedizen');
 await dialog.getByRole('button',{name:'Create invitation link',exact:true}).click();
 await expect(dialog.getByRole('button',{name:'Creating invitation...',exact:true})).toBeDisabled();
 await expect(dialog.getByRole('alert')).toContainText('Please review the invitation email.');
 await expect(review).toContainText('Skyinfinit');
 await dialog.getByRole('button',{name:'Create invitation link',exact:true}).click();
 await expect(page.getByRole('dialog')).toHaveCount(0);
 await expect(page.getByLabel('Secure invitation link')).toHaveValue('https://example.test/invitations/fixture');
 expect(submitted.product_company_ids).toEqual({communication:[1,2,3],projects:[1],'cv-bank':[2]});
 expect(submitted.pms_role_ids).toEqual([1]);expect(submitted.pms_department_ids).toEqual([1]);
 expect(submitted.company_ids.sort()).toEqual([1,2,3]);
 await page.setViewportSize({width:1366,height:1000});
 await page.goto('http://invitation-preview.test/admin');
 await page.locator('.admin-shortcuts').getByRole('button',{name:/Invite employee/}).click();
 await expect(page.getByRole('dialog')).toContainText('Who are you inviting?');
 await page.getByRole('button',{name:'Close dialog',exact:true}).click();
 await page.locator('.admin-navigation').getByRole('button',{name:/^Users/}).click();
 await page.locator('.admin-section-heading').getByRole('button',{name:'Invite employee',exact:true}).click();
 await expect(page.getByRole('dialog')).toContainText('Who are you inviting?');
 await page.getByRole('button',{name:'Close dialog',exact:true}).click();
 await page.goto('http://invitation-preview.test/admin?invite_email=task-invite@example.test#invitations');
 await expect(page.getByRole('dialog')).toContainText('Who are you inviting?');
 await expect(page.getByRole('dialog').getByLabel('Email address')).toHaveValue('task-invite@example.test');
 expect(errors).toEqual([]);
 console.log('PASS: invitation wizard, separate company payload, PMS roles/departments, validation retry, loading, dashboard/users/task entry paths and mobile. All APIs mocked.');
}finally{await browser.close();}
