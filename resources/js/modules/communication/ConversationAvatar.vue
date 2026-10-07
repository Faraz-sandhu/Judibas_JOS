<script setup lang="ts">
import {computed} from 'vue';
const props=defineProps<{kind:string;name:string;members:{id:number;name:string}[];viewer?:number|null;review?:boolean;avatar?:(id:number|null|undefined)=>string|null;large?:boolean}>();
const people=computed(()=>props.kind==='direct'?(props.review?props.members.slice(0,2):props.members.filter(p=>p.id!==props.viewer).slice(0,1)):[]);
</script>
<template>
<span class="conversation-avatar" :class="{paired:people.length>1,large}" :aria-label="people.length?people.map(p=>p.name).join(' and '):name" role="img">
<span v-for="person in people" :key="person.id" class="participant-avatar" :title="person.name"><img v-if="avatar?.(person.id)" :src="avatar(person.id)!" alt=""><span v-else>{{person.name.slice(0,1).toUpperCase()}}</span></span>
<svg v-if="!people.length" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2M16 5a3 3 0 0 1 0 6m2 3a5 5 0 0 1 3 4v2"/></svg>
</span>
</template>
<style scoped>
.conversation-avatar{position:relative;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;width:42px;height:42px;border-radius:50%;background:var(--comm-avatar-background,#18312b);color:#12a88a;overflow:hidden}.participant-avatar{display:flex;align-items:center;justify-content:center;width:100%;height:100%;border-radius:50%;background:#18312b;overflow:hidden}.participant-avatar img{width:100%;height:100%;object-fit:cover}.paired{background:transparent;overflow:visible}.paired .participant-avatar{position:absolute;width:29px;height:29px;border:2px solid var(--comm-panel,#202020);font-size:13px}.paired .participant-avatar:first-child{top:0;left:0}.paired .participant-avatar:last-child{bottom:0;right:0}.conversation-avatar svg{width:25px;height:25px}.large{width:100px;height:100px;font-size:30px}.large.paired .participant-avatar{width:68px;height:68px;font-size:25px;border-width:3px}.large svg{width:52px;height:52px}
</style>
