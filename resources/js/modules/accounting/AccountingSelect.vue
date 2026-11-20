<script setup lang="ts">
import {computed,nextTick,onBeforeUnmount,onMounted,onUpdated,ref,watch,getCurrentInstance} from 'vue';
const props=defineProps<{matchTriggerWidth?:boolean}>();
const listId='ac-options-'+getCurrentInstance()!.uid;
const host=ref<HTMLElement>(),trigger=ref<HTMLButtonElement>(),panel=ref<HTMLElement>(),search=ref<HTMLInputElement>();
const target=ref<HTMLElement|string>('body');
const open=ref(false),query=ref(''),label=ref('Select an option'),name=ref('Select an option'),disabled=ref(false),active=ref(0),position=ref({top:'0px',left:'0px',width:'280px',maxHeight:'320px'});
type Option={value:string;label:string;disabled:boolean;selected:boolean};
const options=ref<Option[]>([]);
const filtered=computed(()=>options.value.filter(o=>o.label.toLowerCase().includes(query.value.trim().toLowerCase())));
let observer:MutationObserver|undefined;
let placementFrame=0;
function trackPlacement(){place();if(open.value)placementFrame=requestAnimationFrame(trackPlacement);}
watch(open,value=>{cancelAnimationFrame(placementFrame);if(value)placementFrame=requestAnimationFrame(trackPlacement);});
function native(){return host.value?.querySelector('select');}
function sync(){const select=native();if(!select)return;const next=Array.from(select.options).map(o=>({value:o.value,label:o.text,disabled:o.disabled,selected:o.selected}));if(JSON.stringify(next)!==JSON.stringify(options.value))options.value=next;label.value=select.selectedOptions[0]?.text||'Select an option';const caption=select.labels?.[0]?.cloneNode(true) as HTMLElement|undefined;caption?.querySelectorAll('.ac-select').forEach(el=>el.remove());name.value=select.getAttribute('aria-label')||caption?.textContent?.trim()||'Select an option';disabled.value=select.matches(':disabled');if(disabled.value)open.value=false;}
function place(){
 if(!open.value||!trigger.value)return;
 const rect=trigger.value.getBoundingClientRect(),gap=4,padding=12;
 const width=Math.min(Math.max(rect.width,props.matchTriggerWidth?0:280),window.innerWidth-padding*2);
 const below=Math.max(0,window.innerHeight-rect.bottom-padding-gap),above=Math.max(0,rect.top-padding-gap);
 const content=panel.value?.querySelector<HTMLElement>('[role=listbox]');
 const desired=Math.min(320,panel.value?(content?.scrollHeight||0)+(search.value?.offsetHeight||38)+22:320);
 const upward=below<desired&&above>below;
 const height=Math.min(desired,upward?above:below);
 const next={top:Math.max(padding,upward?rect.top-height-gap:rect.bottom+gap)+'px',left:Math.max(padding,Math.min(rect.left,window.innerWidth-width-padding))+'px',width:width+'px',maxHeight:height+'px'};
 if(JSON.stringify(next)!==JSON.stringify(position.value))position.value=next;
}

async function show(){sync();if(disabled.value)return;target.value=host.value?.closest('dialog')||document.body;query.value='';active.value=0;open.value=true;place();await nextTick();place();search.value?.focus({preventScroll:true});}
function close(focus=false){open.value=false;if(focus)trigger.value?.focus();}
function choose(option:Option){const select=native();if(!select||option.disabled||select.matches(':disabled'))return;select.value=option.value;select.dispatchEvent(new Event('change',{bubbles:true}));sync();close(true);}
function key(event:KeyboardEvent){if(event.key==='Escape'){event.preventDefault();event.stopPropagation();close(true);}else if(event.key==='ArrowDown'||event.key==='ArrowUp'){event.preventDefault();const count=filtered.value.length;if(count)active.value=(active.value+(event.key==='ArrowDown'?1:-1)+count)%count;void nextTick(()=>panel.value?.querySelector('[data-active="true"]')?.scrollIntoView({block:'nearest'}));}else if(event.key==='Enter'){event.preventDefault();const option=filtered.value[active.value];if(option)choose(option);}else if(event.key==='Tab')close(true);}
function outside(event:PointerEvent){if(!host.value?.contains(event.target as Node)&&!panel.value?.contains(event.target as Node))close();}
function invalid(event:Event){event.preventDefault();trigger.value?.focus();void show();}
onMounted(()=>{native()?.setAttribute('tabindex','-1');sync();observer=new MutationObserver(sync);if(host.value)observer.observe(host.value,{subtree:true,childList:true,attributes:true});native()?.addEventListener('invalid',invalid);document.addEventListener('pointerdown',outside);window.addEventListener('resize',place);window.addEventListener('scroll',place,true);});
onUpdated(()=>{sync();if(open.value)place();});
watch(query,async()=>{await nextTick();place();});
onBeforeUnmount(()=>{cancelAnimationFrame(placementFrame);observer?.disconnect();native()?.removeEventListener('invalid',invalid);document.removeEventListener('pointerdown',outside);window.removeEventListener('resize',place);window.removeEventListener('scroll',place,true);});
</script>
<template><span ref="host" class="ac-select"><span class="ac-select-native" aria-hidden="true"><slot/></span><button ref="trigger" type="button" role="combobox" aria-haspopup="listbox" :aria-controls="open?listId:undefined" :aria-expanded="open" :aria-label="name" :disabled="disabled" @click.prevent="open?close():show()" @keydown.down.prevent="show()"><span>{{label}}</span><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" aria-hidden="true"><path d="m5 7 5 5 5-5"/></svg></button><Teleport :to="target"><div v-if="open" ref="panel" class="ac-select-panel" :style="position" @keydown="key"><input ref="search" v-model="query" type="search" placeholder="Search by code or name…" :aria-label="'Search '+name" autocomplete="off" @input="active=0"><div :id="listId" role="listbox" :aria-label="name"><button v-for="(option,index) in filtered" :key="option.value" type="button" role="option" :aria-selected="option.selected" :disabled="option.disabled" :data-active="index===active" @pointermove="active=index" @click="choose(option)">{{option.label}}<span v-if="option.selected" aria-hidden="true">✓</span></button><p v-if="!filtered.length" role="status">No matching options.</p></div></div></Teleport></span></template>
<style>
.ac-select{display:block;position:relative;min-width:0;width:100%}.ac-select-native{position:absolute;width:1px;height:1px;overflow:hidden;clip-path:inset(50%);white-space:nowrap}.ac-select-native select{position:absolute}.ac-select>button{display:flex;align-items:center;justify-content:space-between;gap:12px;width:100%;min-height:40px;padding:9px 12px;border:1px solid var(--line,#454550);border-radius:8px;background:var(--bg,#171717);color:var(--text,#eee);font:inherit;font-size:12px;text-align:left;cursor:pointer}.ac-select>button>span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.ac-select>button svg{width:16px;height:16px;flex:none}.ac-select>button:focus-visible{outline:2px solid var(--ac-accent,#7865ef);outline-offset:1px}.ac-select>button:disabled,fieldset:disabled .ac-select>button{opacity:.6;cursor:not-allowed}.ac-select-panel{position:fixed;z-index:10050;display:flex;flex-direction:column;box-sizing:border-box;padding:8px;border:1px solid var(--line,#454550);border-radius:10px;background:var(--rail,#222);color:var(--text,#eee);box-shadow:0 12px 36px #0004}.ac-select-panel>input{box-sizing:border-box;width:100%;flex:none;min-height:38px;padding:9px 11px;border:1px solid var(--line,#454550);border-radius:6px;background:var(--bg,#171717);color:var(--text,#eee);font:inherit;font-size:13px;margin-bottom:6px}.ac-select-panel [role=listbox]{overflow-y:auto;min-height:0}.ac-select-panel [role=option]{display:flex;justify-content:space-between;gap:12px;width:100%;border:0;border-radius:5px;padding:10px;text-align:left;background:transparent;color:inherit;font:inherit;font-size:12px;cursor:pointer}.ac-select-panel [role=option][data-active=true],.ac-select-panel [aria-selected=true]{background:color-mix(in srgb,var(--ac-accent,#7865ef) 16%,transparent)}.ac-select-panel [role=option]:disabled{opacity:.5;cursor:not-allowed}.ac-select-panel p{font-size:12px;padding:10px;color:var(--muted,#aaa)}
</style>
