import {chromium} from '@playwright/test';
import {resolve} from 'node:path';
import {pathToFileURL} from 'node:url';
const dir=resolve('docs/accounting-implementation');
const browser=await chromium.launch({channel:'msedge',headless:true});
try{
 const page=await browser.newPage({viewport:{width:1100,height:1200},deviceScaleFactor:1.5});
 await page.goto(pathToFileURL(resolve(dir,'Judibas-Accounting-Implementation.html')).href);
 await page.emulateMedia({media:'print'});
 const issues=await page.locator('.page').evaluateAll(pages=>pages.map((p,i)=>({page:i+1,last:Math.max(...[...p.children].filter(e=>!e.classList.contains('footer')).map(e=>e.getBoundingClientRect().bottom)),limit:p.querySelector('.footer').getBoundingClientRect().top})).filter(x=>x.last>x.limit-8));
 console.log('Layout overflow:',JSON.stringify(issues));if(issues.length)throw Error('Fix page layout before export');
 await page.pdf({path:resolve(dir,'Judibas-Accounting-Implementation.pdf'),format:'A4',printBackground:true,preferCSSPageSize:true});
 const tree=page.locator('.page').nth(2);await tree.locator('svg').screenshot({path:resolve(dir,'implementation-tree.png')});await tree.screenshot({path:resolve(dir,'tree-page-preview.png')});
 for(let i=1;i<=12;i++)await page.locator('.step-figure').nth(i-1).screenshot({path:resolve(dir,'steps',`step-${String(i).padStart(2,'0')}.png`)});
 await page.locator('.page').nth(6).screenshot({path:resolve(dir,'step-04-page-preview.png')});
 console.log('Created 18-page PDF, master tree and 12 individual PNG diagrams.');
}finally{await browser.close();}
