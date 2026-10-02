    </main><!-- /.page-content -->
  </div><!-- /.main-wrap -->
</div><!-- /.app-shell -->

<!-- Modal Universelle de Confirmation de Suppression -->
<div id="confirmDeleteModal" class="modal hidden">
  <div class="modal-card" style="max-width: 420px; text-align: center; padding: 26px 22px;">
    <button type="button" class="modal-close" onclick="closeConfirmModal()" aria-label="Fermer">&#x2715;</button>
    <div style="width: 48px; height: 48px; border-radius: 50%; background: var(--red-50); color: var(--red-500); display: flex; align-items: center; justify-content: center; margin: 0 auto 14px;">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="3 6 5 6 21 6"></polyline>
        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
        <line x1="10" y1="11" x2="10" y2="17"></line>
        <line x1="14" y1="11" x2="14" y2="17"></line>
      </svg>
    </div>
    <h2 id="confirmModalTitle" style="font-size: 16.5px; font-weight: 700; color: var(--text-primary); margin-bottom: 6px; padding-right: 0;">Confirmer la suppression</h2>
    <p id="confirmModalMessage" style="font-size: 13px; color: var(--text-muted); line-height: 1.5; margin-bottom: 22px;">
      Etes-vous sur de vouloir effectuer cette suppression ?
    </p>
    <div style="display: flex; gap: 10px; justify-content: center;">
      <button type="button" class="btn btn-secondary" onclick="closeConfirmModal()" style="min-width: 100px;">Annuler</button>
      <button type="button" id="confirmModalSubmitBtn" class="btn btn-danger" style="min-width: 120px;">Supprimer</button>
    </div>
  </div>
</div>

<script>
  let _confirmTargetForm = null;

  function showDeleteModal(options) {
    const modal = document.getElementById('confirmDeleteModal');
    const titleEl = document.getElementById('confirmModalTitle');
    const msgEl = document.getElementById('confirmModalMessage');
    const confirmBtn = document.getElementById('confirmModalSubmitBtn');

    if (options.title) titleEl.textContent = options.title;
    if (options.message) msgEl.textContent = options.message;

    _confirmTargetForm = options.form || null;

    confirmBtn.onclick = function() {
      if (typeof options.onConfirm === 'function') {
        options.onConfirm();
      } else if (_confirmTargetForm) {
        _confirmTargetForm.submit();
      }
      closeConfirmModal();
    };

    modal.classList.remove('hidden');
  }

  function closeConfirmModal() {
    const modal = document.getElementById('confirmDeleteModal');
    if (modal) modal.classList.add('hidden');
    _confirmTargetForm = null;
  }

  window.addEventListener('click', e => {
    const modal = document.getElementById('confirmDeleteModal');
    if (e.target === modal) closeConfirmModal();
  });
</script>

</body>
</html>