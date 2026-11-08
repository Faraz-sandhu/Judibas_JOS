import {workspaceCompany,companyWorkspaceHref} from '../../companyWorkspace';
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
    const company = workspaceCompany('pms');
    if (company && url.origin === location.origin && url.pathname.startsWith('/pms/api')) {
        const headers = new Headers(options?.headers || (input instanceof Request ? input.headers : undefined));
        headers.set('X-PMS-Company', company);
        options = {...options, headers};
    }
    try { return await fetch(input, options); }
    finally { if (!quiet) pending.value--; }
}

/** Keep company scope on full-page navigation, new tabs and downloads. */
export function pmsWorkspaceHref(path: string): string {
    const url = new URL(path, location.origin);
    const company = url.searchParams.get('workspace_company') || workspaceCompany('pms');
    if (company && url.origin === location.origin) {
        if (url.pathname === '/pms') {
            const target=new URL(companyWorkspaceHref('pms',company),location.origin);
            url.pathname=target.pathname;url.searchParams.delete('workspace_company');
            target.searchParams.forEach((value,key)=>url.searchParams.set(key,value));
        } else if (url.pathname.startsWith('/pms/api') && !url.searchParams.has('workspace_company')) url.searchParams.set('workspace_company', company);
    }
    return url.origin === location.origin ? url.pathname + url.search + url.hash : url.href;
}
