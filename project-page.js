const caseRoot = document.querySelector('#case-content');
const pathSlug = decodeURIComponent(window.location.pathname.replace(/\/+$/, '').split('/').pop() || '');
const pathProject = Object.entries(PROJECTS).find(([, item]) => item.slug === pathSlug);
const caseKey = pathProject?.[0] || new URLSearchParams(window.location.search).get('case');
const project = PROJECTS[caseKey];
const siteUrl = 'https://tuning.bodrbo.ru';

if (!project) {
  document.title = 'Проект не найден — Бодрый Боцман';
  caseRoot.innerHTML = `
    <section class="case-missing">
      <p class="eyebrow">Ошибка маршрута</p>
      <h1>Такого проекта нет</h1>
      <a class="button" href="/proekty/">Смотреть все проекты</a>
    </section>`;
} else {
  document.title = `${project.title} — Бодрый Боцман`;
  document.querySelector('meta[name="description"]').setAttribute('content', project.subtitle);
  const canonicalUrl = `${siteUrl}/proekty/${project.slug}/`;
  document.querySelector('#canonical-url').setAttribute('href', canonicalUrl);
  document.querySelector('#og-title').setAttribute('content', document.title);
  document.querySelector('#og-description').setAttribute('content', project.subtitle);
  document.querySelector('#og-url').setAttribute('content', canonicalUrl);
  document.querySelector('#og-image').setAttribute('content', `${siteUrl}/${project.cover}`);

  const steps = project.steps.map((step, index) => `
    <article class="case-step">
      <div class="case-step__copy">
        <span>${String(index + 1).padStart(2, '0')} / ${String(project.steps.length).padStart(2, '0')}</span>
        <h2>${step[0]}</h2>
        <p>${step[1]}</p>
      </div>
      <figure><img src="/assets/projects/${caseKey}/${step[2]}" alt="${step[0]} — ${project.shortTitle}" loading="lazy"></figure>
    </article>`).join('');

  caseRoot.innerHTML = `
    <article>
      <header class="case-hero">
        <div class="case-hero__copy">
          <a class="case-back" href="/proekty/">← Все проекты</a>
          <p class="eyebrow">${project.category}</p>
          <h1>${project.title}</h1>
          <p class="case-hero__lead">${project.subtitle}</p>
          <div class="case-facts">${project.facts.map(fact => `<span>${fact}</span>`).join('')}</div>
        </div>
        <figure><img src="/${project.cover}" alt="${project.shortTitle}"></figure>
      </header>

      <section class="case-brief">
        <div><p class="eyebrow">Исходная задача</p><h2>Не замаскировать проблему,<br>а решить её инженерно</h2></div>
        <p>${project.summary}</p>
      </section>

      <section class="case-story">
        <div class="case-story__heading"><p class="eyebrow">Ход проекта</p><h2>От диагностики<br>до результата</h2></div>
        <div class="case-steps">${steps}</div>
      </section>

      <section class="case-result">
        <p class="eyebrow">Результат</p>
        <h2>${project.result}</h2>
        <div><a class="button" href="/#request">Обсудить похожую задачу</a><a class="button button--ghost" href="/proekty/">Другие проекты</a></div>
      </section>
    </article>`;
}
