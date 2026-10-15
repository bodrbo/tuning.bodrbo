(() => {
  const form = document.querySelector('.storage-calculator__form');
  if (!form) return;

  const lengthInput = form.querySelector('#storage-length');
  const widthInput = form.querySelector('#storage-width');
  const areaOutput = form.querySelector('#storage-area');
  const priceOutput = form.querySelector('#storage-price');
  const breakdownOutput = form.querySelector('#storage-breakdown');
  const messageInput = form.elements.message;
  const notesInput = form.elements.storage_notes;
  const rate = 990;

  const parseMeasurement = value => {
    const normalized = String(value).trim().replace(',', '.');
    if (!/^\d+(?:\.\d{0,2})?$/.test(normalized)) return null;
    const result = Number(normalized);
    return result > 0 && result <= 30 ? result : null;
  };

  const formatNumber = (value, maximumFractionDigits = 2) => new Intl.NumberFormat('ru-RU', {
    maximumFractionDigits,
  }).format(value);

  const composeMessage = calculation => {
    if (!messageInput) return;
    const lines = ['Заявка на зимнее хранение.'];
    if (calculation) {
      lines.push(`Габариты лодки: ${formatNumber(calculation.length)} × ${formatNumber(calculation.width)} м.`);
      lines.push(`Расчётная площадь: ${formatNumber(calculation.area)} м².`);
      lines.push(`Предварительная стоимость: ${formatNumber(calculation.price, 0)} ₽ в месяц.`);
    }
    const notes = notesInput?.value.trim();
    if (notes) lines.push(`Комментарий: ${notes}`);
    messageInput.value = lines.join('\n');
  };

  const updateCalculation = () => {
    const length = parseMeasurement(lengthInput.value);
    const width = parseMeasurement(widthInput.value);
    lengthInput.setAttribute('aria-invalid', String(length === null));
    widthInput.setAttribute('aria-invalid', String(width === null));
    lengthInput.setCustomValidity(length === null ? 'Введите длину лодки в метрах, например 5,2.' : '');
    widthInput.setCustomValidity(width === null ? 'Введите ширину лодки в метрах, например 2,1.' : '');

    if (length === null || width === null) {
      areaOutput.textContent = '—';
      priceOutput.textContent = '—';
      breakdownOutput.textContent = 'Введите длину и ширину лодки больше нуля.';
      composeMessage(null);
      return;
    }

    const area = (length + 0.5) * (width + 0.5);
    const price = Math.round(area * rate);
    areaOutput.textContent = `${formatNumber(area)} м²`;
    priceOutput.textContent = `${formatNumber(price, 0)} ₽`;
    breakdownOutput.textContent = `(${formatNumber(length)} + 0,5) × (${formatNumber(width)} + 0,5) = ${formatNumber(area)} м²`;
    composeMessage({ length, width, area, price });
  };

  [lengthInput, widthInput, notesInput].forEach(input => input?.addEventListener('input', updateCalculation));
  form.addEventListener('submit', updateCalculation, { capture: true });
  form.addEventListener('reset', () => setTimeout(updateCalculation));
  updateCalculation();
})();
