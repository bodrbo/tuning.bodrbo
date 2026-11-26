(() => {
  if (document.querySelector('.callback-launcher')) return;
  const stylesheet = document.createElement('link');
  stylesheet.rel = 'stylesheet';
  stylesheet.href = '/callback-widget.css?v=1';
  document.head.append(stylesheet);

  const launcher = document.createElement('button');
  launcher.className = 'callback-launcher';
  launcher.type = 'button';
  launcher.setAttribute('aria-label', 'Запросить обратный звонок у Даниила, мастера-приёмщика');
  launcher.setAttribute('aria-haspopup', 'dialog');
  launcher.setAttribute('aria-controls', 'callback-panel');
  launcher.setAttribute('aria-expanded', 'false');
  launcher.innerHTML = '<span class="callback-launcher__label">Даниил<small>Задать вопрос</small></span><img src="/assets/team/daniil.jpg" alt="">';
  const panel = document.createElement('dialog');
  panel.id = 'callback-panel';
  panel.className = 'callback-panel';
  panel.setAttribute('aria-labelledby', 'callback-title');
  panel.innerHTML = `
    <header class="callback-panel__header">
      <img src="/assets/team/daniil.jpg" alt="Даниил, мастер-приёмщик">
      <div><h2 id="callback-title">Даниил</h2><p>Мастер-приёмщик</p></div>
      <button type="button" class="callback-close" aria-label="Закрыть форму">×</button>
    </header>
    <div class="callback-panel__body">
      <p class="callback-greeting">Здравствуйте! Помогу с ремонтом, оборудованием и обслуживанием лодки. Оставьте номер и опишите задачу — я с Вами свяжусь</p>
      <form id="callback-form" action="/submit-request.php" method="post" data-lead-form>
        <input type="hidden" name="form_type" value="callback">
        <input type="hidden" name="request_id">
        <input type="hidden" name="source_url">
        <label class="callback-trap" aria-hidden="true">Сайт компании<input name="company_website" tabindex="-1" autocomplete="off"></label>
        <div class="boat-model-field" data-boat-model-field>
          <label for="callback-boat-model">Модель лодки</label>
          <div class="boat-model-search">
            <input id="callback-boat-model" name="boat_model" type="text" maxlength="160" autocomplete="off" placeholder="Например, Bayliner 175" data-boat-model-search>
            <input type="hidden" name="boat_profile_id">
            <div id="callback-boat-results" class="boat-model-results" data-boat-model-results role="listbox" hidden></div>
          </div>
          <p class="boat-model-status" data-boat-model-status aria-live="polite">Можно выбрать модель из каталога или ввести свою.</p>
        </div>
        <label for="callback-phone">Ваш телефон</label>
        <input id="callback-phone" name="phone" type="tel" autocomplete="tel" required minlength="10" maxlength="40" placeholder="+7 999 000-00-00">
        <label for="callback-message">Комментарий</label>
        <textarea id="callback-message" name="message" maxlength="2000" rows="3" placeholder="Что нужно сделать с лодкой?"></textarea>
        <button class="button callback-submit" type="submit">Запросить звонок</button>
        <p class="callback-consent">Нажимая кнопку, вы даёте согласие на обработку персональных данных в соответствии с <a href="/privacy/" target="_blank" rel="noopener">политикой обработки персональных данных</a>.</p>
        <p class="callback-status" data-lead-status role="status" aria-live="polite"></p>
      </form>
    </div>`;
  // Wait for styles before exposing the fixed controls.
  launcher.hidden = true;
  stylesheet.addEventListener('load', () => { launcher.hidden = false; });
  if (stylesheet.sheet) launcher.hidden = false;
  document.body.append(launcher, panel);
  const phone = panel.querySelector('#callback-phone');
  phone.addEventListener('input', () => {
    const count = phone.value.replace(/\D/g, '').length;
    phone.setCustomValidity(phone.value && (count < 10 || count > 15) ? 'Укажите телефон из 10–15 цифр.' : '');
  });
  panel.querySelector('form').addEventListener('reset', () => phone.setCustomValidity(''));
  initBodrboLeadForm(panel.querySelector('form'));
  launcher.addEventListener('click', () => {
    panel.showModal();
    launcher.classList.add('callback-launcher--opened');
    launcher.setAttribute('aria-expanded', 'true');
    panel.querySelector('#callback-boat-model').focus({ preventScroll: true });
  });
  const close = () => panel.close();
  panel.querySelector('.callback-close').addEventListener('click', close);
  panel.addEventListener('click', event => {
    if (event.target !== panel) return;
    const bounds = panel.getBoundingClientRect();
    if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) close();
  });
  panel.addEventListener('cancel', event => {
    if (panel.querySelector('[data-boat-model-search]').getAttribute('aria-expanded') === 'true') event.preventDefault();
  });
  panel.addEventListener('close', () => {
    launcher.setAttribute('aria-expanded', 'false');
    launcher.focus({ preventScroll: true });
  });
  if (window.initBoatModelSearch) {
    window.initBoatModelSearch(panel);
  } else {
    const search = document.createElement('script');
    search.src = '/boat-model-search.js?v=2';
    document.head.append(search);
  }
})();
