<script setup lang="ts">
import {ref,computed,onMounted,onBeforeUnmount} from 'vue';
const props=defineProps<{mode:'composer'|'reaction'}>();
const emit=defineEmits<{select:[emoji:string]}>();
const root=ref<HTMLElement|null>(null),opened=ref(false),query=ref(''),category=ref('Faces'),position=ref({left:'0px',top:'0px'});
const groups:Record<string,string>={Faces:'😀 😃 😄 😁 😆 😅 😂 🤣 😊 🙂 🙃 😉 😍 🥰 😘 😋 😛 😜 🤪 😎 🤩 🥳 😏 😔 😢 😭 😤 😠 🤔 🤐 😴 😮 😱 🤗 🫡',People:'👍 👎 👏 🙌 🤝 🙏 👋 ✌️ 🤞 💪 👌 🫶 👀 👩 👨 👶 🧑‍💻',Nature:'🐶 🐱 🐼 🦁 🐸 🐦 🦋 🌸 🌹 🌻 🌴 🌱 🌍 ☀️ 🌙 ⭐ 🌈 🔥',Food:'🍎 🍓 🍉 🍕 🍔 🍟 🍿 🍰 🎂 🍫 ☕ 🍵 🥤',Activities:'🎉 🎊 🎈 🎁 🏆 ⚽ 🏏 🎮 🎵 🎤 🚗 ✈️ 🏠 💻 📱 📚 💼 📅',Symbols:'❤️ 🧡 💛 💚 💙 💜 🖤 🤍 💔 💕 💯 ✅ ❌ ❓ ❗ 💡 📌 🔔'};
const names:Record<string,string>={'😀':'grinning happy smile','😊':'smiling happy','😂':'laugh tears joy','🤣':'laugh rolling','❤️':'heart love red','👍':'thumbs up like','🙏':'pray thanks','😮':'surprise wow','🎉':'party celebration','✅':'check done','🔥':'fire'};
const options=computed(()=>props.mode==='reaction'?['👍','❤️','😂','😮','🙏']:query.value?Object.values(groups).join(' ').split(' ').filter(e=>(names[e]||'').includes(query.value.toLowerCase())||e.includes(query.value)):groups[category.value].split(' '));
function toggle(){opened.value=!opened.value;query.value='';if(!opened.value)return;const box=root.value!.getBoundingClientRect(),width=Math.min(props.mode==='reaction'?240:320,window.innerWidth-24),height=props.mode==='reaction'?70:300;position.value={left:Math.max(12,Math.min(box.left,window.innerWidth-width-12))+'px',top:Math.max(12,Math.min(box.top>=height+12?box.top-height-8:box.bottom+8,window.innerHeight-height-12))+'px'};}
defineExpose({open:()=>{if(!opened.value)toggle();}});
function choose(emoji:string){emit('select',emoji);if(props.mode==='reaction')opened.value=false;}
function outside(event:Event){if(!root.value?.contains(event.target as Node))opened.value=false;}
function escape(event:KeyboardEvent){if(event.key==='Escape'&&opened.value){event.stopPropagation();opened.value=false;root.value?.querySelector<HTMLButtonElement>('.emoji-trigger')?.focus();}}
function reposition(event:Event){if(event.target instanceof Node&&root.value?.contains(event.target))return;opened.value=false;}
onMounted(()=>{document.addEventListener('pointerdown',outside);document.addEventListener('keydown',escape);window.addEventListener('resize',reposition);document.addEventListener('scroll',reposition,true);});
onBeforeUnmount(()=>{document.removeEventListener('pointerdown',outside);document.removeEventListener('keydown',escape);window.removeEventListener('resize',reposition);document.removeEventListener('scroll',reposition,true);});
</script>
<template>
<span ref="root" class="chat-emoji" :class="mode">
<button type="button" class="emoji-trigger" :aria-label="mode==='reaction'?'Choose reaction':'Insert emoji'" :title="mode==='reaction'?'React to message':'Emoji'" :aria-expanded="opened" @click="toggle">
<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M8 14a4 4 0 0 0 8 0"/><circle cx="9" cy="9" r=".7" fill="currentColor"/><circle cx="15" cy="9" r=".7" fill="currentColor"/></svg>
</button>
<span v-if="opened" class="emoji-panel" :class="mode" :style="position" role="dialog" :aria-label="mode==='reaction'?'Message reactions':'Emoji picker'">
<template v-if="mode==='composer'"><input v-model="query" class="emoji-search" placeholder="Search emojis" aria-label="Search emojis"><span class="emoji-categories"><button type="button" v-for="name in Object.keys(groups)" :key="name" :class="{selected:category===name&&!query}" @click="category=name;query=''">{{name}}</button></span></template>
<span class="emoji-grid"><button type="button" v-for="emoji in options" :key="emoji" :aria-label="(mode==='reaction'?'React ':'Insert ')+emoji" :title="names[emoji]||emoji" @click="choose(emoji)">{{emoji}}</button><span v-if="!options.length" class="emoji-empty">No matching emojis.</span></span>
</span>
</span>
</template>
<style scoped>
.chat-emoji{display:inline-flex;flex-shrink:0}.emoji-trigger{display:flex!important;align-items:center;justify-content:center;width:32px!important;height:32px!important;padding:4px!important;border:0;background:transparent;color:var(--muted,#999);cursor:pointer;border-radius:6px}.emoji-trigger:hover{background:rgba(128,128,128,.15);color:inherit}.reaction .emoji-trigger{width:26px!important;height:26px!important}
.emoji-panel{position:fixed;z-index:100;width:min(320px,calc(100vw - 24px));height:300px;box-sizing:border-box;display:flex;flex-direction:column;gap:10px;padding:12px;border:1px solid #7775;border-radius:12px;background:var(--rail,#202020);color:var(--text,#eee);box-shadow:0 8px 30px #0004}.emoji-panel.reaction{width:min(240px,calc(100vw - 24px));height:70px;justify-content:center}.emoji-search{width:100%;box-sizing:border-box;padding:8px;border:1px solid #7775;border-radius:6px;background:transparent;color:inherit;font-size:13px}.emoji-categories{display:flex;flex-wrap:wrap;gap:4px}.emoji-categories button{width:auto!important;height:auto!important;font-size:11px!important;padding:5px!important;background:transparent;color:inherit;border:0;border-radius:4px;cursor:pointer}.emoji-categories button.selected{background:#128c7e;color:white}.emoji-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:4px;overflow:auto;align-content:start}.reaction .emoji-grid{grid-template-columns:repeat(5,1fr)}.emoji-grid button{width:100%!important;height:34px!important;padding:2px!important;font-size:23px!important;line-height:1!important;background:transparent;border:0;border-radius:5px;cursor:pointer}.emoji-grid button:hover,.emoji-grid button:focus-visible{background:#7773;outline:2px solid #128c7e}.emoji-empty{grid-column:1/-1;font-size:13px}
:global(html[data-theme="light"]) .emoji-panel{background:#fff;color:#222}
</style>
