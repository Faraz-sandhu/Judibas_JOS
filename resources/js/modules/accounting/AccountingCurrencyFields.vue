<script setup lang="ts">
import {ref,watch} from 'vue';
import AccountingSelect from './AccountingSelect.vue';
const props=defineProps<{model:any;company:number;base:string;date?:string;fixed?:boolean}>();
const codes=['PKR','USD','AED','GBP','EUR','SAR','CAD','AUD'];
const rates=ref<any[]>([]);
watch(()=>props.company,async()=>{rates.value=[];try{const r=await fetch('/accounting/api/currencies?company_id='+props.company,{headers:{Accept:'application/json'}});if(r.ok){rates.value=(await r.json()).rates;if(props.model.exchange_rate==='')changed();}}catch{}},{immediate:true});
watch(()=>props.date,()=>{if(!props.fixed)changed();});
function changed(){if(props.model.currency===props.base)props.model.exchange_rate='1.000000';else{const rate=rates.value.find(r=>r.currency===props.model.currency&&r.rate_date<=(props.date||'9999'));props.model.exchange_rate=rate?.rate||'';}}
</script>
<template><div class="ac-form-grid ac-currency-fields"><label>Transaction currency<AccountingSelect><select v-model="model.currency" :disabled="fixed" @change="changed"><option v-for="code in codes" :key="code">{{code}}</option></select></AccountingSelect></label><label>Exchange rate ({{base}} per 1 {{model.currency||base}})<input v-model="model.exchange_rate" inputmode="decimal" :readonly="model.currency===base" required placeholder="Enter transaction-date rate"><small>Saved with this document. Changing rates later does not change posted entries.</small></label></div></template>
<style scoped>.ac-currency-fields{padding:16px;border:1px solid var(--border);border-radius:10px;background:var(--surface);margin:16px 0;min-width:0}.ac-currency-fields input{min-width:0}</style>
