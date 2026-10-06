import { chromium, expect } from '@playwright/test';
import { readFile } from 'node:fs/promises';
process.env.PLAYWRIGHT_SKIP_SCREENSHOT_FONT_READY='1';
const browser=await chromium.launch({channel:'msedge',headless:true});
const context=await browser.newContext({viewport:{width:1440,height:1000}});
const cookie=JSON.parse(await readFile('.preview/admin-session.json','utf8'));
await context.addCookies([{...cookie,domain:'127.0.0.1',path:'/'}]);
const page=await context.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
let postId=null,csrf='';
try {
await page.goto('http://127.0.0.1:8000/admin',{waitUntil:'networkidle'});
csrf=await page.locator('meta[name="csrf-token"]').getAttribute('content');
await expect(page.getByRole('heading',{name:'Your workspace at a glance'})).toBeVisible();
await page.screenshot({path:'.preview/admin-dashboard.png',fullPage:true});
for(const tab of ['Blogs','Website','Products','Users','Communication','Branding']){
await page.getByRole('button',{name:tab,exact:true}).click();
await expect(page.locator('.super-admin')).toBeVisible();
}
await page.getByRole('button',{name:'Products',exact:true}).click();
await page.getByRole('button',{name:'Edit product'}).first().click();
await expect(page.getByLabel('Display name')).toBeVisible();
await page.getByRole('button',{name:'Cancel',exact:true}).click();
await page.getByRole('button',{name:'Blogs',exact:true}).click();
await page.getByRole('button',{name:'New article'}).click();
const slug='browser-check-'+Date.now();
await page.getByLabel('Title',{exact:true}).fill('Browser verification article');
await page.getByLabel('URL slug').fill(slug);
await page.getByLabel('Excerpt',{exact:true}).fill('Temporary browser verification.');
await page.getByLabel('Article text').fill('This record is removed after verification.');
await page.getByLabel('Publish article on the website').check();
await page.getByRole('button',{name:'Save article'}).click();
await expect(page.getByRole('status')).toContainText('Changes saved.');
const response=await context.request.get('http://127.0.0.1:8000/admin/api');
const payload=await response.json();postId=payload.blogs.find(p=>p.slug===slug).id;
const article=await context.request.get('http://127.0.0.1:8000/blog/'+slug);
expect(article.status()).toBe(200);
await page.getByRole('button',{name:'Blogs',exact:true}).click();
await page.screenshot({path:'.preview/admin-blogs.png',fullPage:true});
await page.setViewportSize({width:390,height:844});
await page.getByRole('button',{name:'Users',exact:true}).click();
await page.getByRole('button',{name:'New employee'}).click();
expect(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth)).toBe(false);
await page.screenshot({path:'.preview/admin-mobile.png',fullPage:true});
expect(errors).toEqual([]);
console.log('Admin browser checks passed: all sections, product editor, publish article, mobile layout, no JavaScript errors.');
}finally{
if(postId)await context.request.delete('http://127.0.0.1:8000/admin/api/blogs/'+postId,{headers:{'X-CSRF-TOKEN':csrf,Accept:'application/json'}});
if(csrf)await context.request.post('http://127.0.0.1:8000/admin/logout',{form:{_token:csrf}});
await browser.close();
}
