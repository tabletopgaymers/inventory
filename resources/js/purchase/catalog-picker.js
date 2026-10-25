import '../inventory-search-controls.js';

// Shared Purchase selection adapter; stage callers own rows, prices and persistence.
export function mountPurchasePicker({root,results,feedback,button,getIds,getCount,onAdd,isBusy=()=>false,stage='request'}) {
    const cell=(tag,text)=>{ const element=document.createElement(tag); element.textContent=text; return element; };
    const pending=()=>Boolean(results.querySelector('[data-search-item-check]:checked')) || [...results.querySelectorAll('[data-search-quantity]')].some(input=>input.value.trim()!=='');
    const message=text=>{ feedback.hidden=false; feedback.textContent=text; };
    let searching=false;
    const choices=new Map();
    const update=()=>{ button.disabled=isBusy() || searching || !results.querySelector('[data-search-item]'); };
    const render=data=>{
        results.replaceChildren(); choices.clear(); feedback.hidden=true;
        let group=null;
        data.rows.forEach(item=>{
            choices.set(String(item.id),item);
            const next=`${item.category} : ${item.collection}`;
            if(group!==next) {
                group=next; const row=document.createElement('tr'); row.className='group-row'; row.dataset.searchGroup='';
                const selection=document.createElement('th'),check=document.createElement('input'); check.type='checkbox'; check.dataset.searchGroupCheck=''; check.setAttribute('aria-label',`Select items in ${group}`); selection.append(check);
                const title=cell('th',group); title.colSpan=3; title.scope='rowgroup'; row.append(selection,title); results.append(row);
            }
            const row=document.createElement('tr'); row.dataset.searchItem=item.id;
            const selection=document.createElement('td'); selection.className='app-grouped-item';
            const check=document.createElement('input'); check.type='checkbox'; check.dataset.searchItemCheck=''; check.setAttribute('aria-label',`Select ${item.name} · ${item.sku}`); selection.append(check);
            const name=cell('td',item.name); name.className='app-grouped-item';
            const price=cell('td',item.unit_price || '—'); price.className='table-numeric';
            const quantity=document.createElement('td'); quantity.className='table-numeric';
            const input=document.createElement('input'); input.dataset.searchQuantity=''; input.maxLength=30; input.setAttribute('aria-label',`Request ${item.name} · ${item.sku}`); quantity.append(input);
            row.append(selection,name,price,quantity); results.append(row);
        });
        if(!data.rows.length){ const row=document.createElement('tr'),text=cell('td','No matching active items to add.'); text.colSpan=4; row.append(text); results.append(row); }
        update();
    };
    results.addEventListener('change',event=>{
        if(!event.target.matches('[data-search-group-check]')) return;
        const checks=[]; let row=event.target.closest('[data-search-group]').nextElementSibling;
        while(row && !row.hasAttribute('data-search-group')){ const check=row.querySelector('[data-search-item-check]'); if(check)checks.push(check); row=row.nextElementSibling; }
        if(event.target.checked || checks.every(check=>check.checked)) checks.forEach(check=>{check.checked=event.target.checked;});
    });
    root.addEventListener('inventory-search:before-search',event=>{
        if(isBusy()){event.preventDefault();return;}
        if(pending()){event.preventDefault();message('Use Add selected or clear checked items and entered quantities before changing search results.');}
    });
    root.addEventListener('inventory-search:before-reset',event=>{
        if(isBusy()){event.preventDefault();return;}
        if(event.detail.reset && pending()){event.preventDefault();message('Use Add selected or clear checked items and entered quantities before changing search results.');}
    });
    root.addEventListener('inventory-search:reset',()=>render({rows:[]}));
    root.addEventListener('inventory-search:busy',event=>{searching=event.detail.busy;update();});
    button.addEventListener('click',()=>{
        if(isBusy() || searching)return;
        const ids=new Set(getIds().map(String));
        const selected=[...results.querySelectorAll('[data-search-item]')].filter(row=>!ids.has(row.dataset.searchItem) && (row.querySelector('[data-search-item-check]').checked || row.querySelector('[data-search-quantity]').value.trim()!==''));
        if(!selected.length){message('Check an item or enter a quantity to add.');return;}
        if(getCount()+selected.length>1000){message('Use up to 1,000 material lines.');return;}
        onAdd(selected.map(row=>{
            const entered=row.querySelector('[data-search-quantity]').value.trim(),normalized=entered.replaceAll(',','');
            const quantity=/^(?:\d+|\d{1,3}(?:,\d{3})+)$/.test(entered) && /^\d+$/.test(normalized) && Number(normalized)<=1000000000 ? String(Number(normalized)) : '';
            return {item:choices.get(row.dataset.searchItem),quantity};
        }));
        selected.forEach(row=>row.remove());
        results.querySelectorAll('[data-search-group]').forEach(group=>{if(!group.nextElementSibling || group.nextElementSibling.hasAttribute('data-search-group'))group.remove();});
        if(!results.querySelector('[data-search-item]'))render({rows:[]});
        feedback.hidden=true; update();
    });
    const api=window.appInventorySearch.mount(root,{prepareUrl(url){url.searchParams.set('live_items','1');url.searchParams.set('stage',stage);getIds().forEach(id=>url.searchParams.append('excluded[]',id));},render});
    return {pending,api};
}
