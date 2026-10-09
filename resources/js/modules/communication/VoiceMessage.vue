<script setup lang="ts">
import {ref,onBeforeUnmount} from 'vue';
defineProps<{src:string}>();
const audio=ref<HTMLAudioElement|null>(null),rate=ref(1);
function speed(){rate.value=rate.value===1?1.5:rate.value===1.5?2:1;if(audio.value)audio.value.playbackRate=rate.value;}
function exclusive(event:Event){const self=event.target;document.querySelectorAll('audio').forEach(a=>{if(a!==self)a.pause();});}
onBeforeUnmount(()=>audio.value?.pause());
</script>
<template><div class="voice-message"><audio ref="audio" :src="src" controls preload="none" aria-label="Voice message" @play="exclusive"/><button type="button" @click="speed" aria-label="Change voice playback speed">{{rate}}x</button></div></template>
<style scoped>.voice-message{display:flex;align-items:center;gap:6px;max-width:100%;margin:6px 0}.voice-message audio{width:240px;max-width:calc(100vw - 160px);height:38px}.voice-message button{border-radius:12px;padding:5px;font-size:11px;min-width:34px}</style>
