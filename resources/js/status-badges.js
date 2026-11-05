// Business status stays intact; the server supplies the one status-to-tone map.
export function updateStatusBadge(badge, status, { label = null } = {}) {
    if (!badge) return;
    const config = JSON.parse(document.querySelector('[data-status-badge-config]').textContent);
    badge.dataset.status = status;
    badge.dataset.statusTone = config.statuses[String(status).trim().toLowerCase()] || config.default;
    badge.textContent = label ?? config.labels?.[String(status).trim().toLowerCase()] ?? status;
}
