@extends('design.phase-a.layout')
@section('title', 'Forms')
@section('intro', 'Shared fields, form actions, and feedback.')
@section('guide-content')
<nav class="app-section-nav" aria-label="On this page">
    <a href="#fields">Form fields</a>
    <a href="#single-record-forms">Single-record forms</a>
    <a href="#errors">Validation</a>
    <a href="#buttons">Buttons</a>
    <a href="#saving-workflows">Save progress</a>
    <a href="#field-states">Field states</a>
    <a href="#special-controls">Special controls</a>
</nav>

<section class="app-content" id="fields" aria-labelledby="fields-title">
    <h2 id="fields-title">Form fields</h2>
    <p>Use visible labels. Fields are required unless marked optional. Use <code>data-optional</code> for optional fields so the shared form code can add the label suffix. Shared focus styles highlight the active field without shifting the layout.</p>
    <div class="app-columns app-columns-two app-columns-fields">
        <div>
            <x-app.field label="Title"><input name="guide_title" value="Sample supply request" required></x-app.field>
            <x-app.field label="Location"><select name="guide_location" required><option>Central</option><option>Sample storage</option></select></x-app.field>
            <x-app.field label="Email"><input type="email" value="sample@example.test" required></x-app.field>
        </div>
        <div>
            <x-app.field label="Date"><input type="date" value="2026-10-11" required></x-app.field>
            <x-app.field label="Details"><textarea name="guide_details" data-optional rows="2">New display stands, quantity 2.</textarea></x-app.field>
        </div>
    </div>
    <div class="app-columns app-columns-two">
        <fieldset>
            <legend>Collections</legend>
            <label class="app-choice"><input type="checkbox" checked> Ribbon : Pronoun</label>
            <label class="app-choice"><input type="checkbox"> Pin : Sample collection</label>
        </fieldset>
        <fieldset>
            <legend>Price basis</legend>
            <label class="app-choice"><input type="radio" name="guide_price_basis" checked> Unit price</label>
            <label class="app-choice"><input type="radio" name="guide_price_basis"> Line total</label>
        </fieldset>
    </div>
    <p class="app-field-help">Use checkboxes for independent choices and radio buttons for one choice in a group.</p>
</section>

<section class="app-content" id="single-record-forms" aria-labelledby="single-record-forms-title">
    <h2 id="single-record-forms-title">Single-record forms</h2>
    <p>Use this pattern when one form creates or edits a record. Put the submit and leave actions beside the page title.</p>
    <x-app.table-region label="Single-record form actions">
        <table>
            <thead><tr><th scope="col">Action</th><th scope="col">New record</th><th scope="col">Existing record</th></tr></thead>
            <tbody>
                <tr><th scope="row">Open</th><td><x-app.action variant="new" href="#fields" /> <code>new</code></td><td><x-app.action variant="secondary" href="#fields">Edit</x-app.action> <code>secondary</code></td></tr>
                <tr><th scope="row">Submit</th><td><x-app.action variant="primary" type="submit">Create</x-app.action> <code>primary</code></td><td><x-app.action variant="primary" type="submit">Save</x-app.action> <code>primary</code></td></tr>
                <tr><th scope="row">Leave</th><td><x-app.action variant="danger" href="#fields">Cancel</x-app.action> <code>danger</code></td><td><x-app.action variant="danger" href="#fields">Discard</x-app.action> <code>danger</code></td></tr>
            </tbody>
        </table>
    </x-app.table-region>
    <ul>
        <li>Cancel returns to the starting page. Discard returns to the record view. Prompt before leaving a form with unsaved changes.</li>
        <li>Keep Create or Save as the label while edits are pending. During submission, show Creating… or Saving… with a spinner, lock fields, and prevent another submit. After success, open the record and show confirmation.</li>
    </ul>
    <div class="app-actions" aria-label="Record save states">
        <x-app.action variant="primary" type="submit" class="app-save-progress">Create</x-app.action>
        <x-app.action variant="primary" type="submit" class="app-save-progress app-save-dirty" aria-label="Create with unsaved changes">Create</x-app.action>
        <x-app.action variant="primary" type="submit" class="app-save-progress" :disabled="true" aria-busy="true">Creating… <span class="app-search-spinner" aria-hidden="true"></span></x-app.action>
    </div>
</section>

<section class="app-content" id="errors" aria-labelledby="errors-title">
    <h2 id="errors-title">Validation and errors</h2>
    <p>Preserve entered values and re-enable the form when validation fails. Show an error summary above the fields, link each error to its field, and keep the field message visible until it is resolved.</p>
    <div class="error" role="alert">
        <p>The form could not be saved.</p>
        <a href="#guide-quantity">Quantity: enter a whole number.</a>
    </div>
    <x-app.field label="Quantity" for="guide-quantity" error="Enter a whole number.">
        <input id="guide-quantity" inputmode="numeric" value="two" required aria-invalid="true" aria-describedby="guide-quantity-error">
    </x-app.field>
</section>

<section class="app-content" id="buttons" aria-labelledby="buttons-title">
    <h2 id="buttons-title">Buttons</h2>
    <p>Use <code>x-app.action</code>. The default is a non-submitting button. Use <code>href</code> for navigation and <code>type="submit"</code> to submit a form. Button labels use semibold (600) through <code>--app-button-weight</code>.</p>
    <x-app.table-region label="Button variants">
        <table>
            <thead><tr><th scope="col">Variant</th><th scope="col">Example</th><th scope="col">Use</th></tr></thead>
            <tbody>
                <tr><th scope="row"><code>secondary</code> (default)</th><td><x-app.action variant="secondary" href="#fields">Edit</x-app.action></td><td>A neutral action.</td></tr>
                <tr><th scope="row"><code>primary</code></th><td><x-app.action variant="primary" type="submit">Save</x-app.action></td><td>The main action.</td></tr>
                <tr><th scope="row"><code>new</code></th><td><x-app.action variant="new" href="#fields" /></td><td>Open a creation form. The component supplies ＋ New.</td></tr>
                <tr><th scope="row"><code>danger</code></th><td><x-app.action variant="danger" href="#fields">Discard</x-app.action></td><td>Leave a single-record form or offer an outlined destructive action.</td></tr>
                <tr><th scope="row"><code>destructive</code></th><td><x-app.action variant="destructive">Confirm removal</x-app.action></td><td>Confirm a destructive change. Name the action in the label.</td></tr>
                <tr><th scope="row"><code>changed</code></th><td><x-app.action variant="changed">Replace supplier</x-app.action></td><td>Apply a pending replacement.</td></tr>
            </tbody>
        </table>
    </x-app.table-region>
    <p>Standard buttons follow display density. Use <code>size="small"</code> for compact actions and <code>:disabled="true"</code> when an action is unavailable.</p>
    <div class="app-actions">
        <x-app.action size="small">Print</x-app.action>
        <x-app.action variant="primary" :disabled="true">Unavailable</x-app.action>
    </div>
</section>

<section class="app-content" id="saving-workflows" aria-labelledby="saving-workflows-title">
    <h2 id="saving-workflows-title">Save progress</h2>
    <p>Use <code>x-workflow.save-progress</code> in the title area of a multi-step workflow such as Purchase or Relocation. A successful save keeps the user on the current step and shows confirmation. Next, Submit, and final confirmation have their own actions.</p>
    <p>Show pending edits with the dirty state. During a save, show Saving… with a spinner and prevent duplicate submissions. Prompt before leaving with unsaved changes.</p>
    <div class="app-actions">
        <x-workflow.save-progress aria-label="Save progress, clean" />
        <x-workflow.save-progress class="app-save-dirty" aria-label="Save progress with unsaved edits" />
        <x-app.action variant="primary" type="submit" class="app-save-progress" :disabled="true" aria-busy="true">Saving… <span class="app-search-spinner" aria-hidden="true"></span></x-app.action>
        <x-workflow.save-progress :disabled="true" aria-label="Save progress, unavailable" />
    </div>
</section>

<section class="app-content" id="field-states" aria-labelledby="field-states-title">
    <h2 id="field-states-title">Field states and numeric values</h2>
    <div class="app-columns app-columns-two app-columns-fields">
        <div>
            <h3>Read-only and disabled</h3>
            <p>Use read-only when a value should remain available for reference. Use disabled for unavailable controls. Fields and buttons share the disabled colors from <code>resources/css/control-states.css</code>.</p>
            <x-app.field label="Account"><input value="sample@example.test" readonly></x-app.field>
            <fieldset disabled>
                <legend>Unavailable options</legend>
                <x-app.field label="Location"><select><option>Sample storage</option></select></x-app.field>
                <label class="app-choice"><input type="checkbox" checked> Include supplies</label>
            </fieldset>
        </div>
        <div>
            <h3>Numeric values</h3>
            <p>Preserve the difference between blank and zero. Allow negative values only where the field’s business rules permit them.</p>
            <div class="app-columns app-columns-two app-columns-fields">
                <div>
                    <x-app.field label="Positive"><input inputmode="numeric" value="25" required></x-app.field>
                    <x-app.field label="Negative"><input inputmode="numeric" value="-25" required></x-app.field>
                </div>
                <div>
                    <x-app.field label="Zero"><input inputmode="numeric" value="0" required></x-app.field>
                    <x-app.field label="Blank"><input inputmode="numeric" data-optional></x-app.field>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="app-content" id="special-controls" aria-labelledby="special-controls-title">
    <h2 id="special-controls-title">Special controls</h2>
    <p>Set the <code>control</code> type. Give icon-only controls an accessible label that names the action.</p>
    <x-app.table-region label="Special button controls">
        <table>
            <thead><tr><th scope="col">Control</th><th scope="col">Example</th><th scope="col">Use</th></tr></thead>
            <tbody>
                <tr><th scope="row"><code>delete</code></th><td><x-app.action control="delete" aria-label="Remove sample item">✕</x-app.action></td><td>Remove an item or row.</td></tr>
                <tr><th scope="row"><code>clear</code></th><td><x-app.action control="clear" aria-label="Clear sample search">✕</x-app.action></td><td>Clear a field or search value.</td></tr>
                <tr><th scope="row"><code>dismiss</code></th><td><x-app.action control="dismiss" aria-label="Dismiss sample notice">×</x-app.action></td><td>Dismiss a notice.</td></tr>
                <tr><th scope="row"><code>filter</code></th><td><x-app.action control="filter" aria-label="Filter by status"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 5h18l-7 8v6l-4 2v-8z" /></svg></x-app.action></td><td>Open or apply a compact filter.</td></tr>
                <tr><th scope="row"><code>copy</code></th><td><x-app.action control="copy" aria-label="Copy sample version">main · a1b2c3d</x-app.action></td><td>Copy visible information while retaining its text style.</td></tr>
            </tbody>
        </table>
    </x-app.table-region>
    <p>For controls created in JavaScript, use <code>createAction()</code> from <code>resources/js/actions.js</code>. It uses the shared Blade templates. Supply the accessible name and behavior in the caller.</p>
</section>
@endsection
