<script setup lang="ts">
import {useAccountingAlerts} from './useAccountingAlerts';
import {useAccountingSuperAdmin} from './permissions';
const voucherSuperAdmin=useAccountingSuperAdmin();
import {useAccountingLiveRefresh} from './workspaceRealtime';
import {computed,onBeforeUnmount,ref,watch} from 'vue';
import AccountingSelect from './AccountingSelect.vue';
import AccountingDocument from './AccountingDocument.vue';
import AccountingApprovalPanel from './AccountingApprovalPanel.vue';
import AdminDialog from '../../AdminDialog.vue';
import {needsApproval} from './approvalPolicy';
import {useAccountingPermissions} from './permissions';
import {requestKey} from './requestKey';

const can=useAccountingPermissions();
const reverseDate=ref(new Date().toLocaleDateString('en-CA')),reason=ref('');
const props=defineProps<{company:number;book:any;accounts:any[];csrf?:string;openId?:number}>();
const emit=defineEmits<{notice:[message:string];changed:[]}>();
const data=ref<any>(null),loading=ref(false),busy=ref(false),error=ref(''),formError=ref(''),page=ref(1),modal=ref(''),form=ref<any>({}),journal=ref<any>(null),confirmAction=ref('');
let generation=0;onBeforeUnmount(()=>generation++);
const today=()=>new Date().toLocaleDateString('en-CA');
const money=(v:any)=>Number(v||0).toLocaleString('en-PK',{minimumFractionDigits:2,maximumFractionDigits:2});
const parents=computed(()=>props.accounts.filter(a=>a.active&&a.is_group&&a.root_type==='Asset'));
const sources=computed(()=>props.accounts.filter(a=>a.active&&!a.is_group&&!a.currency&&a.root_type==='Asset'&&['Cash','Bank'].includes(a.account_type)&&!data.value?.funds.some((f:any)=>f.account_id===a.id)));
const expenses=computed(()=>props.accounts.filter(a=>a.active&&!a.is_group&&a.root_type==='Expense'));
const selectedFund=computed(()=>data.value?.funds.find((f:any)=>f.id===form.value.fund_id));
const printable=computed(()=>journal.value?'/accounting/'+(journal.value.kind==='general'?'journals':'vouchers')+'/'+journal.value.id+'/print?company_id='+props.company: '');
const canPost=computed(()=>data.value?.can_post&&journal.value?.status==='draft'&&!journal.value?.imprest?.void&&!needsApproval(props.book,journal.value.kind)&&(!journal.value.approval||['returned','withdrawn'].includes(journal.value.approval.stage)));

async function api(path:string,body?:any){
 const r=await fetch('/accounting/api/'+path,{method:body?'POST':'GET',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':props.csrf||''},body:body?JSON.stringify(body):undefined});
 const d=await r.json();if(!r.ok)throw Error(Object.values(d.errors||{}).flat().join(' ')||d.message||'Unable to complete this action.');return d;
}
async function load(){const current=++generation;loading.value=true;error.value='';try{const d=await api('imprest?company_id='+props.company+'&page='+page.value);if(current===generation)data.value=d;}catch(e){if(current===generation)error.value=(e as Error).message;}finally{if(current===generation)loading.value=false;}}
async function view(id:number){formError.value='';try{journal.value=(await api('imprest/'+id+'?company_id='+props.company)).journal;}catch(e){error.value=(e as Error).message;}}
watch(()=>props.company,()=>{modal.value='';journal.value=null;page.value=1;void load();},{immediate:true});
watch(()=>props.openId,id=>{if(id)void view(id);},{immediate:true});
function open(type:string,fund?:any){formError.value='';modal.value=type;form.value={name:'',user_id:'',parent_id:parents.value.find(a=>a.name==='Current Assets')?.id||'',fund_id:fund?.id||'',account_id:'',amount:'',posting_date:today(),person:fund?.officer_name||'',remarks:'',request_key:requestKey()};}
async function save(){if(busy.value)return;busy.value=true;formError.value='';try{const result=await api('imprest/'+(modal.value==='fund'?'funds':modal.value==='funding'?'allocate':'spend'),{...form.value,company_id:props.company});modal.value='';emit('notice',result.message);await load();emit('changed');if(result.journal)journal.value=result.journal;}catch(e){formError.value=(e as Error).message;}finally{busy.value=false;}}
async function action(){if(busy.value)return;busy.value=true;formError.value='';try{const result=await api(confirmAction.value==='reverse'?'journals/'+journal.value.id+'/reverse':'imprest/'+journal.value.id+'/'+confirmAction.value,{company_id:props.company,version:journal.value.version,...(confirmAction.value==='reverse'?{posting_date:reverseDate.value,reason:reason.value}:{})});confirmAction.value='';emit('notice',result.message);await view(journal.value.id);await load();emit('changed');}catch(e){formError.value=(e as Error).message;}finally{busy.value=false;}}
async function refreshed(){if(journal.value)await view(journal.value.id);await load();emit('changed');}
useAccountingLiveRefresh(()=>load(),()=>!modal.value&&!journal.value&&!busy.value);
useAccountingAlerts(error,formError);
</script>

<template>
<section class="imprest-workspace" :class="{'ac-document-open':!!journal}">
 <div class="ac-section-heading"><div><h2>Disbursement Voucher</h2><p class="muted">Officer funds, advance vouchers and expenses with a controlled spending balance.</p></div><button v-if="data?.can_manage" class="button primary" :disabled="!book||loading" @click="open('fund')">+ Assign officer fund</button></div>
 <p class="ac-help">Advance: Company Cash / Bank → Officer Cash. Expense: Officer Cash → Expense account. Only posted vouchers affect accounting reports; non-posted expenses reserve the officer's available funds.</p>
 <p v-if="data?.restricted" class="imprest-restriction">Your vouchers are limited to your assigned fund. The company's other cash and bank balances are unavailable for spending.</p>
 <p v-if="error" class="notice admin-error" role="alert">{{error}}</p>
 <p v-if="loading" role="status">Loading officer funds...</p>
 <div v-else class="imprest-fund-grid">
  <article v-for="fund in data?.funds||[]" :key="fund.id" class="imprest-fund">
   <div class="imprest-fund-heading"><span class="imprest-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 7h16v13H4zM4 7l12-4v4M15 12h5v5h-5z"/></svg></span><div><h3>{{fund.name}}</h3><p>{{fund.officer_name}}</p><small>{{fund.officer_email}}</small></div><span class="ac-badge" :class="fund.active?'active':'inactive'">{{fund.active?'Active':'Paused'}}</span></div>
   <dl class="imprest-balances"><div><dt>Posted balance</dt><dd>{{money(fund.balance)}}</dd></div><div><dt>Reserved drafts</dt><dd>{{money(fund.reserved)}}</dd></div><div class="imprest-available"><dt>Available to spend · {{book?.currency}}</dt><dd>{{money(fund.available)}}</dd></div></dl>
   <div class="imprest-actions"><button v-if="data?.can_manage" class="button" :disabled="!fund.active||busy" @click="open('funding',fund)">Allocate / top up</button><button v-if="data?.can_create" class="button primary" :disabled="!fund.active||Number(fund.available)<=0||busy" @click="open('spending',fund)">+ Expense voucher</button></div>
  </article>
  <div v-if="data&&!data.funds.length" class="ac-empty"><h3>No officer funds assigned</h3><p>{{data.can_manage?'Assign a company employee, then create and post an advance voucher.':'Ask your Accounting Manager to assign and fund your officer account.'}}</p></div>
 </div>
 <div class="ac-section-heading imprest-history"><div><h3>Voucher history</h3><p class="muted">IAV: Imprest Advance Voucher · PCV: Petty Cash Payment Voucher</p></div><button class="button" :disabled="loading" @click="load">Refresh</button></div>
 <div class="ac-table-wrap"><table class="ac-finance-table"><thead><tr><th>Voucher / officer</th><th>Type</th><th>Date</th><th>Status</th><th class="numeric">Amount ({{book?.currency}})</th><th></th></tr></thead><tbody><tr v-for="row in data?.movements?.data||[]" :key="row.id"><td><strong>{{row.number}}</strong><small class="imprest-row-name">{{row.officer_name}}</small></td><td>{{row.type==='funding'?'Advance / top-up':'Expense payment'}}</td><td>{{row.posting_date}}</td><td><span class="ac-badge" :class="row.status==='posted'&&!row.void?'active':'inactive'">{{row.void?'Cancelled':row.status==='draft'?'Non-posted':row.status}}</span></td><td class="numeric">{{money(row.amount)}}</td><td><button class="button" @click="view(row.journal_id)">View voucher</button></td></tr><tr v-if="!data?.movements?.data.length"><td colspan="6" class="ac-empty-small">No imprest vouchers yet.</td></tr></tbody></table></div>
 <div v-if="data?.movements" class="ac-pagination"><span>Page {{page}} of {{data.movements.last_page}} · {{data.movements.total}} vouchers</span><button class="button" :disabled="page<=1||loading" @click="page--;load()">Previous</button><button class="button" :disabled="page>=data.movements.last_page||loading" @click="page++;load()">Next</button></div>

 <AdminDialog v-if="modal" :title="modal==='fund'?'Assign officer fund':modal==='funding'?'Create imprest advance':'Create petty cash expense'" :busy="busy" @close="modal=''">
  <p v-if="formError" class="notice admin-error" role="alert">{{formError}}</p>
  <form @submit.prevent="save"><fieldset class="imprest-form" :disabled="busy">
   <template v-if="modal==='fund'"><p class="ac-help">The selected officer will be restricted to their assigned fund for this company, even if their role previously allowed other voucher forms. Give them the Junior Finance Officer role in Administration → Users.</p><label>Officer<AccountingSelect><select v-model.number="form.user_id" required><option value="" disabled>Select an Accounting employee</option><option v-for="u in data.officers" :key="u.id" :value="u.id">{{u.name}} · {{u.email}}</option></select></AccountingSelect></label><label>Fund name<input v-model="form.name" required maxlength="150" placeholder="e.g. Suivillion - Junior Finance Cash"></label><label>Parent Asset group<AccountingSelect><select v-model.number="form.parent_id" required><option value="" disabled>Select an Asset group</option><option v-for="a in parents" :key="a.id" :value="a.id">{{a.code}} - {{a.name}}</option></select></AccountingSelect></label><small>A separate Cash ledger is created automatically with zero balance. Assigning a fund does not move money.</small></template>
   <template v-else><p class="ac-help"><strong>{{selectedFund?.name}}</strong><br>{{modal==='funding'?'An advance transfers existing company cash; it is not an expense. Funds become available after posting.':'Available: '+book?.currency+' '+money(selectedFund?.available)+'. Saving a draft reserves this amount until posted or cancelled.'}}</p><div class="ac-form-grid"><label>Voucher date<input type="date" v-model="form.posting_date" :min="book?.start_date" :max="voucherSuperAdmin?undefined:today()" required></label><label>{{modal==='funding'?'Company Cash / Bank source':'Expense account'}}<AccountingSelect><select v-model.number="form.account_id" required><option value="" disabled>Select account</option><option v-for="a in modal==='funding'?sources:expenses" :key="a.id" :value="a.id">{{a.code}} - {{a.name}}</option></select></AccountingSelect></label><label>Amount ({{book?.currency}})<input v-model="form.amount" inputmode="decimal" pattern="[0-9]{1,10}(\.[0-9]{1,2})?" placeholder="0.00" required></label><label>{{modal==='funding'?'Officer / purpose':'Paid to / purpose'}}<input v-model="form.person" required maxlength="150"></label></div><label>Description (optional)<textarea v-model="form.remarks" rows="2" maxlength="2000"></textarea></label></template>
  </fieldset><div class="ac-form-actions"><button type="button" class="button" :disabled="busy" @click="modal=''">Cancel</button><button class="button primary" :disabled="busy"><span v-if="busy" class="ac-spinner"></span>{{busy?'Saving...':modal==='fund'?'Assign fund':'Save non-posted voucher'}}</button></div></form>
 </AdminDialog>

 <AccountingDocument v-if="journal" :title="journal.number" :busy="busy" @close="journal=null">
  <p v-if="formError" class="notice admin-error" role="alert">{{formError}}</p><p class="ac-help"><strong>{{journal.imprest?.type==='funding'?'Imprest Advance Voucher':'Petty Cash Payment Voucher'}}</strong> · {{journal.imprest?.void?'Cancelled':journal.status==='draft'?'Non-posted - no ledger effect':journal.status}}<br>{{journal.posting_date}} · {{journal.voucher_person}}</p><p>{{journal.remarks}}</p>
  <div class="ac-table-wrap"><table class="ac-finance-table"><thead><tr><th>Ledger account</th><th class="numeric">Debit</th><th class="numeric">Credit</th></tr></thead><tbody><tr v-for="line in journal.lines" :key="line.id"><td>{{line.account_code}} - {{line.account_name}}</td><td class="numeric">{{money(line.debit)}}</td><td class="numeric">{{money(line.credit)}}</td></tr></tbody><tfoot><tr><th>Total ({{book?.currency}})</th><th class="numeric">{{money(journal.total)}}</th><th class="numeric">{{money(journal.total)}}</th></tr></tfoot></table></div>
  <div class="ac-form-actions"><a v-if="!journal.imprest?.void&&can((journal.kind==='general'?'journals':'cash_payments')+'.print')" class="button" :href="printable" target="_blank" rel="noopener">Print voucher</a><button v-if="canPost" class="button primary" @click="confirmAction='post'">Review posting</button><button v-if="journal.status==='draft'&&!journal.imprest?.void&&(data.can_create||data.can_manage)&&(!journal.approval||['returned','withdrawn'].includes(journal.approval.stage))" class="button" @click="confirmAction='cancel'">Cancel draft</button></div>
  <button v-if="journal.status==='posted'&&can((journal.kind==='general'?'journals':'cash_payments')+'.reverse')" class="button" :disabled="busy" @click="confirmAction='reverse';reason='';reverseDate=today()">Reverse posted voucher</button>
  <AccountingApprovalPanel v-if="journal.status==='draft'&&!journal.imprest?.void" :company="company" type="journal" :document-id="journal.id" :version="journal.version" :csrf="csrf" @notice="emit('notice',$event)" @changed="refreshed"/>
 </AccountingDocument>
 <AdminDialog v-if="confirmAction" :title="confirmAction==='post'?'Post saved imprest voucher':confirmAction==='reverse'?'Reverse posted voucher':'Cancel non-posted voucher'" :busy="busy" @close="confirmAction=''"><p v-if="formError" class="notice admin-error" role="alert">{{formError}}</p><template v-if="confirmAction==='reverse'"><p>The original voucher remains in history. An opposite posted entry will reverse it; allocated funds cannot be taken away if already spent or reserved.</p><label>Reversal date<input v-model="reverseDate" type="date" :min="journal.posting_date" :max="today()" required></label><label>Reason<textarea v-model="reason" minlength="5" maxlength="2000" required></textarea></label></template><p v-else>{{confirmAction==='post'?'Post '+journal.number+' to the ledger? Officer balances and company reports will update.':'Cancel '+journal.number+'? Its pending reservation will be released and the draft cannot be posted.'}}</p><div class="ac-form-actions"><button class="button" :disabled="busy" @click="confirmAction=''">Back</button><button class="button primary" :disabled="busy||(confirmAction==='reverse'&&reason.trim().length<5)" @click="action">{{busy?'Processing...':'Confirm'}}</button></div></AdminDialog>
</section>
</template>

<style scoped>
.imprest-fund-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,340px),1fr));gap:18px}.imprest-fund{border:1px solid var(--line);border-radius:14px;background:var(--rail);padding:20px;min-width:0}.imprest-fund-heading{display:flex;gap:12px;align-items:flex-start}.imprest-fund-heading>div{flex:1;min-width:0}.imprest-fund h3{font-size:16px;margin:0 0 5px;overflow-wrap:anywhere}.imprest-fund p{margin:0;font-size:13px}.imprest-fund small{font-size:11px;color:var(--muted);overflow-wrap:anywhere}.imprest-icon{display:grid;place-items:center;width:38px;height:38px;border-radius:10px;background:color-mix(in srgb,#7665ed 12%,var(--bg));color:#7665ed;flex-shrink:0}.imprest-icon svg{width:24px;height:24px}.imprest-balances{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin:20px 0}.imprest-balances dt{font-size:12px;color:var(--muted);margin-bottom:5px}.imprest-balances dd{margin:0;font-size:18px;font-weight:650;font-variant-numeric:tabular-nums}.imprest-available{grid-column:1/-1;padding-top:15px;border-top:1px solid var(--line)}.imprest-available dd{font-size:26px;color:#7665ed}.imprest-actions{display:flex;gap:9px;flex-wrap:wrap}.imprest-history{margin-top:30px}.imprest-history h3{margin-bottom:5px}.imprest-row-name{display:block;color:var(--muted);margin-top:5px;font-size:11px}.imprest-form{border:0;padding:0;margin:0;display:grid;gap:16px;min-width:0}.imprest-form label{display:grid;gap:7px}.imprest-form small{color:var(--muted);line-height:1.6}.imprest-restriction{border:1px solid var(--line);border-left:3px solid #7665ed;background:var(--rail);border-radius:8px;padding:14px;font-size:13px;margin:16px 0}
</style>
