{{--
Include this JSON configuration once in the shell so JavaScript status badges use the same label and tone mapping as Blade.

## Slots

No caller slots.

## Examples

[Application shell](resources/views/components/app/shell.blade.php) and [status badge updater](resources/js/status-badges.js).
--}}<script type="application/json" data-status-badge-config>@json(config('status-badges'))</script>
