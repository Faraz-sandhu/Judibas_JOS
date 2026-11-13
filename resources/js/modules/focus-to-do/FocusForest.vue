<script setup lang="ts">
import {computed,ref,watch,onBeforeUnmount} from 'vue';
const props=defineProps<{sunlight:number;pending:number;busy:boolean}>();defineEmits(['collect']);
const level=computed(()=>Math.floor(props.sunlight/600)),stage=computed(()=>Math.min(3,level.value)),growth=computed(()=>props.sunlight%600);
const names=['A new beginning','Taking root','Growing stronger','Your focus is flourishing'];
const celebrating=ref(false);let timeout:ReturnType<typeof setTimeout>|undefined;
watch(()=>props.sunlight,(value,old)=>{if(value>old){celebrating.value=true;clearTimeout(timeout);timeout=setTimeout(()=>celebrating.value=false,1800);}});
onBeforeUnmount(()=>clearTimeout(timeout));
</script>
<template><article class="ft-panel ft-forest-garden" :class="{'is-celebrating':celebrating}">
  <header><div><p class="ft-eyebrow">YOUR FOCUS IS GROWING</p><h2>Your little forest</h2><p class="ft-muted">A visual reminder of the time you invest in yourself.</p></div><span class="ft-forest-level">Level {{level}}</span></header>
  <div class="ft-forest-scene" :data-growth="stage">
    <svg viewBox="0 0 680 280" role="img" :aria-label="'Growing focus tree: '+names[stage]">
      <g class="ft-forest-cloud" fill="currentColor" opacity=".12"><path d="M70 75c-20-2-20-25-1-27 4-24 38-23 42-4 24-6 39 30 8 31Z"/><path d="M460 108c-20-2-20-24-1-27 5-24 38-22 42-3 24-6 39 30 8 30Z"/></g>
      <g class="ft-forest-sun"><circle cx="548" cy="61" r="25" fill="#f3c75d"/><circle class="ft-sun-halo" cx="548" cy="61" r="35" fill="none" stroke="#f3c75d" stroke-width="2" opacity=".35"/></g>
      <path d="M0 238Q130 175 272 226T680 216V280H0Z" fill="currentColor" opacity=".07"/><path d="M0 257Q170 215 350 249T680 234V280H0Z" fill="currentColor" opacity=".1"/>
      <ellipse cx="340" cy="252" rx="88" ry="9" fill="#25885e" opacity=".14"/>
      <g class="ft-growing-tree">
        <path :d="stage===0?'M340 248V202':'M340 249V137M340 195l-34-29m34 14 31-29'" fill="none" stroke="#927052" :stroke-width="stage===0?7:13" stroke-linecap="round"/>
        <g v-if="stage===0"><path d="M339 221c-36 0-48-37-48-37 37-3 53 13 48 37" fill="#51ae7c"/><path d="M341 207c-1-31 37-43 37-43 5 31-12 47-37 43" fill="#329465"/></g>
        <g v-else><ellipse cx="318" cy="156" :rx="stage===1?40:58" :ry="stage===1?40:54" fill="#348c64"/><ellipse cx="369" cy="151" :rx="stage===1?40:56" :ry="stage===1?42:55" fill="#46a675"/><ellipse cx="341" :cy="stage===1?119:101" :rx="stage===1?43:60" :ry="stage===1?45:61" fill="#5bb785"/><ellipse cx="324" :cy="stage===1?112:89" rx="19" ry="24" fill="#8dcf9f" opacity=".55"/><g v-if="stage===3" fill="#e8bc61"><circle cx="307" cy="140" r="5"/><circle cx="364" cy="112" r="5"/><circle cx="353" cy="165" r="5"/></g></g>
      </g>
      <g class="ft-forest-grass" stroke="#4d9d70" stroke-width="3" stroke-linecap="round"><path d="m249 251-4-9m4 9 5-11m170 11-4-9m4 9 5-11m-132 15-3-8m90 8 4-9"/></g>
      <g class="ft-forest-motes" fill="#e9bd50"><circle cx="268" cy="141" r="3"/><circle cx="412" cy="121" r="2.5"/><circle cx="390" cy="198" r="3"/></g>
    </svg>
    <span class="ft-forest-stage">{{names[stage]}}</span><span v-if="celebrating" class="ft-forest-collected">Sunlight collected!</span>
  </div>
  <div class="ft-forest-growth"><div><strong>{{sunlight.toLocaleString()}}g sunlight</strong><span>{{600-growth}}g to level {{level+1}}</span></div><progress :value="growth" max="600" aria-label="Progress to next forest level"></progress></div>
  <div class="ft-forest-bottom"><p class="ft-muted">Completed focus earns 1g per full minute. Collect your sunlight to grow your tree; every 600g unlocks a new level.</p><button class="ft-primary" :disabled="busy||!pending" @click="$emit('collect')">{{busy?'Collecting…':'Collect '+pending+'g sunlight'}}</button></div>
  <p class="ft-forest-rules">Daily visit: 30g · Consecutive-day visit: 6g. This is a virtual progress garden, not real tree planting.</p>
</article></template>
<style scoped>
.ft-forest-garden{overflow:hidden}.ft-forest-garden>header{display:flex;justify-content:space-between;align-items:flex-start;gap:16px}.ft-forest-garden h2{margin:5px 0 8px}.ft-forest-garden header p{margin:0}.ft-forest-level{padding:7px 12px;border:1px solid var(--line);border-radius:20px;color:#329465;font-size:12px;white-space:nowrap}
.ft-forest-scene{position:relative;margin:24px 0 20px;border-radius:14px;background:color-mix(in srgb,#51ae7c 8%,var(--bg));color:#329465;overflow:hidden}.ft-forest-scene svg{display:block;width:100%;max-height:320px}.ft-growing-tree{transform-origin:340px 249px;animation:ft-tree-sway 6s ease-in-out infinite}.ft-forest-cloud{animation:ft-cloud-drift 18s ease-in-out infinite}.ft-sun-halo{transform-origin:548px 61px;animation:ft-sun-breathe 5s ease-in-out infinite}.ft-forest-motes{animation:ft-motes-float 4s ease-in-out infinite}.ft-forest-stage{position:absolute;bottom:12px;left:16px;font-size:11px;color:var(--muted)}.ft-forest-collected{position:absolute;top:18px;left:50%;transform:translateX(-50%);background:var(--bg);border:1px solid #51ae7c;padding:9px 14px;border-radius:20px;font-size:12px;animation:ft-reward-rise 1.8s ease-out both}
.ft-forest-growth>div{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;font-size:13px}.ft-forest-growth span{font-size:12px;color:var(--muted)}.ft-forest-growth progress{display:block;width:100%;height:9px;margin:12px 0 0;accent-color:#46a675}.ft-forest-bottom{display:flex;justify-content:space-between;align-items:center;gap:24px;margin-top:20px}.ft-forest-bottom p{max-width:490px;font-size:12px;line-height:1.7;margin:0}.ft-forest-bottom button{flex-shrink:0}.ft-forest-rules{margin:20px 0 0;padding-top:15px;border-top:1px solid var(--line);font-size:11px;line-height:1.7;color:var(--muted)}
@keyframes ft-tree-sway{0%,100%{transform:rotate(-1deg)}50%{transform:rotate(1deg)}}@keyframes ft-cloud-drift{50%{transform:translateX(15px)}}@keyframes ft-sun-breathe{50%{transform:scale(1.12);opacity:.6}}@keyframes ft-motes-float{50%{transform:translateY(-9px);opacity:.35}}@keyframes ft-reward-rise{0%{opacity:0;translate:0 10px}20%,70%{opacity:1}100%{opacity:0;translate:0 -16px}}
@media(max-width:650px){.ft-forest-bottom{flex-direction:column;align-items:stretch;gap:14px}.ft-forest-scene svg{min-height:180px}.ft-forest-garden h2{font-size:21px}}
@media(prefers-reduced-motion:reduce){.ft-growing-tree,.ft-forest-cloud,.ft-sun-halo,.ft-forest-motes,.ft-forest-collected{animation:none}.ft-forest-collected{opacity:1}}
</style>
