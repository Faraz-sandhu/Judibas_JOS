// Isolated browser fixtures; real editing/privacy rules are covered by PHP tests.
import {chromium,expect} from '@playwright/test';
import {readFile,mkdir} from 'node:fs/promises';
import {resolve,extname} from 'node:path';
const manifest=JSON.parse(await readFile('public/build/manifest.json','utf8')),entry=manifest['resources/js/app.ts'];
let superAdmin=false,body='Original delivery plan';const edits=[],errors=[];
const author={id:2,name:'Preview Employee',email:'preview@example.test'},recipient={id:3,name:'Preview Colleague',email:'colleague@example.test'};
const chat={id:1,kind:'direct',name:'Preview conversation',members:[author,recipient],preview:body,unread:0,last_message_at:null};
const snapshot=()=>({admin:superAdmin,manager:superAdmin,profile:superAdmin?null:author,company_id:null,user_id:superAdmin?null:2,people:[author,recipient],review_people:[author],companies:[],conversations:[chat],stories:[],viewed_user:null,calling_enabled:false,call_history_enabled:false});
const portal=()=>({name:'Judibas',workspacePath:'/communication',products:[],posts:[],admin:superAdmin,administrator:superAdmin,employee:superAdmin?null:author,superAdmin,validationErrors:[],announcement:{}});
const html=()=>`<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="fixture">${entry.css.map(c=>`<link rel="stylesheet" href="/build/${c}">`).join('')}</head><body><script>window.portal=${JSON.stringify(portal())}</script><div id="app"></div><script type="module" src="/build/${entry.file}"></script></body></html>`;
const message=()=>({id:1,sender_id:2,sender_name:author.name,body,created_at:'2026-11-18T08:00:00Z',edited_at:edits.length?'2026-11-18T09:00:00Z':null,deleted_at:null,protected_payload:null,reactions:[],...(superAdmin?{edit_history:edits}:{})});
const browser=await chromium.launch({channel:'msedge',headless:true});
try{
 const page=await browser.newPage({viewport:{width:1366,height:900}});page.on('pageerror',e=>errors.push(e.message));
 await page.route('**/*',async route=>{
  const url=new URL(route.request().url()),path=url.pathname;
  if(url.hostname!=='edit-preview.test')return route.abort();
  if(path==='/communication')return route.fulfill({contentType:'text/html',body:html()});
  if(path==='/communication/api/messages/1'&&route.request().method()==='PATCH'){
   expect(superAdmin).toBe(false);const text=route.request().postDataJSON().body;
   edits.push({id:edits.length+1,before:body,after:text,editor_name:author.name,edited_at:'2026-11-18T09:00:00Z'});body=text;
   return route.fulfill({json:{message:'Message updated.'}});
  }
  if(path.startsWith('/communication/api')){
   let data={};if(path==='/communication/api')data=snapshot();
   else if(path.endsWith('/presence'))data={states:{},preferences:{}};
   else if(path.endsWith('/realtime/config')||path.endsWith('/protection'))data={enabled:false};
   else if(path.endsWith('/messages'))data={messages:[message()],pinned:[],has_more:false};
   else if(path.endsWith('/shared'))data={items:[],members:[author,recipient],has_more:false};
   return route.fulfill({json:data});
  }
  try{return route.fulfill({body:await readFile(resolve('public','.'+path)),contentType:{'.js':'text/javascript','.css':'text/css','.woff2':'font/woff2'}[extname(path)]||'application/octet-stream'});}catch{return route.fulfill({status:404,body:''});}
 });
 await page.goto('http://edit-preview.test/communication?fixture=employee');
 await page.locator('.comm-conversations>button').first().click();
 await page.getByRole('textbox',{name:'Message',exact:true}).fill('Unsent draft');
 await page.locator('#chat-message-1').hover();await page.getByRole('button',{name:'Message options 1',exact:true}).click();
 await page.getByRole('menuitem',{name:'Edit',exact:true}).click();
 await page.getByRole('button',{name:'Cancel editing',exact:true}).click();
 await expect(page.getByRole('textbox',{name:'Message',exact:true})).toHaveValue('Unsent draft');
 expect(edits).toHaveLength(0);
 for(const text of ['Revised delivery plan','Final delivery plan']){
  await page.locator('#chat-message-1').hover();await page.getByRole('button',{name:'Message options 1',exact:true}).click();
  await page.getByRole('menuitem',{name:'Edit',exact:true}).click();
  await expect(page.getByRole('textbox',{name:'Message',exact:true})).toHaveValue(body);
  await page.getByRole('textbox',{name:'Message',exact:true}).fill(text);
  await page.getByRole('button',{name:'Update',exact:true}).click();
  await expect(page.locator('#chat-message-1 .comm-bubble>p')).toHaveText(text);
 }
 await expect(page.locator('.comm-edit-history')).toHaveCount(0);
 await expect(page.locator('#chat-message-1 .comm-bubble-time')).toContainText('Edited');
 superAdmin=true;await page.goto('http://edit-preview.test/communication?action=review&fixture=super');
 await page.locator('.comm-conversations>button').first().click();
 await page.locator('.comm-edit-history summary').click();
 await expect(page.locator('.comm-edit-versions article')).toHaveCount(2);
 await expect(page.locator('.comm-edit-before').first()).toHaveText('Original delivery plan');
 await expect(page.locator('.comm-edit-after').last()).toHaveText('Final delivery plan');
 await expect(page.getByRole('button',{name:'Message options 1',exact:true})).toHaveCount(0);
 await mkdir('.preview',{recursive:true});await page.screenshot({path:'.preview/communication-edit-history-desktop.png',fullPage:true});
 await page.setViewportSize({width:390,height:844});expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2)).toBe(true);
 await expect(page.locator('.comm-edit-versions')).toBeVisible();await page.screenshot({path:'.preview/communication-edit-history-mobile.png',fullPage:true});
 expect(errors).toEqual([]);console.log('PASS: employee own-message Edit action; two revisions; Super Admin inline before/after history in read-only review; history hidden from employee; mobile layout and runtime checks. APIs mocked.');
}finally{await browser.close();}
