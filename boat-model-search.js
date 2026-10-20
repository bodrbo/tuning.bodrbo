(() => {
  const fields = document.querySelectorAll('[data-boat-model-search]');
  if (!fields.length) return;

  const cyrillicToLatin = {
    а: 'a', б: 'b', в: 'v', г: 'g', д: 'd', е: 'e', ё: 'e', ж: 'zh', з: 'z',
    и: 'i', й: 'y', к: 'k', л: 'l', м: 'm', н: 'n', о: 'o', п: 'p', р: 'r',
    с: 's', т: 't', у: 'u', ф: 'f', х: 'h', ц: 'ts', ч: 'ch', ш: 'sh',
    щ: 'sch', ъ: '', ы: 'y', ь: '', э: 'e', ю: 'yu', я: 'ya',
  };

  const transliterate = value => Array.from(value.toLowerCase())
    .map(character => cyrillicToLatin[character] ?? character)
    .join('')
    .replace(/[^a-z0-9]+/g, ' ')
    .trim();

  fields.forEach((input, fieldIndex) => {
    const root = input.closest('[data-boat-model-field]');
    const list = root?.querySelector('[data-boat-model-results]');
    const status = root?.querySelector('[data-boat-model-status]');
    const profileId = root?.querySelector('input[name="boat_profile_id"]');
    if (!root || !list || !status || !profileId) return;

    const listId = list.id || `boat-model-results-${fieldIndex + 1}`;
    list.id = listId;
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-controls', listId);
    input.setAttribute('aria-expanded', 'false');

    let timer = 0;
    let controller = null;
    let options = [];
    let matches = [];
    let activeIndex = -1;
    let selectedName = '';

    const setStatus = (message, state = '') => {
      status.textContent = message;
      status.dataset.state = state;
    };

    const closeList = () => {
      list.hidden = true;
      list.replaceChildren();
      options = [];
      matches = [];
      activeIndex = -1;
      input.setAttribute('aria-expanded', 'false');
      input.removeAttribute('aria-activedescendant');
    };

    const selectItem = item => {
      input.value = item.model_name;
      profileId.value = String(item.id);
      selectedName = item.model_name;
      setStatus(`Выбрано из каталога: ${item.model_name}`, 'selected');
      closeList();
      input.focus();
    };

    const setActive = index => {
      if (!options.length) return;
      activeIndex = (index + options.length) % options.length;
      options.forEach((option, optionIndex) => {
        const active = optionIndex === activeIndex;
        option.classList.toggle('is-active', active);
        option.setAttribute('aria-selected', String(active));
      });
      const activeOption = options[activeIndex];
      input.setAttribute('aria-activedescendant', activeOption.id);
      activeOption.scrollIntoView({ block: 'nearest' });
    };

    const renderItems = items => {
      list.replaceChildren();
      activeIndex = -1;
      matches = items;
      options = items.map((item, optionIndex) => {
        const option = document.createElement('div');
        option.id = `${listId}-option-${optionIndex + 1}`;
        option.className = 'boat-model-option';
        option.setAttribute('role', 'option');
        option.setAttribute('aria-selected', 'false');

        const name = document.createElement('strong');
        name.textContent = item.model_name;
        option.append(name);
        if (item.brand && !item.model_name.toLowerCase().startsWith(item.brand.toLowerCase())) {
          const brand = document.createElement('span');
          brand.textContent = item.brand;
          option.append(brand);
        }

        option.addEventListener('pointerdown', event => {
          event.preventDefault();
          selectItem(item);
        });
        option.addEventListener('pointerenter', () => setActive(optionIndex));
        list.append(option);
        return option;
      });

      if (options.length) {
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        setStatus('Выберите точную модель из каталога', 'results');
      } else {
        closeList();
        setStatus('Совпадений нет — можно оставить своё название', 'empty');
      }
    };

    const search = async query => {
      controller?.abort();
      controller = new AbortController();
      setStatus('Ищем модель в каталоге…', 'loading');

      const params = new URLSearchParams({
        q: query,
        translit: transliterate(query),
        limit: '8',
      });

      try {
        const response = await fetch(`/boat-catalog-search.php?${params}`, {
          headers: { Accept: 'application/json' },
          signal: controller.signal,
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok || !payload.ok || !Array.isArray(payload.items)) {
          throw new Error(payload.message || 'catalog_unavailable');
        }
        renderItems(payload.items);
      } catch (error) {
        if (error.name === 'AbortError') return;
        closeList();
        setStatus('Каталог сейчас недоступен — название можно ввести вручную', 'error');
      }
    };

    input.addEventListener('input', () => {
      const query = input.value.trim();
      if (query !== selectedName) {
        profileId.value = '';
        selectedName = '';
      }
      window.clearTimeout(timer);
      controller?.abort();
      closeList();
      if (query.length < 2) {
        setStatus('Введите хотя бы 2 символа — понимаем и русское написание', 'hint');
        return;
      }
      timer = window.setTimeout(() => search(query), 220);
    });

    input.addEventListener('keydown', event => {
      if (event.key === 'ArrowDown' && options.length) {
        event.preventDefault();
        setActive(activeIndex + 1);
      } else if (event.key === 'ArrowUp' && options.length) {
        event.preventDefault();
        setActive(activeIndex - 1);
      } else if (event.key === 'Enter' && activeIndex >= 0) {
        event.preventDefault();
        selectItem(matches[activeIndex]);
      } else if (event.key === 'Escape') {
        closeList();
      }
    });

    input.addEventListener('focus', () => {
      if (options.length) {
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
      }
    });

    document.addEventListener('pointerdown', event => {
      if (!root.contains(event.target)) closeList();
    });

    input.form?.addEventListener('reset', () => {
      window.setTimeout(() => {
        profileId.value = '';
        selectedName = '';
        closeList();
        setStatus('Введите хотя бы 2 символа — понимаем и русское написание', 'hint');
      });
    });
  });
})();
