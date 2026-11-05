import '../inventory-search-controls.js';

// Shared Purchase selection adapter; stage callers own rows, prices and persistence.
export function mountPurchasePicker({root,results,feedback,button,getIds,getCount,onAdd,isBusy=()=>false,stage='request',getDestinationChoice=null}) {
    const cell=(tag,text)=>{ const element=document.createElement(tag); element.textContent=text; return element; };
    const pending=()=>Boolean(results.querySelector('[data-search-item-check]:checked')) || [...results.querySelectorAll('[data-search-quantity]')].some(input=>input.value.trim()!=='');
    const message=text=>{ feedback.hidden=false; feedback.textContent=text; };
    let searching=false;
    const table=results.closest('table');
    let savedDestinationChoice=getDestinationChoice?.();
    let hasDestinationStock=Boolean(table.querySelector('[data-destination-stock-heading]'));
    let columnCount=hasDestinationStock ? 5 : 4;
    const destinationSaved=()=>!getDestinationChoice || getDestinationChoice()===savedDestinationChoice;
    const stockDestination=location=>{
        table.querySelectorAll('[data-destination-stock-heading], [data-destination-stock-column], [data-destination-stock-value]').forEach(element=>element.remove());
        hasDestinationStock=Boolean(location);columnCount=hasDestinationStock ? 5 : 4;
        if(location){
            const heading=cell('th',location.name);heading.className='table-heading-center';heading.dataset.destinationStockHeading='';
            table.tHead.rows[0].insertBefore(heading,table.tHead.rows[0].cells[2]);
            const column=document.createElement('col');column.className='table-quantity-column';column.dataset.destinationStockColumn='';
            table.querySelector('colgroup').insertBefore(column,table.querySelector('colgroup').children[2]);
        }
        results.querySelectorAll('[data-search-group] th:last-child').forEach(title=>{title.colSpan=columnCount-1;});
        results.querySelectorAll('td[colspan]').forEach(text=>{text.colSpan=columnCount;});
    };
    const invalidateDestination=()=>{
        stockDestination(null);
        message('Delivery destination changed. '+(destinationSaved() ? 'Search again' : 'Save progress, then search again')+' to see current destination quantities. Pending item selections are retained.');
    };
    const choices=new Map();
    const update=()=>{ button.disabled=isBusy() || searching || !results.querySelector('[data-search-item]'); };
    const render=data=>{
        if(getDestinationChoice && 'destination_choice' in data && (!destinationSaved() || data.destination_choice!==savedDestinationChoice)) {
            invalidateDestination();return;
        }
        stockDestination(data.destination_location || null);
        results.replaceChildren(); choices.clear(); feedback.hidden=true;
        let group=null;
        data.rows.forEach(item=>{
            choices.set(String(item.id),item);
            const next=`${item.category} : ${item.collection}`;
            if(group!==next) {
                group=next; const row=document.createElement('tr'); row.className='group-row'; row.dataset.searchGroup='';
                const selection=document.createElement('th'),check=document.createElement('input'); check.type='checkbox'; check.dataset.searchGroupCheck=''; check.setAttribute('aria-label',`Select items in ${group}`); selection.append(check);
                const title=cell('th',group); title.colSpan=columnCount-1; title.scope='rowgroup'; row.append(selection,title); results.append(row);
            }
            const row=document.createElement('tr'); row.dataset.searchItem=item.id;
            const selection=document.createElement('td'); selection.className='app-grouped-item';
            const check=document.createElement('input'); check.type='checkbox'; check.dataset.searchItemCheck=''; check.setAttribute('aria-label',`Select ${item.name} · ${item.sku}`); selection.append(check);
            const name=cell('td',item.name); name.className='app-grouped-item';
            const price=cell('td',item.unit_price_display ?? '—'); price.className='table-numeric';
            const quantity=document.createElement('td'); quantity.className='table-numeric';
            const input=document.createElement('input'); input.dataset.searchQuantity=''; input.maxLength=30; input.setAttribute('aria-label',`Request ${item.name} · ${item.sku}`); quantity.append(input);
            row.append(selection,name);
            if(hasDestinationStock){const stock=cell('td',Number(item.destination_quantity ?? 0).toLocaleString());stock.className='table-numeric';stock.dataset.destinationStockValue='';row.append(stock);}
            row.append(price,quantity); results.append(row);
        });
        if(!data.rows.length){ const row=document.createElement('tr'),text=cell('td','No matching active items to add.'); text.colSpan=columnCount; row.append(text); results.append(row); }
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
        if(!destinationSaved()){event.preventDefault();invalidateDestination();return;}
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
    return {pending,api,invalidateDestination,setSavedDestination(choice){
        if(savedDestinationChoice!==choice){savedDestinationChoice=choice;invalidateDestination();}
    }};
}
