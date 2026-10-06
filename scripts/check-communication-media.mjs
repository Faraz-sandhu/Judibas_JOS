import {chromium,expect} from '@playwright/test';
import fs from 'node:fs';
const r=JSON.parse(fs.readFileSync('.preview/communication-test-resources.json','utf8'));
const browser=await chromium.launch({channel:'msedge',headless:true});
try{
 const page=await browser.newPage({viewport:{width:1440,height:1000}});
 const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto('http://127.0.0.1:8000/login');
 await page.locator('input[name=email]').fill('comm-a-'+r.suffix+'@example.test');
 await page.locator('input[name=password]').fill('communication-test-password');
 await page.getByRole('button',{name:'Sign in'}).click();await page.waitForURL('**/communication',{timeout:15000});
 await page.locator('.comm-conversations>button').filter({hasText:'Communication Test B'}).click();
 await page.locator('.comm-file-input').setInputFiles({name:'shared-photo.png',mimeType:'image/png',buffer:Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=','base64')});
 await page.locator('.comm-send').click();await expect(page.locator('.comm-bubble').filter({hasText:'shared-photo.png'})).toBeVisible();
 await page.getByRole('button',{name:'Open chat profile'}).click();
 await page.locator('.comm-detail-tabs').getByRole('button',{name:'Media',exact:true}).click();
 await expect(page.locator('.comm-media-grid img')).toHaveCount(1);
 await expect.poll(()=>page.locator('.comm-media-grid img').evaluate(img=>img.naturalWidth),{timeout:10000}).toBe(1);
 await page.setViewportSize({width:390,height:844});
 await expect.poll(()=>page.evaluate(()=>document.documentElement.scrollWidth>innerWidth)).toBe(false);
 await page.screenshot({path:'.preview/communication-media-mobile.png',fullPage:true});
 await page.getByRole('button',{name:'Close chat details'}).click();
 await expect(page.locator('.comm-composer')).toBeVisible();
 if(errors.length)throw new Error(errors.join('\n'));
 console.log('Shared image preview and mobile drawer checks passed.');
}finally{await browser.close();}
