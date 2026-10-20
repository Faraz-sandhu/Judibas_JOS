import {chromium,expect} from '@playwright/test';
import {execFileSync} from 'node:child_process';
import assert from 'node:assert/strict';
const origin=process.env.JUDIBAS_TEST_URL||'http://127.0.0.1:8000';
const browser=await chromium.launch({channel:'msedge',headless:true});
const marker='Daymark websocket verification '+Date.now();let cleanup=null;
async function api(page,path,body){return page.evaluate(async({path,body})=>{const r=await fetch('/daymark/api/'+path,{method:body?'POST':'GET',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content},body:body?JSON.stringify(body):undefined});return {status:r.status,data:await r.json()};},{path,body});}
try{
 const pages={};
 for(const role of ['employee1','pm','admin']){
  const context=await browser.newContext({serviceWorkers:'block',viewport:{width:1440,height:1050}}),page=await context.newPage();pages[role]=page;
  await page.goto(origin+'/login');await page.locator('input[name=email]').fill(`daymark.${role}.alpha@example.test`);await page.locator('input[name=password]').fill('DaymarkDemo2026!');
  await Promise.all([page.waitForURL(url=>url.pathname!=='/login'),page.getByRole('button',{name:/Sign in/}).click()]);
  await page.goto(origin+'/daymark',{waitUntil:'networkidle'});await expect(page.locator('.dm-live-status')).toHaveText('Live',{timeout:15000});
 }
 const {employee1:employee,pm,admin}=pages;const config=await api(employee,'realtime/config');assert.equal(config.data.enabled,true);
 let date;for(let n=35;n<65;n++){const d=new Date();d.setDate(d.getDate()-n);const candidate=d.toLocaleDateString('en-CA');const own=await api(employee,'own-report?date='+candidate);if(!own.data.report){date=candidate;break;}}assert.ok(date);
 const who=await api(employee,'data?date='+date);cleanup={owner:who.data.viewer.id,date,marker};
 for(const page of Object.values(pages)){await page.getByRole('navigation',{name:'Daymark navigation'}).getByRole('button',{name:'Daily reports',exact:true}).click();await page.getByLabel('Report date',{exact:true}).fill(date);await expect(page.locator('.dm-main')).toHaveAttribute('aria-busy','false');}
 const payload={date,status:'submitted',hours:8,completed:marker,progress:'Testing live updates',plan:'Verify report review',blockers:'None'};
 const started=Date.now();assert.equal((await api(employee,'report',payload)).status,200);
 for(const page of [pm,admin]){await expect(page.locator('.dm-table-panel')).toContainText(who.data.viewer.name);await expect(page.locator('.dm-table-panel')).toContainText('Submitted');}
 const elapsed=Date.now()-started;const report=(await api(employee,'own-report?date='+date)).data.report;
 await employee.getByRole('button',{name:/View report/}).click();
 assert.equal((await api(pm,'review',{id:report.id,status:'changes_requested',feedback:marker+' feedback'})).status,200);
 await expect(employee.getByRole('dialog')).toContainText(marker+' feedback');await expect(employee.getByRole('dialog').locator('.dm-badge')).toHaveText('Changes Requested');await expect(admin.locator('.dm-table-panel')).toContainText('Changes Requested');
 assert.equal((await api(employee,'report',payload)).status,200);await expect(pm.locator('.dm-table-panel')).toContainText('Submitted');
 await employee.getByRole('button',{name:'Edit your report',exact:true}).click();
 const editor=employee.getByRole('textbox',{name:'Completed tasks',exact:true});await editor.click();await employee.keyboard.insertText(' unsaved text');
 assert.equal((await api(pm,'review',{id:report.id,status:'reviewed',feedback:'Live review verified.'})).status,200);
 await expect(employee.getByRole('dialog')).toContainText('reviewed and locked');await expect(employee.getByRole('button',{name:'Reviewed - read only',exact:true})).toBeDisabled();await expect(editor).toContainText('unsaved text');await expect(admin.locator('.dm-table-panel')).toContainText('Reviewed');
 console.log(`Real websocket checks passed: employee submission → PM and company admin (${elapsed} ms), review feedback → employee, resubmission and approval. No page refreshes.`);
}finally{
 await browser.close();
 if(cleanup)execFileSync('php',['scripts/daymark-live-cleanup.php',String(cleanup.owner),cleanup.date,cleanup.marker],{stdio:'pipe'});
}
