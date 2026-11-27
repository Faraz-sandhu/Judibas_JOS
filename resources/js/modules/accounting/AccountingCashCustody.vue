<script setup lang="ts">
import {computed,onBeforeUnmount,ref,watch} from 'vue';
import AccountingSelect from './AccountingSelect.vue';
import AccountingAdminRecordActions from './AccountingAdminRecordActions.vue';
import AccountingDocument from './AccountingDocument.vue';
import AccountingPaymentProofs from './AccountingPaymentProofs.vue';
import AccountingApprovalPanel from './AccountingApprovalPanel.vue';
import AdminDialog from '../../AdminDialog.vue';
import {useAccountingSuperAdmin} from './permissions';
import {useAccountingLiveRefresh} from './workspaceRealtime';
import {useAccountingAlerts} from './useAccountingAlerts';
import {needsApproval} from './approvalPolicy';
import {requestKey} from './requestKey';

const props=defineProps<{company:number;book:any;accounts:any[];csrf?:string}>();
const emit=defineEmits<{notice:[message:string];changed:[]}>();
const superAdmin=useAccountingSuperAdmin();
const activityCustodian=ref(''),activityPage=ref(1);
const data=ref<any>(null),page=ref(1),loading=ref(false),busy=ref(false),error=ref(''),formError=ref(''),modal=ref(''),form=ref<any>({}),journal=ref<any>(null),proofReady=ref(false),receiving=ref<any>(null),receiptNote=ref('');
let revision=0;onBeforeUnmount(()=>revision++);
const money=(value:any)=>Number(value||0).toLocaleString('en-PK',{minimumFractionDigits:2,maximumFractionDigits:2});
const parents=computed(()=>props.accounts.filter(a=>a.active&&!a.archived&&a.is_group&&a.root_type==='Asset'));
const headAccounts=computed(()=>props.accounts.filter(a=>a.active&&!a.archived&&!a.is_group&&a.account_type==='Cash'&&(!a.currency||a.currency===props.book?.currency)&&!a.imprest_fund&&!data.value?.custodians.some((c:any)=>c.account_id===a.id)));
const currentCustodian=computed(()=>data.value?.custodians.find((c:any)=>String(c.id)===String(form.value.custodian_id)));
const currentTransfer=computed(()=>data.value?.transfers.data.find((t:any)=>t.journal_id===journal.value?.id));
async function api(path:string,body?:any){const r=await fetch('/accounting/api/'+path,{method:body?'POST':'GET',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':props.csrf||''},body:body?JSON.stringify(body):undefined});const d=await r.json();if(!r.ok)throw Error(Object.values(d.errors||{}).flat().join(' ')||d.message||'Unable to complete this action.');return d;}
async function load(){const generation=++revision;loading.value=true;try{const d=await api('cash-custody?company_id='+props.company+'&page='+page.value+'&activity_page='+activityPage.value+(activityCustodian.value?'&custodian_id='+activityCustodian.value:''));if(generation===revision)data.value=d;}catch(e){if(generation===revision)error.value=(e as Error).message;}finally{if(generation===revision)loading.value=false;}}
watch(()=>props.company,()=>{journal.value=null;modal.value='';page.value=1;activityPage.value=1;activityCustodian.value='';void load();},{immediate:true});
watch(()=>form.value.custodian_id,()=>{if(form.value.direction==='issue')form.value.receiver_id=currentCustodian.value?.user_id||'';});
function open(type:string,custodian?:any){modal.value=type;formError.value='';form.value={user_id:'',name:'',parent_id:parents.value.find(a=>a.name==='Current Assets')?.id||'',custodian_id:custodian?.id||'',direction:type==='return'?'return':'issue',receiver_id:type==='issue'?custodian?.user_id||'':'',head_account_id:headAccounts.value[0]?.id||'',amount:'',posting_date:new Date().toLocaleDateString('en-CA'),remarks:'',request_key:requestKey()};}
async function save(){if(busy.value)return;busy.value=true;try{const d=await api(modal.value==='assign'?'cash-custody/custodians':'cash-custody',{...form.value,company_id:props.company});modal.value='';page.value=1;emit('notice',d.message);await load();emit('changed');if(d.journal){proofReady.value=false;journal.value=d.journal;}}catch(e){formError.value=(e as Error).message;}finally{busy.value=false;}}
async function view(id:number){try{proofReady.value=false;journal.value=(await api('journals?company_id='+props.company+'&id='+id)).journal;}catch(e){error.value=(e as Error).message;}}
async function refresh(){await load();if(journal.value)await view(journal.value.id);emit('changed');}
async function receive(){if(busy.value)return;busy.value=true;try{const d=await api('cash-custody/'+receiving.value.journal_id+'/receive',{company_id:props.company,version:receiving.value.version,note:receiptNote.value});receiving.value=null;receiptNote.value='';emit('notice',d.message);await refresh();}catch(e){formError.value=(e as Error).message;}finally{busy.value=false;}}
async function post(){if(busy.value)return;busy.value=true;try{const d=await api('journals/'+journal.value.id+'/post',{company_id:props.company,version:journal.value.version});emit('notice',d.message);await refresh();}catch(e){error.value=(e as Error).message;}finally{busy.value=false;}}
useAccountingLiveRefresh(()=>refresh(),()=>!modal.value&&!receiving.value&&!busy.value&&!journal.value);
useAccountingAlerts(error,formError);
</script>

<template>
<section class="custody-workspace">
 <template v-if="!journal">
  <div class="ac-section-heading"><div><h2>Cash Custody Vouchers</h2><p class="muted">Cash issued, collected and handed back to Head Office.</p></div><button v-if="data?.can_manage" class="button primary" :disabled="loading||!book" @click="open('assign')">+ Assign cash custodian</button></div>
  <p class="ac-help">Client collections: use the custodian's Cash account on the existing receipt or invoice payment. Cash payments use that same account. Custody vouchers transfer cash only; they do not create income or expenses.</p>
  <p v-if="loading&&!data" role="status">Loading cash custody...</p>
  <div class="custody-grid">
   <article v-for="c in data?.custodians||[]" :key="c.id" class="custody-card"><span class="eyebrow">CASH CUSTODIAN</span><h3>{{c.name}}</h3><p class="muted">{{c.code}} · {{c.account_name}}</p><small>Cash held · {{book?.currency}} · posted through today</small><strong class="custody-balance">{{money(c.balance)}}</strong><div class="custody-actions"><button v-if="data.can_create" class="button" :disabled="!c.active||busy" @click="open('issue',c)">Issue cash</button><button v-if="data.can_create" class="button primary" :disabled="!c.active||Number(c.balance)<=0||busy" @click="open('return',c)">Hand over cash</button></div></article>
   <p v-if="data&&!data.custodians.length" class="ac-empty">No cash custodians assigned yet. Assign a person to create their dedicated Cash account.</p>
  </div>
  <div class="ac-section-heading"><div><h3>Custody transfers</h3><p class="muted">Receipt confirmation is separate from ledger posting. Drafts do not reserve cash.</p></div><button class="button" :disabled="loading" @click="load">Refresh</button></div>
  <div class="ac-table-wrap"><table class="ac-finance-table"><thead><tr><th>Voucher / date</th><th>Cash movement</th><th>Receiver</th><th>Status</th><th class="numeric">Amount ({{book?.currency}})</th><th>Actions</th></tr></thead><tbody><tr v-for="t in data?.transfers.data||[]" :key="t.id"><td><strong>{{t.number}}</strong><small class="custody-block">{{t.posting_date}}</small></td><td>{{t.source_name}}<span class="custody-block">→ {{t.destination_name}}</span></td><td>{{t.receiver_name}}<small class="custody-block">{{t.received_at?'Receipt confirmed':'Awaiting cash receipt'}}</small></td><td><span class="ac-badge" :class="t.status==='posted'?'active':'inactive'">{{t.status==='draft'?'Non-posted':t.status}}</span></td><td class="numeric">{{money(t.amount)}}</td><td><div class="custody-actions"><button class="button" @click="view(t.journal_id)">View</button><button v-if="t.can_receive" class="button" @click="receiving=t;receiptNote=''">Confirm receipt</button></div></td></tr><tr v-if="data&&!data.transfers.data.length"><td colspan="6">No custody transfers recorded.</td></tr></tbody></table></div>
  <div v-if="data?.transfers" class="ac-pagination"><span>Page {{page}} of {{data.transfers.last_page}} · {{data.transfers.total}} vouchers</span><button class="button" :disabled="page<=1||loading" @click="page--;load()">Previous</button><button class="button" :disabled="page>=data.transfers.last_page||loading" @click="page++;load()">Next</button></div>
  <template v-if="data?.custodians.length">
   <div class="ac-section-heading"><div><h3>Custodian cash activity</h3><p class="muted">Existing posted receipts, payments and transfers. Reversed entries and their offsets remain visible for audit.</p></div><label>Cash custodian<AccountingSelect><select v-model="activityCustodian" @change="activityPage=1;load()"><option value="">{{data.custodians[0]?.name}}</option><option v-for="c in data.custodians.slice(1)" :key="c.id" :value="c.id">{{c.name}}</option></select></AccountingSelect></label></div>
   <div class="ac-table-wrap"><table class="ac-finance-table"><thead><tr><th>Date</th><th>Voucher</th><th>Note</th><th class="numeric">Cash in</th><th class="numeric">Cash out</th></tr></thead><tbody><tr v-for="l in data.activity.data" :key="l.id"><td>{{l.posting_date}}</td><td>{{l.number}}<small v-if="l.status==='reversed'" class="custody-block">Reversed</small></td><td>{{l.remarks||'?'}}</td><td class="numeric">{{Number(l.cash_in)?money(l.cash_in):'?'}}</td><td class="numeric">{{Number(l.cash_out)?money(l.cash_out):'?'}}</td></tr><tr v-if="!data.activity.data.length"><td colspan="5">No posted cash movements for this custodian.</td></tr></tbody></table></div>
   <div class="ac-pagination"><span>Page {{activityPage}} of {{data.activity.last_page}} ? {{data.activity.total}} movements</span><button class="button" :disabled="activityPage<=1||loading" @click="activityPage--;load()">Previous</button><button class="button" :disabled="activityPage>=data.activity.last_page||loading" @click="activityPage++;load()">Next</button></div>
  </template>
 </template>
 <AccountingDocument v-else :title="journal.number+' · Cash Custody Voucher'" :busy="busy" @close="journal=null">
  <p class="ac-help">{{journal.status==='draft'?'Non-posted: no ledger effect until Super Admin posting.':journal.status==='posted'?'Posted cash transfer.':'Reversed cash transfer.'}} {{currentTransfer?.received_at?'Cash receipt confirmed.':'Receiver confirmation is required before posting.'}}</p>
  <div class="ac-table-wrap"><table class="ac-finance-table"><thead><tr><th>Cash account</th><th class="numeric">Debit</th><th class="numeric">Credit</th></tr></thead><tbody><tr v-for="l in journal.lines" :key="l.id"><td>{{l.account_code}} · {{l.account_name}}</td><td class="numeric">{{money(l.debit)}}</td><td class="numeric">{{money(l.credit)}}</td></tr></tbody></table></div>
  <p>{{journal.posting_date}} · {{journal.remarks}}</p>
  <p v-if="currentTransfer?.receipt_note" class="ac-help">Receiving note: {{currentTransfer.receipt_note}}</p>
  <AccountingAdminRecordActions :company="company" type="journal" :record="journal" :csrf="csrf" :allow-edit="false" @notice="emit('notice',$event)" @changed="journal=null;refresh()" />
  <AccountingPaymentProofs :company="company" :journal-id="journal.id" :version="journal.version" :csrf="csrf" @notice="emit('notice',$event)" @ready="proofReady=$event" />
  <div class="custody-actions"><a class="button" :href="'/accounting/vouchers/'+journal.id+'/print?company_id='+company" target="_blank" rel="noopener">Print custody voucher</a><button v-if="currentTransfer?.can_receive" class="button" @click="receiving=currentTransfer;receiptNote=''">Confirm cash receipt</button><button v-if="superAdmin&&journal.status==='draft'&&!needsApproval(book,'contra')" class="button primary" :disabled="busy||!proofReady||!currentTransfer?.received_at" @click="post">{{busy?'Posting...':'Post custody voucher'}}</button></div>
  <AccountingApprovalPanel :company="company" type="journal" :document-id="journal.id" :version="journal.version" :csrf="csrf" :payment-proof-ready="proofReady" @changed="refresh" @notice="emit('notice',$event)" />
 </AccountingDocument>
 <AdminDialog v-if="modal" :title="modal==='assign'?'Assign cash custodian':modal==='issue'?'Issue cash to custodian':'Hand over cash to Head Office'" :busy="busy" @close="!busy&&(modal='')">
  <form class="custody-form" @submit.prevent="save">
   <template v-if="modal==='assign'"><label>Person<AccountingSelect><select v-model="form.user_id" required><option value="">Select an Accounting user</option><option v-for="u in data?.users" :key="u.id" :value="u.id">{{u.name}}</option></select></AccountingSelect></label><label>Cash account name<input v-model="form.name" required maxlength="150" placeholder="Cash held by Ahmed"></label><label>Parent Asset group<AccountingSelect><select v-model="form.parent_id" required><option value="">Select a group</option><option v-for="a in parents" :key="a.id" :value="a.id">{{a.code}} · {{a.name}}</option></select></AccountingSelect></label></template>
   <template v-else><p class="ac-help">{{currentCustodian?.name}} · Posted cash held: {{book?.currency}} {{money(currentCustodian?.balance)}}</p><label>Head Office Cash account<AccountingSelect><select v-model="form.head_account_id" required><option value="">Select Cash account</option><option v-for="a in headAccounts" :key="a.id" :value="a.id">{{a.code}} · {{a.name}}</option></select></AccountingSelect></label><label>Receiver<AccountingSelect><select v-model="form.receiver_id" required :disabled="form.direction==='issue'"><option value="">Select receiver</option><option v-for="u in data?.users" :key="u.id" :value="u.id">{{u.name}}</option></select></AccountingSelect></label><div class="custody-fields"><label>Voucher date<input v-model="form.posting_date" type="date" required></label><label>Amount ({{book?.currency}})<input v-model="form.amount" type="number" min="0.01" step="0.01" required></label></div><label>Note<textarea v-model="form.remarks" maxlength="2000" rows="3" placeholder="Cash issued / end-of-day handover"></textarea></label></template>
   <div class="custody-actions"><button type="button" class="button" :disabled="busy" @click="modal=''">Cancel</button><button class="button primary" :disabled="busy">{{busy?'Saving...':modal==='assign'?'Create custody account':'Save non-posted voucher'}}</button></div>
  </form>
 </AdminDialog>
 <AdminDialog v-if="receiving" title="Confirm physical cash receipt" :busy="busy" @close="!busy&&(receiving=null)"><form class="custody-form" @submit.prevent="receive"><p>Confirm receipt of {{book?.currency}} {{money(receiving.amount)}} for {{receiving.number}}.</p><p class="ac-help">Only confirm after the cash has physically arrived. Super Admin may confirm on behalf of the designated receiver; this is recorded in audit history.</p><label>Receiving note<textarea v-model="receiptNote" required minlength="5" maxlength="500" rows="3" placeholder="Cash counted and received in full"></textarea></label><button class="button primary" :disabled="busy">{{busy?'Confirming...':'Confirm cash received'}}</button></form></AdminDialog>
</section>
</template>

<style scoped>
.custody-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,270px),1fr));gap:16px;margin:20px 0 28px}.custody-card{padding:22px;border:1px solid var(--border,#4444);border-radius:14px;background:var(--surface,#8881);min-width:0}.custody-card h3{margin:10px 0 6px}.custody-balance{display:block;font-size:28px;margin:10px 0 18px}.custody-actions{display:flex;flex-wrap:wrap;gap:8px;align-items:center}.custody-block{display:block;margin-top:5px;font-size:12px;opacity:.75}.custody-form{display:grid;gap:18px}.custody-form label{display:grid;gap:8px;min-width:0}.custody-fields{display:grid;grid-template-columns:1fr 1fr;gap:16px}.custody-form input,.custody-form textarea{width:100%;box-sizing:border-box}.custody-workspace .ac-section-heading{gap:16px;flex-wrap:wrap}@media(max-width:600px){.custody-fields{grid-template-columns:1fr}}
</style>
