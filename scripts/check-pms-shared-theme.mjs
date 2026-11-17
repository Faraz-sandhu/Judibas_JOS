// Uses the real production bundle with isolated API fixtures; no live records are changed.
import {chromium,expect} from '@playwright/test';
import {readFile,writeFile} from 'node:fs/promises';
import {resolve,extname} from 'node:path';
const manifest=JSON.parse(await readFile('public/build/manifest.json','utf8'));
const entry=manifest['resources/js/app.ts'];
const portal={name:'Judibas',tagline:'Your company workspace',logo:null,products:[{slug:'projects',name:'PMS',workspace:'/pms',features:[]}],product:null,page:null,workspacePath:'/pms',workspaceCompanies:[{id:101,name:'Skyinfinit',slug:'skyinfinit'}],admin:true,administrator:true,superAdmin:true,settingsPage:false,productsPage:false,loginPage:false,employee:null,posts:[],announcement:{title:'',text:'',url:''},error:null,success:null,validationErrors:[],pmsBootstrap:{permissions:['*'],user_id:null,name:'Preview Admin',branding:{},company_context:null}};
const html='<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="preview">'+entry.css.map(c=>'<link rel="stylesheet" href="/build/'+c+'">').join('')+'</head><body><script>window.portal='+JSON.stringify(portal)+'</script><div id="app"></div><script type="module" src="/build/'+entry.file+'"></script></body></html>';
const browser=await chromium.launch({channel:'msedge',headless:true});
const results=[],errors=[];
try{
 const context=await browser.newContext({serviceWorkers:'block',viewport:{width:1440,height:1000}});
 await context.route('**/*',async route=>{
  const url=new URL(route.request().url()),path=url.pathname;
  if(url.hostname!=='pms-preview.test')return route.abort();
  if(path.startsWith('/pms/api')){
   let body={};
   if(path.endsWith('dashboard/bundle'))body={data:{adminCount:2,managerCount:3,employeeCount:12,ongoingProjectCount:5,completedProjectCount:8,dashboardCharts:{projects:{labels:['Pending','Ongoing','Completed'],series:[2,5,8]},tasks:{labels:['To do','In progress','Done'],series:[8,5,12]}},dashboardInsights:{show_workload:false,deadlines:[],deadline_total:0}},table:[]};
   else if(path.endsWith('/my-work'))body={data:{projects:[],sprints:[],workflows:[],assignees:[],canManageTeam:false,canQuickAssign:false,selectedWorkflow:{id:1,columns:[{id:1,name:'In progress',color:'primary'},{id:2,name:'Review',color:'warning'},{id:3,name:'Done',color:'success'}]},statusCounts:{1:1},tasks:[{id:1,title:'Prepare responsive interface designs',project:{name:'Atlas Shipment Tracking'},sprint:{name:'Shipment Tracking Release - Graphic - Extra Long Sprint Name For Layout Check',status:'active'},workflow_column_id:1,users:[],priority:1,original_estimate_minutes:60}]}};
   else if(path.endsWith('/clients'))body={companies:[{id:101,name:'Skyinfinit'}],clients:[{id:1,company_id:101,name:'Atlas Logistics',email:'example@example.test',company:{name:'Skyinfinit'},projects_count:2,status:true}]};
   else if(path.endsWith('/workflows'))body={workflows:[{id:1,name:'Delivery workflow',transition_mode:'any',is_active:true,columns:[{id:1,name:'To do',color:'secondary',is_initial:true},{id:2,name:'Done',color:'success',is_completed:true}]}],departments:[{id:1,dept_name:'Development'}]};
   else if(path.endsWith('/reports'))body={data:{projects:[],users:[],departments:[],clients:[],companies:[]}};
   else if(path.endsWith('/reports/overview'))body={summary:{total:0,completed:0,overdue:0,dueSoon:0,loggedSeconds:0,progress:0},departments:[]};
   else if(path.endsWith('/reports/data'))body={data:[],recordsFiltered:0};
   else if(path.endsWith('/work-logs'))body={data:route.request().headers()['x-requested-with']==='XMLHttpRequest'?[]:{projects:[],users:[]}};
   else if(path.endsWith('/departments'))body={data:[{id:1,dept_name:'Development',description:'Delivery team',status:1}]};
   else if(path.endsWith('/notifications'))body={items:[],unread:0};
   else if(path.endsWith('/realtime/config'))body={enabled:false};
   else if(path.includes('hierarchy'))body={companies:[],clients:[],projects:[],show_companies:true};
   return route.fulfill({json:body});
  }
  if(path==='/pms')return route.fulfill({contentType:'text/html',body:html});
  try{
   const file=resolve('public','.'+path),data=await readFile(file);
   const type={'.js':'text/javascript','.css':'text/css','.svg':'image/svg+xml','.png':'image/png','.woff2':'font/woff2','.json':'application/json'}[extname(file)]||'application/octet-stream';
   return route.fulfill({body:data,contentType:type});
  }catch{return route.fulfill({status:404,body:''});}
 });
 const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));
 for(const theme of ['light','dark']){
  await page.addInitScript(t=>localStorage.setItem('judibas-theme-v2',t),theme);
  await page.goto('http://pms-preview.test/pms?section=dashboard');
  await expect(page.locator('.dashboard-heading')).toBeVisible();
  await expect(page.locator('.pms-global-loader')).toHaveCount(0);
  const colors=await page.evaluate(()=>{const p=document.querySelector('.pms-workspace'),s=getComputedStyle(p),root=getComputedStyle(document.documentElement);return {theme:document.documentElement.dataset.theme,page:s.backgroundColor,surface:getComputedStyle(document.querySelector('.dashboard-heading')).backgroundColor,bg:root.getPropertyValue('--bg').trim(),rail:root.getPropertyValue('--rail').trim(),primary:s.getPropertyValue('--pms-primary').trim(),overflow:document.documentElement.scrollWidth>innerWidth+2};});
  expect(colors.theme).toBe(theme);expect(colors.page).toBe(theme==='dark'?'rgb(23, 23, 23)':'rgb(255, 255, 255)');expect(colors.surface).toBe(theme==='dark'?'rgb(32, 32, 32)':'rgb(250, 250, 250)');expect(colors.overflow).toBe(false);
  const headerGap=await page.evaluate(()=>document.querySelector('.pms-sidebar').getBoundingClientRect().top-document.querySelector('.pms-product-heading').getBoundingClientRect().bottom);expect(Math.abs(headerGap-28)).toBeLessThan(2);results.push(colors);await page.evaluate(()=>document.fonts.ready);await page.waitForTimeout(700);await page.screenshot({path:'.preview/pms-judibas-dashboard-'+theme+'.png',fullPage:true});
  await page.locator('.pms-sidebar').getByRole('button',{name:'Clients',exact:true}).click();
  await expect(page.locator('.pms-content')).toContainText('Atlas Logistics');
  await page.getByRole('button',{name:'Create client',exact:true}).click();
  const dialog=page.getByRole('dialog',{name:'Create client',exact:true});await expect(dialog).toBeVisible();
  const dialogBg=await dialog.evaluate(el=>getComputedStyle(el).backgroundColor);expect(dialogBg).toBe(colors.page);
  await page.screenshot({path:'.preview/pms-judibas-client-dialog-'+theme+'.png'});
  await dialog.getByRole('button',{name:'Close',exact:true}).click();await expect(dialog).toHaveCount(0);
  await page.locator('.pms-sidebar').getByRole('button',{name:'Workflows',exact:true}).click();await expect(page.locator('.pms-content')).toContainText('Delivery workflow');await page.getByRole('button',{name:'New workflow',exact:true}).click();const workflowDialog=page.getByRole('dialog',{name:'Workflow',exact:true});await expect(workflowDialog).toBeVisible();await expect(workflowDialog.getByLabel('Column name',{exact:true})).toHaveCount(2);await page.keyboard.press('Escape');await expect(workflowDialog).toHaveCount(0);
  await page.locator('.pms-sidebar').getByRole('button',{name:'Departments',exact:true}).click();await expect(page.locator('.pms-content')).toContainText('Development');await page.getByRole('button',{name:'Create',exact:true}).click();await expect(page.getByRole('dialog',{name:'Create department',exact:true})).toBeVisible();await page.keyboard.press('Escape');
  await page.locator('.pms-sidebar').getByRole('button',{name:'Reports',exact:true}).click();await expect(page.locator('.pms-report-table-card')).toBeVisible();expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2)).toBe(true);await page.setViewportSize({width:390,height:844});expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2)).toBe(true);await page.setViewportSize({width:1440,height:1000});
  await page.locator('.pms-sidebar').getByRole('button',{name:'Work Logs',exact:true}).click();await expect(page.locator('.pms-work-logs')).toBeVisible();await expect(page.locator('.pms-content')).toContainText('No work logs');
  await page.locator('.pms-sidebar').getByRole('button',{name:'My Work',exact:true}).click();await expect(page.locator('.pms-work-sprint-name')).toBeVisible();const bounds=await page.locator('.pms-work-sprint-name').evaluate(el=>{const a=el.getBoundingClientRect(),b=el.closest('.work-card').getBoundingClientRect();return {right:a.right<=b.right,left:a.left>=b.left}});expect(bounds).toEqual({right:true,left:true});expect(await page.locator('.pms-sidebar').evaluate(el=>getComputedStyle(el).borderRadius)).toBe('14px');await page.screenshot({path:'.preview/pms-judibas-board-'+theme+'.png',fullPage:true});
  await page.waitForTimeout(350);
  const movement=await page.evaluate(async()=>{const card=document.querySelector('[data-motion-task]'),lists=document.querySelectorAll('.work-list');lists[1].append(card);await new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)));return card.getAnimations().some(a=>a.effect.getKeyframes().some(k=>String(k.transform).includes('translate')));});expect(movement).toBe(true);
  await page.emulateMedia({reducedMotion:'reduce'});
  const reducedMovement=await page.evaluate(async()=>{const card=document.querySelector('[data-motion-task]');card.getAnimations().forEach(a=>a.cancel());document.querySelectorAll('.work-list')[0].append(card);await new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)));return card.getAnimations().length;});expect(reducedMovement).toBe(0);
  await page.emulateMedia({reducedMotion:'no-preference'});
  await page.setViewportSize({width:390,height:844});
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2)).toBe(true);
  await page.getByRole('button',{name:'Open navigation',exact:true}).click();await expect(page.locator('.pms-sidebar')).toBeVisible();await page.getByRole('button',{name:'Close navigation',exact:true}).last().click();await expect(page.locator('.pms-sidebar')).toBeHidden();await page.screenshot({path:'.preview/pms-judibas-mobile-'+theme+'.png'});
  await page.setViewportSize({width:1440,height:1000});
 }
 for(const count of [0,1,2]){
  await page.route('**/pms/api/my-work?*',route=>route.fulfill({json:{data:{projects:[],sprints:[],assignees:[],canManageTeam:false,canQuickAssign:false,tasks:[],statusCounts:{},workflows:Array.from({length:count},(_,i)=>({id:i+1,name:'Workflow '+(i+1)})),selectedWorkflow:count?{id:1,columns:[{id:1,name:'To do',color:'secondary'}]}:null}}}));
  await page.goto('http://pms-preview.test/pms?section=my-work');
  await expect(page.locator('.filter-card')).toBeVisible();
  await expect(page.locator('#work-workflow')).toHaveCount(count>1?1:0);
  if(count===0)await expect(page.getByRole('heading',{name:'No assigned work yet'})).toBeVisible();
  if(count>1)await expect(page.locator('#work-workflow')).toHaveValue('1');
  await expect(page.locator('.filter-card button')).toBeEnabled();
  await page.unroute('**/pms/api/my-work?*');
 }
 expect(errors).toEqual([]);await writeFile('.preview/pms-shared-theme-results.json',JSON.stringify({results,errors},null,2));
 console.log('PASS: light/dark surfaces, dashboard, client/workflow/department dialogs, reports, work logs, narrow board badges, mobile navigation/overflow and runtime checks. No database writes.');
}finally{await browser.close();}
