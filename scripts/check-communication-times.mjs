import fs from 'node:fs';import ts from 'typescript';import assert from 'node:assert/strict';import {chromium,expect} from '@playwright/test';
const compiled=ts.transpileModule(fs.readFileSync('resources/js/chatTime.ts','utf8'),{compilerOptions:{module:ts.ModuleKind.ESNext,target:ts.ScriptTarget.ES2020}}).outputText;
const {conversationTime,parseChatDate}=await import('data:text/javascript;base64,'+Buffer.from(compiled).toString('base64'));
const now=new Date(2026,9,5,14,0);
const formatDay=days=>{const d=new Date(now);d.setDate(d.getDate()-days);return d.toISOString();};
assert.match(conversationTime(formatDay(0),now),/\d{2}:\d{2}/);
assert.equal(conversationTime(formatDay(1),now),'Yesterday');
for(const days of [2,6,7])assert.equal(conversationTime(formatDay(days),now),new Date(formatDay(days)).toLocaleDateString('en',{weekday:'long'}));
assert.equal(conversationTime(formatDay(8),now),'27/09/2026');
assert.equal(conversationTime(null,now),'');assert.equal(conversationTime('bad',now),'');
assert.equal(parseChatDate('2026-10-05T14:00:00+05:00').toISOString(),'2026-10-05T09:00:00.000Z');
assert.equal(parseChatDate('2026-10-05 09:00:00').toISOString(),'2026-10-05T09:00:00.000Z');
const r=JSON.parse(fs.readFileSync('.preview/communication-test-resources.json','utf8')),base='http://127.0.0.1:8000',browser=await chromium.launch({channel:'msedge',headless:true});
try{
 const page=await browser.newPage({viewport:{width:1440,height:1000}});await page.goto(base+'/login');await page.locator('input[name=email]').fill('comm-a-'+r.suffix+'@example.test');await page.locator('input[name=password]').fill('communication-test-password');await page.getByRole('button',{name:'Sign in'}).click();await page.waitForURL('**/communication');
 const csrf=await page.locator('meta[name=csrf-token]').getAttribute('content');const response=await page.request.post(base+'/communication/api/conversations/'+r.conversations[2]+'/messages',{headers:{'X-CSRF-TOKEN':csrf},data:{body:'Forward layout check'}});assert.equal(response.status(),201);await page.reload();
 const row=page.locator('.comm-conversations>button').filter({hasText:'Browser Project Group'});await expect(row.locator('time')).toHaveText(/\d{2}:\d{2}/);await row.click();await page.locator('.comm-message').filter({hasText:'Forward layout check'}).hover();await page.getByRole('button',{name:/Message options/}).click();await page.getByRole('menuitem',{name:'Forward',exact:true}).click();
 const aligned=await page.locator('.comm-forward-list>label').first().evaluate(label=>{const radio=label.querySelector('input').getBoundingClientRect(),text=label.querySelector('span').getBoundingClientRect();return getComputedStyle(label).display==='flex'&&radio.right<=text.left&&Math.abs((radio.top+radio.height/2)-(text.top+text.height/2))<2;});assert.equal(aligned,true);
 await page.screenshot({path:'.preview/communication-forward-aligned.png',fullPage:true});
 await page.setViewportSize({width:390,height:844});await expect.poll(()=>page.evaluate(()=>document.documentElement.scrollWidth>innerWidth)).toBe(false);await expect(page.locator('.comm-forward-list')).toBeVisible();
 console.log('Sidebar timestamp formats, timezone/day boundaries, forwarding alignment and mobile layout passed.');
}finally{await browser.close();}
