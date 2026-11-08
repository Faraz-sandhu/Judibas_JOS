type WorkspaceCompany={id:number;name:string;slug:string};
function directory():WorkspaceCompany[]{return window.portal.workspaceCompanies||[];}
export function workspaceCompany(product:string):string {
    const prefix='/'+product+'/';
    if(location.pathname.startsWith(prefix)){
        const slug=location.pathname.slice(prefix.length);
        if(slug==='all')return 'all';
        const company=directory().find(c=>c.slug===slug);
        if(company)return String(company.id);
        const initial=window.portal.companyWorkspace;
        if(initial?.product===product&&initial.slug===slug)return String(initial.id);
    }
    return new URLSearchParams(location.search).get(product==='pms'?'workspace_company':'company_id')||(product==='pms'&&location.pathname==='/pms'?'all':'');
}
export function companyWorkspaceHref(product:string,id:string|number):string {
    if(!id)return '/'+product;
    if(String(id)==='all')return '/'+product+'/all';
    const company=directory().find(c=>String(c.id)===String(id));
    return company?'/'+product+'/'+company.slug:'/'+product+'?'+(product==='pms'?'workspace_company':'company_id')+'='+encodeURIComponent(id);
}
export function replaceCompanyWorkspace(product:string,id:string|number):void {
    const url=new URL(location.href),target=new URL(companyWorkspaceHref(product,id),location.origin);
    url.pathname=target.pathname;
    url.searchParams.delete(product==='pms'?'workspace_company':'company_id');
    target.searchParams.forEach((value,key)=>url.searchParams.set(key,value));
    history.replaceState(null,'',url);
}
