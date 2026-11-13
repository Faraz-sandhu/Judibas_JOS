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
<template>
  <section class="ac-currency-fields" aria-label="Document currency">
    <header class="ac-currency-heading">
      <span class="ac-currency-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="3"/><circle cx="12" cy="12" r="3"/><path d="M6 9v6m12-6v6"/></svg>Currency &amp; exchange rate</span>
      <span class="ac-currency-base">Base currency <strong>{{base}}</strong></span>
    </header>
    <div class="ac-currency-controls">
      <label class="ac-currency-field"><span>Transaction currency</span><AccountingSelect><select v-model="model.currency" :disabled="fixed" @change="changed"><option v-for="code in codes" :key="code">{{code}}</option></select></AccountingSelect></label>
      <label class="ac-currency-field"><span>Exchange rate <span class="ac-currency-unit">{{base}} per 1 {{model.currency||base}}</span></span><input v-model="model.exchange_rate" inputmode="decimal" :readonly="model.currency===base" :aria-label="'Exchange rate ('+base+' per 1 '+(model.currency||base)+')'" required placeholder="Enter transaction-date rate"></label>
    </div>
    <p class="ac-currency-note"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="10" cy="10" r="7"/><path d="M10 9v5m0-8v1"/></svg><span>{{model.currency===base?'The base currency uses a fixed exchange rate of 1.':'Use the rate for this transaction date.'}} Saved rates stay with this document.</span></p>
  </section>
</template>
<style scoped>
.ac-currency-fields{box-sizing:border-box;grid-column:1/-1;min-width:0;margin:18px 0;padding:18px 20px;border:1px solid var(--line);border-radius:10px;background:var(--bg);color:var(--text)}
.ac-currency-heading{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:0 0 16px;padding:0;border:0;flex-wrap:wrap}
.ac-currency-title{display:inline-flex;align-items:center;gap:8px;font-size:12px;font-weight:600;line-height:1.5}.ac-currency-title svg{width:18px;height:18px;color:var(--ac-accent,#7665ed);flex:none}
.ac-currency-base{display:inline-flex;align-items:center;gap:7px;padding:4px 8px;border:1px solid var(--line);border-radius:6px;font-size:10px;line-height:1.5;color:var(--muted);background:var(--rail)}.ac-currency-base strong{color:var(--text);font-size:11px;font-weight:600}
.ac-currency-controls{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px 24px;align-items:start}
.ac-currency-controls .ac-currency-field{display:flex;flex-direction:column;gap:8px;min-width:0;margin:0;font-size:12px;line-height:1.5;color:var(--text)}
.ac-currency-field>span{display:flex;align-items:baseline;justify-content:space-between;gap:8px;min-height:18px;flex-wrap:wrap}.ac-currency-unit{color:var(--muted);font-size:10px;font-weight:400}
.ac-currency-controls input{box-sizing:border-box;display:block;width:100%;min-width:0;height:42px;margin:0;font-variant-numeric:tabular-nums;background:var(--rail)}
.ac-currency-controls :deep(.ac-select>button){box-sizing:border-box;height:42px;min-height:42px;margin:0;background:var(--rail)}
.ac-currency-controls input[readonly]{color:var(--muted);background:var(--bg)}
.ac-currency-controls input:focus-visible{outline:2px solid var(--ac-accent,#7665ed);outline-offset:2px}
.ac-currency-note{display:flex;align-items:flex-start;gap:7px;margin:13px 0 0;padding:0;color:var(--muted);font-size:11px;line-height:1.6}.ac-currency-note svg{width:14px;height:14px;flex:none;margin-top:2px}
@media(max-width:650px){.ac-currency-fields{padding:15px;margin:15px 0}.ac-currency-controls{grid-template-columns:minmax(0,1fr);gap:14px}.ac-currency-heading{margin-bottom:14px}.ac-currency-field>span{justify-content:flex-start}}
</style>
