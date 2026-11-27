<script setup lang="ts">
import {ref,computed} from 'vue';
import {useAccountingSuperAdmin} from './permissions';
import {useAccountingAlerts} from './useAccountingAlerts';
import AccountingSelect from './AccountingSelect.vue';
import AdminDialog from '../../AdminDialog.vue';
const props=defineProps<{company:number;record:any;accounts:any[];csrf?:string}>(),emit=defineEmits(['changed','notice']);
const superAdmin=useAccountingSuperAdmin(),editing=ref(false),busy=ref(false),form=ref<any>({}),error=ref('');useAccountingAlerts(error);
const parents=computed(()=>props.accounts.filter(a=>a.id!==props.record.id&&a.is_group&&a.root_type===props.record.root_type&&!a.archived));
function open(){form.value={...props.record,reason:''};error.value='';editing.value=true;}
async function save(){if(busy.value)return;busy.value=true;error.value='';try{
 const r=await fetch('/accounting/api/admin-accounts/'+props.record.id,{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':props.csrf||''},body:JSON.stringify({...form.value,company_id:props.company})});
 const d=await r.json();if(!r.ok)throw Error(Object.values(d.errors||{}).flat().join(' ')||d.message);editing.value=false;emit('notice',d.message);emit('changed');
}catch(e){error.value=(e as Error).message;}finally{busy.value=false;}}
</script>
<template><template v-if="superAdmin&&!record.archived"><button type="button" class="button" @click="open">Edit account</button><AdminDialog v-if="editing" title="Super Admin: edit account" :busy="busy" @close="!busy&&(editing=false)"><form @submit.prevent="save"><p class="ac-help">Update this account without changing posted amounts. The ledger/group kind, root classification and transaction currency stay attached to the existing history.</p><div class="ac-form-grid"><label>Account code<input v-model="form.code" required maxlength="255" :disabled="busy"></label><label>Account name<input v-model="form.name" required maxlength="150" :disabled="busy"></label><label v-if="!record.is_system">Parent group<AccountingSelect><select v-model.number="form.parent_id" required :disabled="busy"><option v-for="a in parents" :key="a.id" :value="a.id">{{a.code}} - {{a.name}}</option></select></AccountingSelect></label><label>Description<textarea v-model="form.description" maxlength="2000" rows="2" :disabled="busy"></textarea></label><label class="ac-check"><input type="checkbox" v-model="form.active" :disabled="busy">Active</label></div><label>Reason<textarea v-model="form.reason" required minlength="5" maxlength="2000" rows="2" :disabled="busy"></textarea></label><div class="ac-form-actions"><button class="button" type="button" :disabled="busy" @click="editing=false">Cancel</button><button class="button primary" :disabled="busy||form.reason.trim().length<5">{{busy?'Saving...':'Save account'}}</button></div></form></AdminDialog></template></template>
