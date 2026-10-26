<script setup lang="ts">
defineProps<{title:string;busy?:boolean}>();
defineEmits(['close']);
</script>
<template><section class="ac-document ac-dialog" :aria-label="title"><header class="ac-document-heading"><div><span class="eyebrow">ACCOUNTING DOCUMENT</span><h2>{{title}}</h2></div><button type="button" class="button" :disabled="busy" aria-label="Close document" @click="$emit('close')">&larr; Back to records</button></header><div class="ac-document-body"><slot/></div></section></template>
