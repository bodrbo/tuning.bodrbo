const productImage = document.querySelector('#motor-product-image');
const productThumbs = Array.from(document.querySelectorAll('[data-motor-picture]'));
const shareButton = document.querySelector('#motor-share');

productThumbs.forEach(button => {
  button.addEventListener('click', () => {
    if (!productImage) return;
    productImage.src = button.dataset.motorPicture || productImage.src;
    productThumbs.forEach(item => item.classList.toggle('is-active', item === button));
  });
});

shareButton?.addEventListener('click', async () => {
  const shareData = {
    title: shareButton.dataset.shareTitle || document.title,
    url: window.location.href,
  };

  try {
    if (navigator.share) {
      await navigator.share(shareData);
    } else {
      await navigator.clipboard.writeText(shareData.url);
      const originalText = shareButton.textContent;
      shareButton.textContent = 'Ссылка скопирована';
      window.setTimeout(() => {
        shareButton.textContent = originalText;
      }, 2200);
    }
    if (typeof window.ym === 'function') {
      window.ym(104372402, 'reachGoal', 'motor_share', { motor: shareData.title });
    }
  } catch (error) {
    if (error?.name !== 'AbortError') {
      window.prompt('Скопируйте ссылку на мотор', shareData.url);
    }
  }
});
