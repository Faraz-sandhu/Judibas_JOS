import {chromium,expect} from '@playwright/test';
import assert from 'node:assert/strict';
import {mkdir} from 'node:fs/promises';

// Browser interaction checks use isolated API fixtures; no real projects are changed.
const origin=process.env.PMS_TEST_URL||'http://127.0.0.1:8000';
const browser=await chromium.launch({channel:'msedge',headless:true});
try{
 const context=await browser.newContext({serviceWorkers:'block',viewport:{width:1440,height:1000}});
 const page=await context.newPage(),errors=[];
 page.on('pageerror',error=>errors.push(error.message));
 const client={id:21,name:'Atlas Logistics'};
 const projects=Array.from({length:12},(_,index)=>({id:100+index,name:index===11?'Atlas Shipment Tracking':index===10?'Atlas Customer Portal':'Atlas Project '+index,client_id:21,company_id:1,description:'<p>A project brief for the client.</p>',status:'pending',approval:'approved',start_date:'2026-10-01',end_date:'2026-12-01',tasks_count:4,unassigned_tasks_count:1,users:[]}));
 const departments=id=>id===110?[{id:13,dept_name:'SEO'}]:[{id:11,dept_name:'Development'},{id:12,dept_name:'Design'}];
 let permissions=['project-view','client-view','project-add','project-edit','project-trash','project-assign'];
 let delays={},denied=new Set(),denyBoard=false;
 const detail=id=>({...projects.find(project=>project.id===id),client,company:{id:1,name:'Company'},departments:departments(id)});
 await page.route(/\/pms\/api(?:\/|$)/,async route=>{
  const url=new URL(route.request().url()),path=url.pathname.replace('/pms/api','').replace(/\/$/,'');
  let body={};
  if(!path)body={permissions,branding:{},user_id:null};
  else if(path==='/projects')body={data:{projects:permissions.includes('project-edit')?projects:[projects[11]],clients:[client],availableWorkflows:[{id:1,name:'Default workflow'}],departments:[...departments(111),...departments(110)],filteredUsers:[]}};
  else if(path==='/hierarchy')body={companies:[{id:1,name:'Company',clients:[{...client,projects:projects.map(project=>({...project,departments:departments(project.id)}))}],unassigned_projects:[]}],unassigned_projects:[]};
  else if(path==='/realtime/config')body={enabled:false};
  else if(path==='/notifications')body={items:[],unread:0};
  else if(path==='/project-status'){const input=route.request().postDataJSON();projects.find(project=>project.id===input.id).status=input.status;body={message:'Updated'};}
  else if(/^\/projects\/\d+\/approval$/.test(path)){projects.find(project=>project.id===Number(path.split('/')[2])).approval=route.request().postDataJSON().status;body={message:'Updated'};}
  else if(path.startsWith('/projects/')){
   const id=Number(path.split('/')[2]);
   if((denyBoard&&/^\/projects\/\d+$/.test(path))||(!permissions.includes('project-edit')&&path.endsWith('/edit'))){await route.fulfill({status:403,json:{message:'Not permitted.'}});return;}
   if(denied.has(id)){await route.fulfill({status:404,json:{message:'Project not available.'}});return;}
   if(path.endsWith('/departments')){if(delays[id])await new Promise(resolve=>setTimeout(resolve,delays[id]));body={data:{project:detail(id),hasUnallocatedWork:id===111}};}
   else if(path.endsWith('/edit'))body={data:detail(id),workflow_ids:[1],department_ids:departments(id).map(department=>department.id)};
   else if(path.endsWith('/task-list'))body={data:{tasks:[]}};
   else{const sprint={id:901,name:'Sprint One',status:'active',goal:'Deliver the release',tasks_count:0};body={data:{project:detail(id),boardDepartment:departments(id).find(department=>department.id===Number(url.searchParams.get('department'))),selectedWorkflow:{id:1,columns:[{id:1,name:'To Do',color:'secondary'}]},sprints:[sprint],selectedSprint:url.searchParams.get('sprint')==='901'?sprint:null,boardTasks:[],asignee:[],workloadUsers:[],sprintStats:{total:1,active:1,planned:0,completed:0}}};}
  }
  await route.fulfill({json:body});
 });
 await page.route(/\/pms(?:\?.*)?$/,async route=>{
  const response=await page.request.get(origin+'/products/projects');
  const html=(await response.text()).replace(/(<\/script>\s*<div id="app">)/,'window.portal.admin=true;window.portal.administrator=true;$1');
  await route.fulfill({contentType:'text/html',body:html});
 });
 await page.goto(origin+'/pms?section=projects&client=21',{waitUntil:'networkidle'});
 await expect(page.locator('.pms-project-context h2')).toHaveText(client.name);
 await expect(page.locator('.pms-project-card')).toHaveCount(10);
 await page.locator('.pms-pagination').getByRole('button',{name:'Next',exact:true}).click();
 await expect(page.locator('.pms-project-card')).toHaveCount(2);
 await page.locator('.pms-pagination').getByRole('button',{name:'Previous',exact:true}).click();
 await page.getByLabel('Sort projects',{exact:true}).selectOption('id:asc');await expect(page.locator('.pms-project-card-title').first()).toHaveText('Atlas Project 0');await page.getByLabel('Sort projects',{exact:true}).selectOption('id:desc');
 await page.getByRole('button',{name:'Show departments for Atlas Shipment Tracking',exact:true}).click();
 await expect(page.locator('#pms-project-department-panel h3')).toHaveText('Atlas Shipment Tracking');
 await expect(page.locator('.pms-project-card')).toHaveCount(10);
 assert.ok(await page.locator('#pms-project-department-panel').evaluate(panel=>panel.getBoundingClientRect().top>=document.querySelector('.pms-project-grid').getBoundingClientRect().bottom));
 await page.locator('#pms-project-department-panel').getByRole('button',{name:/Development/}).click();
 await expect(page).toHaveURL(/department=11/);
 await expect(page.getByRole('heading',{name:'Sprint board',exact:true})).toBeVisible();
 await page.getByLabel('Board sprint',{exact:true}).selectOption('901');
 await expect(page).toHaveURL(/sprint=901/);
 await page.getByRole('button',{name:'Back to projects',exact:true}).click();
 await expect(page.locator('#pms-project-department-panel h3')).toHaveText('Atlas Shipment Tracking');
 await expect(page.locator('.pms-project-card.selected')).toHaveCount(1);
 await page.getByRole('button',{name:'Actions for Atlas Shipment Tracking',exact:true}).click();
 await page.getByRole('button',{name:'Preview details',exact:true}).click();
 await expect(page.getByRole('dialog')).toContainText('Atlas Shipment Tracking');
 await page.keyboard.press('Escape');
 await page.getByRole('button',{name:'Actions for Atlas Shipment Tracking',exact:true}).click();
 await page.getByRole('button',{name:'Edit project',exact:true}).first().click();
 await expect(page.getByRole('dialog')).toContainText('Edit project');
 await page.keyboard.press('Escape');
 await page.getByRole('button',{name:'Actions for Atlas Shipment Tracking',exact:true}).click();
 await page.getByLabel('Status for Atlas Shipment Tracking',{exact:true}).click();await page.getByRole('listbox',{name:'Status for Atlas Shipment Tracking',exact:true}).getByRole('option',{name:'Progress',exact:true}).click();
 await expect(page.locator('.pms-project-card').filter({has:page.getByRole('button',{name:'Show departments for Atlas Shipment Tracking',exact:true})}).locator('.pms-project-card-status')).toHaveText('progress');
 projects[10].name='Updated Customer Portal';
 await page.evaluate(()=>window.dispatchEvent(new CustomEvent('pms:changed',{detail:{project_id:110}})));
 await expect(page.getByRole('button',{name:'Show departments for Updated Customer Portal',exact:true})).toBeVisible();
 projects[10].name='Atlas Customer Portal';
 await page.evaluate(()=>window.dispatchEvent(new CustomEvent('pms:changed',{detail:{project_id:110}})));
 await expect(page.getByRole('button',{name:'Show departments for Atlas Customer Portal',exact:true})).toBeVisible();
 denyBoard=true;
 await page.locator('#pms-project-department-panel').getByRole('button',{name:/Development/}).click();
 await expect(page.locator('.admin-error')).toContainText('Not permitted');
 await expect(page.locator('.pms-project-card')).toHaveCount(10);
 await expect(page.locator('#pms-project-department-panel h3')).toHaveText('Atlas Shipment Tracking');
 denyBoard=false;
 await page.getByLabel('Search table',{exact:true}).evaluate(input=>{input.value='Customer Portal';input.dispatchEvent(new Event('input',{bubbles:true}));});
 await expect(page.locator('.pms-project-card')).toHaveCount(1);
 await page.getByLabel('Search table',{exact:true}).evaluate(input=>{input.value='';input.dispatchEvent(new Event('input',{bubbles:true}));});
 delays={111:500,110:40};
 await page.getByRole('button',{name:'Show departments for Atlas Shipment Tracking',exact:true}).click();
 await page.getByRole('button',{name:'Show departments for Atlas Customer Portal',exact:true}).click();
 await expect(page.locator('#pms-project-department-panel h3')).toHaveText('Atlas Customer Portal');
 await page.waitForTimeout(650);
 await expect(page.locator('#pms-project-department-panel h3')).toHaveText('Atlas Customer Portal');
 await expect(page.locator('#pms-project-department-panel')).toContainText('SEO');
 delays={};denied.add(109);
 await page.getByRole('button',{name:'Show departments for Atlas Project 9',exact:true}).click();
 await expect(page.locator('#pms-project-department-panel')).toContainText('Unable to load');
 await expect(page.locator('.pms-project-card')).toHaveCount(10);
 await page.goto(origin+'/pms?section=project-departments&project=111',{waitUntil:'networkidle'});
 await expect(page.locator('.pms-project-context h2')).toHaveText(client.name);
 await expect(page).toHaveURL(/client=21/);
 await expect(page.locator('#pms-project-department-panel h3')).toHaveText('Atlas Shipment Tracking');
 await page.reload({waitUntil:'networkidle'});
 await expect(page.locator('#pms-project-department-panel h3')).toHaveText('Atlas Shipment Tracking');
 await page.setViewportSize({width:390,height:844});
 await expect(page.locator('.pms-project-card').first()).toBeVisible();
 assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
 await mkdir('.preview',{recursive:true});await page.screenshot({path:'.preview/pms-project-grid-mobile.png',fullPage:true});
 await page.setViewportSize({width:1440,height:1000});await page.screenshot({path:'.preview/pms-project-grid-desktop.png',fullPage:true});
 await page.emulateMedia({colorScheme:'dark'});await expect(page.locator('.pms-workspace')).toHaveClass(/pms-dark/);await page.screenshot({path:'.preview/pms-project-grid-dark.png',fullPage:true});
 permissions=['project-view','client-view'];
 await page.goto(origin+'/pms?section=projects&client=21',{waitUntil:'networkidle'});
 await expect(page.locator('.pms-project-card')).toHaveCount(1);
 await expect(page.locator('.pms-table-heading').getByRole('button',{name:'Create',exact:true})).toHaveCount(0);
 await page.getByRole('button',{name:'Actions for Atlas Shipment Tracking',exact:true}).click();
 await expect(page.locator('.pms-project-card-options').getByRole('button',{name:'Edit project',exact:true})).toHaveCount(0);
 await expect(page.locator('.pms-project-card-options').getByRole('button',{name:'Delete project',exact:true})).toHaveCount(0);
 await page.getByRole('button',{name:'Preview details',exact:true}).click();
 await expect(page.getByRole('dialog')).toContainText('Atlas Shipment Tracking');
 await page.keyboard.press('Escape');
 await page.getByRole('button',{name:'Show departments for Atlas Shipment Tracking',exact:true}).click();
 await expect(page.locator('#pms-project-department-panel h3')).toHaveText('Atlas Shipment Tracking');
 await expect(page.locator('#pms-project-department-panel').getByRole('button',{name:'Edit project',exact:true})).toHaveCount(0);
 assert.deepEqual(errors,[]);
 console.log('PMS grid checks passed: client context, pagination, inline departments, board/backlog/sprint navigation, preview/edit/status actions, search, rapid project switching, failure recovery, refresh, mobile and employee actions.');
}finally{await browser.close();}
