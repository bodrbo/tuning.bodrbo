const toggle = document.querySelector('.menu-toggle');
const menu = document.querySelector('.mobile-menu');
if (toggle && menu) {
  toggle.addEventListener('click', () => {
    const open = menu.classList.toggle('open');
    toggle.setAttribute('aria-expanded', String(open));
  });
  menu.querySelectorAll('a').forEach(link => link.addEventListener('click', () => {
    menu.classList.remove('open');
    toggle.setAttribute('aria-expanded', 'false');
  }));
}

const leadForm = document.querySelector('#lead-form');
if (leadForm) {
  const requestIdField = leadForm.elements.request_id;
  const sourceUrlField = leadForm.elements.source_url;
  const submitButton = leadForm.querySelector('button[type="submit"]');
  const submitLabel = submitButton?.textContent || 'Получить консультацию';
  const toast = document.querySelector('.toast');
  let toastTimer;

  const createRequestId = () => {
    if (globalThis.crypto?.randomUUID) return globalThis.crypto.randomUUID();
    if (globalThis.crypto?.getRandomValues) {
      const bytes = new Uint8Array(16);
      globalThis.crypto.getRandomValues(bytes);
      return Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('');
    }
    return `${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}`;
  };

  const prepareSubmission = () => {
    if (requestIdField && !requestIdField.value) requestIdField.value = createRequestId();
    if (sourceUrlField) sourceUrlField.value = window.location.href.slice(0, 500);
  };

  const showToast = (message, isError = false) => {
    if (!toast) return;
    clearTimeout(toastTimer);
    toast.textContent = message;
    toast.classList.toggle('toast--error', isError);
    toast.setAttribute('role', isError ? 'alert' : 'status');
    toast.classList.add('show');
    toastTimer = setTimeout(() => toast.classList.remove('show'), 5200);
  };

  prepareSubmission();

  leadForm.addEventListener('submit', async event => {
    event.preventDefault();
    prepareSubmission();

    if (submitButton) {
      submitButton.disabled = true;
      submitButton.setAttribute('aria-busy', 'true');
      submitButton.textContent = 'Отправляем…';
    }

    try {
      const response = await fetch(leadForm.action, {
        method: 'POST',
        headers: { Accept: 'application/json' },
        body: new FormData(leadForm),
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok || !payload.ok) {
        const error = new Error(payload.message || 'Не удалось отправить заявку');
        error.status = response.status;
        throw error;
      }

      leadForm.reset();
      if (requestIdField) requestIdField.value = '';
      prepareSubmission();
      showToast('Спасибо! Заявка отправлена — скоро мы свяжемся с вами.');
    } catch (error) {
      const message = error.status === 429
        ? 'Слишком много попыток. Подождите несколько минут или позвоните нам.'
        : 'Не удалось отправить заявку. Попробуйте ещё раз или позвоните нам.';
      showToast(message, true);
    } finally {
      if (submitButton) {
        submitButton.disabled = false;
        submitButton.removeAttribute('aria-busy');
        submitButton.textContent = submitLabel;
      }
    }
  });
}
