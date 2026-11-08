import {chromium,expect} from '@playwright/test';
import assert from 'node:assert/strict';
const browser=await chromium.launch({channel:'msedge',headless:true});
try{
 const page=await browser.newPage({viewport:{width:1440,height:1000},serviceWorkers:'block'}),errors=[];page.on('pageerror',e=>{errors.push(e.message);console.log('Browser error:',e.message);});
 
 let created=false;const writes=[],messages=[];
 const person={id:41,name:'Demo employee',email:'employee@example.test',company_id:1,company_ids:[1]};
 const own={id:21,kind:'direct',name:'Demo employee',members:[person],preview:'No messages yet',last_message_at:null,unread:0};
 const reviewed={id:22,kind:'direct',name:'Private employee chat',members:[person,{...person,id:42,name:'Other employee'}],preview:'Employee conversation',last_message_at:null,unread:0};
 await page.route(/\/communication\/api(?:\/|\?|$)/,async route=>{
  const url=new URL(route.request().url()),path=url.pathname.replace('/communication/api',''),method=route.request().method(),mode=url.searchParams.get('mode');let data={};
  if(path==='/realtime/config')data={enabled:false};
  else if(path==='/presence')data={states:{},preferences:{}};
  else if(path==='')data={admin:true,manager:true,user_id:null,profile:null,company_id:null,calling_enabled:false,call_history_enabled:false,companies:[{id:1,name:'Demo company'}],people:[person],review_people:[person],stories:[],viewed_user:null,conversations:mode==='review'?[reviewed,...(created?[own]:[])]:created?[own]:[]};
  else if(path==='/conversations'&&method==='POST'){const body=route.request().postDataJSON();writes.push({mode,body});created=true;data={id:21};}
  else if(path.endsWith('/messages')){if(method==='POST'){const form=route.request().postData();writes.push({mode,body:form});const message={id:1,sender_id:null,sender_name:'Super Admin',body:'Hello colleague',created_at:new Date().toISOString(),reactions:[],attachment_url:null,deleted_at:null};messages.push(message);data={id:1,message};}else data={messages:path.includes('/21/')?messages:[],pinned:[],has_more:false,has_newer:false};}
  await route.fulfill({json:data});
 });
 await page.route(/\/communication$/,async route=>{const html=await page.request.get('http://127.0.0.1:8000/products/communication');await route.fulfill({contentType:'text/html',body:await html.text()});});
 await page.goto('http://127.0.0.1:8000/communication',{waitUntil:'networkidle'});
 await expect(page.getByRole('button',{name:'My chats',exact:true})).toHaveAttribute('aria-pressed','true');await expect(page.locator('#comm-review-person')).toHaveCount(0);
 await page.getByRole('button',{name:'New conversation',exact:true}).click();await expect(page.getByRole('dialog')).toBeVisible();await expect(page.getByLabel('Conversation type')).toHaveValue('direct');await page.getByRole('radio').first().check();
 await page.getByRole('dialog').getByRole('button',{name:'Create chat',exact:true}).click();await expect(page.getByLabel('Message',{exact:true})).toBeVisible();
 await page.getByLabel('Message',{exact:true}).fill('Hello colleague');await page.getByRole('button',{name:'Send',exact:true}).click();await expect(page.locator('.comm-messages')).toContainText('Hello colleague');
 await page.getByRole('button',{name:'Conversation review',exact:true}).click();await expect(page.locator('#comm-review-person')).toBeVisible();await expect(page.locator('.comm-review-banner')).toBeVisible();await expect(page.getByLabel('Message',{exact:true})).toHaveCount(0);await expect(page.getByRole('button',{name:'New conversation',exact:true})).toHaveCount(0);await expect(page.locator('.comm-review-banner')).toHaveCount(0,{timeout:6500});await expect(page.getByRole('button',{name:'Conversation review',exact:true})).toHaveAttribute('aria-pressed','true');
 await page.getByRole('button',{name:'My chats',exact:true}).click();await expect(page.locator('#comm-review-person')).toHaveCount(0);await expect(page.locator('.comm-review-banner')).toHaveCount(0);assert.ok(writes.every(w=>w.mode==='personal'));
 await page.setViewportSize({width:390,height:844});assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);assert.deepEqual(errors,[]);console.log('Super Admin browser checks passed: separate personal/review tabs, direct chat creation, sending, read-only review and mobile layout.');
}finally{await browser.close();}
