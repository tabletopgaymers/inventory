@extends('design.phase-a.layout')
@section('title', 'Notifications')
@section('intro', 'Notices, confirmation dialogs and warnings about unsaved work.')
@section('guide-content')
<nav class="app-section-nav" aria-label="On this page"><a href="#notices">Notices</a><a href="#confirmations">Confirmations</a></nav>
<section id="notices" aria-labelledby="notices-title">
<div class="app-content">
    <h2 id="notices-title">Notices</h2>
    <p class="notice" role="status">Changes saved. Sample success notice.<br>This multiline message reserves room beside the dismiss control. <a href="/design/phase-a/forms">View the form examples</a>.</p>
    <p class="warning" role="status">Working changes have not been saved. Sample warning notice.</p>
    <p class="error" role="alert">The record could not be saved. Your entered values have been retained.</p>
    <p>Use success, warning and error notices for feedback. Success and warning notices can be dismissed for the current page using ×; error notices remain visible. New feedback may appear after an earlier notice is dismissed.</p>
</div>
</section>
<x-app.guide-divider />
<section id="confirmations" aria-labelledby="confirmations-title">
<div class="app-content">
    <h2 id="confirmations-title">Confirmation dialogs</h2>
    <p>Brief confirmations belong in modal dialogs rather than separate pages. Name the action, explain its effect and offer Cancel plus a clearly labeled confirmation button. Focus remains inside the modal; Cancel and Escape return focus to its trigger.</p>
    <button type="button" data-guide-decision="Apply the selected sample setting?" data-guide-decision-label="Apply setting" aria-haspopup="dialog">Preview confirmation</button>
    <h3>High-impact action confirmation</h3>
    <p>Ask for explicit confirmation before a risky action or one with a potentially large impact. Identify the affected record, describe what will happen and distinguish reversible actions from irreversible ones. Use a destructive-action button where appropriate.</p>
    <button type="button" class="discard" data-guide-open="guide-disable-dialog" aria-haspopup="dialog" aria-controls="guide-disable-dialog">Preview disable account</button>
    <h3>Unsaved changes warning</h3>
    <p>Before navigating away from unsaved work, use a modal with Keep editing and Leave without saving. Keep editing and Escape preserve the current work. This is a decision dialog, separate from a dismissible notice.</p>
    <button type="button" data-guide-decision="Your changes have not been saved. Keep editing or leave without saving." data-guide-decision-label="Leave without saving" aria-haspopup="dialog">Preview unsaved changes warning</button>
    <h3>Long-form review</h3>
    <p>Review pages remain appropriate when users need to inspect many fields, item quantities or calculation details before confirming. Use a full review page for that work; reserve modals for brief, focused decisions.</p>
    <p id="guide-decision-result" class="notice" role="status" hidden></p>
    <p id="guide-disable-result" class="notice" role="status" hidden></p>
</div>
</section>
@include('design.phase-a.disable-preview')
<dialog id="guide-decision-dialog" class="app-user-disable-dialog" aria-labelledby="guide-decision-title" aria-describedby="guide-decision-description">
    <h2 id="guide-decision-title">Confirm action</h2>
    <p id="guide-decision-description"></p>
    <div class="actions"><button type="button" data-guide-close id="guide-decision-cancel" autofocus>Cancel</button><button type="button" data-guide-close data-guide-decision-confirm id="guide-decision-confirm" class="app-user-disable-confirm">Confirm</button></div>
</dialog>
@endsection
