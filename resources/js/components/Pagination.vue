<script setup lang="ts">
import {computed,watch} from 'vue';
const props=defineProps<{page:number;pageSize:number;total:number}>();
const emit=defineEmits<{'update:page':[number];'update:pageSize':[number]}>();
const lastPage=computed(()=>Math.max(1,Math.ceil(props.total/props.pageSize)));
const pages=computed(()=>Array.from({length:Math.min(5,lastPage.value)},(_,index)=>Math.max(1,Math.min(props.page-2,lastPage.value-4))+index));
watch(lastPage,value=>{if(props.page>value)emit('update:page',value)});
</script>
<template>
  <footer class="cv-pagination">
    <div class="cv-pagination-summary"><label>Show <select :value="pageSize" @change="emit('update:pageSize',Number(($event.target as HTMLSelectElement).value));emit('update:page',1)"><option :value="6">6</option><option :value="12">12</option><option :value="24">24</option></select> per page</label><span aria-live="polite">{{total?(page-1)*pageSize+1:0}}–{{Math.min(page*pageSize,total)}} of {{total}}</span></div>
    <nav aria-label="Pagination"><button :disabled="page===1" @click="emit('update:page',1)" aria-label="First page">«</button><button :disabled="page===1" @click="emit('update:page',page-1)">Previous</button><button v-for="number in pages" :key="number" :class="{active:page===number}" :aria-current="page===number?'page':undefined" :aria-label="'Page '+number" @click="emit('update:page',number)">{{number}}</button><button :disabled="page===lastPage" @click="emit('update:page',page+1)">Next</button><button :disabled="page===lastPage" @click="emit('update:page',lastPage)" aria-label="Last page">»</button></nav>
  </footer>
</template>
<style scoped>
.cv-pagination{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;padding:20px 0;color:var(--muted);font-size:12px}.cv-pagination-summary{display:flex;align-items:center;gap:18px;flex-wrap:wrap}.cv-pagination-summary label{display:flex;align-items:center;gap:7px}.cv-pagination select{background:var(--bg);color:var(--text);border:1px solid var(--line);border-radius:7px;padding:8px;font:inherit}.cv-pagination nav{display:flex;gap:5px;flex-wrap:wrap}.cv-pagination button{background:var(--rail);color:var(--text);border:1px solid var(--line);border-radius:7px;min-width:32px;padding:8px 10px;font:inherit;cursor:pointer}.cv-pagination button.active{color:white;background:#6557ed;border-color:#6557ed}.cv-pagination button:disabled{opacity:.4;cursor:default}@media(max-width:550px){.cv-pagination{align-items:flex-start;flex-direction:column}.cv-pagination nav{max-width:100%}}
</style>
