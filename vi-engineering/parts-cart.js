/* VI Engineering itemized parts cart. Amounts are verified again by PHP. */
window.viPartsCart = (() => {
  const tbody = document.getElementById('partsCartRows');
  const add = document.getElementById('addPartBtn');
  const cartInput = document.getElementById('partsCartJson');
  const descInput = document.getElementById('partsDescription');
  const partsInput = document.getElementById('parts_cost');
  let locked = false;
  const money = n => (Math.round((Number(n) || 0) * 100) / 100).toFixed(2);
  function row(description = '', qty = '1', price = '0') {
    const tr = document.createElement('tr');
    tr.innerHTML = '<td><input class="part-desc" maxlength="500" placeholder="Description"></td><td><input class="part-qty" type="number" min="0.001" step="0.001"></td><td><input class="part-price" type="number" min="0" step="0.01"></td><td><output class="part-total">0.00</output></td><td><button type="button" class="part-remove" aria-label="Remove part">×</button></td>';
    tr.querySelector('.part-desc').value = description;
    tr.querySelector('.part-qty').value = qty;
    tr.querySelector('.part-price').value = price;
    tr.querySelector('.part-remove').onclick = () => { if (locked) return; tr.remove(); update(); };
    tr.querySelectorAll('input').forEach(el => el.addEventListener('input', update));
    tbody.appendChild(tr);
    tr.querySelectorAll('input,button').forEach(el => el.disabled = locked);
    update();
  }
  function update() {
    const items = [];
    let cents = 0;
    for (const tr of tbody.children) {
      const description = tr.querySelector('.part-desc').value.trim();
      const qty = Number(tr.querySelector('.part-qty').value);
      const price = Number(tr.querySelector('.part-price').value);
      if (!description && !price) continue;
      const line = Math.round(qty * price * 100);
      tr.querySelector('.part-total').textContent = money(line / 100);
      items.push({description, qty, price}); cents += line;
    }
    cartInput.value = JSON.stringify(items);
    descInput.value = items.map(x => `${x.description} (x${x.qty} @ R${money(x.price)})`).join('\n');
    partsInput.value = money(cents / 100);
    if (typeof calculate === 'function') calculate();
  }
  function init(items, legacyDescription, legacyPrice) {
    tbody.innerHTML = '';
    if (Array.isArray(items) && items.length) {
      for (const item of items) row(String(item.description ?? ''), String(item.qty ?? 1), String(item.price ?? 0));
    } else if (legacyDescription || Number(legacyPrice)) {
      // Legacy FileMaker job: preserve the old parts amount as a single cart item.
      row(String(legacyDescription || 'Legacy parts'), '1', String(Number(legacyPrice) || 0));
    } else { row('', '1', '0'); }
    update();
  }
  function setLocked(value) {
    locked = value; add.disabled = value;
    tbody.querySelectorAll('input,button').forEach(el => el.disabled = value);
  }
  add.addEventListener('click', () => { if (!locked) row('', '1', '0'); });
  return {init,setLocked,update};
})();
