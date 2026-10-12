import {ref, onMounted, onUnmounted} from 'vue';

export function useBoardDrag() {
  const dragging = ref<number|null>(null);
  const target = ref<number|string|null>(null);
  function clear() { dragging.value = null; target.value = null; }
  function start(id:number|null) {
    dragging.value = id;
    if (id === null) target.value = null;
  }
  function over(id:number|string, event:DragEvent) {
    if (dragging.value === null) return;
    target.value = id;
    if (event.dataTransfer) event.dataTransfer.dropEffect = 'move';
  }
  function leave(id:number|string, event:DragEvent) {
    // Moving between cards inside one column should keep its highlight.
    const next = event.relatedTarget;
    if (next instanceof Node && (event.currentTarget as HTMLElement).contains(next)) return;
    if (target.value === id) target.value = null;
  }
  onMounted(() => {
    document.addEventListener('dragend', clear);
    document.addEventListener('drop', clear);
  });
  onUnmounted(() => {
    document.removeEventListener('dragend', clear);
    document.removeEventListener('drop', clear);
  });
  return {dragging, target, start, over, leave, clear};
}
