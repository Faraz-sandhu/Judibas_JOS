<script setup lang="ts">
import {ref,computed,nextTick,onMounted,onBeforeUnmount} from 'vue';
import EmojiPicker from './EmojiPicker.vue';
const props=defineProps<{id:number;mine:boolean;canReply:boolean;canReact:boolean;canPin:boolean;pinned:boolean;canEdit:boolean;canDelete:boolean;contact?:string;canDownload?:boolean}>();
const emit=defineEmits<{action:[action:string];reaction:[emoji:string]}>();
const root=ref<HTMLElement|null>(null),panel=ref<HTMLElement|null>(null),trigger=ref<HTMLButtonElement|null>(null),picker=ref<InstanceType<typeof EmojiPicker>|null>(null),opened=ref(false),position=ref({left:'0px',top:'0px'});
const options=computed(()=>[
 ...(props.canReply?[{key:'reply',label:'Reply',icon:'↩'}]:[]),
 {key:'forward',label:'Forward',icon:'↪'},{key:'copy',label:'Copy',icon:'▣'},
 ...(props.canDownload?[{key:'download',label:'Download',icon:'↓'}]:[]),
 ...(props.canReact?[{key:'react',label:'React',icon:'☺'}]:[]),
 ...(props.canPin?[{key:'pin',label:props.pinned?'Unpin':'Pin',icon:'⌖'}]:[]),
 ...(props.contact?[{key:'contact',label:'Message '+props.contact,icon:'✉'}]:[]),
 ...(props.canEdit?[{key:'edit',label:'Edit',icon:'✎'}]:[]),
 ...(props.canDelete?[{key:'delete',label:'Delete',icon:'×'}]:[])
]);
async function toggle(){opened.value=!opened.value;if(!opened.value)return;const box=trigger.value!.getBoundingClientRect(),height=options.value.length*38+12;position.value={left:Math.max(12,Math.min(props.mine?box.right-220:box.left,innerWidth-232))+'px',top:Math.max(12,Math.min(box.bottom+height<innerHeight-12?box.bottom+6:box.top-height-6,innerHeight-height-12))+'px'};await nextTick();panel.value?.querySelector<HTMLButtonElement>('button')?.focus();}
function select(action:string){opened.value=false;if(action==='react')picker.value?.open();else emit('action',action);}
function outside(e:Event){if(e.target instanceof Node&&!root.value?.contains(e.target)&&!panel.value?.contains(e.target))opened.value=false;}
function key(e:KeyboardEvent){if(!opened.value)return;if(e.key==='Escape'){opened.value=false;trigger.value?.focus();}else if(['ArrowDown','ArrowUp'].includes(e.key)){e.preventDefault();const buttons=Array.from(panel.value!.querySelectorAll<HTMLButtonElement>('button')),index=buttons.indexOf(document.activeElement as HTMLButtonElement);buttons[(index+(e.key==='ArrowDown'?1:buttons.length-1))%buttons.length]?.focus();}}
function scroll(e:Event){if(e.target instanceof Node&&panel.value?.contains(e.target))return;opened.value=false;}
onMounted(()=>{document.addEventListener('pointerdown',outside);document.addEventListener('keydown',key);document.addEventListener('scroll',scroll,true);window.addEventListener('resize',scroll);});
onBeforeUnmount(()=>{document.removeEventListener('pointerdown',outside);document.removeEventListener('keydown',key);document.removeEventListener('scroll',scroll,true);window.removeEventListener('resize',scroll);});
</script>
<template>
<div class="comm-message-tools" ref="root" :class="{mine}">
<EmojiPicker v-if="canReact" ref="picker" mode="reaction" @select="emit('reaction',$event)"/>
<button class="comm-message-chevron" ref="trigger" :aria-label="'Message options '+id" aria-haspopup="menu" :aria-expanded="opened" @click="toggle"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></button>
<Teleport to="body"><div v-if="opened" ref="panel" class="comm-message-menu" :style="position" role="menu" aria-label="Message actions"><button v-for="option in options" :key="option.key" role="menuitem" :class="{danger:option.key==='delete'}" @click="select(option.key)"><span aria-hidden="true">{{option.icon}}</span>{{option.label}}</button></div></Teleport>
</div>
</template>