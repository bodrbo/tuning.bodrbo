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

function initLeadPhoto(form) {
  const field = document.createElement('div');
  field.className = 'lead-photo-field form-wide';
  const id = form.id + '-photo';
  field.innerHTML = `
    <label for="${id}">Фотография лодки <small>необязательно</small></label>
    <input id="${id}" type="file" name="photo" accept="image/jpeg,image/png,image/webp" aria-describedby="${id}-status">
    <p id="${id}-status" class="lead-photo-hint" aria-live="polite">JPG, PNG или WebP · до 5 МБ</p>
    <div class="lead-photo-preview" hidden><img alt="Прикреплённая фотография"><div><span></span><button type="button">Удалить фото</button></div></div>`;
  form.enctype = 'multipart/form-data';
  form.querySelector('button[type="submit"]').before(field);
  const input = field.querySelector('input');
  const status = field.querySelector('.lead-photo-hint');
  const preview = field.querySelector('.lead-photo-preview');
  const image = preview.querySelector('img');
  let imageUrl = '';
  const clearPreview = () => {
    if (imageUrl) URL.revokeObjectURL(imageUrl);
    imageUrl = '';
    image.removeAttribute('src');
    preview.hidden = true;
    input.setCustomValidity('');
    status.textContent = 'JPG, PNG или WebP · до 5 МБ';
    delete status.dataset.error;
  };
  input.addEventListener('change', () => {
    clearPreview();
    const file = input.files[0];
    if (!file) return;
    let error = '';
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) error = 'Выберите фотографию JPG, PNG или WebP.';
    else if (file.size > 5 * 1024 * 1024) error = 'Фотография больше 5 МБ. Уменьшите файл или выберите другой.';
    if (error) {
      input.setCustomValidity(error);
      status.textContent = error;
      status.dataset.error = 'true';
      preview.querySelector('span').textContent = file.name;
      image.hidden = true;
      preview.hidden = false;
      return;
    }
    imageUrl = URL.createObjectURL(file);
    image.src = imageUrl;
    image.hidden = false;
    preview.querySelector('span').textContent = file.name;
    preview.hidden = false;
  });
  preview.querySelector('button').addEventListener('click', () => {
    input.value = '';
    clearPreview();
    input.focus();
  });
  form.addEventListener('reset', clearPreview);
}

function initBodrboLeadForm(leadForm) {
  if (leadForm.dataset.leadReady) return;
  leadForm.dataset.leadReady = 'true';
  initLeadPhoto(leadForm);
  const requestIdField = leadForm.elements.request_id;
  const sourceUrlField = leadForm.elements.source_url;
  const submitButton = leadForm.querySelector('button[type="submit"]');
  const submitLabel = submitButton?.textContent || 'Получить консультацию';
  const toast = document.querySelector('.toast');
  const status = leadForm.querySelector('[data-lead-status]');
  leadForm.addEventListener('input', () => {
    if (status?.dataset.state === 'success') {
      status.textContent = '';
      delete status.dataset.state;
    }
  });
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
    if (status) {
      status.textContent = message;
      status.dataset.state = isError ? 'error' : 'success';
      status.setAttribute('role', isError ? 'alert' : 'status');
      return;
    }
    if (!toast) return;
    clearTimeout(toastTimer);
    toast.textContent = message;
    toast.classList.toggle('toast--error', isError);
    toast.setAttribute('role', isError ? 'alert' : 'status');
    toast.classList.add('show');
    toastTimer = setTimeout(() => toast.classList.remove('show'), 5200);
  };

  prepareSubmission();

  if (leadForm.id === 'lead-form') {
    document.querySelectorAll('[data-request-model]').forEach(link => {
      link.addEventListener('click', () => {
        const modelField = leadForm.elements.boat_model;
        if (modelField) {
          modelField.value = link.dataset.requestModel || '';
          modelField.dispatchEvent(new Event('input', { bubbles: true }));
        }
      });
    });
  }

  leadForm.addEventListener('submit', async event => {
    event.preventDefault();
    if (submitButton?.disabled) return;
    prepareSubmission();
    if (status) {
      status.textContent = '';
      delete status.dataset.state;
    }

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
      const message = error.status === 413 || error.status === 422
        ? error.message
        : error.status === 429
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
document.querySelectorAll('#lead-form, [data-lead-form]').forEach(initBodrboLeadForm);

const callbackWidget = document.createElement('script');
callbackWidget.src = '/callback-widget.js?v=2';
document.head.append(callbackWidget);
