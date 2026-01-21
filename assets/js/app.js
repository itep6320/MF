
/* ======================================================
   GESTION DU SCAN DES FILMS (Folder)
====================================================== */
document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('scan-films');
    if (btn) {
        btn.addEventListener('click', function () {
            btn.disabled = true;
            btn.textContent = 'Scan en cours...';
            fetch('scan.php')
                .then(res => res.text())
                .then(data => {
                    alert(data); // ou afficher dans un div dédié
                    btn.textContent = 'Scan';
                    btn.disabled = false;
                })
                .catch(err => {
                    alert('Erreur lors du scan.');
                    btn.textContent = 'Scan';
                    btn.disabled = false;
                });
        });
    }
});

/* ======================================================
   GESTION DU CLAMP (PLUS / MOINS D'INFOS)
====================================================== */
function initDescriptionToggle() {
    const descEl = document.getElementById('film-description');
    const toggleEl = document.getElementById('toggle-description');
    if (!descEl || !toggleEl) return;

    // Appliquer le clamp par défaut
    descEl.classList.remove('expanded');
    descEl.classList.add('clamped');

    // Vérifier si ça dépasse 3 lignes
    setTimeout(() => {
        if (descEl.scrollHeight > descEl.clientHeight + 5) {
            toggleEl.style.display = 'inline';
            toggleEl.textContent = 'Plus d’infos';
        } else {
            toggleEl.style.display = 'none';
        }
    }, 100);

    // Gestion du clic
    toggleEl.onclick = () => {
        const expanded = descEl.classList.toggle('expanded');
        descEl.classList.toggle('clamped', !expanded);
        toggleEl.textContent = expanded ? 'Moins d’infos' : 'Plus d’infos';
        toggleEl.classList.toggle('active', expanded);
    };

}

/* ======================================================
   GESTION DES NOTIFICATIONS
====================================================== */
document.addEventListener('DOMContentLoaded', () => {
    const notifBtn = document.getElementById('notif-btn');
    const notifPanel = document.getElementById('notif-panel');
    const notifCount = document.getElementById('notif-count');
    const notifList = document.getElementById('notif-list');

    function loadNotifications() {
        fetch('notifications.php')
            .then(r => r.json())
            .then(data => {
                notifList.innerHTML = '';
                if (data.length === 0) {
                    notifList.innerHTML = '<div class="p-2 text-gray-500 text-sm">Aucune notification</div>';
                    notifCount.classList.add('hidden');
                    return;
                }
                notifCount.textContent = data.length;
                notifCount.classList.remove('hidden');

                data.forEach(note => {
                    const div = document.createElement('div');
                    div.className = 'p-2 border-b text-sm';
                    div.innerHTML = `
                        <div class="flex justify-between items-center">
                            <div><strong>${note.username}</strong><br>${note.contenu}</div>
                            <form method="post" action="delete_notification.php">
                                <input type="hidden" name="note_id" value="${note.id}">
                                <input type="hidden" name="csrf" value="${window.csrfToken}">
                                <button class="text-red-600 text-xs ml-2">🗑️</button>
                            </form>
                        </div>`;
                    notifList.appendChild(div);
                });
            });
    }

    notifBtn.addEventListener('click', () => {
        notifPanel.classList.toggle('hidden');
        if (!notifPanel.classList.contains('hidden')) loadNotifications();
    });

    // Fermeture clic extérieur
    document.addEventListener('click', e => {
        if (!notifPanel.contains(e.target) && e.target !== notifBtn) {
            notifPanel.classList.add('hidden');
        }
    });

    // Rafraîchissement auto toutes les 60s
    setInterval(loadNotifications, 60000);

    // --- Gestion modale d'ajout de notification (admin)
    const addNoteBtn = document.getElementById('add-note-btn');
    const noteModal = document.getElementById('note-modal');
    const cancelNoteBtn = document.getElementById('cancel-note');

    addNoteBtn?.addEventListener('click', () => {
        noteModal.classList.remove('hidden');
    });

    cancelNoteBtn?.addEventListener('click', () => {
        noteModal.classList.add('hidden');
    });

    // Ferme si clic sur le fond noir
    noteModal?.addEventListener('click', e => {
        if (e.target === noteModal) noteModal.classList.add('hidden');
    });
});
