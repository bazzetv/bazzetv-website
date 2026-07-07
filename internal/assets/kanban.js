(function () {
  const board = document.getElementById('board');
  const stages = JSON.parse(board.dataset.stages);
  const modalBackdrop = document.getElementById('modal-backdrop');
  const modalTitle = document.getElementById('modal-title');
  const form = document.getElementById('card-form');
  const deleteBtn = document.getElementById('delete-btn');
  const paymentLabels = { unpaid: 'Non payé', pending: 'En attente', partial: 'Partiel', paid: 'Payé' };

  let cards = [];

  async function api(action, payload, method) {
    const opts = { method: method || 'POST', headers: { 'Content-Type': 'application/json' } };
    if (opts.method === 'POST') opts.body = JSON.stringify(payload || {});
    const res = await fetch('api/kanban.php?action=' + action, opts);
    if (!res.ok) {
      const data = await res.json().catch(() => ({}));
      throw new Error(data.error || ('HTTP ' + res.status));
    }
    return res.json();
  }

  function fmtDate(d) {
    if (!d) return '';
    const dt = new Date(d + 'T00:00:00');
    return dt.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' });
  }

  function cardEl(card) {
    const el = document.createElement('div');
    el.className = 'card';
    el.draggable = true;
    el.dataset.id = card.id;

    const stageOptions = stages.map(([key, label]) =>
      `<option value="${key}" ${key === card.stage ? 'selected' : ''}>${label}</option>`
    ).join('');

    const amount = card.payment_amount !== null
      ? Number(card.payment_amount).toLocaleString('fr-FR', { minimumFractionDigits: 0 }) + ' ' + (card.currency || 'EUR')
      : '';

    el.innerHTML = `
      <div class="brand"></div>
      <div class="meta">
        ${card.deadline ? `<span class="tag">⏱ ${fmtDate(card.deadline)}</span>` : ''}
        ${card.contact ? `<span class="tag contact"></span>` : ''}
        <span class="tag ${card.payment_status}">${paymentLabels[card.payment_status]}${amount ? ' · ' + amount : ''}</span>
      </div>
      <div class="move-row">
        <select class="stage-select">${stageOptions}</select>
        <div class="card-actions">
          <button type="button" class="btn secondary edit-btn">✎</button>
        </div>
      </div>
    `;
    el.querySelector('.brand').textContent = card.brand;
    if (card.contact) el.querySelector('.tag.contact').textContent = card.contact;

    el.querySelector('.stage-select').addEventListener('change', async (e) => {
      await api('move', { id: card.id, stage: e.target.value });
      await load();
    });
    el.querySelector('.edit-btn').addEventListener('click', () => openModal(card));

    el.addEventListener('dragstart', (e) => {
      e.dataTransfer.setData('text/plain', String(card.id));
    });

    return el;
  }

  function render() {
    board.innerHTML = '';
    for (const [key, label] of stages) {
      const col = document.createElement('div');
      col.className = 'column';
      col.dataset.stage = key;
      const inStage = cards.filter((c) => c.stage === key);
      col.innerHTML = `<h2>${label} <span class="count">(${inStage.length})</span></h2>`;
      inStage.forEach((c) => col.appendChild(cardEl(c)));

      col.addEventListener('dragover', (e) => { e.preventDefault(); col.classList.add('dragover'); });
      col.addEventListener('dragleave', () => col.classList.remove('dragover'));
      col.addEventListener('drop', async (e) => {
        e.preventDefault();
        col.classList.remove('dragover');
        const id = e.dataTransfer.getData('text/plain');
        await api('move', { id: Number(id), stage: key });
        await load();
      });

      board.appendChild(col);
    }
  }

  async function load() {
    const data = await api('list', null, 'GET');
    cards = data.cards;
    render();
  }

  function openModal(card) {
    form.reset();
    form.id.value = card ? card.id : '';
    modalTitle.textContent = card ? 'Modifier la collaboration' : 'Nouvelle collaboration';
    deleteBtn.style.display = card ? '' : 'none';
    if (card) {
      form.brand.value = card.brand || '';
      form.contact.value = card.contact || '';
      form.deadline.value = card.deadline || '';
      form.stage.value = card.stage;
      form.payment_status.value = card.payment_status;
      form.payment_amount.value = card.payment_amount ?? '';
      form.notes.value = card.notes || '';
    }
    modalBackdrop.style.display = 'flex';
  }

  function closeModal() {
    modalBackdrop.style.display = 'none';
  }

  document.getElementById('new-card-btn').addEventListener('click', () => openModal(null));
  document.getElementById('cancel-btn').addEventListener('click', closeModal);
  modalBackdrop.addEventListener('click', (e) => { if (e.target === modalBackdrop) closeModal(); });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const payload = {
      brand: form.brand.value.trim(),
      contact: form.contact.value.trim(),
      deadline: form.deadline.value,
      stage: form.stage.value,
      payment_status: form.payment_status.value,
      payment_amount: form.payment_amount.value === '' ? null : form.payment_amount.value,
      currency: 'EUR',
      notes: form.notes.value.trim(),
    };
    if (form.id.value) {
      payload.id = Number(form.id.value);
      await api('update', payload);
    } else {
      await api('create', payload);
    }
    closeModal();
    await load();
  });

  deleteBtn.addEventListener('click', async () => {
    if (!form.id.value) return;
    if (!confirm('Supprimer cette collaboration ?')) return;
    await api('delete', { id: Number(form.id.value) });
    closeModal();
    await load();
  });

  load();
})();
