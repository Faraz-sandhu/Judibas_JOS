import {chromium,expect} from '@playwright/test';
import {readFile} from 'node:fs/promises';
import {resolve,extname} from 'node:path';
const manifest=JSON.parse(await readFile('public/build/manifest.json','utf8')),entry=manifest['resources/js/app.ts'];
const crm='https://crm.judibas.com/';let theme='classic';
const product={slug:'crm',name:'CRM',external_url:crm,description:'Customer relationships',category:'Sales',icon:'',color:'#f5f5f5',features:[]};
const portal=()=>({name:'Judibas',products:[product,{slug:"communication",name:"Communication",workspace:"/communication"}],productsPage:true,productsTheme:theme,employee:{name:'Preview employee',email:'preview@example.test'},admin:false,administrator:false,posts:[],validationErrors:[],announcement:{},catalogueGroups:[{id:'crm',slug:'crm',name:'CRM',description:'Customer relationships',planned:false,externalUrl:crm,allHref:crm,companies:[{name:'Cruxlo',href:crm},{name:'Skyinfinit',href:crm}]}]});
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
 for(theme of ['directory','disclosure','stacked']){
  await page.goto('http://crm-preview.test/products');
  const cards=page.locator('.pd-card');await expect(cards).toHaveCount(2);
  await expect(page.locator('.pd-card[open]')).toHaveCount(0);
  if(theme==='stacked'){const a=await cards.first().boundingBox(),b=await cards.last().boundingBox();expect(Math.abs(a.width-b.width)).toBeLessThan(2);expect(b.y).toBeGreaterThanOrEqual(a.y+a.height);}
  await cards.first().locator('summary').click();await expect(cards.first()).toHaveAttribute('open','');
  await cards.last().locator('summary').click();await expect(page.locator('.pd-card[open]')).toHaveCount(1);await expect(cards.first()).not.toHaveAttribute('open','');
  await expect(cards.last().locator('.pd-company').first()).toBeVisible();
  if(theme==='stacked'){const a=await cards.last().locator('.pd-company').nth(0).boundingBox(),b=await cards.last().locator('.pd-company').nth(1).boundingBox();expect(Math.abs(a.y-b.y)).toBeLessThan(2);}
  await cards.last().locator('summary').press('Enter');await expect(page.locator('.pd-card[open]')).toHaveCount(0);
  await page.getByRole('searchbox').fill('Cruxlo');await expect(cards.last()).toHaveAttribute('open','');
  await page.getByRole('searchbox').fill('');await expect(page.locator('.pd-card[open]')).toHaveCount(0);
  await page.setViewportSize({width:390,height:844});
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
  await page.setViewportSize({width:1366,height:900});
 }
 theme='workspace';await page.goto('http://crm-preview.test/products');await expect(page.locator('details.pd-card')).toHaveCount(0);await expect(page.locator('.pd-companies')).toBeVisible();
 expect(errors).toEqual([]);console.log('PASS: themes 2/4 start closed, only one opens, keyboard toggling, search, mobile layout; theme 1 unchanged.');
}finally{await browser.close();}
