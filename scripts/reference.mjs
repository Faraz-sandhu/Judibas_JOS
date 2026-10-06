import { chromium } from '@playwright/test';
const browser=await chromium.launch({channel:'msedge',headless:true});
const page=await browser.newPage({viewport:{width:1440,height:1100},colorScheme:'light'});
try {
await page.goto('https://frappe.io/crm',{waitUntil:'domcontentloaded',timeout:45000});
await page.screenshot({path:'.preview/frappe-crm.png',timeout:15000});
console.log('Reference screenshot captured.');
} finally {await browser.close();}
