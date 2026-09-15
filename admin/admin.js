(() => {
  const form = document.querySelector('#content-form');
  if (!form) return;

  const panels = [...document.querySelectorAll('[data-admin-panel]')];
  const tabs = [...document.querySelectorAll('[data-admin-tab]')];
  const caseButtons = [...document.querySelectorAll('[data-case-select]')];
  const caseEditors = [...document.querySelectorAll('[data-case-editor]')];
  const saveState = document.querySelector('[data-save-state]');
  const toast = document.querySelector('.admin-toast');
  const csrfToken = document.querySelector('meta[name="admin-csrf"]')?.content || '';
  let dirty = false;
  let draggedStep = null;

  const setDirty = () => {
    dirty = true;
    if (saveState) saveState.textContent = 'Есть несохранённые изменения';
    document.body.classList.add('has-unsaved');
  };

  const showToast = (message, error = false) => {
    if (!toast) return;
    toast.textContent = message;
    toast.classList.toggle('is-error', error);
    toast.classList.add('is-visible');
    window.setTimeout(() => toast.classList.remove('is-visible'), 4200);
  };

  const activateTab = name => {
    tabs.forEach(button => button.classList.toggle('is-active', button.dataset.adminTab === name));
    panels.forEach(panel => panel.classList.toggle('is-active', panel.dataset.adminPanel === name));
  };

  const activateCase = key => {
    caseButtons.forEach(button => button.classList.toggle('is-active', button.dataset.caseSelect === key));
    caseEditors.forEach(editor => editor.classList.toggle('is-active', editor.dataset.caseEditor === key));
    if (key) history.replaceState(null, '', `#case-${key}`);
  };

  const renumber = list => {
    [...list.querySelectorAll(':scope > [data-step]')].forEach((step, index, all) => {
      const number = step.querySelector('[data-step-number]');
      if (number) number.textContent = String(index + 1).padStart(2, '0');
      const up = step.querySelector('[data-step-up]');
      const down = step.querySelector('[data-step-down]');
      if (up) up.disabled = index === 0;
      if (down) down.disabled = index === all.length - 1;
    });
  };

  const serializeProjects = () => {
    let projects = {};
    try { projects = JSON.parse(document.querySelector('#projects-json').value || '{}'); } catch (_) {}
    caseEditors.forEach(editor => {
      const key = editor.dataset.caseEditor;
      const current = projects[key] || {};
      editor.querySelectorAll('[data-project-field]').forEach(field => {
        const name = field.dataset.projectField;
        if (name === 'facts') current.facts = field.value.split(/\r?\n/).map(value => value.trim()).filter(Boolean);
        else current[name] = field.value.trim();
      });
      current.steps = [...editor.querySelectorAll('[data-step-list] > [data-step]')].map(step => [
        step.querySelector('[data-step-field="title"]')?.value.trim() || '',
        step.querySelector('[data-step-field="text"]')?.value.trim() || '',
        step.querySelector('[data-step-field="image"]')?.value.trim() || '',
      ]);
      projects[key] = current;
    });
    document.querySelector('#projects-json').value = JSON.stringify(projects);
  };

  const bindUpload = input => {
    input.addEventListener('change', async () => {
      const file = input.files?.[0];
      if (!file) return;
      if (file.size > 8 * 1024 * 1024) {
        showToast('Фотография больше 8 МБ. Уменьшите файл и повторите.', true);
        input.value = '';
        return;
      }

      const control = input.closest('[data-image-control]');
      const label = input.closest('.upload-button');
      const previousText = label?.childNodes[0]?.textContent || 'Выбрать фото';
      if (label?.childNodes[0]) label.childNodes[0].textContent = 'Загружаем…';
      input.disabled = true;
      const data = new FormData();
      data.append('csrf', csrfToken);
      data.append('project_key', input.dataset.projectKey || input.closest('[data-case-editor]')?.dataset.caseEditor || '');
      data.append('kind', input.dataset.imageKind || 'step');
      data.append('image', file);

      try {
        const response = await fetch('/admin/upload.php', { method: 'POST', body: data, headers: { Accept: 'application/json' } });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok || !payload.ok) throw new Error(payload.message || 'Не удалось загрузить фото');
        const value = control?.querySelector('[data-image-value]');
        if (value) value.value = payload.value;
        let preview = control?.querySelector('[data-image-preview]');
        if (preview && preview.tagName !== 'IMG') {
          const image = document.createElement('img');
          image.dataset.imagePreview = '';
          preview.replaceWith(image);
          preview = image;
        }
        if (preview) preview.src = `${payload.url}?v=${Date.now()}`;
        setDirty();
        showToast('Фотография загружена. Сохраните изменения.');
      } catch (error) {
        showToast(error.message || 'Ошибка загрузки', true);
      } finally {
        input.disabled = false;
        input.value = '';
        if (label?.childNodes[0]) label.childNodes[0].textContent = previousText;
      }
    });
  };

  tabs.forEach(button => button.addEventListener('click', () => activateTab(button.dataset.adminTab)));
  caseButtons.forEach(button => button.addEventListener('click', () => activateCase(button.dataset.caseSelect)));

  document.querySelectorAll('[data-step-list]').forEach(renumber);
  document.querySelectorAll('[data-image-upload]').forEach(bindUpload);

  document.addEventListener('input', event => {
    if (event.target.closest('#content-form')) setDirty();
  });
  document.addEventListener('click', event => {
    const add = event.target.closest('[data-add-step]');
    if (add) {
      const editor = add.closest('[data-case-editor]');
      const list = editor?.querySelector('[data-step-list]');
      const fragment = document.querySelector('#step-template')?.content.cloneNode(true);
      if (!list || !fragment) return;
      const upload = fragment.querySelector('[data-image-upload]');
      if (upload) upload.dataset.projectKey = add.dataset.projectKey || '';
      list.append(fragment);
      const step = list.lastElementChild;
      const uploadInput = step?.querySelector('[data-image-upload]');
      if (uploadInput) bindUpload(uploadInput);
      renumber(list);
      step?.querySelector('input')?.focus();
      setDirty();
      return;
    }

    const remove = event.target.closest('[data-remove-step]');
    if (remove) {
      const step = remove.closest('[data-step]');
      const list = step?.parentElement;
      if (!list || list.querySelectorAll('[data-step]').length === 1) {
        showToast('В кейсе должен остаться хотя бы один этап.', true);
        return;
      }
      if (confirm('Удалить этот этап из статьи?')) {
        step.remove();
        renumber(list);
        setDirty();
      }
      return;
    }

    const direction = event.target.closest('[data-step-up], [data-step-down]');
    if (direction) {
      const step = direction.closest('[data-step]');
      const list = step?.parentElement;
      if (!step || !list) return;
      if (direction.hasAttribute('data-step-up') && step.previousElementSibling) list.insertBefore(step, step.previousElementSibling);
      if (direction.hasAttribute('data-step-down') && step.nextElementSibling) list.insertBefore(step.nextElementSibling, step);
      renumber(list);
      setDirty();
    }
  });

  document.addEventListener('pointerdown', event => {
    const handle = event.target.closest('.drag-handle');
    const step = handle?.closest('[data-step]');
    if (step) step.draggable = true;
  });
  document.addEventListener('pointerup', event => {
    const step = event.target.closest('.drag-handle')?.closest('[data-step]');
    if (step && step !== draggedStep) step.draggable = false;
  });
  document.addEventListener('dragstart', event => {
    const step = event.target.closest('[data-step]');
    if (!step || !step.draggable) return;
    draggedStep = step;
    step.classList.add('is-dragging');
    event.dataTransfer.effectAllowed = 'move';
  });
  document.addEventListener('dragend', () => {
    if (!draggedStep) return;
    const list = draggedStep.parentElement;
    draggedStep.classList.remove('is-dragging');
    draggedStep.draggable = false;
    draggedStep = null;
    if (list) renumber(list);
    setDirty();
  });
  document.addEventListener('dragover', event => {
    const list = event.target.closest('[data-step-list]');
    if (!list || !draggedStep || draggedStep.parentElement !== list) return;
    event.preventDefault();
    const candidates = [...list.querySelectorAll('[data-step]:not(.is-dragging)')];
    const after = candidates.reduce((closest, candidate) => {
      const box = candidate.getBoundingClientRect();
      const offset = event.clientY - box.top - box.height / 2;
      return offset < 0 && offset > closest.offset ? { offset, element: candidate } : closest;
    }, { offset: Number.NEGATIVE_INFINITY, element: null }).element;
    if (after) list.insertBefore(draggedStep, after);
    else list.append(draggedStep);
  });

  form.addEventListener('submit', event => {
    serializeProjects();
    const missingImage = [...form.querySelectorAll('[data-step-field="image"]')].find(field => !field.value.trim());
    const invalid = missingImage || [...form.querySelectorAll('input, textarea')].find(field => field.willValidate && !field.checkValidity());
    if (invalid) {
      event.preventDefault();
      const panel = invalid.closest('[data-admin-panel]');
      if (panel) activateTab(panel.dataset.adminPanel);
      const editor = invalid.closest('[data-case-editor]');
      if (editor) activateCase(editor.dataset.caseEditor);
      const focusTarget = missingImage ? missingImage.closest('[data-image-control]')?.querySelector('[data-image-upload]') : invalid;
      window.setTimeout(() => focusTarget?.focus(), 50);
      showToast(missingImage ? 'Добавьте фотографию к новому этапу.' : 'Проверьте выделенное поле.', true);
      return;
    }
    dirty = false;
    document.body.classList.add('is-saving');
    const button = form.querySelector('.admin-savebar button');
    if (button) {
      button.disabled = true;
      button.textContent = 'Сохраняем…';
    }
  });
  window.addEventListener('beforeunload', event => {
    if (!dirty) return;
    event.preventDefault();
    event.returnValue = '';
  });

  const hashCase = location.hash.match(/^#case-([a-z0-9_-]+)$/)?.[1];
  const initialCase = hashCase && caseEditors.some(editor => editor.dataset.caseEditor === hashCase) ? hashCase : caseEditors[0]?.dataset.caseEditor;
  if (hashCase && initialCase === hashCase) activateTab('cases');
  activateCase(initialCase);
})();
