(() => {
  const form = document.querySelector('#new-case-form');
  if (!form) return;

  const title = form.querySelector('[data-new-title]');
  const shortTitle = form.querySelector('[data-new-short-title]');
  const subtitle = form.querySelector('[data-new-subtitle]');
  const cardSummary = form.querySelector('[data-new-card-summary]');
  const seoTitle = form.querySelector('[data-seo-title]');
  const seoDescription = form.querySelector('[data-seo-description]');
  const seoUrl = form.querySelector('[data-seo-url]');
  const preview = form.querySelector('[data-new-image-preview]');
  let shortTitleTouched = Boolean(shortTitle?.value.trim());
  let cardSummaryTouched = Boolean(cardSummary?.value.trim());
  let previewUrl = '';

  const transliteration = {
    а: 'a', б: 'b', в: 'v', г: 'g', д: 'd', е: 'e', ё: 'e', ж: 'zh', з: 'z', и: 'i', й: 'y', к: 'k', л: 'l', м: 'm', н: 'n', о: 'o', п: 'p', р: 'r', с: 's', т: 't', у: 'u', ф: 'f', х: 'kh', ц: 'ts', ч: 'ch', ш: 'sh', щ: 'sch', ъ: '', ы: 'y', ь: '', э: 'e', ю: 'yu', я: 'ya',
  };

  const slugify = value => {
    const transliterated = String(value || '').toLowerCase().split('').map(letter => transliteration[letter] ?? letter).join('');
    return transliterated.replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 96).replace(/-$/, '') || 'novyi-keis';
  };

  const excerpt = (value, maxLength) => value.length > maxLength ? `${value.slice(0, maxLength - 1).trim()}…` : value;

  const updateSeo = () => {
    const titleValue = shortTitle?.value.trim() || title?.value.trim() || 'Название кейса';
    const descriptionValue = excerpt(subtitle?.value.trim() || 'Краткое описание проекта появится здесь.', 160);
    if (seoTitle) seoTitle.textContent = `${excerpt(titleValue, 42)} — Бодрый Боцман`;
    if (seoDescription) seoDescription.textContent = descriptionValue;
    if (seoUrl) seoUrl.textContent = `/proekty/${slugify(titleValue)}/`;
  };

  title?.addEventListener('input', () => {
    if (!shortTitleTouched && shortTitle) shortTitle.value = title.value;
    updateSeo();
  });
  shortTitle?.addEventListener('input', () => { shortTitleTouched = true; updateSeo(); });
  subtitle?.addEventListener('input', () => {
    if (!cardSummaryTouched && cardSummary) cardSummary.value = subtitle.value;
    updateSeo();
  });
  cardSummary?.addEventListener('input', () => { cardSummaryTouched = true; });

  form.querySelector('input[name="cover"]')?.addEventListener('change', event => {
    const file = event.target.files?.[0];
    if (!file || !preview) return;
    if (previewUrl) URL.revokeObjectURL(previewUrl);
    previewUrl = URL.createObjectURL(file);
    const image = document.createElement('img');
    image.src = previewUrl;
    image.alt = 'Предпросмотр обложки';
    preview.replaceChildren(image);
  });

  form.addEventListener('submit', () => {
    const button = form.querySelector('button[type="submit"]');
    if (button && form.checkValidity()) {
      button.disabled = true;
      button.textContent = 'Создаём кейс…';
    }
  });

  updateSeo();
})();
