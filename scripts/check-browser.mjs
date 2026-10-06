import { chromium } from '@playwright/test';
import assert from 'node:assert/strict';
process.env.PLAYWRIGHT_SKIP_SCREENSHOT_FONT_READY='1';
const browser=await chromium.launch({channel:'msedge',headless:true});
try {
const page=await browser.newPage({viewport:{width:1440,height:1100},colorScheme:'light'});
const errors=[];page.on('pageerror',error=>errors.push(error.message));
for(const url of ['/', '/products', '/products/communication', '/products/crm','/products/framework','/products/hr','/products/projects','/products/inventory','/products/helpdesk','/products/accounts','/products/erp','/products/learning','/products/insights','/products/lending','/products/builder','/products/drive','/products/gameplan','/products/press','/about','/team','/values','/blog','/contact','/admin']){
const response=await page.goto('http://127.0.0.1:8000'+url,{waitUntil:'networkidle'});
assert.equal(response.status(),200);
assert.ok(await page.locator('h1').innerText());
if(url==='/') {assert.equal(await page.locator('#products .directory-row').count(),16);await page.screenshot({path:'.preview/judibas-home-v2.png',fullPage:true});}
if(url==='/products'){assert.equal(await page.locator('.catalogue-product').count(),16);assert.equal(await page.locator('.home-intro').count(),0);assert.equal(await page.locator('.breadcrumbs').innerText(),'Judibas\n›\nProducts');await page.screenshot({path:'.preview/products-catalogue.png',fullPage:true});}
if(url==='/products/crm')await page.screenshot({path:'.preview/judibas-crm-v2.png',fullPage:true});
}
assert.equal(errors.length,0,errors.join('\n'));
await page.goto('http://127.0.0.1:8000/');
await page.getByRole('button',{name:'dark',exact:true}).click();
assert.equal(await page.locator('html').getAttribute('data-theme'),'dark');
await page.screenshot({path:'.preview/judibas-dark-v2.png'});
await page.getByRole('button',{name:'light',exact:true}).click();
await page.setViewportSize({width:390,height:844});
for(const url of ['/','/products','/products/crm','/admin']){
await page.goto('http://127.0.0.1:8000'+url,{waitUntil:'networkidle'});
const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth);
assert.equal(overflow,false,'Horizontal overflow: '+url);
await page.screenshot({path:'.preview/mobile-'+(url==='/'?'home':url.split('/').pop())+'-v2.png',fullPage:true});
}
console.log('Browser checks passed: all page routes, catalogue, theme switch, no JS errors, mobile overflow.');
} finally {await browser.close();}
