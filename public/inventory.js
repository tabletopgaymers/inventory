(() => {
  const editor = document.querySelector('[data-stock-editor]');
  const integer = (input, blankZero = false) => {
    const text = input.value;
    if (blankZero && text === '') return 0n;
    if (!/^[+-]?\d{1,10}$/.test(text)) return null;
    const value = BigInt(text);
    return value >= -1000000000n && value <= 1000000000n ? value : null;
  };
  if (editor) {
    const update = () => {
      let total = 0n, valid = true;
      editor.querySelectorAll('[data-stock-row]').forEach(row => {
        const set = integer(row.querySelector('[data-set]'));
        const adjust = integer(row.querySelector('[data-adjust]'), true);
        const value = set === null || adjust === null ? null : set + adjust;
        const safe = value !== null && value >= -1000000000n && value <= 1000000000n;
        row.querySelector('[data-new]').textContent = safe ? value.toLocaleString('en-US') : 'Invalid';
        row.querySelector('[data-set]').setCustomValidity(safe ? '' : 'Enter whole numbers with a new quantity between −1,000,000,000 and 1,000,000,000.');
        if (safe) total += value; else valid = false;
      });
      editor.querySelector('[data-new-total]').textContent = valid ? total.toLocaleString('en-US') : 'Invalid';
    };
    editor.addEventListener('input', update); update();
  }
  const form = document.querySelector('[data-dirty-warning], [data-review-warning]');
  if (form) {
    const initial = new URLSearchParams(new FormData(form)).toString();
    let leaving = false;
    const dirty = () => form.hasAttribute('data-review-warning') || form.hasAttribute('data-pending-draft') || new URLSearchParams(new FormData(form)).toString() !== initial;
    form.addEventListener('submit', () => { leaving = true; });
    document.querySelectorAll('a').forEach(link => link.addEventListener('click', event => {
      if (leaving || !dirty()) return;
      if (form.hasAttribute('data-review-warning') && !link.hasAttribute('data-discard')) {
        // Edit retains the draft. History links still warn before leaving review.
        if (link.getAttribute('href')?.includes('/edit?operation=')) { leaving = true; return; }
      }
      if (!window.confirm('Discard your unsaved correction? Choose Cancel to keep editing.')) event.preventDefault();
      else leaving = true;
    }));
    window.addEventListener('beforeunload', event => { if (!leaving && dirty()) { event.preventDefault(); event.returnValue = ''; } });
  }
  document.querySelectorAll('[data-history-url]').forEach(row => row.addEventListener('click', event => {
    if (!event.target.closest('a')) window.location.href = row.dataset.historyUrl;
  }));
})();
