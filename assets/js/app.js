/* ======================================================
   GESTION DES NOTIFICATIONS (Desktop + Mobile)
====================================================== */
document.addEventListener('DOMContentLoaded', () => {
    // Initialiser pour desktop ET mobile
    initNotifications('desktop');
    initNotifications('mobile');

    // Rafraîchissement auto toutes les 60s
    setInterval(() => {
        loadNotifications('desktop');
        loadNotifications('mobile');
    }, 60000);
});

function initNotifications(prefix) {
    const notifBtn = document.getElementById(`notif-btn-${prefix}`);
    const notifPanel = document.getElementById(`notif-panel-${prefix}`);
    const notifCount = document.getElementById(`notif-count-${prefix}`);
    const notifList = document.getElementById(`notif-list-${prefix}`);
    const addNoteBtn = document.getElementById(`add-note-btn-${prefix}`);

    if (!notifBtn || !notifPanel) return;

    // Toggle panneau
    notifBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        notifPanel.classList.toggle('hidden');
        if (!notifPanel.classList.contains('hidden')) {
            loadNotifications(prefix);
        }
    });

    // Fermeture clic extérieur
    document.addEventListener('click', (e) => {
        const wrapper = document.getElementById(`notif-wrapper-${prefix}`);
        if (wrapper && !wrapper.contains(e.target)) {
            notifPanel.classList.add('hidden');
        }
    });

    // Gestion modale d'ajout de notification (admin)
    if (addNoteBtn) {
        addNoteBtn.addEventListener('click', () => {
            const noteModal = document.getElementById('note-modal');
            if (noteModal) {
                noteModal.classList.remove('hidden');
                notifPanel.classList.add('hidden');
            }
        });
    }
}

function loadNotifications(prefix) {
    const notifList = document.getElementById(`notif-list-${prefix}`);
    const notifCount = document.getElementById(`notif-count-${prefix}`);

    if (!notifList) return;

    fetch('notifications.php')
        .then(r => r.json())
        .then(data => {
            notifList.innerHTML = '';
            
            if (data.length === 0) {
                notifList.innerHTML = '<div class="p-2 text-gray-500 text-sm">Aucune notification</div>';
                if (notifCount) notifCount.classList.add('hidden');
                return;
            }

            if (notifCount) {
                notifCount.textContent = data.length;
                notifCount.classList.remove('hidden');
            }

            data.forEach(note => {
                const div = document.createElement('div');
                div.className = 'p-2 border-b text-sm';
                div.innerHTML = `
                    <div class="flex justify-between items-center">
                        <div><strong>${escapeHtml(note.username)}</strong><br>${escapeHtml(note.contenu)}</div>
                        <form method="post" action="delete_notification.php">
                            <input type="hidden" name="note_id" value="${note.id}">
                            <input type="hidden" name="csrf" value="${window.csrfToken || ''}">
                            <button class="text-red-600 text-xs ml-2">🗑️</button>
                        </form>
                    </div>`;
                notifList.appendChild(div);
            });
        })
        .catch(err => {
            console.error('Erreur chargement notifications:', err);
            notifList.innerHTML = '<div class="p-2 text-red-500 text-sm">Erreur de chargement</div>';
        });
}

/* ======================================================
   GESTION MODALE D'AJOUT DE NOTIFICATION
====================================================== */
document.addEventListener('DOMContentLoaded', () => {
    const noteModal = document.getElementById('note-modal');
    const cancelNoteBtn = document.getElementById('cancel-note');

    if (cancelNoteBtn && noteModal) {
        cancelNoteBtn.addEventListener('click', () => {
            noteModal.classList.add('hidden');
        });
    }

    // Ferme si clic sur le fond noir
    if (noteModal) {
        noteModal.addEventListener('click', e => {
            if (e.target === noteModal) {
                noteModal.classList.add('hidden');
            }
        });
    }
});

/* ======================================================
   GESTION DU SCAN DES FILMS (Desktop + Mobile)
====================================================== */
document.addEventListener('DOMContentLoaded', function () {
    // Chercher les boutons de scan (desktop et mobile)
    const scanBtnDesktop = document.querySelector('[id$="-desktop"][id^="scan-"]');
    const scanBtnMobile = document.querySelector('[id$="-mobile"][id^="scan-"]');

    if (scanBtnDesktop) {
        const scanId = scanBtnDesktop.id.replace('-desktop', '');
        initScanButton(scanBtnDesktop, scanId);
    }

    if (scanBtnMobile) {
        const scanId = scanBtnMobile.id.replace('-mobile', '');
        initScanButton(scanBtnMobile, scanId);
    }

    // Fallback pour l'ancien format (sans suffixe)
    const oldScanBtn = document.getElementById('scan-films');
    if (oldScanBtn && !scanBtnDesktop && !scanBtnMobile) {
        initScanButton(oldScanBtn, 'scan-films');
    }
});

function initScanButton(btn, scanId, videoElement) {
    if (!btn) return;

    const originalLabel = btn.textContent.trim();

    btn.addEventListener('click', function () {
        let itemLabel;
        let fetchUrl;

        if (scanId === 'scan-series') {
            itemLabel = 'séries';
            fetchUrl = 'scan_series.php';
        } else if (scanId === 'scan-films') {
            itemLabel = 'films';
            fetchUrl = 'scan_films.php';
        } else if (scanId === 'scan-videos') {
            itemLabel = 'vidéos';
            fetchUrl = 'scan_videos.php';
        } else {
            alert('Scan non supporté');
            return;
        }

        if (!confirm(`Lancer le scan des ${itemLabel} ?`)) return;

        btn.disabled = true;
        btn.classList.add('opacity-50', 'cursor-not-allowed');
        btn.innerHTML = '⏳ Scan en cours...';

        if (videoElement) {
            videoElement.style.display = 'block';
            videoElement.play();
        }

        fetch(fetchUrl)
            .then(res => res.text())
            .then(data => {
                alert(`✅ Scan des ${itemLabel} terminé !\n\n${data}`);
                if (videoElement) {
                    videoElement.pause();
                    videoElement.style.display = 'none';
                }
                location.reload();
            })
            .catch(err => {
                alert(`❌ Erreur lors du scan des ${itemLabel} : ${err.message}`);
                btn.textContent = originalLabel;
                btn.disabled = false;
                btn.classList.remove('opacity-50', 'cursor-not-allowed');
                if (videoElement) {
                    videoElement.pause();
                    videoElement.style.display = 'none';
                }
            });
    });
}

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
            toggleEl.textContent = 'Plus d\'infos';
        } else {
            toggleEl.style.display = 'none';
        }
    }, 100);

    // Gestion du clic
    toggleEl.onclick = () => {
        const expanded = descEl.classList.toggle('expanded');
        descEl.classList.toggle('clamped', !expanded);
        toggleEl.textContent = expanded ? 'Moins d\'infos' : 'Plus d\'infos';
        toggleEl.classList.toggle('active', expanded);
    };
}

/* ======================================================
   FAIRE APPARAÎTRE ET DISPARAÎTRE L'ALERTE
====================================================== */
document.addEventListener('DOMContentLoaded', () => {
    const wrapper = document.getElementById('alert-notice');
    if (wrapper) {
        const alertEl = wrapper.firstElementChild;
        if (alertEl) {
            // Faire apparaître
            setTimeout(() => {
                alertEl.classList.add('opacity-100');
            }, 100);
            
            // Disparaît après 4 secondes
            setTimeout(() => {
                alertEl.classList.remove('opacity-100');
                alertEl.classList.add('opacity-0');
            }, 4000);

            // Supprime complètement après l'animation
            setTimeout(() => {
                wrapper.remove();
            }, 4500);
        }
    }
});

/* ======================================================
   ENREGISTRER LA LECTURE VIDÉO
====================================================== */
document.addEventListener('DOMContentLoaded', function () {
    const video = document.querySelector('video');
    let viewRecorded = false;

    if (video) {
        // Récupérer l'ID du film depuis un attribut data ou une variable globale
        const filmId = video.getAttribute('data-film-id');
        
        if (!filmId) {
            console.warn('⚠️ ID du film non trouvé pour l\'enregistrement de lecture');
            return;
        }

        // Option A : Enregistrer quand la vidéo démarre
        video.addEventListener('play', function () {
            if (!viewRecorded) {
                recordView(filmId);
                viewRecorded = true;
            }
        });

        // Option B : Enregistrer quand 90% est atteint (plus précis)
        video.addEventListener('timeupdate', function () {
            if (!viewRecorded && video.duration && video.currentTime / video.duration >= 0.9) {
                recordView(filmId);
                viewRecorded = true;
            }
        });
    }

    function recordView(filmId) {
        fetch('record_view.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `film_id=${encodeURIComponent(filmId)}`
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('✅ Lecture enregistrée');
                } else {
                    console.warn('⚠️ Erreur enregistrement:', data.error);
                }
            })
            .catch(error => console.error('❌ Erreur:', error));
    }
});

/* ======================================================
   UTILITAIRES
====================================================== */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateStr) {
    const d = new Date(dateStr);
    const now = new Date();
    const diff = Math.floor((now - d) / 1000);

    if (diff < 60) return 'À l\'instant';
    if (diff < 3600) return Math.floor(diff / 60) + ' min';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h';
    return d.toLocaleDateString('fr-FR');
}