// Uses the real production bundle with isolated API fixtures; no live records are changed.
import {chromium,expect} from '@playwright/test';
import {readFile,writeFile} from 'node:fs/promises';
import {resolve,extname} from 'node:path';
const manifest=JSON.parse(await readFile('public/build/manifest.json','utf8'));
const entry=manifest['resources/js/app.ts'];
const portal={name:'Judibas',tagline:'Your company workspace',logo:null,products:[{slug:'projects',name:'PMS',workspace:'/pms',features:[]}],product:null,page:null,workspacePath:'/pms',workspaceCompanies:[{id:101,name:'Skyinfinit',slug:'skyinfinit'}],admin:true,administrator:true,superAdmin:true,settingsPage:false,productsPage:false,loginPage:false,employee:null,posts:[],announcement:{title:'',text:'',url:''},error:null,success:null,validationErrors:[],pmsBootstrap:{permissions:['*'],user_id:null,name:'Preview Admin',branding:{},company_context:null}};
const html='<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="preview">'+entry.css.map(c=>'<link rel="stylesheet" href="/build/'+c+'">').join('')+'</head><body><script>window.portal='+JSON.stringify(portal)+'</script><div id="app"></div><script type="module" src="/build/'+entry.file+'"></script></body></html>';
const browser=await chromium.launch({channel:'msedge',headless:true});
let showCompanies=true;const results=[],errors=[];
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
   else if(path.includes('hierarchy'))body={companies:[{id:101,name:'Skyinfinit International Technology and Consulting Company',unassigned_projects:[],clients:[{id:1,name:'Atlas International Logistics and Distribution Client',projects:[{id:11,name:'Comprehensive Customer Shipment Tracking and Integration Platform',departments:[{id:4,dept_name:'DevelopmentAndQualityAssuranceDepartmentWithoutSpaces'}]}]}]}],unassigned_projects:[],show_companies:showCompanies};
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

 for(const management of [true,false]){
 showCompanies=management;await page.goto('http://pms-preview.test/pms?section=dashboard');
 await page.locator('.pms-client-workspace>summary').click();await expect(page.locator('.pms-hierarchy-client')).toHaveCount(1);
 await page.locator('.pms-hierarchy-nav details').evaluateAll(nodes=>nodes.forEach(n=>n.open=true));
 for(const width of [1440,1000]){
 await page.setViewportSize({width,height:1000});
 const dimensions=await page.locator('.pms-hierarchy-nav .pms-tree-label,.pms-hierarchy-department>span').evaluateAll(nodes=>nodes.filter(n=>n.getBoundingClientRect().height).map(n=>({text:n.textContent,whiteSpace:getComputedStyle(n).whiteSpace,overflow:getComputedStyle(n).textOverflow,client:n.clientWidth,scroll:n.scrollWidth})));
 const alignment=await page.locator('.pms-hierarchy-nav summary').evaluateAll(nodes=>nodes.filter(n=>n.getBoundingClientRect().height).flatMap(n=>{const eye=n.querySelector(':scope>.pms-tree-eye'),arrow=n.querySelector(':scope>.pms-tree-chevron');return eye&&arrow?[Math.abs((eye.getBoundingClientRect().top+eye.getBoundingClientRect().height/2)-(arrow.getBoundingClientRect().top+arrow.getBoundingClientRect().height/2))]:[];}));for(const delta of alignment)expect(delta).toBeLessThanOrEqual(1);
 expect(dimensions.length).toBeGreaterThan(3);for(const d of dimensions){expect(d.whiteSpace).toBe('normal');expect(d.overflow).not.toBe('ellipsis');expect(d.scroll).toBeLessThanOrEqual(d.client+2);}
 await expect.poll(()=>page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2)).toBe(true);
 }
 await page.screenshot({path:'.preview/pms-tree-full-names-'+(management?'admin':'employee')+'.png',fullPage:true});
 }
 expect(errors).toEqual([]);console.log('PASS: full tree names wrap without clipping for company and client roots, including long unbroken names at desktop/tablet widths. No API writes.');
}finally{await browser.close();}
