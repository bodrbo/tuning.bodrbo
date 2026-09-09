const catalogGrid = document.querySelector('#motor-grid');

if (catalogGrid) {
  const catalogStatus = document.querySelector('#catalog-status');
  const catalogCount = document.querySelector('#catalog-count');
  const catalogUpdated = document.querySelector('#catalog-updated');
  const searchInput = document.querySelector('#motor-search');
  const powerSelect = document.querySelector('#motor-power');
  const sortSelect = document.querySelector('#motor-sort');
  const categoryButtons = Array.from(document.querySelectorAll('[data-motor-category]'));
  const dialog = document.querySelector('#motor-dialog');
  const dialogImage = document.querySelector('#motor-dialog-image');
  const dialogGallery = document.querySelector('#motor-dialog-gallery');
  const dialogCategory = document.querySelector('#motor-dialog-category');
  const dialogTitle = document.querySelector('#motor-dialog-title');
  const dialogPrice = document.querySelector('#motor-dialog-price');
  const dialogSpecs = document.querySelector('#motor-dialog-specs');
  const dialogRequest = document.querySelector('#motor-dialog-request');
  const powerRange = document.querySelector('#motor-power-range');
  const requestSection = document.querySelector('#motor-request');
  const leadForm = document.querySelector('#lead-form');
  const modelField = leadForm?.elements.boat_model;
  const messageField = leadForm?.elements.message;
  const priceFormatter = new Intl.NumberFormat('ru-RU', {
    style: 'currency',
    currency: 'RUB',
    maximumFractionDigits: 0,
  });

  let products = [];
  let activeCategory = 'all';
  let activeProduct = null;

  const createElement = (tag, className, text) => {
    const element = document.createElement(tag);
    if (className) element.className = className;
    if (text !== undefined) element.textContent = text;
    return element;
  };

  const formatPrice = value => priceFormatter.format(Number(value) || 0).replace(/\s₽$/, ' ₽');

  const formatModelCount = count => {
    const lastTwo = count % 100;
    const last = count % 10;
    const word = lastTwo >= 11 && lastTwo <= 14
      ? 'моделей'
      : last === 1
        ? 'модель'
        : last >= 2 && last <= 4
          ? 'модели'
          : 'моделей';
    return `${count} ${word}`;
  };

  const productSearchText = product => [
    product.model,
    product.name,
    product.category,
    ...Object.values(product.specs || {}),
  ].join(' ').toLocaleLowerCase('ru-RU');

  const matchesPower = (product, range) => {
    const power = Number(product.power) || 0;
    if (range === 'to-10') return power <= 10;
    if (range === '11-30') return power > 10 && power <= 30;
    if (range === '31-60') return power > 30 && power <= 60;
    if (range === 'over-60') return power > 60;
    return true;
  };

  const getFilteredProducts = () => {
    const query = (searchInput?.value || '').trim().toLocaleLowerCase('ru-RU');
    const powerRange = powerSelect?.value || 'all';
    const sort = sortSelect?.value || 'power-asc';

    const filtered = products.filter(product => {
      const categoryMatches = activeCategory === 'all' || product.category_key === activeCategory;
      const searchMatches = !query || productSearchText(product).includes(query);
      return categoryMatches && searchMatches && matchesPower(product, powerRange);
    });

    filtered.sort((left, right) => {
      if (sort === 'price-asc') return left.price - right.price || left.power - right.power;
      if (sort === 'price-desc') return right.price - left.price || right.power - left.power;
      if (sort === 'power-desc') return right.power - left.power || left.price - right.price;
      return left.power - right.power || left.price - right.price;
    });

    return filtered;
  };

  const createMotorCard = product => {
    const card = createElement('article', 'motor-card');
    const media = createElement('div', 'motor-card__media');
    const image = createElement('img');
    image.src = product.image;
    image.alt = product.name;
    image.loading = 'lazy';
    image.decoding = 'async';
    image.addEventListener('error', () => {
      image.hidden = true;
      media.classList.add('motor-card__media--empty');
    });

    const status = createElement(
      'span',
      `motor-card__status${product.available ? '' : ' motor-card__status--muted'}`,
      product.available ? 'Доступен к заказу' : 'Наличие уточняется'
    );
    media.append(image, status);

    const content = createElement('div', 'motor-card__content');
    const category = createElement('p', 'motor-card__category', product.category);
    const title = createElement('h2', 'motor-card__title', product.model);
    const priceLabel = createElement('span', 'motor-card__price-label', 'Рекомендованная цена');
    const price = createElement('p', 'motor-card__price', formatPrice(product.price));
    price.prepend(priceLabel);

    const specs = createElement('dl', 'motor-card__specs');
    const primarySpecs = [
      ['Мощность', product.specs?.['Мощность']],
      ['Тактность', product.specs?.['Тактность']],
      ['Управление', product.specs?.['Управление']],
      ['Транец', product.specs?.['Высота транца']],
    ].filter(([, value]) => value);

    primarySpecs.forEach(([label, value]) => {
      const row = createElement('div');
      row.append(createElement('dt', '', label), createElement('dd', '', value));
      specs.append(row);
    });

    const actions = createElement('div', 'motor-card__actions');
    const requestButton = createElement('button', 'button motor-card__request', 'Подобрать к катеру');
    requestButton.type = 'button';
    requestButton.dataset.motorRequest = product.id;
    const detailsButton = createElement('button', 'motor-card__details', 'Характеристики');
    detailsButton.type = 'button';
    detailsButton.dataset.motorDetails = product.id;
    actions.append(requestButton, detailsButton);

    content.append(category, title, price, specs, actions);
    card.append(media, content);
    return card;
  };

  const renderProducts = () => {
    const filtered = getFilteredProducts();
    catalogGrid.replaceChildren();

    if (catalogCount) {
      catalogCount.textContent = formatModelCount(filtered.length);
    }

    if (!filtered.length) {
      const empty = createElement('div', 'motor-catalog__empty');
      empty.append(
        createElement('strong', '', 'Не нашли подходящую модель?'),
        createElement('p', '', 'Сбросьте фильтры или оставьте заявку — подберём мотор под катер и задачи.')
      );
      const reset = createElement('button', 'button button--ghost', 'Сбросить фильтры');
      reset.type = 'button';
      reset.addEventListener('click', () => {
        activeCategory = 'all';
        if (searchInput) searchInput.value = '';
        if (powerSelect) powerSelect.value = 'all';
        if (sortSelect) sortSelect.value = 'power-asc';
        categoryButtons.forEach(button => button.setAttribute('aria-pressed', String(button.dataset.motorCategory === 'all')));
        renderProducts();
      });
      empty.append(reset);
      catalogGrid.append(empty);
      return;
    }

    const fragment = document.createDocumentFragment();
    filtered.forEach(product => fragment.append(createMotorCard(product)));
    catalogGrid.append(fragment);
  };

  const selectMotor = product => {
    if (!product || !requestSection) return;
    if (modelField) modelField.value = `Marine Rocket ${product.model}`.slice(0, 160);
    if (messageField && !messageField.value.trim()) {
      messageField.value = `Интересует мотор Marine Rocket ${product.model}. Нужен подбор под катер и установка.`;
    }
    if (typeof window.ym === 'function') {
      window.ym(111997516, 'reachGoal', 'motor_request_click', { motor: product.model });
    }
    requestSection.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
    window.setTimeout(() => leadForm?.elements.name?.focus({ preventScroll: true }), 500);
  };

  const openProductDialog = product => {
    if (!dialog || !product) return;
    activeProduct = product;
    if (dialogCategory) dialogCategory.textContent = product.category;
    if (dialogTitle) dialogTitle.textContent = product.model;
    if (dialogPrice) dialogPrice.textContent = formatPrice(product.price);
    if (dialogImage) {
      dialogImage.src = product.image;
      dialogImage.alt = product.name;
    }
    if (dialogSpecs) {
      dialogSpecs.replaceChildren();
      Object.entries(product.specs || {}).forEach(([label, value]) => {
        const row = createElement('div');
        row.append(createElement('dt', '', label), createElement('dd', '', value));
        dialogSpecs.append(row);
      });
    }
    if (dialogGallery) {
      dialogGallery.replaceChildren();
      (product.pictures || []).forEach((source, index) => {
        const button = createElement('button', `motor-dialog__thumb${index === 0 ? ' is-active' : ''}`);
        button.type = 'button';
        button.setAttribute('aria-label', `Показать фотографию ${index + 1}`);
        const thumb = createElement('img');
        thumb.src = source;
        thumb.alt = '';
        thumb.loading = 'lazy';
        button.append(thumb);
        button.addEventListener('click', () => {
          if (dialogImage) dialogImage.src = source;
          dialogGallery.querySelectorAll('button').forEach(item => item.classList.toggle('is-active', item === button));
        });
        dialogGallery.append(button);
      });
    }
    if (typeof dialog.showModal === 'function') dialog.showModal();
    else dialog.setAttribute('open', '');
  };

  categoryButtons.forEach(button => {
    button.addEventListener('click', () => {
      activeCategory = button.dataset.motorCategory || 'all';
      categoryButtons.forEach(item => item.setAttribute('aria-pressed', String(item === button)));
      renderProducts();
    });
  });
  searchInput?.addEventListener('input', renderProducts);
  powerSelect?.addEventListener('change', renderProducts);
  sortSelect?.addEventListener('change', renderProducts);

  catalogGrid.addEventListener('click', event => {
    const requestButton = event.target.closest('[data-motor-request]');
    const detailsButton = event.target.closest('[data-motor-details]');
    if (requestButton) selectMotor(products.find(product => product.id === requestButton.dataset.motorRequest));
    if (detailsButton) openProductDialog(products.find(product => product.id === detailsButton.dataset.motorDetails));
  });

  dialog?.querySelector('[data-dialog-close]')?.addEventListener('click', () => dialog.close());
  dialog?.addEventListener('click', event => {
    if (event.target !== dialog) return;
    const bounds = dialog.getBoundingClientRect();
    const inside = event.clientX >= bounds.left && event.clientX <= bounds.right && event.clientY >= bounds.top && event.clientY <= bounds.bottom;
    if (!inside) dialog.close();
  });
  dialogRequest?.addEventListener('click', () => {
    dialog?.close();
    selectMotor(activeProduct);
  });

  fetch('marine-rocket-catalog.php', { headers: { Accept: 'application/json' } })
    .then(response => response.json().then(payload => ({ response, payload })))
    .then(({ response, payload }) => {
      if (!response.ok || !payload.ok || !Array.isArray(payload.products)) {
        throw new Error(payload.message || 'Каталог временно недоступен');
      }
      products = payload.products;
      categoryButtons.forEach(button => {
        const category = button.dataset.motorCategory;
        button.hidden = category !== 'all' && !products.some(product => product.category_key === category);
      });
      if (powerRange) {
        const powers = products.map(product => Number(product.power)).filter(Number.isFinite);
        if (powers.length) powerRange.textContent = `${Math.min(...powers)}–${Math.max(...powers)}`;
      }
      if (catalogUpdated) {
        const sourceTime = payload.source_updated_at ? `Данные поставщика: ${payload.source_updated_at}` : 'Данные поставщика обновлены';
        catalogUpdated.textContent = payload.stale ? `${sourceTime} · показываем сохранённую копию` : sourceTime;
      }
      if (catalogStatus) catalogStatus.remove();
      renderProducts();
    })
    .catch(error => {
      catalogGrid.replaceChildren();
      const failure = createElement('div', 'motor-catalog__empty motor-catalog__empty--error');
      failure.append(
        createElement('strong', '', 'Каталог сейчас не загрузился'),
        createElement('p', '', error.message || 'Позвоните нам — подберём мотор вручную.')
      );
      const phone = createElement('a', 'button', 'Позвонить +7 (921) 967-61-15');
      phone.href = 'tel:+79219676115';
      failure.append(phone);
      catalogGrid.append(failure);
      if (catalogCount) catalogCount.textContent = 'Нет данных';
      if (catalogStatus) catalogStatus.remove();
    });
}
