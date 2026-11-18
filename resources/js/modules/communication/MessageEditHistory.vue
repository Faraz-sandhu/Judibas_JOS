<script setup lang="ts">
import {parseChatDate} from './chatTime';
export type MessageEdit={id:number;before:string;after:string;editor_name:string;edited_at:string};
defineProps<{entries:MessageEdit[]}>();
const date=(value:string)=>parseChatDate(value).toLocaleString();
</script>
<template>
<details class="comm-edit-history">
 <summary>Edit history <span>{{entries.length}}</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary>
 <div class="comm-edit-versions"><article v-for="(edit,index) in entries" :key="edit.id"><header><strong>Edit {{index+1}}</strong><time :datetime="edit.edited_at">{{date(edit.edited_at)}}</time></header><small>{{edit.editor_name}}</small><dl><dt>Before</dt><dd class="comm-edit-before">{{edit.before||'(No text)'}}</dd><dt>After</dt><dd class="comm-edit-after">{{edit.after}}</dd></dl></article></div>
</details>
</template>
<style scoped>
.comm-edit-history{border-top:1px solid var(--line);margin-top:9px;padding-top:8px;min-width:0}.comm-edit-history summary{display:flex;align-items:center;gap:7px;list-style:none;cursor:pointer;font-size:11px;font-weight:600;color:var(--muted);user-select:none}.comm-edit-history summary::-webkit-details-marker{display:none}.comm-edit-history summary>span{padding:1px 6px;border-radius:10px;background:var(--rail);font-size:10px}.comm-edit-history summary>svg{width:14px;height:14px;margin-left:auto;transition:transform .15s ease}.comm-edit-history[open] summary>svg{transform:rotate(180deg)}.comm-edit-versions{max-height:300px;overflow:auto;overscroll-behavior:contain;scrollbar-width:thin;margin-top:10px}.comm-edit-versions article{padding:10px;border:1px solid var(--line);border-radius:8px;background:var(--bg);margin-top:8px}.comm-edit-versions header{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;font-size:11px}.comm-edit-versions time,.comm-edit-versions small{font-size:10px;color:var(--muted)}.comm-edit-versions dl{margin:10px 0 0}.comm-edit-versions dt{font-size:10px;font-weight:650;color:var(--muted);margin:8px 0 3px}.comm-edit-versions dd{margin:0;font-size:12px;line-height:1.5;white-space:pre-wrap;overflow-wrap:anywhere}.comm-edit-before{border-left:2px solid #da9c5d;padding-left:8px}.comm-edit-after{border-left:2px solid #2aa58b;padding-left:8px}@media(prefers-reduced-motion:reduce){.comm-edit-history summary>svg{transition:none}}
</style>
