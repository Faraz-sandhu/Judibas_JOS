<script setup lang="ts">
import {computed,ref,watch} from 'vue';
import AdminDialog from './AdminDialog.vue';
type Company={id:number;name:string};
const props=defineProps<{data:Record<string,any>;csrf?:string}>();
const emit=defineEmits(['changed']);
const search=ref(''),page=ref(1),pageSize=ref(10),editing=ref<{id?:number;name:string}|null>(null),busy=ref(false),error=ref(''),message=ref('');
const rows=computed(()=>((props.data.companies||[]) as Company[]).filter(c=>c.name.toLocaleLowerCase().includes(search.value.trim().toLocaleLowerCase())));
const pages=computed(()=>Math.max(1,Math.ceil(rows.value.length/pageSize.value)));
const visible=computed(()=>rows.value.slice((page.value-1)*pageSize.value,page.value*pageSize.value));
watch([search,pageSize],()=>page.value=1);watch(pages,n=>page.value=Math.min(page.value,n));
function employees(id:number){return (props.data.users||[]).filter((u:any)=>(u.company_ids?.length?u.company_ids:[u.company_id]).includes(id));}
function open(company?:Company){error.value='';editing.value=company?{...company}:{name:''};}
async function save(){if(!editing.value||busy.value)return;busy.value=true;error.value='';try{
 const response=await fetch('/communication/api/companies'+(editing.value.id?'/'+editing.value.id:''),{method:'POST',headers:{Accept:'application/json','Content-Type':'application/json','X-CSRF-TOKEN':props.csrf||''},body:JSON.stringify({name:editing.value.name.trim()})});
 const result=await response.json();if(!response.ok)throw new Error(result.errors?Object.values(result.errors).flat().join(' '):result.message||'Unable to save company.');
 message.value=editing.value.id?'Company name updated.':'Company created.';editing.value=null;emit('changed');
}catch(e){error.value=(e as Error).message;}finally{busy.value=false;}}
</script>
<template>
<section class="admin-company-directory">
 <div class="admin-section-heading"><div><p class="admin-kicker">PEOPLE &amp; ACCESS</p><h2>Companies</h2><p class="muted">One shared directory for company access across your Judibas products.</p></div><button class="button primary" @click="open()"><span aria-hidden="true">+</span> Create company</button></div>
 <p v-if="message" class="notice" role="status">{{message}}</p>
 <div class="admin-directory-summary"><div><span class="admin-summary-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M5 21V3h14v18M3 21h18M9 7h2m2 0h2m-6 4h2m2 0h2M9 21v-6h6v6"/></svg></span><div><strong>{{data.companies.length}}</strong><span>Companies</span></div></div><div><strong>{{(data.users||[]).filter((u:any)=>u.company_ids?.length||u.company_id).length}}</strong><span>Employees with company access</span></div><p>Each company keeps its own workspace and employee assignments.</p></div>
 <div class="admin-table-card"><div class="admin-filterbar"><label class="admin-search-field">Find a company<input v-model="search" type="search" placeholder="Search by company name..."></label><label>Rows per page<select v-model.number="pageSize"><option :value="10">10</option><option :value="20">20</option><option :value="50">50</option></select></label></div>
 <div class="admin-table-scroll"><table class="admin-company-table"><thead><tr><th>Company</th><th>Assigned employees</th><th>Active employees</th><th>Actions</th></tr></thead><tbody><tr v-for="company in visible" :key="company.id"><td><div class="admin-person"><span class="admin-avatar">{{company.name.slice(0,2).toUpperCase()}}</span><strong>{{company.name}}</strong></div></td><td>{{employees(company.id).length}}</td><td><span class="admin-status is-active">{{employees(company.id).filter((u:any)=>u.is_active).length}} active</span></td><td><button class="button" :aria-label="'Rename '+company.name" @click="open(company)">Rename</button></td></tr><tr v-if="!rows.length"><td colspan="4" class="empty-state"><strong>{{search?'No matching companies':'Your company directory is empty'}}</strong><p>{{search?'Try a different company name.':'Create a company to start assigning employee access.'}}</p><button v-if="search" class="button" @click="search=''">Clear search</button></td></tr></tbody></table></div>
 <div class="admin-pagination"><span>{{rows.length?((page-1)*pageSize+1):0}}&ndash;{{Math.min(page*pageSize,rows.length)}} of {{rows.length}} companies</span><div><button class="button" :disabled="page<=1" @click="page--">Previous</button><span>Page {{page}} of {{pages}}</span><button class="button" :disabled="page>=pages" @click="page++">Next</button></div></div></div>
 <AdminDialog v-if="editing" :title="editing.id?'Rename company':'Create company'" subtitle="This company name is shared across your Judibas products." dialog-class="admin-company-dialog" :busy="busy" @close="editing=null"><form class="admin-company-form" @submit.prevent="save"><label>Company name<input v-model="editing.name" required maxlength="100" autofocus autocomplete="organization" placeholder="e.g. Skyinfinit" :disabled="busy"></label><p class="muted">{{editing.id?'Existing records and employee assignments will stay with this company.':'You can assign employees to this company from Users or Invitations.'}}</p><p v-if="error" class="notice admin-error" role="alert">{{error}}</p><div class="editor-actions"><button type="button" class="button" :disabled="busy" @click="editing=null">Cancel</button><button class="button primary" :disabled="busy||!editing.name.trim()"><span v-if="busy" class="admin-saving-spinner" aria-hidden="true"></span>{{busy?'Saving...':editing.id?'Save name':'Create company'}}</button></div></form></AdminDialog>
</section>
</template>
