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
  leadForm.addEventListener('submit', event => {
    event.preventDefault();
    const toast = document.querySelector('.toast');
    toast?.classList.add('show');
    event.currentTarget.reset();
    setTimeout(() => toast?.classList.remove('show'), 4200);
  });
}
