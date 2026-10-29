(() => {
  const formatter = new Intl.DateTimeFormat('th-TH-u-ca-buddhist', {day: 'numeric', month: 'long', year: 'numeric'});
  const refreshers = [];
  const initialize = () => {
    document.querySelectorAll('input[type="date"]').forEach((input, index) => {
      const preview = document.createElement('span');
      preview.className = 'form-text thai-date-preview';
      preview.id = 'thai-date-preview-' + index;
      preview.style.display = 'block';
      preview.setAttribute('aria-live', 'polite');
      input.insertAdjacentElement('afterend', preview);
      input.setAttribute('aria-describedby', [input.getAttribute('aria-describedby'), preview.id].filter(Boolean).join(' '));
      const refresh = () => {
        const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(input.value);
        const date = match ? new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3])) : null;
        preview.textContent = date && !Number.isNaN(date.getTime()) ? formatter.format(date) : '';
      };
      input.addEventListener('input', refresh);
      input.addEventListener('change', refresh);
      refreshers.push(refresh);
      refresh();
    });
    document.addEventListener('shown.bs.modal', () => refreshers.forEach(refresh => refresh()));
    document.addEventListener('reset', () => setTimeout(() => refreshers.forEach(refresh => refresh()), 0));
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, {once: true});
  else initialize();
})();
