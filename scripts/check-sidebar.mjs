import { chromium, expect } from '@playwright/test';
import assert from 'node:assert/strict';
const browser=await chromium.launch({channel:'msedge',headless:true});
try {
const page=await browser.newPage({viewport:{width:1440,height:1000}});
for(const route of ['/','/products','/products/crm','/admin']){
await page.goto('http://127.0.0.1:8000'+route);
const rail=page.locator('.rail');
await expect(rail).toHaveCSS('width','53px');
const margin=await page.locator('.site-shell').evaluate(el=>getComputedStyle(el).marginLeft);
await rail.hover();
await expect(rail).toHaveCSS('width','218px');
await expect(rail.locator('.rail-label').first()).toBeVisible();
assert.equal(await page.locator('.site-shell').evaluate(el=>getComputedStyle(el).marginLeft),margin);
await page.mouse.move(500,50);
await expect(rail).toHaveCSS('width','53px');
await rail.locator('a').first().focus();
await expect(rail).toHaveCSS('width','218px');
await page.locator('.top-action').focus();
await expect(rail).toHaveCSS('width','53px');
}
await page.setViewportSize({width:390,height:844});
await page.goto('http://127.0.0.1:8000/products');
assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
console.log('Sidebar checks passed: hover expansion, mouse-leave collapse, keyboard focus, stable content position, mobile width.');
}finally{await browser.close();}
