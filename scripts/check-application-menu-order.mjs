import {chromium,expect} from '@playwright/test';
import {readFile} from 'node:fs/promises';
import {resolve,extname} from 'node:path';
const manifest=JSON.parse(await readFile('public/build/manifest.json','utf8')),entry=manifest['resources/js/app.ts'];
const products=[['focus-to-do','Focus To Do'],['daymark','Daymark'],['cv-bank','CV Bank'],['crm','CRM'],['accounts','Accounting'],['communication','Communication'],['projects','PMS'],['canteen','Canteen']].map(([slug,name])=>({slug,name,workspace:slug==='projects'?'/pms':'/'+slug,...(slug==='crm'?{external_url:'https://crm.judibas.com/'}:{}),category:'Business',description:'Application',features:[],icon:'',color:'#777'}));
let mode='super';const errors=[];
const portal=()=>({name:'Judibas',products:mode==='employee'?products.filter(p=>['projects','focus-to-do'].includes(p.slug)):products,productsPage:true,productsTheme:'classic',employee:{name:'Preview User',email:'preview@example.test'},admin:mode==='super',administrator:mode==='admin',posts:[],validationErrors:[],announcement:{}});
const html=()=>`<!doctype html><html><head>${entry.css.map(c=>`<link rel="stylesheet" href="/build/${c}">`).join('')}</head><body><script>window.portal=${JSON.stringify(portal())}</script><div id="app"></div><script type="module" src="/build/${entry.file}"></script></body></html>`;
const browser=await chromium.launch({channel:'msedge',headless:true});
try{
 const page=await browser.newPage();page.on('pageerror',e=>errors.push(e.message));
 await page.route('**/*',async route=>{
  const url=new URL(route.request().url());if(url.hostname!=='menu-preview.test')return route.abort();
  if(url.pathname==='/products')return route.fulfill({contentType:'text/html',body:html()});
  try{return route.fulfill({body:await readFile(resolve('public','.'+url.pathname)),contentType:{'.js':'text/javascript','.css':'text/css'}[extname(url.pathname)]||'application/octet-stream'});}catch{return route.fulfill({status:404,body:''});}
 });
 for(mode of ['super','admin','employee']){
  await page.goto('http://menu-preview.test/products?mode='+mode);await page.locator('.account-menu summary').click();
  const names=await page.locator('.account-app-grid .account-app-name').allTextContents();
  expect(names).toEqual(mode==='employee'?['Focus To Do','PMS']:['Accounting','Administration','Canteen','Communication','CRM','CV Bank','Daymark','Focus To Do','PMS']);
  if(mode!=='employee')await expect(page.locator('.account-app-grid a').filter({hasText:'CRM'})).toHaveAttribute('target','_blank');
  expect(await page.evaluate(()=>window.portal.products.map(p=>p.name))).toEqual((mode==='employee'?products.filter(p=>['projects','focus-to-do'].includes(p.slug)):products).map(p=>p.name));
 }
 expect(errors).toEqual([]);console.log('PASS: A-Z application order including Administration; employee visibility and external CRM link preserved.');
}finally{await browser.close();}
