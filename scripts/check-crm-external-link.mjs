import {chromium,expect} from '@playwright/test';
import {readFile} from 'node:fs/promises';
import {resolve,extname} from 'node:path';
const manifest=JSON.parse(await readFile('public/build/manifest.json','utf8')),entry=manifest['resources/js/app.ts'];
const crm='https://crm.judibas.com/';let theme='classic';
const product={slug:'crm',name:'CRM',external_url:crm,description:'Customer relationships',category:'Sales',icon:'',color:'#f5f5f5',features:[]};
const portal=()=>({name:'Judibas',products:[product],productsPage:true,productsTheme:theme,employee:{name:'Preview employee',email:'preview@example.test'},admin:false,administrator:false,posts:[],validationErrors:[],announcement:{},catalogueGroups:[{id:'crm',slug:'crm',name:'CRM',description:'Customer relationships',planned:false,externalUrl:crm,allHref:crm,companies:[{name:'Cruxlo',href:crm}]}]});
const html=()=>`<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1">${entry.css.map(c=>`<link rel="stylesheet" href="/build/${c}">`).join('')}</head><body><script>window.portal=${JSON.stringify(portal())}</script><div id="app"></div><script type="module" src="/build/${entry.file}"></script></body></html>`;
const browser=await chromium.launch({channel:'msedge',headless:true}),errors=[];
try{
 const context=await browser.newContext({serviceWorkers:'block',viewport:{width:1366,height:900}});
 await context.route('**/*',async route=>{
  const {hostname,pathname:path}=new URL(route.request().url());
  if(hostname==='crm.judibas.com')return route.fulfill({contentType:'text/html',body:'External CRM preview'});
  if(hostname!=='crm-preview.test')return route.abort();
  if(path==='/products')return route.fulfill({contentType:'text/html',body:html()});
  try{const file=resolve('public','.'+path);return route.fulfill({body:await readFile(file),contentType:{'.js':'text/javascript','.css':'text/css','.woff2':'font/woff2','.svg':'image/svg+xml'}[extname(file)]||'application/octet-stream'});}catch{return route.fulfill({status:404,body:''});}
 });
 const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));
 for(theme of ['classic','workspace','directory','disclosure']){
  await page.goto('http://crm-preview.test/products');
  const link=page.locator(theme==='classic'?'.catalogue-product':'.pd-card h2 a').filter({hasText:'CRM'});
  await expect(link).toHaveAttribute('href',crm);await expect(link).toHaveAttribute('target','_blank');await expect(link).toHaveAttribute('rel',/noopener/);
  const popupPromise=page.waitForEvent('popup');await link.click();const popup=await popupPromise;await popup.waitForURL(crm);await popup.close();
  expect(page.url()).toBe('http://crm-preview.test/products');
  await page.locator('.account-menu summary').click();
  await expect(page.locator('.account-app-grid a').filter({hasText:'CRM'})).toHaveAttribute('target','_blank');
 }
 expect(errors).toEqual([]);console.log('PASS: CRM external link opens a separate tab in all four product themes and the application switcher. External website mocked.');
}finally{await browser.close();}
