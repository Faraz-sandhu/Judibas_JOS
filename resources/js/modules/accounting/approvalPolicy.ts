export const approvalTypes=[
 {key:'contra',name:'CV - Contra vouchers',paper:true},
 {key:'general',name:'Journal entries',paper:true},{key:'opening',name:'Opening entries',paper:true},
 {key:'cash_payment',name:'CPV - Cash payment vouchers',paper:true},{key:'cash_receipt',name:'CRV - Cash receipt vouchers',paper:true},
 {key:'bank_payment',name:'BPV - Bank payment vouchers',paper:true},{key:'bank_receipt',name:'BRV - Bank receipt vouchers',paper:true},
 {key:'expense',name:'EV - Expense vouchers',paper:true},{key:'income',name:'IV - Income vouchers',paper:true},
 {key:'sale',name:'Sales invoices',paper:false},{key:'purchase',name:'Purchase invoices',paper:false},
 {key:'customer_payment',name:'Customer payment requests',paper:false},{key:'supplier_payment',name:'Supplier payment requests',paper:false},
];
export function needsApproval(book:any,key:string):boolean{
 if(!book?.approval_required)return false;
 let types=book.approval_types;
 if(types==null)return true;
 if(typeof types==='string'){try{types=JSON.parse(types);}catch{return true;}}
 return Array.isArray(types)&&types.includes(key);
}

export function selectedApprovalTypes(book:any):string[]{
 let types=book?.approval_types;
 if(typeof types==='string'){try{types=JSON.parse(types);}catch{types=null;}}
 return types==null?approvalTypes.map(t=>t.key):Array.isArray(types)?types.filter(t=>approvalTypes.some(a=>a.key===t)):[];
}
