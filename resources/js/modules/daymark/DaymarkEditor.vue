<script setup lang="ts">
import {ref,onMounted,onBeforeUnmount,watch,computed} from 'vue';
// The same bundled Quill editor used by the original Daymark application.
import '../../vendor/quill/quill.js';
import '../../vendor/quill/quill.snow.css';
const Quill=(window as any).Quill;
const props=defineProps<{modelValue:string;label:string;readonly?:boolean}>();
const emit=defineEmits(['update:modelValue']);
const area=ref<HTMLElement|null>(null);
let editor:any=null;
let lastHtml='';
const activeFormats=ref<Record<string,any>>({});
const count=computed(()=>new DOMParser().parseFromString(props.modelValue||'','text/html').body.textContent?.length||0);
function update(){
 if(!editor)return;
 lastHtml=editor.getText().trim()?editor.getSemanticHTML():'';
 emit('update:modelValue',lastHtml);
 activeFormats.value=editor.getFormat();
}
function format(command:string,value?:string){
 if(props.readonly||!editor)return;
 editor.focus();
 const range=editor.getSelection(true);
 const current=editor.getFormat(range);
 if(command==='undo'||command==='redo')editor.history[command]();
 else if(command==='removeFormat')editor.removeFormat(range.index,range.length,'user');
 else {
  const name=command==='strikeThrough'?'strike':command==='insertUnorderedList'||command==='insertOrderedList'?'list':command==='formatBlock'?(value==='blockquote'?'blockquote':'header'):command;
  const next=name==='list'?(command==='insertUnorderedList'?'bullet':'ordered'):name==='header'?3:name==='link'?value:true;
  editor.history.cutoff();
  editor.format(name,current[name]===next?false:next,'user');
  editor.history.cutoff();
 }
 activeFormats.value=editor.getFormat();
}
function link(){
 const url=prompt('Link address (https:// or mailto:)');
 if(url&&/^(https?:\/\/|mailto:)/i.test(url))format('link',url);
}
function load(value:string){
 if(!editor)return;
 editor.clipboard.dangerouslyPasteHTML(value||'','silent');
 editor.history.clear();
 lastHtml=value;
}
onMounted(()=>{
 editor=new Quill(area.value,{theme:'snow',readOnly:!!props.readonly,formats:['header','bold','italic','underline','strike','list','blockquote','link'],modules:{toolbar:false,history:{userOnly:true}}});
 editor.root.setAttribute('role','textbox');
 editor.root.setAttribute('aria-label',props.label);
 editor.root.setAttribute('aria-multiline','true');
 editor.root.setAttribute('aria-readonly',String(!!props.readonly));
 load(props.modelValue);
 editor.on('text-change',update);
 editor.on('selection-change',(range:any)=>{if(range)activeFormats.value=editor.getFormat(range);});
});
watch(()=>props.modelValue,value=>{if(editor&&value!==lastHtml)load(value);});
watch(()=>props.readonly,value=>{editor?.enable(!value);editor?.root.setAttribute('aria-readonly',String(!!value));});
onBeforeUnmount(()=>{editor?.off('text-change',update);editor=null;});
</script>
<template>
<div class="dm-editor">
 <div v-if="!readonly" class="dm-editor-tools" role="toolbar" :aria-label="label+' formatting'">
  <button type="button" title="Undo" aria-label="Undo" @mousedown.prevent @click="format('undo')"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 5 4 10l5 5 M4 10h10a6 6 0 0 1 0 12"/></svg></button>
  <button type="button" title="Redo" aria-label="Redo" @mousedown.prevent @click="format('redo')"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 5 5 5-5 5 M20 10H10a6 6 0 0 0 0 12"/></svg></button>
  <button type="button" title="Insert link" aria-label="Insert link" @mousedown.prevent @click="link"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m10 14 4-4 M8 16l-1 1a4 4 0 0 1-6-6l4-4a4 4 0 0 1 6 0 M16 8l1-1a4 4 0 0 1 6 6l-4 4a4 4 0 0 1-6 0"/></svg></button>
  <button type="button" title="Bold" aria-label="Bold" @mousedown.prevent @click="format('bold')"><b>B</b></button>
  <button type="button" title="Italic" aria-label="Italic" @mousedown.prevent @click="format('italic')"><i>I</i></button>
  <button type="button" title="Underline" aria-label="Underline" @mousedown.prevent @click="format('underline')"><u>U</u></button>
  <button type="button" title="Strike through" aria-label="Strike through" @mousedown.prevent @click="format('strikeThrough')"><s>S</s></button>
  <button type="button" title="Bulleted list" :aria-pressed="activeFormats.list==='bullet'" aria-label="Bulleted list" @mousedown.prevent @click="format('insertUnorderedList')"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6h12 M9 12h12 M9 18h12 M3 6h1 M3 12h1 M3 18h1"/></svg></button>
  <button type="button" title="Numbered list" :aria-pressed="activeFormats.list==='ordered'" aria-label="Numbered list" @mousedown.prevent @click="format('insertOrderedList')">1. List</button>
  <button type="button" title="Heading" aria-label="Heading" @mousedown.prevent @click="format('formatBlock','h3')">Heading</button>
  <button type="button" title="Quote" aria-label="Quote" @mousedown.prevent @click="format('formatBlock','blockquote')">Quote</button>
  <button type="button" title="Clear formatting" aria-label="Clear formatting" @mousedown.prevent @click="format('removeFormat')">Clear</button>
 </div>
 <div ref="area" class="dm-editor-surface"></div>
 <small class="dm-editor-count" :class="{exceeded:count>10000}">{{count.toLocaleString()}} / 10,000 characters</small>
</div>
</template>
<style scoped>.dm-editor-count{display:block;padding:5px 12px;text-align:right;color:var(--muted)}.dm-editor-count.exceeded{color:#d75c5c}.dm-editor{border:1px solid var(--line);border-radius:9px;overflow:hidden}.dm-editor-tools{display:flex;flex-wrap:wrap;gap:3px;padding:7px;border-bottom:1px solid var(--line);background:var(--bg)}.dm-editor-tools button{background:transparent;border:0;border-radius:4px;color:var(--text);padding:5px 8px;cursor:pointer}.dm-editor-tools svg{width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}.dm-editor-tools button{min-width:30px;min-height:30px;display:inline-flex;align-items:center;justify-content:center}.dm-editor-tools button:focus-visible{outline:2px solid #7766ed;outline-offset:1px}.dm-editor-tools button:hover{background:var(--line)}.dm-editor :deep(.ql-editor){min-height:120px;padding:12px;outline:none;line-height:1.7;overflow-wrap:anywhere}.dm-editor :deep(.ql-editor:focus){box-shadow:inset 0 0 0 2px #7766ed}.dm-editor :deep(.ql-container.ql-snow){border:0;height:auto;font:inherit;color:var(--text)}.dm-editor :deep(.ql-editor){height:auto;max-height:320px;overflow-y:auto;white-space:pre-wrap}.dm-editor-tools button[aria-pressed="true"]{background:var(--line);color:#9b8aff}.dm-editor :deep(.ql-editor ol),.dm-editor :deep(.ql-editor ul){padding-left:1.5em}</style>
