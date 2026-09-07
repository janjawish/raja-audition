document.addEventListener('DOMContentLoaded', () => {
  const body = document.body;
  document.querySelectorAll('[data-menu-toggle]').forEach((button) => {
    button.addEventListener('click', () => body.classList.toggle('menu-open'));
  });

  document.querySelectorAll('[data-dismiss]').forEach((button) => {
    button.addEventListener('click', () => button.closest('.notice')?.remove());
  });

  document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
      if (!window.confirm(form.dataset.confirm || 'Confirmer cette action ?')) event.preventDefault();
    });
  });

  document.querySelectorAll('[data-client-filter]').forEach((input) => {
    input.addEventListener('input', () => {
      const needle = input.value.trim().toLowerCase();
      document.querySelectorAll(input.dataset.clientFilter).forEach((row) => {
        row.hidden = needle !== '' && !row.textContent.toLowerCase().includes(needle);
      });
    });
  });

  document.querySelectorAll('[data-stock-toggle]').forEach((select) => {
    const update = () => {
      document.querySelectorAll('[data-stock-dependent]').forEach((field) => {
        field.hidden = select.value !== '0';
      });
    };
    select.addEventListener('change', update);
    update();
  });

  document.querySelectorAll('[data-file-label]').forEach((input) => {
    input.addEventListener('change', () => {
      const output = document.querySelector(input.dataset.fileLabel);
      if (output) output.textContent = input.files?.[0]?.name || 'Aucun fichier choisi';
    });
  });
});
