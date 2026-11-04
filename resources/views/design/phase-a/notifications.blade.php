@extends('design.phase-a.layout')
@section('title', 'Notifications')
@section('intro', 'Notices, confirmation dialogs and warnings about unsaved work.')
@section('guide-content')
<nav class="app-section-nav" aria-label="On this page"><a href="#notices">Notices</a><a href="#confirmations">Confirmations</a></nav>
<section id="notices" aria-labelledby="notices-title">
<div class="app-content">
    <h2 id="notices-title">Notices</h2>
    <p class="notice" role="status">Changes saved. Sample success notice.<br>This multiline message reserves room beside the dismiss control. <a href="/style-guide/forms">View the form examples</a>.</p>
    <p class="warning" role="status">Working changes have not been saved. Sample warning notice.</p>
    <p class="error" role="alert">The record could not be saved. Your entered values have been retained.</p>
    <p>Use success, warning and error notices for feedback. Success and warning notices can be dismissed for the current page using ×; error notices remain visible. New feedback may appear after an earlier notice is dismissed.</p>
</div>
</section>
<x-app.guide-divider />
<section id="confirmations" aria-labelledby="confirmations-title">
<div class="app-content">
    <h2 id="confirmations-title">Confirmation dialogs</h2>
    <p>Brief confirmations belong in modal dialogs rather than separate pages. Name the action, explain its effect and offer Cancel plus a clearly labeled confirmation button. Unsaved-change decisions use Keep editing and Leave without saving. Focus remains inside the modal; dismissal and Escape return focus to its trigger. These previews invoke the same appRequestDecision modal used by Purchase and Relocation; confirming only displays sample feedback.</p>
    <x-app.action type="button" data-guide-decision="Apply the selected sample setting?" data-guide-decision-label="Apply setting" aria-haspopup="dialog">Preview confirmation</x-app.action>
    <h3>High-impact action confirmation</h3>
    <p>Ask for explicit confirmation before a risky action or one with a potentially large impact. Identify the affected record, describe what will happen and distinguish reversible actions from irreversible ones. Use a destructive-action button where appropriate.</p>
    <x-app.action variant="danger" type="button"  data-guide-open="guide-disable-dialog" aria-haspopup="dialog">Preview disable account</x-app.action>
    <h3>Unsaved changes warning</h3>
    <p>Before navigating away from unsaved work, use a modal with Keep editing and Leave without saving. Keep editing and Escape preserve the current work. This is a decision dialog, separate from a dismissible notice.</p>
    <x-app.action type="button" data-guide-decision="Your changes have not been saved. Keep editing or leave without saving." data-guide-decision-label="Leave without saving" aria-haspopup="dialog">Preview unsaved changes warning</x-app.action>
    <h3>Shared implementation</h3>
    <p>The application shell renders <code>x-app.confirmation-dialog</code> once. <code>workflow/confirmation-dialog.js</code> owns opening, labels, focus, dismissal and one-shot callbacks. Use the options API below; keep record updates and form submission in the caller. Text is rendered as text, never HTML. One active decision is allowed; repeated requests focus the existing dialog.</p>
@verbatim
    <pre class="guide-search-data"><code>window.appRequestDecision({
    title: 'Confirm action',
    message: 'Continue with this change?',
    cancelLabel: 'Cancel',
    confirmLabel: 'Continue',
    variant: 'primary', // or 'danger'
    origin: triggerButton,
    onConfirm: () =&gt; performAction(),
    onCancel: () =&gt; { /* optional */ }
});</code></pre>
@endverbatim
    <p>Purchase and Relocation normally call <code>mountUnsavedChanges(...).confirm(callback, options)</code>, which delegates to this same dialog and owns the departure flag. Plain Cancel/Escape preserves edits and returns focus to the trigger. Confirmation closes the dialog before running the caller’s action once. Native browser reload/close warnings remain browser-controlled.</p>
    <h3>Long-form review</h3>
    <p>Review pages remain appropriate when users need to inspect many fields, item quantities or calculation details before confirming. Use a full review page for that work; reserve modals for brief, focused decisions.</p>
    <p id="guide-decision-result" class="notice" role="status" hidden></p>
    <p id="guide-disable-result" class="notice" role="status" hidden></p>
</div>
</section>

@endsection
