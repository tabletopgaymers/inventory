@extends('design.phase-a.layout')
@section('title', 'Forms')
@section('intro', 'Field types, value semantics, buttons, saving workflows and validation feedback.')
@section('guide-content')
<nav class="app-section-nav" aria-label="On this page"><a href="#fields">Form fields</a><a href="#buttons">Buttons</a><a href="#saving-workflows">Saving workflows</a><a href="#errors">Error handling</a></nav>
<section id="fields" aria-labelledby="fields-title">
<div class="app-content">
    <h2 id="fields-title">Form fields</h2>
    <p>Fields are required unless explicitly labeled “optional.” Keep labels visible and mark optional fields consistently. Read-only fields display information; disabled controls indicate unavailable interaction.</p>
    <p>Focused text fields, text areas and dropdowns use a thicker purple edge without changing their size or moving their contents. Apply this through the shared field styles, including composite SKU fields; buttons, links and choice controls retain their keyboard focus indicators.</p>
    <label>Text field<input name="guide_title" value="Sample supply request" required></label>
    <label>Text area<textarea name="guide_details" data-optional rows="2">New display stands, quantity 2.</textarea></label>
    <label>Dropdown<select name="guide_location" required><option>Central</option><option>Sample storage</option></select></label>
    <label>Email<input type="email" value="sample@example.test" required></label>
    <label>Date field<input type="date" value="2026-10-11" required></label>
    <label>Account field<input id="guide-readonly-text" value="sample@example.test" readonly></label>
    <label>Value field (USD)<input id="guide-readonly-value" value="0.125" readonly></label>
    <label>Disabled input<input value="Unavailable" disabled></label>
    <label>Disabled dropdown<select disabled><option>Sample storage</option></select></label>
    <label>Disabled text area<textarea rows="2" disabled>Saved details cannot be edited here.</textarea></label>
    <fieldset disabled><legend>Disabled field group</legend><label>Text field<input value="Unavailable through its group"></label><label class="app-choice"><input type="checkbox" checked> Unavailable checkbox</label><label class="app-choice"><input type="radio" checked> Unavailable radio button</label></fieldset>
    <fieldset><legend>Checkbox group</legend><label class="app-choice"><input type="checkbox" checked> Ribbon : Pronoun</label><label class="app-choice"><input type="checkbox"> Pin : Sample collection</label></fieldset>
    <fieldset><legend>Radio-button group</legend><label class="app-choice"><input type="radio" name="guide_price_basis" checked> Unit price</label><label class="app-choice"><input type="radio" name="guide_price_basis"> Line total</label></fieldset>
    <h3>Numeric values</h3>
    <p>Positive and negative values describe direction where the field permits signed values. Zero is an explicit value; blank means no value has been supplied. Validation follows each field's business rules: quantities and prices do not automatically permit negatives.</p>
    <label>Positive value<input inputmode="numeric" value="25" required></label>
    <label>Negative value<input inputmode="numeric" value="-25" required></label>
    <label>Zero value<input inputmode="numeric" value="0" required></label>
    <label>Blank value<input data-optional inputmode="numeric" aria-describedby="guide-blank-help"></label>
    <p id="guide-blank-help" class="app-field-help">Blank is not zero. Preserve that distinction in displays and saved values.</p>
    <p class="app-field-help">These sample fields demonstrate appearance and have no submission.</p>
</div>
</section>
<x-app.guide-divider />
<section id="buttons" aria-labelledby="buttons-title">
<div class="app-content">
    <h2 id="buttons-title">Buttons</h2>
    <div class="actions app-user-actions"><button type="button" class="primary">Save</button><a class="button" href="#fields">Edit</a><button type="button" class="app-new-action">+ New</button><button type="button" class="discard">Discard</button><button type="button" disabled>Unavailable</button></div>
    <p>Use primary buttons for Create and Save, a neutral action for Edit and a green + New action to open a creation form. Give destructive actions explicit labels. One disabled example is sufficient to show the shared unavailable appearance.</p>
</div>
</section>
<x-app.guide-divider />
<section id="saving-workflows" aria-labelledby="saving-workflows-title">
<div class="app-content">
    <h2 id="saving-workflows-title">Saving workflows</h2>
    <p>Choose the saving process according to the task: completing one record or preserving work within a longer process. These names identify the interaction patterns; use the button labels below on the actual forms.</p>
    <h3>Record save</h3>
    <p>Use for a single-record form, such as a Category, Collection or Supplier. Creating or editing the record is the complete task, so a successful save opens its view page.</p>
    <ul>
        <li>Place Cancel and the primary action in the title area. Label the action Create for a new record and Save for an existing record.</li>
        <li>While submitting, show Creating… or Saving… with a spinner, disable duplicate submission and lock the fields.</li>
        <li>On success, keep the spinner and fields locked until the record view loads. Show the creation or save confirmation at the top of that view.</li>
        <li>Cancel returns to the index for a new record or the record view when editing. If there are unsaved changes, show the Keep editing / Leave without saving modal first.</li>
    </ul>
    <p class="app-field-help">Catalog New and Edit forms use this process for Categories, Collections, Items, Locations, Suppliers, Purposes and Programs.</p>
    <div class="actions app-user-actions">
        <button type="button">Cancel</button>
        <button type="button" class="primary app-save-progress">Create</button>
        <button type="button" class="primary app-save-progress app-save-dirty" aria-label="Create with unsaved changes">Create</button>
        <button type="button" class="primary app-save-progress" disabled aria-busy="true">Creating… <span class="app-search-spinner" aria-hidden="true"></span></button>
        <button type="button" class="primary app-save-progress">Save</button>
        <button type="button" class="primary app-save-progress app-save-dirty" aria-label="Save with unsaved changes">Save</button>
        <button type="button" class="primary app-save-progress" disabled aria-busy="true">Saving… <span class="app-search-spinner" aria-hidden="true"></span></button>
    </div>
    <p class="app-field-help">Create and Save retain their labels when edits are unsaved; the changed appearance identifies pending work. Busy examples stay disabled until the record view loads. These are visual examples only.</p>
    <h3>Save progress</h3>
    <p>Use for a multi-step workflow, such as a Purchase or Relocation, where users work across several pages and may need to pause and resume.</p>
    <ul>
        <li>Place Save progress in the title area and indicate when there are unsaved changes.</li>
        <li>While saving, show Saving… with a spinner and disable duplicate submission.</li>
        <li>On success, stay on the current step, restore the Save progress button to its saved appearance and show confirmation at the top of the working page.</li>
        <li>Keep Next, Review, Submit and final confirmation actions separate. Saving progress does not itself complete the workflow or perform its final inventory action.</li>
        <li>Use the unsaved-changes modal before leaving unsaved work. Longer confirmations belong on a Review page.</li>
    </ul>
    <div class="actions app-user-actions">
        <button type="button" class="primary app-save-progress">Save progress</button>
        <button type="button" class="primary app-save-progress app-save-dirty">Save progress — unsaved</button>
        <button type="button" class="primary app-save-progress" disabled aria-busy="true">Saving… <span class="app-search-spinner" aria-hidden="true"></span></button>
        <button type="button" class="primary app-save-progress" disabled>Save progress — unavailable</button>
        <button type="button" class="primary app-save-progress app-save-dirty" disabled>Save progress — unsaved and unavailable</button>
    </div>
    <p class="app-field-help">Purchase and Relocation use these states for saved work, unsaved edits, a save in progress, unavailable saving and unsaved work that cannot currently be saved. These examples do not run a save.</p>
    <p>For both processes, validation failures retain entered values, restore the available controls and show an error summary above the form with field-specific feedback. A save is successful only after the server confirms it.</p>
</div>
</section>
<x-app.guide-divider />
<section id="errors" aria-labelledby="errors-title">
<div class="app-content">
    <h2 id="errors-title">Error handling</h2>
    <div class="error" role="alert"><p>The form could not be saved. Correct the highlighted field and try again.</p><a href="#guide-quantity">Quantity: enter a whole number.</a></div>
    <label>Quantity<input id="guide-quantity" value="two" required aria-invalid="true" aria-describedby="guide-quantity-error"></label>
    <p class="app-field-error" id="guide-quantity-error">Enter a whole quantity.</p>
    <p>Show a general error summary at the top of the form and a specific message beside the affected field. Link summary errors to their fields, preserve entered values and keep errors visible until resolved.</p>
</div>
</section>
@endsection
