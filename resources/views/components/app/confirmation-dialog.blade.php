{{--
Include this shared native dialog once in the shell. Call window.appRequestDecision to set the title, message, actions, and confirmation behavior for a particular operation.

## Slots

No caller slots.

## Example

[Application shell](resources/views/components/app/shell.blade.php) and [Notifications](/style-guide/notifications#confirmations).
--}}<dialog id="app-confirmation-dialog" class="app-user-disable-dialog" aria-labelledby="app-confirmation-title" aria-describedby="app-confirmation-message" data-confirmation-dialog data-notice-dismissible="false">
    <h2 id="app-confirmation-title" data-confirmation-title>Confirm action</h2>
    <p id="app-confirmation-message" data-confirmation-message></p>
    <div class="actions">
        <x-app.action type="button" data-confirmation-cancel autofocus>Cancel</x-app.action>
        <x-app.action type="button" data-confirmation-continue>Continue</x-app.action>
    </div>
</dialog>
