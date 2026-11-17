import {onMounted, onBeforeUnmount} from 'vue';
import './product-motion.css';

// Animate only presentation. Requests, permissions and drag/drop remain unchanged.
export function useProductMotion(scope: string) {
 let observer: MutationObserver | undefined;
 let frame = 0;
 const positions = new Map<string, {left:number; top:number}>();
 const animations = new Set<Animation>();
 const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
 function animate(element: HTMLElement, frames: Keyframe[], duration = 220) {
  if (reduced.matches || !element.getClientRects().length) return;
  const animation = element.animate(frames, {duration, easing:'cubic-bezier(.2,.75,.25,1)'});
  animations.add(animation);
  animation.finished.catch(() => {}).finally(() => animations.delete(animation));
 }
 onMounted(() => {
  const root = document.querySelector(scope);
  if (!root) return;
  function snapshot(move = false) {
   const next = new Map<string, {left:number; top:number}>();
   root!.querySelectorAll<HTMLElement>('[data-motion-task]').forEach(card => {
    const id = card.dataset.motionTask!;
    const rect = card.getBoundingClientRect(), previous = positions.get(id);
    if (move && previous && !reduced.matches) {
     const x = previous.left - rect.left, y = previous.top - rect.top;
     if (Math.abs(x) + Math.abs(y) > 2) animate(card, [{transform:`translate(${x}px,${y}px)`,opacity:.75},{transform:'translate(0,0)',opacity:1}],300);
    }
    next.set(id,{left:rect.left,top:rect.top});
   });
   positions.clear(); next.forEach((value,key) => positions.set(key,value));
  }
  snapshot();
  observer = new MutationObserver(records => {
   const added = new Set<HTMLElement>();
   const selector = '.comm-chat,.comm-message,.comm-details,.work-card,.jira-card,.pms-my-work-empty';
   records.forEach(record => record.addedNodes.forEach(node => {
    if (!(node instanceof HTMLElement)) return;
    if (node.matches(selector)) added.add(node);
    node.querySelectorAll<HTMLElement>(selector).forEach(el => added.add(el));
   }));
   // Avoid animating an entire loaded history or a large board at once.
   if (added.size <= 8) added.forEach(el => {
    if (!el.hasAttribute('data-motion-task') || !positions.has(el.dataset.motionTask!))
     animate(el,[{opacity:0,transform:'translateY(6px)'},{opacity:1,transform:'translateY(0)'}]);
   });
   cancelAnimationFrame(frame);
   frame = requestAnimationFrame(() => snapshot(true));
  });
  observer.observe(root,{childList:true,subtree:true});
  const refresh = () => snapshot();
  root.addEventListener('scroll',refresh,true);
  window.addEventListener('resize',refresh);
  cleanup = () => {root.removeEventListener('scroll',refresh,true);window.removeEventListener('resize',refresh);};
 });
 let cleanup = () => {};
 onBeforeUnmount(() => {observer?.disconnect();cancelAnimationFrame(frame);animations.forEach(a=>a.cancel());positions.clear();cleanup();});
}
