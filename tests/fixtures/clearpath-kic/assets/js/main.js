document.documentElement.classList.add('js');
document.querySelectorAll('[data-component="faq-item"]').forEach((item) => {
  const button = item.querySelector('.faq-question');
  const answer = item.querySelector('.faq-answer');
  if (!button || !answer) return;
  answer.hidden = true;
  button.addEventListener('click', () => {
    const open = button.getAttribute('aria-expanded') === 'true';
    button.setAttribute('aria-expanded', String(!open));
    answer.hidden = open;
  });
});
