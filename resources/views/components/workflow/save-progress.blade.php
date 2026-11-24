{{--
Use for a workflow’s save-without-advancing action. It submits action=save and supplies the shared Save progress label, spinner, and shortcut hook. The consuming workflow manages save states and results. Pass form when the button sits outside the form.

## Slots

No caller slots.

## Examples

[Purchase work](resources/views/purchase/work.blade.php) and [Saving workflows](/style-guide/forms#saving-workflows).
--}}<x-app.action variant="primary" type="submit" name="action" value="save" {{ $attributes->class(['app-save-progress']) }} data-save-shortcut aria-busy="false"><span data-save-label>Save progress</span><span class="app-search-spinner" aria-hidden="true"></span></x-app.action>
