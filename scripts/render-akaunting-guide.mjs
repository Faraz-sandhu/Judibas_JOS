import {chromium} from '@playwright/test';
import {resolve} from 'node:path';
import {pathToFileURL} from 'node:url';
const dir=resolve('docs/akaunting');
const browser=await chromium.launch({channel:'msedge',headless:true});
try{
 const page=await browser.newPage({viewport:{width:1100,height:1200},deviceScaleFactor:1.5});
 await page.goto(pathToFileURL(resolve(dir,'Akaunting-Guide.html')).href);
 await page.emulateMedia({media:'print'});
 const overflow=await page.locator('.page').evaluateAll(pages=>pages.map((page,i)=>({page:i+1,last:Math.max(...[...page.children].filter(e=>!e.classList.contains('footer')).map(e=>e.getBoundingClientRect().bottom)),limit:page.querySelector('.footer').getBoundingClientRect().top})).filter(x=>x.last>x.limit-8));
 console.log('Page overflow checks:',JSON.stringify(overflow));
 if(overflow.length)throw Error('Content overlaps footer');
 await page.pdf({path:resolve(dir,'Akaunting-Guide.pdf'),format:'A4',printBackground:true,preferCSSPageSize:true});
 await page.locator('.page').nth(2).screenshot({path:resolve(dir,'feature-tree-page.png')});
 for(const [name,index] of [['feature-tree',2],['accounting-workflow-tree',3]])await page.locator('.page').nth(index).locator('svg').screenshot({path:resolve(dir,name+'.png')});
 await page.locator('.page').nth(12).screenshot({path:resolve(dir,'accounting-example-page.png')});
 console.log('PDF and diagram images created.');
}finally{await browser.close();}
