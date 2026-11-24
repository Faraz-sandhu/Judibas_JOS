import {chromium,expect} from '@playwright/test';
import assert from 'node:assert/strict';
import {mkdir} from 'node:fs/promises';
let failNextComplete=false,dataDelay=0;
const origin=process.env.JUDIBAS_TEST_URL||'http://127.0.0.1:8000';
const browser=await chromium.launch({channel:'msedge',headless:true});
try{
 const page=await browser.newPage({viewport:{width:1440,height:1000}}),errors=[],writes=[];
 page.on('pageerror',e=>errors.push(e.message));
 await page.addInitScript(()=>{window.__focusTones=0;const original=AudioContext.prototype.createOscillator;AudioContext.prototype.createOscillator=function(...args){window.__focusTones++;return original.apply(this,args);};});
 const today=new Date().toISOString().slice(0,10),settings={focus_minutes:25,short_break:5,long_break:15,long_after:4,daily_goal:180,timezone:'Asia/Karachi',auto_focus:false,auto_break:false,disable_breaks:false,add_top:true,completion_sound:false,groups:true,forest:true,ranking:true,noise:'none',noise_volume:30,alarm:'none',alarm_volume:70,background:'plain',lists:['Tomorrow','This week','Planned','Events','Completed','All']};
 const fixture={settings,tasks:Array.from({length:14},(_,i)=>({id:i+1,title:'Review project '+(i+1),notes:'',project_id:null,priority:i===0?'high':'none',estimate:2,due_date:today,remind_at:null,repeat:'none',tags:['work'],subtasks:[],completed_at:null})),projects:[],timer:null,server_now:new Date().toISOString(),today,summary:{seconds:1800,completed:2},notifications:[],sessions:[],companies:[{id:1,name:'Skyinfinit'}],rewards:[{id:1,reason:'Daily visit',grams:30,collected_at:null,created_at:new Date().toISOString()}],sunlight:0};
 await page.route(/\/focus-to-do(?:\?|$)/,async route=>{const r=await page.request.get(origin+'/products/focus-to-do');await route.fulfill({contentType:'text/html',body:await r.text()});});
 await page.route(/\/focus-to-do\/api\//,async route=>{
  const r=route.request(),u=new URL(r.url()),path=u.pathname.replace('/focus-to-do/api/','');let result={ok:true};
  if(r.method()==='POST'&&path.endsWith('/complete')&&failNextComplete){failNextComplete=false;await route.fulfill({status:422,json:{message:'Test: completion could not be saved.'}});return;}
  if(r.method()==='POST'){
   const v=r.postDataJSON();writes.push({path,v});
   if(path==='tasks'){fixture.tasks.unshift({...v,id:100+fixture.tasks.length,completed_at:null});}
   if(/^tasks\/\d+$/.test(path))Object.assign(fixture.tasks.find(t=>t.id===Number(path.split('/')[1])),v);
   if(path.endsWith('/complete'))fixture.tasks.find(t=>t.id===Number(path.split('/')[1])).completed_at=v.completed?new Date().toISOString():null;
   if(path==='projects')fixture.projects.push({...v,id:1});
   if(path==='settings')Object.assign(fixture.settings,v);
   if(path==='collect'){fixture.sunlight+=fixture.rewards.filter(r=>!r.collected_at).reduce((sum,r)=>sum+r.grams,0);fixture.rewards.forEach(r=>r.collected_at=new Date().toISOString());}
   if(path==='timer'){
    if(v.action==='start')fixture.timer={id:99,phase:'focus',mode:'countdown',status:'running',target_seconds:1500,elapsed_seconds:0,segment_started_at:new Date().toISOString(),task_title:'Review project 1'};
    if(v.action==='pause')fixture.timer.status='paused';if(v.action==='resume')fixture.timer.status='running';if(v.action==='stop')fixture.timer=null;
    result={timer:fixture.timer,server_now:new Date().toISOString()};
   }
  }else if(path==='data'){fixture.server_now=new Date().toISOString();result=JSON.parse(JSON.stringify(fixture));if(dataDelay)await new Promise(resolve=>setTimeout(resolve,dataDelay));}
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
 await page.locator('.ft-sidebar').getByRole('button',{name:/Today/}).click();await page.locator('.ft-task-title').filter({hasText:'Draft new proposal'}).click();
 await expect(page.getByRole('dialog')).toBeVisible();await page.getByLabel('Notes',{exact:true}).fill('Notes belong to this task only.');await page.getByRole('button',{name:'+ Add subtask',exact:true}).click();await page.getByPlaceholder('Subtask',{exact:true}).fill('Check details');await page.getByRole('button',{name:'Save changes',exact:true}).click();await expect(page.getByRole('dialog')).not.toBeVisible();
 const completionCount=()=>writes.filter(w=>w.path.endsWith('/complete')).length;
 const draftRow=page.locator('.ft-task-row').filter({hasText:'Draft new proposal'});const beforeComplete=completionCount();
 await draftRow.getByRole('checkbox',{name:'Complete task',exact:true}).check();await expect(draftRow).toHaveCount(0);assert.equal(completionCount(),beforeComplete+1);
 await page.getByRole('button',{name:'Show completed tasks',exact:true}).click();await expect(draftRow.getByRole('checkbox',{name:'Reopen task',exact:true})).toBeChecked();assert.equal(completionCount(),beforeComplete+1);
 await draftRow.getByRole('checkbox',{name:'Reopen task',exact:true}).uncheck();await expect(draftRow.getByRole('checkbox',{name:'Complete task',exact:true})).not.toBeChecked();assert.equal(completionCount(),beforeComplete+2);
 await page.getByRole('button',{name:'Hide completed tasks',exact:true}).click();
 failNextComplete=true;await draftRow.getByRole('checkbox',{name:'Complete task',exact:true}).check();await expect(page.getByRole('alert')).toContainText('completion could not be saved');await expect(draftRow.getByRole('checkbox')).not.toBeChecked();
 dataDelay=300;await page.getByLabel('List options',{exact:true}).click();await page.getByRole('button',{name:'Sync now',exact:true}).click();await expect(draftRow.getByRole('checkbox')).toBeDisabled();await expect(draftRow.getByRole('checkbox')).toBeEnabled();dataDelay=0;
 await page.locator('.ft-sidebar').getByRole('button',{name:/Tomorrow/}).click();await page.getByRole('textbox',{name:'New task'}).fill('Tomorrow regression');await page.getByRole('textbox',{name:'New task'}).press('Enter');await expect(page.locator('.ft-task-title').filter({hasText:'Tomorrow regression'})).toBeVisible();
 const tomorrow=new Date(today+'T12:00:00');tomorrow.setDate(tomorrow.getDate()+1);assert.equal(writes.find(w=>w.path==='tasks'&&w.v.title==='Tomorrow regression').v.due_date,tomorrow.toLocaleDateString('en-CA'));
 await page.locator('.ft-sidebar').getByRole('button',{name:/Today/}).click();
 await page.locator('.ft-section-header h2').click();
 await page.locator('.ft-task-row').first().locator('.ft-row-menu summary').click();await page.locator('.ft-task-row').first().getByTitle('Share task',{exact:true}).click();
 await expect(page.getByLabel('Share preview')).toHaveValue(/Draft new proposal/);await expect(page.getByLabel('Share preview')).not.toHaveValue(/Notes belong/);
 await page.evaluate(()=>{const original=HTMLAnchorElement.prototype.click;HTMLAnchorElement.prototype.click=function(){if(this.download){window.__focusDownload={name:this.download,url:this.href};return;}original.call(this);};});await page.getByRole('button',{name:'Download card',exact:true}).click();await expect.poll(()=>page.evaluate(()=>window.__focusDownload?.name||'')).toMatch(/\.png$/);
 await page.getByRole('button',{name:'Close share',exact:true}).click();
 await page.getByLabel('List options',{exact:true}).click();await page.getByRole('button',{name:'Share list',exact:true}).click();await expect(page.getByLabel('Share preview')).toHaveValue(/tasks/);await page.getByRole('button',{name:'Close share',exact:true}).click();
 await page.locator('.ft-task-row').first().getByTitle('Focus on this task').click();await page.getByRole('button',{name:'Start focus',exact:true}).click();await expect(page.locator('.ft-clock small')).toHaveText('running');await page.getByRole('button',{name:'Pause',exact:true}).click();await expect(page.locator('.ft-clock small')).toHaveText('paused');await page.getByRole('button',{name:'Start / resume',exact:true}).click();
 await page.locator('.ft-sidebar').getByRole('button',{name:/Today/}).click();await expect(page.locator('.ft-timer-dock')).toBeVisible();await page.getByRole('button',{name:'Open timer',exact:true}).click();await page.getByRole('button',{name:'Finish session',exact:true}).click();
 for(const section of ['Reports','Groups','Ranking','Forest','Settings']){await page.locator('.ft-header-tools').getByRole('button',{name:section,exact:true}).click();await expect(page.getByRole('heading',{name:section,exact:true})).toBeVisible();if(section==='Groups'){await page.getByRole('button',{name:'Discover',exact:true}).click();await expect(page.getByRole('heading',{name:'Morning focus',exact:true})).toBeVisible();}}
 await page.locator('.ft-header-tools').getByRole('button',{name:'Forest',exact:true}).click();await expect(page.getByRole('img',{name:/Growing focus tree/})).toBeVisible();await page.getByRole('button',{name:'Collect 30g sunlight',exact:true}).click();await expect(page.getByRole('status')).toContainText('Sunlight');await page.emulateMedia({reducedMotion:'reduce'});assert.equal(await page.locator('.ft-growing-tree').evaluate(el=>getComputedStyle(el).animationName),'none');await page.setViewportSize({width:390,height:844});assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);await page.setViewportSize({width:1440,height:1000});await page.emulateMedia({reducedMotion:'no-preference'});await page.locator('.ft-forest-garden').screenshot({path:'.preview/focus-forest.png',animations:'disabled'});
 await page.locator('.ft-header-tools').getByRole('button',{name:'Settings',exact:true}).click();await page.locator('.ft-tabs').getByRole('button',{name:'Timer',exact:true}).click();const tones=()=>page.evaluate(()=>window.__focusTones);const before=await tones();await page.getByLabel('Focus alarm',{exact:true}).selectOption('bell');await expect.poll(tones).toBe(before+3);await page.getByRole('button',{name:/Preview focus alarm/}).click();await expect.poll(tones).toBe(before+6);await page.getByLabel('Focus alarm',{exact:true}).selectOption('none');assert.equal(await tones(),before+6);await expect(page.getByRole('button',{name:/Preview focus alarm/})).toBeDisabled();await page.getByLabel('Break alarm',{exact:true}).selectOption('digital');await expect.poll(tones).toBe(before+9);
 await page.getByRole('button',{name:'General',exact:true}).click();await page.getByLabel('Show Forest',{exact:true}).uncheck();await page.getByRole('button',{name:'Save preferences',exact:true}).click();await expect(page.locator('.ft-header-tools').getByRole('button',{name:'Forest',exact:true})).toHaveCount(0);
 await page.getByRole('button',{name:'Lists',exact:true}).click();await page.getByLabel('Overdue',{exact:true}).check();await page.getByRole('button',{name:'Save preferences',exact:true}).click();await page.locator('.ft-extra-lists summary').click();await expect(page.locator('.ft-sidebar').getByRole('button',{name:/Overdue/})).toBeVisible();
 await page.locator('.ft-sidebar').getByRole('button',{name:/Today/}).click();await page.locator('.ft-section-header h2').click();await mkdir('.preview',{recursive:true});await page.screenshot({path:'.preview/focus-desktop.png',fullPage:true,animations:'disabled'});
 for(const width of [390,768,1440]){await page.setViewportSize({width,height:900});assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,`Overflow at ${width}`);}
 await page.setViewportSize({width:390,height:844});await page.screenshot({path:'.preview/focus-mobile.png',fullPage:true,animations:'disabled'});
 assert.equal(errors.length,0,errors.join('\n'));assert.ok(writes.some(w=>w.path==='tasks'));console.log('Focus browser checks passed: complete/reopen, failed-save rollback, delayed-sync locking, tasks, details/subtasks, timer, mini timer, reports, groups, rankings, forest, settings, pagination and responsive widths.');
 fixture.tasks=fixture.tasks.slice(0,2).map((t,i)=>({...t,project_id:i===0?1:null,due_date:null,estimate:5}));fixture.projects=[{id:1,name:'test',color:'#a98cc8',is_folder:false,folder_id:null}];fixture.summary={seconds:0,completed:0};fixture.settings.lists=['Tomorrow','This week','Planned','Events','Completed'];fixture.settings.forest=true;
 await page.setViewportSize({width:1600,height:760});await page.evaluate(()=>localStorage.setItem('judibas-theme-v2','dark'));await page.reload({waitUntil:'networkidle'});await expect(page.getByText('No tasks yet',{exact:true})).toBeVisible();await page.mouse.click(310,132);await page.screenshot({path:'.preview/focus-reference-dark.png',fullPage:true,animations:'disabled'});
 await page.goto('about:blank');
}finally{for(const context of browser.contexts()){for(const page of context.pages())await page.unrouteAll({behavior:'ignoreErrors'});await context.close();}await browser.close();}
