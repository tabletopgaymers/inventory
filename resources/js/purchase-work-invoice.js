import './app.js';

import { mountQuantityNavigation } from './workflow/quantity-navigation.js';
import { mountUnsavedChanges } from './workflow/unsaved-changes.js';
import { mountProgressSave } from './workflow/progress-save.js';
import { mountPurchasePicker } from './purchase/catalog-picker.js';

(() => {
    const form = document.querySelector('[data-purchase-invoice]');
    if (!form) return;
    const linkedField = document.getElementById(decodeURIComponent(location.hash.slice(1)));
    if (linkedField && !linkedField.closest('[hidden]')) { linkedField.focus(); linkedField.select?.(); }
    let dirty = false;
    let editGeneration = 0;
    const searchRoot=form.querySelector('[data-inventory-search]'), searchResults=form.querySelector('[data-purchase-search-results]');
    const pendingSelections=()=>Boolean(searchResults?.querySelector('[data-search-item-check]:checked')) || [...(searchResults?.querySelectorAll('[data-search-quantity]') || [])].some(input=>input.value.trim()!=='');
    const save = document.querySelector('[data-save-shortcut]');
    const markDirty = () => {
        editGeneration++; dirty = true; save?.classList.add('app-save-dirty');
        form.querySelectorAll('[data-invoice-derived]').forEach(element => { element.hidden = true; });
        form.querySelectorAll('[data-derived-stale]').forEach(element => { element.hidden = false; });
    };
    const changed=event=>{if(!searchRoot?.contains(event.target) && !searchResults?.contains(event.target))markDirty();};
    form.addEventListener('input', changed);
    form.addEventListener('change', changed);
    const navigation = mountUnsavedChanges(form, { isDirty: () => dirty || pendingSelections(),
        message: 'Unsaved invoice edits will be discarded. Keep editing or leave without saving.' });
    const progress=mountProgressSave(form, {feedback:document.querySelector('[data-progress-feedback]'), canSave:()=>!searchRoot || !window.appInventorySearch.get(searchRoot)?.getState().busy, getGeneration:() => editGeneration, onSaved:(result, {unchanged}) => {
        document.querySelectorAll('input[name="revision"]').forEach(input => { input.value = result.revision; });
        if (!unchanged) return;
        dirty = false; save?.classList.remove('app-save-dirty');
    }});
    form.querySelector('[data-delivery-choice]')?.addEventListener('change', event => {
        const option = event.target.selectedOptions[0];
        ['name','address','email','phone'].forEach(field => { form.elements[`delivery_${field}`].value = option.dataset[field] || ''; });
        form.elements.receiving_location_id.value = event.target.value.startsWith('location:') ? event.target.value.slice(9) : '';
    });
    form.addEventListener('submit', event => {
        if ((event.submitter?.value || 'save') === 'save') return;
        if (event.submitter?.value === 'reconcile_receipt' && !navigation.isLeaving()) {
            event.preventDefault(); const button=event.submitter;
            navigation.confirm(()=>form.requestSubmit(button),{origin:button,title:'Reconcile with updated order?',actionLabel:'Reconcile',text:'Keep your entered receiving values and use the updated order as their reference? New items need receiving counts; check removed and changed items before confirmation.'});
            return;
        }
        if (event.submitter?.value === 'replace_vendor' && !navigation.isLeaving()) {
            event.preventDefault(); const button = event.submitter;
            navigation.confirm(() => form.requestSubmit(button), {origin:button,title:'Replace vendor?',actionLabel:'Replace vendor',
                text:'Replace the vendor with the selected vendor and preserve its details in a permanent note? If removal is selected, only an unused, unshared vendor will be deleted. Other unsaved invoice changes will not be saved.'});
            return;
        }
        if (event.submitter?.value === 'cancel_receipt' && !navigation.isLeaving()) {
            event.preventDefault();
            const button = event.submitter;
            navigation.confirm(() => form.requestSubmit(button), { origin: button, title: 'Cancel receiving?',
                text: 'Unsaved invoice edits and the saved receipt draft will be discarded.', actionLabel: 'Cancel receiving' });
            return;
        }
        navigation.allowDeparture();
    });
    document.querySelectorAll('form:not([data-purchase-invoice])').forEach(other => other.addEventListener('submit', event => {
        if (navigation.isLeaving()) return;
        if (!dirty) { navigation.allowDeparture(); return; }
        event.preventDefault();
        const button = event.submitter;
        navigation.confirm(() => other.requestSubmit(button), { origin: button,
            text: 'This action does not save your unsaved invoice changes. Continue?', actionLabel: 'Continue' });
    }));
    const decimal = (value, places) => {
        value=value.trim().replace(/^\./,'0.');
        if (!/^\d+(?:\.\d+)?$/.test(value)) throw Error('decimal');
        const [whole, fraction = ''] = value.split('.');
        if (fraction.length > places) throw Error('precision');
        return BigInt(whole) * 10n ** BigInt(places) + BigInt(fraction.padEnd(places, '0') || '0');
    };
    const round = (number, denominator) => (number + denominator / 2n) / denominator;
    const format = (value, places) => `${value / 10n ** BigInt(places)}.${(value % 10n ** BigInt(places)).toString().padStart(places, '0')}`;
    const calculate = (row, changed) => {
        const fields = Object.fromEntries([...row.querySelectorAll('[data-invoice-field]')].map(input => [input.dataset.invoiceField, input]));
        try {
            const quantity = decimal(fields.quantity.value.replaceAll(',', ''), 0);
            if (quantity > 1000000000n) throw Error('quantity');
            if (quantity === 0n) {
                fields.cost.value='0.00'; row.querySelector('[data-line-basis]').value='unit';
                row.querySelector('[data-line-total]').textContent=format(decimal(fields.fee.value,2),2); return;
            }
            if (changed === 'cost') {
                fields.unit.value = format(round(decimal(fields.cost.value, 2) * 1000000000000n, quantity * 100n), 12);
                row.querySelector('[data-line-basis]').value = 'cost';
            } else if (changed === 'unit' || changed === 'quantity') {
                fields.cost.value = format(round(decimal(fields.unit.value, 12) * quantity, 10000000000n), 2);
                row.querySelector('[data-line-basis]').value = 'unit';
            }
            row.querySelector('[data-line-total]').textContent = format(decimal(fields.cost.value, 2) + decimal(fields.fee.value, 2), 2);
        } catch { row.querySelector('[data-line-total]').textContent = '—'; }
    };
    form.querySelectorAll('[data-invoice-line]').forEach(row => {
        calculate(row, 'initial');
    });
    form.addEventListener('input', event => {
        if (event.target.matches('[data-invoice-field]')) calculate(event.target.closest('[data-invoice-line]'), event.target.dataset.invoiceField);
    });
    form.addEventListener('click', event => {
        const button = event.target.closest('[data-recalculate]');
        if (button) { calculate(button.closest('[data-invoice-line]'), 'unit'); markDirty(); }
    });
    const body=form.querySelector('[data-invoice-body]');
    const invoiceRows=()=>[...form.querySelectorAll('[data-invoice-line]')];
    const regroup=()=>{
        if(!body)return;
        const rows=invoiceRows().sort((a,b)=>a.dataset.group.localeCompare(b.dataset.group,undefined,{numeric:true}) || a.querySelector('[data-invoice-description]').value.localeCompare(b.querySelector('[data-invoice-description]').value,undefined,{numeric:true}));
        body.replaceChildren();let group=null;
        rows.forEach(row=>{if(group!==row.dataset.group){group=row.dataset.group;const heading=document.createElement('tr'),label=document.createElement('th');heading.className='group-row';label.colSpan=9;label.scope='rowgroup';label.textContent=group;heading.append(label);body.append(heading);}body.append(row);});
        if(!rows.length){const row=document.createElement('tr'),text=document.createElement('td');text.colSpan=9;text.textContent='No order material selected.';row.append(text);body.append(row);}
    };
    const append=({item,quantity=''})=>{
        const key=crypto.randomUUID(),row=document.createElement('tr');row.dataset.invoiceLine='';row.dataset.lineKey=key;row.dataset.group=item?`${item.category} : ${item.collection}`:'Other items';
        const td=()=>document.createElement('td');
        const field=(name,value)=>{const input=document.createElement('input');input.name=`lines[${key}][${name}]`;input.value=value;return input;};
        const action=td(),remove=document.createElement('button');remove.type='button';remove.className='material-remove';remove.dataset.removeInvoice='';remove.textContent='✕';remove.setAttribute('aria-label',`Remove ${item?.name || 'Other item'}`);action.append(remove);
        const description=td();description.className='app-grouped-item';const name=field('description',item?.name || '');name.dataset.invoiceDescription='';name.maxLength=500;name.readOnly=Boolean(item);name.setAttribute('aria-label','Item description');description.append(name);
        [['item_id',item?.id || '', 'invoiceItem'],['group_label',row.dataset.group,'invoiceGroup'],['source_request_key','','sourceRequestKey']].forEach(([key,value,hook])=>{const input=field(key,value);input.type='hidden';input.dataset[hook]='';description.append(input);});
        if(!item)description.append(form.querySelector('[data-catalog-link-template]').content.cloneNode(true));
        const requested=td();requested.textContent='—';requested.className='table-numeric';row.append(action,description,requested);
        ['quantity','unit','cost','fee'].forEach(key=>{const cell=td(),input=field(key,key==='quantity'?quantity:key==='unit'?(item?.unit_price || ''):key==='fee'?'0.00':'');input.dataset.invoiceField=key;input.id=`purchase-line-${row.dataset.lineKey}-${key}`;input.inputMode=key==='quantity'?'numeric':'decimal';input.setAttribute('aria-label',`${key[0].toUpperCase()+key.slice(1)} for ${item?.name || 'Other item'}`);cell.append(input);row.append(cell);});
        const total=td();total.dataset.lineTotal='';total.className='table-numeric';total.textContent='—';const actions=td(),basis=field('basis','unit');basis.type='hidden';basis.dataset.lineBasis='';const recalculate=document.createElement('button');recalculate.type='button';recalculate.dataset.recalculate='';recalculate.textContent='Recalculate';actions.append(basis,recalculate);row.append(total,actions);body.append(row);calculate(row,'unit');return row;
    };
    form.querySelector('[data-add-other]')?.addEventListener('click',()=>{if(progress.isBusy() || invoiceRows().length>=1000)return;const row=append({});regroup();markDirty();row.querySelector('[data-invoice-description]').focus();});
    body?.addEventListener('click',event=>{const remove=event.target.closest('[data-remove-invoice]');if(!remove || progress.isBusy())return;remove.closest('[data-invoice-line]').remove();regroup();markDirty();});
    form.addEventListener('change',event=>{
        if(!event.target.matches('[data-link-catalog]') || progress.isBusy())return;
        const row=event.target.closest('[data-invoice-line]'),id=event.target.value;
        if(id && invoiceRows().some(other=>other!==row && other.querySelector('[data-invoice-item]').value===id)){event.target.value=row.querySelector('[data-invoice-item]').value;const feedback=document.querySelector('[data-progress-feedback]');feedback.hidden=false;feedback.textContent='That catalog item is already on this order.';return;}
        row.querySelector('[data-invoice-item]').value=id;
        row.dataset.group=id?event.target.selectedOptions[0].dataset.group:'Other items';row.querySelector('[data-invoice-group]').value=row.dataset.group;
        const description=row.querySelector('[data-invoice-description]');description.readOnly=Boolean(id);if(id)description.value=event.target.selectedOptions[0].textContent;regroup();markDirty();
    });
    if(searchRoot)mountPurchasePicker({root:searchRoot,results:searchResults,feedback:form.querySelector('[data-purchase-search-feedback]'),button:form.querySelector('[data-add-selected]'),stage:form.elements.mode.value,
        getIds:()=>invoiceRows().map(row=>row.querySelector('[data-invoice-item]').value).filter(Boolean),getCount:()=>invoiceRows().length,isBusy:()=>progress.isBusy(),onAdd:selections=>{let first;selections.forEach(selection=>{const row=append(selection);first ||= row;});regroup();markDirty();first?.querySelector('[data-invoice-field="quantity"]').focus();}});
    mountQuantityNavigation(form, { resolveFields: input => input.matches('[data-invoice-field="quantity"]') ? form.querySelectorAll('[data-invoice-field="quantity"]') : input.matches('[data-search-quantity]') ? form.querySelectorAll('[data-search-quantity]') : [] });
})();
