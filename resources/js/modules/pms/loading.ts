import {computed, ref} from 'vue';

const pending = ref(0);
let backgroundDepth = 0;
export const pmsLoading = computed(() => pending.value > 0);

export function withoutPmsLoader<T>(action: () => T): T {
    backgroundDepth++;
    try { return action(); } finally { backgroundDepth--; }
}

export async function pmsFetch(input: RequestInfo | URL, options?: RequestInit): Promise<Response> {
    const url = new URL(input instanceof Request ? input.url : String(input), location.origin);
    const quiet = backgroundDepth > 0 || /\/(realtime\/config|broadcasting\/auth|timers\/running)$/.test(url.pathname);
    if (!quiet) pending.value++;
    try { return await fetch(input, options); }
    finally { if (!quiet) pending.value--; }
}
