import {chromium,expect} from '@playwright/test';
import assert from 'node:assert/strict';
import {mkdir} from 'node:fs/promises';
// Isolated API fixtures: browser checks never alter live users or reports.
const origin=process.env.JUDIBAS_TEST_URL||'http://127.0.0.1:8000';
const browser=await chromium.launch({channel:'msedge',headless:true});
try{
 const context=await browser.newContext({serviceWorkers:'block',viewport:{width:1440,height:1050}}),page=await context.newPage(),errors=[];
 page.on('pageerror',error=>errors.push(error.message));
 const today=new Date().toLocaleDateString('en-CA');const previous=new Date();previous.setDate(previous.getDate()-1);const previousDate=previous.toLocaleDateString('en-CA');
 let role='super_admin',writes=[],failNextReport=false,failOwnLookup=false;
 const company={id:51,name:'Skyinfinit'},departments=[{id:1,company_id:51,name:'Development'},{id:2,company_id:51,name:'Marketing'}];
 const abilities={view_reports:'View reports',write_reports:'Write reports',export_reports:'Export reports',view_companies:'View companies',view_people:'View people',review_reports:'Review reports',manage_departments:'Manage departments',manage_invitations:'Invite people',manage_people:'Manage people',manage_roles:'Manage roles',view_roles:'View roles',view_audit:'View audit',manage_settings:'Manage settings'};
 const users=Array.from({length:15},(_,i)=>({id:100+i,name:'Employee '+i,email:'employee'+i+'@example.test',department:'Development',designation:'Developer',company_id:51,role:i===0?'pm':'employee',active:true,role_name:i===0?'Project Manager':'Employee'}));
 const reports=users.map((u,i)=>({id:i+1,user_id:u.id,name:u.name,company_id:51,department:'Development',designation:'Developer',date:today,hours:8,status:i%3===0?'reviewed':'submitted',feedback:'',content_format:'html',completed:'<p>Completed a feature</p>',progress:'<p>Testing</p>',plan:'<p>Release</p>',blockers:'<p>None</p>',sections_html:{completed:'<p><strong>Completed a feature</strong></p>',progress:'<p>Testing</p>',plan:'<p>Release</p>',blockers:'<p>None</p>'},sections_text:{completed:'Completed a feature',progress:'Testing',plan:'Release',blockers:'None'}}));
 reports[0].date=previousDate;
 await page.route(/\/daymark\/api\//,async route=>{
  const requestUrl=new URL(route.request().url()),path=requestUrl.pathname.replace('/daymark/api/','');let result={};
  if(path==='own-report'&&failOwnLookup){failOwnLookup=false;await route.fulfill({status:503,json:{message:'Report lookup unavailable. Please retry.'}});return;}
  if(route.request().method()==='POST'&&path==='report'&&failNextReport){failNextReport=false;await route.fulfill({status:422,json:{message:'Please check the report and try again.'}});return;}
  if(route.request().method()==='POST'){writes.push({path,body:route.request().postDataJSON()});result={message:'Saved successfully.',invitation_url:origin+'/invitations/test-token',role:{id:1}};}
  else if(path==='data'){const permissions=role==='super_admin'?Object.keys(abilities).filter(key=>key!=='write_reports'):['view_reports','write_reports','export_reports','view_companies'];result={users:role==='employee'?[users[1]]:users,companies:[company],departments,reports:(role==='employee'?[reports[1]]:reports).filter(r=>!requestUrl.searchParams.has('date')||r.date===requestUrl.searchParams.get('date')),viewer:role==='super_admin'?{id:0,name:'Super Admin',email:'',role}:users[1],permissions,custom_roles:[{id:1,company_id:51,name:'Report reader',base_role:'employee',permissions:['view_reports']}],assignments:[{manager_id:100,employee_id:101}],audit:[{id:1,name:'Super Admin',action:'Department saved',target:'Development',created_at:new Date().toISOString()}],action_items:role==='super_admin'?[{id:1,date:previousDate,name:'Employee 0',status:'changes_requested',feedback:'Please add details.'}]:[],role_matrix:{abilities,roles:[{role:'employee',permissions:['view_reports','write_reports','export_reports','view_companies'],scope:'Own reports only'},{role:'company_admin',permissions:Object.keys(abilities),scope:'Own company'}]}};}
  else if(path==='invitations')result={invitations:[]};
  else if(path==='own-report')result={report:null};
  if(path==='departments'&&route.request().method()==='POST'){const input=route.request().postDataJSON();let department=departments.find(d=>d.company_id===input.company_id&&d.name===input.name);if(!department){department={id:Math.max(...departments.map(d=>d.id))+1,company_id:input.company_id,name:input.name};departments.push(department);}result.department=department;}
  await route.fulfill({json:result});
 });
 await page.route(/\/daymark(?:\?.*)?$/,async route=>{const r=await page.request.get(origin+'/products/daymark');await route.fulfill({contentType:'text/html',body:await r.text()});});
 await page.goto(origin+'/daymark',{waitUntil:'networkidle'});
 await expect(page.getByRole('heading',{name:'Daymark',exact:true})).toBeVisible();
 await expect(page.locator('.dm-latest')).toHaveCount(5);
 await page.getByRole('button',{name:'View all reports',exact:false}).click();
 await expect(page.getByRole('tab',{name:'Monthly reports',exact:true})).toHaveAttribute('aria-selected','true');
 await expect(page.locator('.dm-table-panel tbody tr')).toHaveCount(12);
 await page.getByRole('button',{name:'Next',exact:true}).click();await expect(page.locator('.dm-table-panel tbody tr')).toHaveCount(3);
 await page.getByRole('button',{name:'Previous',exact:true}).click();
 await page.getByRole('button',{name:'View report ↗',exact:true}).first().click();
 await expect(page.getByRole('dialog')).toContainText('Completed a feature');
 await page.getByLabel('Feedback',{exact:true}).fill('Please add details.');
 await page.getByLabel('Review decision',{exact:true}).selectOption('changes_requested');
 await page.getByRole('button',{name:'Save review',exact:true}).click();
 await expect(page.getByRole('dialog')).toHaveCount(0);
 assert.equal(writes.at(-1).path,'review');assert.equal(writes.at(-1).body.status,'changes_requested');
 await page.getByRole('navigation',{name:'Daymark navigation'}).getByRole('button',{name:'Monthly overview',exact:true}).click();
 await page.locator('.dm-filters input[type=search]').fill('Employee 1');
 await page.getByRole('navigation',{name:'Daymark navigation'}).getByRole('button',{name:'Daily reports',exact:true}).click();await expect(page.locator('.dm-filters input[type=search]')).toHaveValue('');
 await page.locator('.dm-filters select').last().selectOption('submitted');
 await page.getByRole('navigation',{name:'Daymark navigation'}).getByRole('button',{name:'Overview',exact:true}).click();await expect(page.locator('.dm-filters input[type=search]')).toHaveValue('');
 await page.getByRole('navigation',{name:'Daymark navigation'}).getByRole('button',{name:'Monthly overview',exact:true}).click();await expect(page.locator('.dm-filters input[type=search]')).toHaveValue('Employee 1');await expect(page.locator('.dm-filters select').last()).toHaveValue('');
 await page.getByRole('navigation',{name:'Daymark navigation'}).getByRole('button',{name:'Daily reports',exact:true}).click();await expect(page.locator('.dm-filters select').last()).toHaveValue('submitted');
 await page.getByRole('button',{name:'Day-end report',exact:true}).click();await page.getByLabel('Export company',{exact:true}).selectOption('51');await page.getByLabel('Export department',{exact:true}).selectOption('Development');await page.keyboard.press('Escape');await expect(page.locator('.dm-filters select').first()).toHaveValue('');await expect(page.locator('.dm-filters select').nth(1)).toHaveValue('');

 await page.getByRole('navigation',{name:'Daymark navigation'}).getByRole('button',{name:'Overview',exact:true}).click();
 await page.locator('.dm-followup').first().click();await expect(page.getByRole('dialog')).toContainText('Employee 0');await expect(page.getByRole('dialog')).toContainText(previousDate);await page.keyboard.press('Escape');await page.getByRole('navigation',{name:'Daymark navigation'}).getByRole('button',{name:'Overview',exact:true}).click();
 await expect(page.locator('.judibas-toast')).toHaveCount(0,{timeout:6000});
 await mkdir('.preview',{recursive:true});await page.screenshot({path:'.preview/daymark-desktop.png',fullPage:true});
 await page.emulateMedia({colorScheme:'dark'});await page.screenshot({path:'.preview/daymark-dark.png',fullPage:true});
 await page.setViewportSize({width:390,height:844});await page.getByRole('button',{name:'Navigation',exact:false}).click();await expect(page.getByRole('navigation',{name:'Daymark navigation'}).getByRole('button',{name:'Daily reports',exact:true})).toBeVisible();assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);await page.getByRole('button',{name:'Navigation',exact:false}).click();await page.screenshot({path:'.preview/daymark-mobile.png',fullPage:true});
 role='employee';await page.goto(origin+'/daymark',{waitUntil:'networkidle'});
 await expect(page.getByRole('heading',{name:'Daymark',exact:true})).toBeVisible();
 await expect(page.getByRole('button',{name:'Write report',exact:false})).toBeVisible();
 await expect(page.getByRole('navigation',{name:'Daymark navigation'}).getByRole('button',{name:'People',exact:true})).toHaveCount(0);
 failOwnLookup=true;await page.getByRole('button',{name:'Write report',exact:false}).click();await expect(page.getByRole('dialog').getByRole('alert')).toContainText('lookup unavailable');await expect(page.getByRole('button',{name:'Submit report',exact:true})).toBeDisabled();await page.keyboard.press('Escape');
 await page.getByRole('button',{name:'Write report',exact:false}).click();await expect(page.getByRole('textbox',{name:'Completed tasks',exact:true})).toBeVisible();
 for(const name of ['Completed tasks','Work in progress','Next plan','Hurdles & blockers']){const editor=page.getByRole('textbox',{name,exact:true});await editor.click();await expect(editor).toBeFocused();await page.keyboard.insertText('Test progress');}
 const firstEditor=page.getByRole('textbox',{name:'Completed tasks',exact:true});
 await firstEditor.click();await page.getByRole('toolbar',{name:'Completed tasks formatting',exact:true}).getByRole('button',{name:'Bold',exact:true}).click();await expect(firstEditor).toBeFocused();await page.keyboard.insertText(' formatted');
 await page.getByRole('toolbar',{name:'Completed tasks formatting',exact:true}).getByRole('button',{name:'Undo',exact:true}).click();
 await page.getByRole('toolbar',{name:'Completed tasks formatting',exact:true}).getByRole('button',{name:'Redo',exact:true}).click();await expect(firstEditor).toContainText('formatted');
 assert.equal(await page.locator('.dm-editor-tools').first().innerText().then(text=>text.includes('?')),false);
 await page.setViewportSize({width:1440,height:1050});await page.getByRole('dialog').evaluate(el=>el.scrollTop=0);await page.screenshot({path:'.preview/daymark-report-desktop.png'});await page.setViewportSize({width:390,height:844});await page.getByRole('dialog').evaluate(el=>el.scrollTop=0);await page.screenshot({path:'.preview/daymark-report-mobile.png'});assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
 await page.getByRole('button',{name:'Save private draft',exact:true}).click();assert.equal(writes.at(-1).body.status,'draft');assert.ok(writes.at(-1).body.completed.includes('formatted'));for(const key of ['progress','plan','blockers'])assert.ok(writes.at(-1).body[key].replace(/&nbsp;/g,' ').includes('Test progress'));
 await page.getByRole('button',{name:'Write report',exact:false}).click();
 for(const name of ['Completed tasks','Work in progress','Next plan','Hurdles & blockers']){const editor=page.getByRole('textbox',{name,exact:true});await editor.click();await expect(editor).toBeFocused();await page.keyboard.insertText('Ready to submit');}
 failNextReport=true;await page.getByRole('button',{name:'Submit report',exact:true}).click();await expect(page.getByRole('dialog').getByRole('alert')).toContainText('Please check');await expect(page.getByRole('textbox',{name:'Completed tasks',exact:true})).toContainText('Ready to submit');
 await page.getByRole('button',{name:'Submit report',exact:true}).click();await expect(page.getByRole('dialog')).toHaveCount(0);assert.equal(writes.at(-1).body.status,'submitted');assert.ok(writes.at(-1).body.completed.replace(/&nbsp;/g,' ').includes('Ready to submit'));

 await page.route(/\/admin$/,async route=>{const r=await page.request.get(origin+'/products/daymark');const html=(await r.text()).replace(/(<\/script>\s*<div id="app">)/,'window.portal.admin=true;window.portal.superAdmin=true;window.portal.administrator=true;window.portal.settingsPage=true;$1');await route.fulfill({contentType:'text/html',body:html});});
 await page.route(/\/admin\/api(?:\/.*)?$/,async route=>{const path=new URL(route.request().url()).pathname;await route.fulfill({json:path.endsWith('/invitations')?{invitations:[]}:{users:[],products:[{slug:'daymark',name:'Daymark',workspace:'/daymark',category:'Collaboration'}],companies:[company],daymark_departments:departments,daymark_roles:[],daymark_role_matrix:{abilities,roles:[{role:'employee',permissions:['view_reports','write_reports','export_reports','view_companies'],scope:'Own reports only'}]},daymark_managers:[users[0]],pms_roles:[],pms_departments:[],pms_tasks:[],canteen_levels:[],groups:[],blogs:[],pages:{},announcement:{}}});});
 await page.setViewportSize({width:1440,height:1050});await page.goto(origin+'/admin#users',{waitUntil:'networkidle'});
 await page.goto(origin+'/admin#daymark-setup',{waitUntil:'networkidle'});await expect(page.getByRole('heading',{name:'Daymark setup',exact:true})).toBeVisible();
 await page.getByRole('button',{name:'Add department',exact:true}).click();await expect(page.getByRole('dialog')).toContainText('Department name');await page.keyboard.press('Escape');
 await page.getByRole('tab',{name:'Roles & permissions',exact:true}).click();await page.getByRole('button',{name:'Create role',exact:true}).click();await expect(page.getByRole('dialog')).toContainText('Reporting permissions');await page.keyboard.press('Escape');
 await page.goto(origin+'/admin#users',{waitUntil:'networkidle'});
 await page.getByRole('button',{name:'New employee',exact:true}).click();
 await page.locator('.admin-product-options label').filter({hasText:'Daymark'}).getByRole('checkbox').check();
 await expect(page.getByRole('dialog')).toContainText('Daymark reporting access');
 await page.getByLabel('Skyinfinit',{exact:true}).check();
 await page.getByLabel('Reporting company',{exact:false}).selectOption('51');
 await expect(page.locator('label').filter({hasText:'Department'}).locator('select').last().locator('option')).toHaveCount(departments.length+1);
 await page.getByRole('button',{name:'Close dialog',exact:true}).click();
 await page.getByRole('button',{name:'Invite employee',exact:true}).click();
 await page.locator('.admin-product-options label').filter({hasText:'Daymark'}).getByRole('checkbox').check();
 await expect(page.getByRole('dialog')).toContainText('Daymark reporting access');
 await page.keyboard.press('Escape');
 assert.deepEqual(errors,[]);console.log('Daymark browser checks passed: dashboard, pagination, reports/reviews, departments, people, roles, invitations, employee draft, mobile, dark theme and central user/invitation access fields.');
}finally{await browser.close();}
