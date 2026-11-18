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
data.users=[{id:42,name:'Scoped Employee',email:'scoped@example.test',is_active:true,product_slugs:['projects','communication'],company_ids:[1,2,3],product_company_ids:{projects:[1],communication:[1,2,3]},pms_role_ids:[1],pms_department_ids:[1],canteen_level_ids:{},communication_admin:false}];
const browser=await chromium.launch({channel:'msedge',headless:true});
try {
 const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.route('**/*',async route=>{
  const path=new URL(route.request().url()).pathname;
  if(path==='/admin')return route.fulfill({contentType:'text/html',body:html});
  if(path==='/admin/api')return route.fulfill({json:data});
  if(path==='/admin/api/users/42'){submitted=route.request().postDataJSON();return route.fulfill({json:{message:'Saved.'}});}
  if(path.includes('/api/'))return route.fulfill({json:{items:[],invitations:[],unread:0,enabled:false}});
  try{return route.fulfill({body:await readFile(resolve('public','.'+path)),contentType:{'.js':'text/javascript','.css':'text/css'}[extname(path)]||'application/octet-stream'});}catch{return route.fulfill({status:404,body:''});}
 });
 await page.goto('http://invitation-preview.test/admin#users');
 await page.getByRole('button',{name:'Edit Scoped Employee',exact:true}).click();
 const dialog=page.getByRole('dialog',{name:'Edit employee'});
 await expect(dialog.getByLabel('Full name')).toHaveValue('Scoped Employee');
 await dialog.getByRole('button',{name:/Choose product access/}).click();
 await dialog.getByRole('button',{name:'Configure PMS',exact:true}).click();
 const pms=dialog.getByRole('region',{name:'PMS access settings'});
 await expect(pms.getByRole('checkbox',{name:'Skyinfinit',exact:true})).toBeChecked();
 await expect(pms.getByRole('checkbox',{name:'Employee',exact:true})).toBeChecked();
 await expect(pms.getByRole('checkbox',{name:'Development',exact:true})).toBeChecked();
 await dialog.getByRole('button',{name:'Configure Communication',exact:true}).click();
 const comm=dialog.getByRole('region',{name:'Communication access settings'});
 await comm.getByRole('checkbox',{name:'Other company',exact:true}).uncheck();
 await dialog.getByRole('button',{name:/Review changes/}).click();
 await dialog.getByRole('button',{name:'Save employee',exact:true}).click();
 await expect(dialog).toHaveCount(0);
 expect(submitted.product_company_ids).toEqual({projects:[1],communication:[1,2]});
 expect(submitted.pms_role_ids).toEqual([1]);expect(submitted.pms_department_ids).toEqual([1]);expect(submitted.is_active).toBe(true);
 expect(errors).toEqual([]);console.log('Employee edit: hydrated access, independent company changes, save payload and browser errors checked.');
} finally {await browser.close();}
