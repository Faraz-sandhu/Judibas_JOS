import {chromium,expect} from '@playwright/test';
import assert from 'node:assert/strict';
import {mkdir} from 'node:fs/promises';
const origin='http://127.0.0.1:8000',browser=await chromium.launch({channel:'msedge',headless:true});
try{
 const page=await browser.newPage({viewport:{width:1440,height:1000}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
 let permissions=['overview.view','notifications.view','journals.view','audit.view'],items=[],socket,subscribed=false,feedRequests=0;
 await page.routeWebSocket(/ws:\/\/127\.0\.0\.1:19999\//,ws=>{
  socket=ws;ws.send(JSON.stringify({event:'pusher:connection_established',data:JSON.stringify({socket_id:'111.222',activity_timeout:120})}));
  ws.onMessage(raw=>{const m=JSON.parse(raw);if(m.event==='pusher:subscribe'){assert.equal(m.data.channel,'private-accounting.activity.admin');subscribed=true;ws.send(JSON.stringify({event:'pusher_internal:subscription_succeeded',channel:m.data.channel,data:'{}'}));}if(m.event==='pusher:ping')ws.send(JSON.stringify({event:'pusher:pong',data:'{}'}));});
 });
 await page.route(/\/accounting\/api\//,async route=>{
  const u=new URL(route.request().url()),path=u.pathname.replace('/accounting/api/','');let data={};
  if(path==='data')data={company_id:51,companies:[{id:51,name:'Notification demo'}],role:'manager',role_name:'Accounting Manager',permissions,can_manage:true,can_delete:true,accounts:[],account_types:{},book:{version:1,currency:'PKR',start_date:'2026-01-01',approval_required:true}};
  else if(path==='notifications'){feedRequests++;data={items,unread_count:items.filter(n=>!n.read_at).length,latest_id:items[0]?.id||0,has_more:false};}
  else if(path==='notifications/realtime/config')data={enabled:true,provider:'reverb',key:'notification-test-key',cluster:'mt1',host:'127.0.0.1',port:19999,scheme:'http',channels:['accounting.activity.admin']};
  else if(path==='notifications/realtime/auth'){const body=route.request().postDataJSON();assert.equal(body.socket_id,'111.222');assert.equal(body.channel_name,'private-accounting.activity.admin');data={auth:'notification-test-key:test-signature'};}
  else if(path==='notifications/read'){const body=route.request().postDataJSON();assert.equal(body.company_id,51);items=items.map(n=>n.id<=(body.through_id||body.id)?{...n,read_at:'2026-11-04T10:10:00Z'}:n);data={message:'Notifications read.'};}
  await route.fulfill({contentType:'application/json',body:JSON.stringify(data)});
 });
 await page.route(/\/accounting(?:\?.*)?$/,async route=>{const r=await page.request.get(origin+'/products/accounts');await route.fulfill({contentType:'text/html',body:await r.text()});});
 await page.goto(origin+'/accounting?company_id=51#overview',{waitUntil:'networkidle'});
 await expect.poll(()=>subscribed).toBe(true);
 const bell=page.getByRole('button',{name:'Accounting notifications',exact:true});await expect(bell).toBeVisible();
 async function checkAlignment(){await expect(page.getByRole('combobox',{name:'Company',exact:true})).toHaveCount(0);const heading=await page.locator('.ac-heading').boundingBox(),b=await bell.boundingBox();assert.ok(b.x>=heading.x&&b.x+b.width<=heading.x+heading.width+1,'Notification bell must stay inside the header');}
 await checkAlignment();
 const before=feedRequests;
 items=[{id:1,company_id:51,company_name:'Notification demo',actor_name:'Finance Officer',title:'Journal draft created',subject:'JV-0000001',section:'journals',created_at:'2026-11-04T10:00:00Z',read_at:null,url:'/accounting?company_id=51#journals'}];
 socket.send(JSON.stringify({event:'accounting.activity',channel:'private-accounting.activity.admin',data:JSON.stringify({company_id:51,notification_id:1})}));
 await expect(bell.locator('.acn-count')).toHaveText('1');assert.ok(feedRequests>before);
 await bell.click();const panel=page.getByRole('region',{name:'Accounting activity notifications'});await expect(panel).toContainText('Finance Officer');await expect(panel).toContainText('JV-0000001');await expect(panel).toContainText('Live updates');
 await page.getByRole('button',{name:'Mark all read',exact:true}).click();await expect(bell.locator('.acn-count')).toHaveCount(0);await expect(panel).toContainText('0 unread');
 await page.keyboard.press('Escape');await expect(panel).toHaveCount(0);await expect(bell).toBeFocused();
 await bell.click();await page.getByRole('heading',{name:'Your company at a glance',exact:true}).click();await expect(panel).toHaveCount(0);
 items=Array.from({length:6},(_,i)=>({...items[0],id:6-i,subject:'JV-'+(6-i)}));
 await bell.click();await expect(panel.locator('.acn-item')).toHaveCount(6);
 const geometry=await panel.locator('.acn-items').evaluate(el=>({height:el.clientHeight,content:el.scrollHeight,row:el.querySelector('.acn-item').getBoundingClientRect().height}));
 assert.equal(geometry.row,88);assert.equal(geometry.height,geometry.row*5);assert.ok(geometry.content>geometry.height);assert.ok((await panel.boundingBox()).width<=350);
 await page.keyboard.press('Escape');
 await page.setViewportSize({width:390,height:844});await checkAlignment();await bell.click();await expect(panel).toBeVisible();const box=await panel.boundingBox();assert.ok(box.x>=0&&box.x+box.width<=390);assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
 await mkdir('.preview',{recursive:true});await page.screenshot({path:'.preview/accounting-notifications-mobile.png',fullPage:true});
 permissions=['overview.view'];await page.goto(origin+'/accounting?company_id=51#overview',{waitUntil:'networkidle'});await page.reload({waitUntil:'networkidle'});await expect(bell).toHaveCount(0);
 assert.deepEqual(errors,[]);console.log('Accounting notifications browser check passed: private-channel authentication, WebSocket event updates unread badge without refresh, dropdown details, mark-all-read, Escape/outside close, mobile bounds and permission hiding. Backend APIs/WebSocket server mocked.');
}finally{await browser.close();}
