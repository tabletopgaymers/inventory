@extends('design.phase-a.layout')
@section('title', 'Tab form')
@section('intro', 'Demo values are retained in this browser. Switch tabs to work with different sections of one form.')
@section('guide-content')
<form class="app-panel-stack" id="guide-tab-form">
    <x-workflow.tabs prefix="guide-demo" label="Demo form sections" active="basic" :tabs="['basic'=>'Basic info','material'=>'Material','review'=>'Review','fulfillment'=>'Fulfillment','tracking'=>'Tracking']" />
        <p data-tab-storage-feedback role="status" hidden></p>
        <div class="app-content">
        <x-workflow.heading>
            <h2 data-tab-section-title>Basic info</h2>
            <select name="status" data-tab-field="status" aria-label="Status">
                @foreach(['New', 'Draft', 'Requested', 'Accepted', 'Fulfilled', 'Completed'] as $status)
                <option value="{{ $status }}">{{ $status }}</option>
                @endforeach
            </select>
        </x-workflow.heading>
        <section role="tabpanel" id="guide-demo-panel-basic" aria-labelledby="guide-demo-tab-basic" tabindex="0">
            <p data-tab-unavailable hidden></p>
            <div data-tab-content>
            @foreach(['name', 'source', 'destination'] as $field)
            <label>{{ $field }}<input type="text" name="{{ $field }}" data-tab-field="{{ $field }}"></label>
            @endforeach
            </div>
            <div class="actions"><button type="button" data-tab-action="next">Next ▷</button></div>
        </section>
        <section role="tabpanel" id="guide-demo-panel-material" aria-labelledby="guide-demo-tab-material" tabindex="0" hidden>
            <p data-tab-unavailable hidden></p>
            <div data-tab-content>
            @foreach(['button_type', 'button_request', 'button_fulfilled'] as $field)
            <label>{{ $field }}<input type="text" name="request_{{ $field }}" data-tab-field="{{ $field }}" @readonly($field === 'button_fulfilled')></label>
            @endforeach
            </div>
            <div class="actions"><button type="button" data-tab-action="review">Review</button></div>
        </section>
        <section role="tabpanel" id="guide-demo-panel-review" aria-labelledby="guide-demo-tab-review" tabindex="0" hidden>
            <p data-tab-unavailable hidden></p>
            <div data-tab-content>
            <x-app.detail-list variant="content" >
                @foreach(['name', 'source', 'destination', 'button_type', 'button_request', 'button_fulfilled'] as $field)
                <dt>{{ $field }}</dt><dd data-tab-review="{{ $field }}">—</dd>
                @endforeach
            </x-app.detail-list>
            <label class="app-choice"><input type="checkbox" name="approved" data-tab-field="approved"> approved</label>
            <label>signature<input type="text" name="signature" data-tab-field="signature"></label>
            </div>
            <div class="actions"><button type="button" data-tab-action="submit">Submit</button></div>
        </section>
        <section role="tabpanel" id="guide-demo-panel-fulfillment" aria-labelledby="guide-demo-tab-fulfillment" tabindex="0" hidden>
            <p data-tab-unavailable hidden></p>
            <div data-tab-content>
            @foreach(['button_type', 'button_request', 'button_fulfilled'] as $field)
            <label>{{ $field }}<input type="text" name="fulfillment_{{ $field }}" data-tab-field="{{ $field }}" @readonly($field !== 'button_fulfilled')></label>
            @endforeach
            </div>
            <div class="actions"><button type="button" data-tab-action="accept">Accept</button></div>
        </section>
        <section role="tabpanel" id="guide-demo-panel-tracking" aria-labelledby="guide-demo-tab-tracking" tabindex="0" hidden>
            <p data-tab-unavailable hidden></p>
            <div data-tab-content>
            <template data-tab-shipper-template>
                <fieldset>
                    <legend>Shipper</legend>
                    <label>shipper<select data-shipper-carrier>
                        <option>US Postal</option><option>UPS</option><option>FedEx</option><option>Other</option>
                    </select></label>
                    <label>tracker<input type="text" data-shipper-tracker></label>
                    <button type="button" data-shipper-delete>Delete shipper</button>
                </fieldset>
            </template>
            <div data-tab-shippers></div>
            <button type="button" data-tab-add-shipper>Add shipper</button>
            </div>
            <div class="actions"><button type="button" data-tab-action="ship">Ship</button></div>
        </section>
        </div>
</form>
@endsection
