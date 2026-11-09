import {chromium,expect} from '@playwright/test';
import assert from 'node:assert/strict';
import {mkdir} from 'node:fs/promises';
const origin=process.env.JUDIBAS_TEST_URL||'http://127.0.0.1:8000';
const browser=await chromium.launch({channel:'msedge',headless:true});
try{
 const page=await browser.newPage({viewport:{width:1440,height:1000}}),errors=[],writes=[];
 page.on('pageerror',e=>errors.push(e.message));
 const today=new Date().toISOString().slice(0,10),settings={focus_minutes:25,short_break:5,long_break:15,long_after:4,daily_goal:180,timezone:'Asia/Karachi',auto_focus:false,auto_break:false,disable_breaks:false,add_top:true,completion_sound:false,groups:true,forest:true,ranking:true,noise:'none',noise_volume:30,alarm:'none',alarm_volume:70,background:'plain',lists:['Tomorrow','This week','Planned','Events','Completed','All']};
 const fixture={settings,tasks:Array.from({length:14},(_,i)=>({id:i+1,title:'Review project '+(i+1),notes:'',project_id:null,priority:i===0?'high':'none',estimate:2,due_date:today,remind_at:null,repeat:'none',tags:['work'],subtasks:[],completed_at:null})),projects:[],timer:null,server_now:new Date().toISOString(),today,summary:{seconds:1800,completed:2},notifications:[],sessions:[],companies:[{id:1,name:'Skyinfinit'}],rewards:[{id:1,reason:'Daily visit',grams:30,collected_at:null,created_at:new Date().toISOString()}],sunlight:0};
 await page.route(/\/focus-to-do(?:\?|$)/,async route=>{const r=await page.request.get(origin+'/products/focus-to-do');await route.fulfill({contentType:'text/html',body:await r.text()});});
 await page.route(/\/focus-to-do\/api\//,async route=>{
  const r=route.request(),u=new URL(r.url()),path=u.pathname.replace('/focus-to-do/api/','');let result={ok:true};
  if(r.method()==='POST'){
   const v=r.postDataJSON();writes.push({path,v});
   if(path==='tasks'){fixture.tasks.unshift({...v,id:100+fixture.tasks.length,completed_at:null});}
   if(/^tasks\/\d+$/.test(path))Object.assign(fixture.tasks.find(t=>t.id===Number(path.split('/')[1])),v);
   if(path.endsWith('/complete'))fixture.tasks.find(t=>t.id===Number(path.split('/')[1])).completed_at=v.completed?new Date().toISOString():null;
   if(path==='projects')fixture.projects.push({...v,id:1});
   if(path==='settings')Object.assign(fixture.settings,v);
   if(path==='timer'){
    if(v.action==='start')fixture.timer={id:99,phase:'focus',mode:'countdown',status:'running',target_seconds:1500,elapsed_seconds:0,segment_started_at:new Date().toISOString(),task_title:'Review project 1'};
    if(v.action==='pause')fixture.timer.status='paused';if(v.action==='resume')fixture.timer.status='running';if(v.action==='stop')fixture.timer=null;
    result={timer:fixture.timer,server_now:new Date().toISOString()};
   }
  }else if(path==='data'){fixture.server_now=new Date().toISOString();result=fixture;}
  else if(path==='realtime/config')result={enabled:false};
  else if(path==='records')result={records:[],page:1,last_page:1,total:0};
  else if(path==='report')result={seconds:3600,completed:3,daily:{[today]:3600},hours:Array.from({length:24},(_,i)=>i===10?3600:0),projects:{0:3600},goal_seconds:10800};
  else if(path==='social')result={groups:[{id:1,name:'Morning focus',description:'Company group',members:[{name:'Colleague',seconds:3600}],mine:false,owned:false,seconds:3600}],rankings:[{name:'Colleague',seconds:3600,you:true}]};
  await route.fulfill({json:result});
 });
 await page.goto(origin+'/focus-to-do',{waitUntil:'networkidle'});
 await expect(page.getByRole('heading',{name:'Focus To Do',exact:true})).toBeVisible();
 await expect(page.locator('.ft-task-row')).toHaveCount(10);await page.getByRole('button',{name:'Next',exact:true}).click();await expect(page.locator('.ft-task-row')).toHaveCount(4);
 await page.getByRole('textbox',{name:'New task'}).fill('Draft new proposal');await page.getByRole('textbox',{name:'New task'}).press('Enter');await expect(page.getByRole('status')).toContainText('Task added');
 await page.locator('.ft-sidebar').getByRole('button',{name:/Today/}).click();await page.getByRole('button',{name:'Draft new proposal',exact:false}).click();
 await expect(page.getByRole('dialog')).toBeVisible();await page.getByLabel('Notes',{exact:true}).fill('Notes belong to this task only.');await page.getByRole('button',{name:'+ Add subtask',exact:true}).click();await page.getByPlaceholder('Subtask',{exact:true}).fill('Check details');await page.getByRole('button',{name:'Save changes',exact:true}).click();await expect(page.getByRole('dialog')).not.toBeVisible();
 await page.locator('.ft-task-row').first().getByTitle('Focus on this task').click();await page.getByRole('button',{name:'Start focus',exact:true}).click();await expect(page.locator('.ft-clock small')).toHaveText('running');await page.getByRole('button',{name:'Pause',exact:true}).click();await expect(page.locator('.ft-clock small')).toHaveText('paused');await page.getByRole('button',{name:'Start / resume',exact:true}).click();
 await page.locator('.ft-sidebar').getByRole('button',{name:/Today/}).click();await expect(page.locator('.ft-mini-timer')).toBeVisible();await page.locator('.ft-mini-timer').click();await page.getByRole('button',{name:'Finish session',exact:true}).click();
 for(const section of ['Reports','Groups','Ranking','Forest','Settings']){await page.locator('.ft-sidebar').getByRole('button',{name:section,exact:true}).click();await expect(page.getByRole('heading',{name:section,exact:true})).toBeVisible();if(section==='Groups'){await page.getByRole('button',{name:'Discover',exact:true}).click();await expect(page.getByRole('heading',{name:'Morning focus',exact:true})).toBeVisible();}}
 await page.getByRole('button',{name:'General',exact:true}).click();await page.getByLabel('Show Forest',{exact:true}).uncheck();await page.getByRole('button',{name:'Save preferences',exact:true}).click();await expect(page.locator('.ft-sidebar').getByRole('button',{name:'Forest',exact:true})).toHaveCount(0);
 await page.getByRole('button',{name:'Lists',exact:true}).click();await page.getByLabel('Overdue',{exact:true}).check();await page.getByRole('button',{name:'Save preferences',exact:true}).click();await expect(page.locator('.ft-sidebar').getByRole('button',{name:/Overdue/})).toBeVisible();
 await page.locator('.ft-sidebar').getByRole('button',{name:/Today/}).click();await mkdir('.preview',{recursive:true});await page.screenshot({path:'.preview/focus-desktop.png',fullPage:true});
 for(const width of [390,768,1440]){await page.setViewportSize({width,height:900});assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,`Overflow at ${width}`);}
 await page.setViewportSize({width:390,height:844});await page.screenshot({path:'.preview/focus-mobile.png',fullPage:true});
 assert.equal(errors.length,0,errors.join('\n'));assert.ok(writes.some(w=>w.path==='tasks'));console.log('Focus browser checks passed: tasks, details/subtasks, timer, mini timer, reports, groups, rankings, forest, settings, pagination and responsive widths.');
}finally{await browser.close();}
