// Business status stays intact; the server supplies the one status-to-tone map.
/**
 * Applies the server-provided tone and label map to the supplied badge while keeping the underlying status value. Requires the shared status badge configuration on the page.
 *
 * Scope: Shared
 * Task: Refresh a rendered status badge
 * @example resources/js/relocation.js
 */
export function updateStatusBadge(badge, status, { label = null } = {}) {
    if (!badge) return;
    const config = JSON.parse(document.querySelector('[data-status-badge-config]').textContent);
    badge.dataset.status = status;
    badge.dataset.statusTone = config.statuses[String(status).trim().toLowerCase()] || config.default;
    badge.textContent = label ?? config.labels?.[String(status).trim().toLowerCase()] ?? status;
}
