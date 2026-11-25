(() => {
  const form = document.querySelector('#videos-form');
  const list = document.querySelector('#video-list');
  const projects = JSON.parse(document.querySelector('#video-projects').textContent);
  const csrf = document.querySelector('meta[name="admin-csrf"]').content;
  const status = document.querySelector('#video-status');
  let dirty = false;
  let uploads = 0;
  const changed = () => {
    dirty = true;
    document.querySelector('#video-save-state').textContent = 'Есть несохранённые изменения';
  };
  const message = text => { status.textContent = text; };
  function data(card) {
    const value = { id: card.dataset.id };
    card.querySelectorAll('[data-field]').forEach(field => {
      value[field.dataset.field] = field.type === 'checkbox' ? field.checked : field.value.trim();
    });
    return value;
  }
  function updateOrder() {
    [...list.children].forEach((card, index, cards) => {
      card.querySelector('[data-up]').disabled = index === 0;
      card.querySelector('[data-down]').disabled = index === cards.length - 1;
    });
  }
  function add(item) {
    const card = document.querySelector('#video-template').content.firstElementChild.cloneNode(true);
    card.dataset.id = item.id || crypto.randomUUID();
    const project = card.querySelector('[data-field="project"]');
    project.add(new Option('Без привязки', ''));
    Object.entries(projects).forEach(([key, value]) => project.add(new Option(value.shortTitle || value.title, key)));
    function steps(selected = '') {
      const field = card.querySelector('[data-field="step"]');
      field.replaceChildren(new Option('О проекте в целом', ''));
      (projects[project.value]?.steps || []).forEach((step, index) => field.add(new Option((index + 1) + '. ' + step[0], step[3] || step[2])));
      if (selected && ![...field.options].some(option => option.value === selected)) field.add(new Option('Этап удалён — выберите другой', selected));
      field.value = selected;
      field.disabled = !project.value;
    }
    card.querySelectorAll('[data-field]').forEach(field => {
      if (field.type === 'checkbox') field.checked = Boolean(item[field.dataset.field]);
      else if (field.dataset.field !== 'step') field.value = item[field.dataset.field] || '';
    });
    steps(item.step);
    project.addEventListener('change', () => steps());
    const title = card.querySelector('[data-field="title"]');
    const heading = () => { card.querySelector('[data-heading]').textContent = title.value || 'Новый выпуск'; };
    title.addEventListener('input', heading);
    heading();
    const cover = card.querySelector('[data-field="cover"]');
    const preview = card.querySelector('[data-cover-preview]');
    preview.hidden = !cover.value;
    if (cover.value) preview.src = '/' + cover.value;
    card.querySelector('[data-cover-upload]').addEventListener('change', async event => {
      const input = event.target;
      const file = input.files[0];
      if (!file) return;
      if (file.size > 8 * 1024 * 1024) return message('Обложка больше 8 МБ.');
      uploads++;
      input.disabled = true;
      const payload = new FormData();
      payload.set('csrf', csrf); payload.set('kind', 'video'); payload.set('image', file);
      try {
        const response = await fetch('/admin/upload.php', { method: 'POST', body: payload });
        const result = await response.json();
        if (!response.ok || !result.ok) throw Error(result.message || 'Загрузка не удалась.');
        cover.value = result.value; preview.src = result.url; preview.hidden = false;
        changed(); message('Обложка загружена. Сохраните выпуски.');
      } catch (error) { message(error.message); }
      finally { uploads--; input.disabled = false; input.value = ''; }
    });
    card.querySelector('[data-preview]').addEventListener('click', async () => {
      const slot = card.querySelector('[data-player]');
      slot.replaceChildren();
      const payload = new FormData();
      payload.set('csrf', csrf); payload.set('action', 'preview'); payload.set('source', data(card).source);
      try {
        const response = await fetch('/admin/videos.php', { method: 'POST', body: payload });
        const result = await response.json();
        if (!response.ok) throw Error(result.error || 'Не удалось проверить ссылку.');
        const frame = document.createElement('iframe');
        frame.src = result.embed; frame.title = title.value || 'VK Video'; frame.allowFullscreen = true;
        frame.allow = 'autoplay; encrypted-media; fullscreen; picture-in-picture';
        slot.append(frame);
        message('Проверьте воспроизведение. Если VK ограничил доступ, используйте код для вставки и проверьте доступность видео.');
      } catch (error) { message(error.message); }
    });
    card.querySelector('[data-remove]').addEventListener('click', () => {
      if (confirm('Удалить выпуск с сайта?')) { card.remove(); updateOrder(); changed(); }
    });
    card.querySelector('[data-up]').addEventListener('click', () => { if (card.previousElementSibling) list.insertBefore(card, card.previousElementSibling); updateOrder(); changed(); });
    card.querySelector('[data-down]').addEventListener('click', () => { if (card.nextElementSibling) list.insertBefore(card.nextElementSibling, card); updateOrder(); changed(); });
    list.append(card); updateOrder();
  }
  JSON.parse(document.querySelector('#videos-input').value).forEach(add);
  document.querySelector('#add-video').addEventListener('click', () => {
    add({ home: true }); changed(); list.lastElementChild.querySelector('input').focus();
  });
  form.addEventListener('input', changed);
  form.addEventListener('change', changed);
  form.addEventListener('submit', event => {
    if (uploads) { event.preventDefault(); return message('Дождитесь загрузки обложки.'); }
    const missing = [...list.children].find(card => !data(card).cover);
    if (missing) { event.preventDefault(); missing.scrollIntoView({ block: 'center' }); return message('Добавьте обложку к каждому выпуску.'); }
    document.querySelector('#videos-input').value = JSON.stringify([...list.children].map(data));
    dirty = false;
  });
  window.addEventListener('beforeunload', event => { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
})();
