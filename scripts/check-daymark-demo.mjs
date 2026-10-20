import {chromium,expect} from '@playwright/test';
import assert from 'node:assert/strict';
const origin=process.env.JUDIBAS_TEST_URL||'http://127.0.0.1:8000';
const browser=await chromium.launch({channel:'msedge',headless:true});
try{
 for(const role of ['employee1','pm']){
  const context=await browser.newContext({serviceWorkers:'block',viewport:{width:1440,height:1050}}),page=await context.newPage();
  const email=`daymark.${role}.alpha@example.test`;
  await page.goto(origin+'/login');
  await page.locator('input[name=email]').fill(email);await page.locator('input[name=password]').fill('DaymarkDemo2026!');
  await Promise.all([page.waitForURL(url=>url.pathname!=='/login'),page.getByRole('button',{name:/Sign in/}).click()]);
  await page.goto(origin+'/daymark',{waitUntil:'networkidle'});await expect(page.getByRole('heading',{name:'Daymark',exact:true})).toBeVisible();
  const response=await page.request.get(origin+'/daymark/api/data?month='+new Date().toLocaleDateString('en-CA').slice(0,7));assert.equal(response.status(),200);const data=await response.json();
  assert.equal(data.companies.length,1);assert.equal(data.users.length,role==='pm'?3:1);assert.ok(data.reports.length>0);assert.ok(data.reports.every(report=>report.company_id===data.viewer.company_id));
  if(role==='employee1')assert.ok(data.reports.every(report=>report.user_id===data.viewer.id));
  assert.ok(data.reports.every(report=>report.status!=='draft'||report.user_id===data.viewer.id));
  for(const name of ['Companies','Departments','Invitations','People','Roles & access'])await expect(page.getByRole('navigation',{name:'Daymark navigation'}).getByRole('button',{name,exact:true})).toHaveCount(0);
  assert.equal((await page.request.get(origin+'/admin/api')).status(),403);
  await page.screenshot({path:`.preview/daymark-demo-${role}.png`,fullPage:true});await context.close();
 }
 console.log('Real Daymark demo employee and PM logins passed: own/team scope, private drafts, product navigation and central admin restrictions.');
}finally{await browser.close();}
