(() => {
  const videos = (window.BODRBO_VIDEOS || []).filter(video => video.published);
  const projects = window.BODRBO_PROJECTS || {};
  const element = (tag, className, text) => {
    const node = document.createElement(tag);
    node.className = className;
    if (text) node.textContent = text;
    return node;
  };
  let dialog;
  function play(video) {
    if (!dialog) {
      dialog = element('dialog', 'video-dialog');
      const close = element('button', 'video-dialog__close', '×');
      close.type = 'button'; close.setAttribute('aria-label', 'Закрыть видео');
      close.addEventListener('click', () => dialog.close());
      dialog.append(close, element('div', 'video-dialog__body'));
      dialog.setAttribute('aria-label', 'Просмотр видео');
      dialog.addEventListener('close', () => dialog.querySelector('.video-dialog__body').replaceChildren());
      dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
      document.body.append(dialog);
    }
    const body = dialog.querySelector('.video-dialog__body');
    const frame = element('iframe', 'video-player');
    frame.src = video.embed;
    frame.title = video.title;
    frame.allow = 'autoplay; encrypted-media; fullscreen; picture-in-picture';
    frame.allowFullscreen = true;
    const link = element('a', 'video-vk-link', 'Открыть в VK Video ↗');
    link.href = video.url; link.target = '_blank'; link.rel = 'noopener noreferrer';
    body.replaceChildren(frame, element('h2', '', video.title), link);
    dialog.showModal();
  }
  function poster(video) {
    const button = element('button', 'video-poster');
    button.type = 'button';
    button.setAttribute('aria-label', 'Смотреть «' + video.title + '»');
    const img = element('img', '');
    img.src = '/' + video.cover; img.alt = video.title; img.loading = 'lazy';
    const icon = element('span', 'video-play', '▶');
    icon.setAttribute('aria-hidden', 'true');
    button.append(img, icon);
    if (video.duration) button.append(element('span', 'video-duration', video.duration));
    button.addEventListener('click', () => play(video));
    return button;
  }
  function related(video, label) {
    const card = element('article', 'video-related');
    const copy = element('div', 'video-related__copy');
    copy.append(element('small', '', label), element('h3', '', video.title));
    if (video.description) copy.append(element('p', '', video.description));
    const button = element('button', 'video-text-button', 'Смотреть выпуск →');
    button.type = 'button'; button.addEventListener('click', () => play(video));
    copy.append(button); card.append(poster(video), copy);
    return card;
  }
  function render() {
    const home = document.querySelector('[data-video-home]');
    const selected = videos.filter(video => video.home).slice(0, 3);
    if (home && selected.length) {
      const grid = element('div', 'video-blog__grid');
      grid.classList.toggle('video-blog__grid--single', selected.length === 1);
      const first = selected[0];
      const feature = element('article', 'video-feature');
      const copy = element('div', 'video-feature__copy');
      copy.append(element('small', '', 'Последний выпуск'), element('h3', '', first.title));
      if (first.description) copy.append(element('p', '', first.description));
      const project = projects[first.project];
      if (project) {
        const link = element('a', '', 'Посмотреть проект ↗');
        link.href = '/proekty/' + project.slug + '/';
        copy.append(link);
      }
      feature.append(poster(first), copy); grid.append(feature);
      if (selected.length > 1) {
        const aside = element('div', 'video-blog__previous');
        aside.append(element('h3', '', 'Ранее в блоге'));
        selected.slice(1).forEach(video => aside.append(related(video, 'VK Video')));
        grid.append(aside);
      }
      home.querySelector('[data-video-list]').replaceChildren(grid);
      home.hidden = false;
    }
    document.querySelectorAll('[data-video-project]').forEach(root => {
      const key = root.dataset.videoProject;
      const caseVideos = videos.filter(video => video.project === key);
      const hero = root.querySelector('.case-hero');
      if (!hero || !caseVideos.length) return;
      const section = element('section', 'case-videos');
      section.setAttribute('aria-label', 'Видео о проекте');
      caseVideos.forEach(video => {
        const stage = [...root.querySelectorAll('[data-video-step]')].find(node => node.dataset.videoStep === video.step);
        const title = stage?.querySelector('h2')?.textContent;
        section.append(related(video, title ? 'Видео к этапу «' + title + '»' : 'Видео о проекте'));
      });
      hero.after(section);
    });
    const services = { 'Механика': 'mechanics', 'Корпус': 'hull', 'Электрика': 'electrics', 'Электроника': 'electronics', 'Интерьер': 'interior', 'Тюнинг': 'tuning' };
    document.querySelectorAll('.service-grid > article, [data-video-service]').forEach(root => {
      const service = root.dataset.videoService || services[root.querySelector('h3')?.textContent.trim()];
      const video = videos.find(item => item.service === service);
      if (!video) return;
      const button = element('button', 'video-service-link', '▶ Видео о работе');
      button.type = 'button'; button.addEventListener('click', () => play(video)); root.append(button);
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', render, { once: true });
  else render();
})();
