import {chromium} from '@playwright/test';
import {readFile,mkdir} from 'node:fs/promises';
import assert from 'node:assert/strict';
const browser=await chromium.launch({channel:'msedge',headless:true}),origin='http://127.0.0.1:8000';
try{
 const context=await browser.newContext(),page=await context.newPage(),errors=[],failures=[];page.on('pageerror',e=>errors.push(e.message));
 try{const cookie=JSON.parse(await readFile('.preview/admin-session.json','utf8'));await context.addCookies([{...cookie,domain:'127.0.0.1',path:'/'}]);}catch{}
 const paths=['/products','/products/communication','/products/projects','/products/canteen','/products/daymark','/products/cv-bank','/products/accounts','/admin'];
 const checks=[];
 for(const width of [1440,390]){await page.setViewportSize({width,height:1000});for(const theme of ['light','dark']){for(const path of paths){const response=await page.goto(origin+path,{waitUntil:'networkidle'});await page.evaluate(value=>{localStorage.setItem('theme',value);document.documentElement.dataset.theme=value;},theme);assert.equal(response.status(),200,path);const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth+1);if(overflow)failures.push({path,width,theme,issue:'Page overflow'});checks.push({path,width,theme,actual:new URL(page.url()).pathname});}}}
 assert.deepEqual(errors,[],'Browser runtime errors');assert.deepEqual(failures,[],'Layout failures');await mkdir('.preview',{recursive:true});await import('node:fs/promises').then(fs=>fs.writeFile('.preview/product-layout-audit.json',JSON.stringify(checks,null,2)));console.log('Product layout smoke checks passed: '+checks.length+' route/viewport/theme checks. '+checks.filter(x=>x.actual==='/login').length+' checks reached login (authenticated workspace coverage is provided by isolated product scripts).');
}finally{await browser.close();}
