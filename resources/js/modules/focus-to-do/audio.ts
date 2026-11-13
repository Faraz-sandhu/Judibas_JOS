// Generated sound only: no external audio or tracking requests.
let context:AudioContext|undefined,source:AudioBufferSourceNode|undefined,tick:number|undefined;
const ctx=()=>context||=(new AudioContext());
export function unlockAudio(){try{const c=ctx();void c.resume().catch(()=>{});}catch{/* Audio is optional on unsupported browsers. */}}
export function stopNoise(){source?.stop();source=undefined;window.clearInterval(tick);tick=undefined;}
export function alarm(volume:number,kind='bell'){
 if(kind==='none'||volume<=0)return;let c:AudioContext;try{c=ctx();void c.resume().catch(()=>{});}catch{return;}
 [0,.25,.5].forEach((delay,i)=>{const osc=c.createOscillator(),gain=c.createGain();osc.type=kind==='digital'?'square':'sine';osc.frequency.value=(kind==='chime'?660:880)+i*110;gain.gain.setValueAtTime(volume/100*.15,c.currentTime+delay);gain.gain.exponentialRampToValueAtTime(.001,c.currentTime+delay+.25);osc.connect(gain).connect(c.destination);osc.start(c.currentTime+delay);osc.stop(c.currentTime+delay+.26);});
}
export function noise(kind:string,volume:number){
 stopNoise();if(kind==='none')return;const c=ctx();void c.resume();
 if(kind==='tick'||kind==='countdown'){tick=window.setInterval(()=>alarm(volume*.25,'digital'),kind==='tick'?1000:2000);return;}
 const buffer=c.createBuffer(1,c.sampleRate*3,c.sampleRate),samples=buffer.getChannelData(0);let last=0;
 for(let i=0;i<samples.length;i++){last=(last+Math.random()*.04-.02)/1.02;samples[i]=last*3;}
 source=c.createBufferSource();source.buffer=buffer;source.loop=true;const gain=c.createGain();gain.gain.value=volume/100*(kind==='classroom'?.25:.6);source.connect(gain).connect(c.destination);source.start();
}
