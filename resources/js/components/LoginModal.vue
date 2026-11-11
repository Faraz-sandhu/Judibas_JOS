<script setup lang="ts">
import {onMounted,onBeforeUnmount,ref} from 'vue';
import LoginPanel from './LoginPanel.vue';
defineProps<{name:string;csrf?:string;destination:string}>();const emit=defineEmits<{close:[]}>();
const dialog=ref<HTMLDialogElement|null>(null),busy=ref(false);let previousOverflow='';
function close(){if(!busy.value){dialog.value?.close();emit('close');}}
onMounted(()=>{previousOverflow=document.body.style.overflow;document.body.style.overflow='hidden';dialog.value?.showModal();});
onBeforeUnmount(()=>{document.body.style.overflow=previousOverflow;dialog.value?.close();});
</script>
<template><dialog ref="dialog" class="judibas-login-dialog" aria-labelledby="judibas-login-title" @cancel.prevent="close" @click="event=>{if(event.target===dialog)close();}"><LoginPanel :name="name" :csrf="csrf" :destination="destination" modal @close="close" @busy="busy=$event"/></dialog></template>
<style scoped>.judibas-login-dialog{width:min(420px,calc(100vw - 32px));max-width:none;max-height:calc(100dvh - 40px);overflow-y:auto;padding:0;margin:auto;border:1px solid var(--line,#eeedf4);border-radius:20px;background:var(--bg,#fff);color:var(--text,#25252c);box-shadow:0 28px 90px #20203838}.judibas-login-dialog::backdrop{background:rgba(30,31,48,.32);backdrop-filter:blur(4px)}@media(max-height:600px){.judibas-login-dialog{max-height:calc(100dvh - 20px)}}</style>
