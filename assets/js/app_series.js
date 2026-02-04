window.isEditingEpisode = false;

// app_series.js - Gestionnaire principal pour la recherche de séries
document.addEventListener('DOMContentLoaded', function () {
    console.log("📺 Initialisation de la recherche de séries");

    const apiRadios = document.querySelectorAll('input[name="api"]');
    const searchInput = document.getElementById('serie-search');

    // Fonction pour charger dynamiquement le bon script API
    function loadAPIScript(api) {
        // Supprimer les anciens scripts
        const oldScripts = document.querySelectorAll('[data-api-script]');
        oldScripts.forEach(s => s.remove());

        // Créer le nouveau script
        const script = document.createElement('script');
        script.setAttribute('data-api-script', 'true');

        let scriptFile = '';
        let initFunction = null;

        switch (api) {
            case 'TMDb':
                scriptFile = 'assets/js/TMDb_series.js';
                initFunction = 'initTMDbSeriesSearch';
                break;
            /*             case 'OMDb':
                            scriptFile = 'assets/js/OMDb_series.js';
                            initFunction = 'initOMDbSeriesSearch';
                            break;
                        case 'IMDb':
                            scriptFile = 'assets/js/IMDb_series.js';
                            initFunction = 'initIMDbSeriesSearch';
                            break; */
        }

        script.src = scriptFile;
        script.onload = function () {
            console.log(`✅ Script ${api} chargé`);

            // Initialiser la recherche après le chargement
            if (initFunction && typeof window[initFunction] === 'function') {
                window[initFunction]();
            }
        };

        document.body.appendChild(script);
    }

    // Charger l'API sélectionnée par défaut
    const selectedRadio = document.querySelector('input[name="api"]:checked');
    if (selectedRadio) {
        loadAPIScript(selectedRadio.value);
    }

    // Écouter les changements d'API
    apiRadios.forEach(radio => {
        radio.addEventListener('change', function () {
            console.log("🔄 Changement d'API vers :", this.value);

            // Réinitialiser les résultats
            const resultsList = document.getElementById('search-results');
            if (resultsList) {
                resultsList.innerHTML = '';
                resultsList.classList.add('hidden');
            }

            // Charger le nouveau script API
            loadAPIScript(this.value);
        });
    });

    // Permettre la recherche avec la touche Entrée
    if (searchInput) {
        searchInput.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                // Le listener de recherche est géré par chaque script API
            }
        });
    }
});

// Fonction utilitaire globale pour mettre à jour le DOM
// (utilisée par tous les scripts API)
window.updateSerieDOM = function (s) {
    const titreEl = document.getElementById('serie-titre');
    if (titreEl) titreEl.textContent = s.titre || 'Titre inconnu';

    const anneeEl = document.getElementById('serie-annee');
    if (anneeEl) anneeEl.childNodes[0].textContent = (s.annee || '????') + ' ';

    const genreEl = document.getElementById('serie-genre');
    if (genreEl) genreEl.textContent = s.genre || '—';

    const descEl = document.getElementById('serie-description');
    if (descEl) descEl.textContent = s.description || '—';

    const imgEl = document.querySelector('aside img');
    if (imgEl) imgEl.src = s.affiche || 'assets/img/no-poster.jpg';

    // Mise à jour du nombre de saisons
    const nbSaisonsEl = document.getElementById('serie-nb-saisons');
    if (nbSaisonsEl && s.nb_saisons) {
        nbSaisonsEl.textContent = s.nb_saisons;
        // Mettre à jour le pluriel
        const saisonText = nbSaisonsEl.parentElement;
        if (saisonText) {
            saisonText.innerHTML = `📺 <span id="serie-nb-saisons">${s.nb_saisons}</span> saison${s.nb_saisons > 1 ? 's' : ''}`;
        }
    }
};

// Fonction utilitaire globale pour réinitialiser l'UI
window.resetSearchUI = function () {
    const resultsList = document.getElementById('search-results');
    const searchInput = document.getElementById('serie-search');
    if (resultsList) {
        resultsList.innerHTML = '';
        resultsList.classList.add('hidden');
    }
    if (searchInput) {
        searchInput.value = '';
        searchInput.focus();
    }
};

// Edition manuelle
document.addEventListener('DOMContentLoaded', () => {

    document.querySelectorAll('.btn-edit-episode').forEach(btn => {

        btn.addEventListener('click', () => {

            const episodeId = btn.dataset.episodeId;
            window.isEditingEpisode = true;

            const block = document.querySelector(
                `.border-t[data-episode-id="${episodeId}"]`
            );
            if (!block) return;

            const titre = block.querySelector('.episode-titre');
            const description = block.querySelector('.episode-description');

            if (!titre || !description) {
                console.error('Éléments épisode introuvables', episodeId);
                return;
            }

            const original = {
                titre: titre.innerText,
                description: description.innerText
            };

            // Mode édition ON
            titre.contentEditable = true;
            description.contentEditable = true;
            titre.classList.add('bg-yellow-100');
            description.classList.add('bg-yellow-100');
            titre.focus();

            // Remplacer le bouton par Sauvegarder / Annuler
            const saveBtn = document.createElement('button');
            saveBtn.textContent = '💾';
            saveBtn.className = 'px-2 py-1 bg-green-600 text-white text-xs rounded hover:bg-green-700';

            const cancelBtn = document.createElement('button');
            cancelBtn.textContent = '❌';
            cancelBtn.className = 'px-2 py-1 bg-gray-400 text-white text-xs rounded hover:bg-gray-500';

            btn.replaceWith(saveBtn);
            saveBtn.after(cancelBtn);

            cancelBtn.addEventListener('click', () => {
                titre.innerText = original.titre;
                description.innerText = original.description;
                exitEditMode();
            });

            saveBtn.addEventListener('click', () => {
                fetch('update_episode_fields.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        csrf: csrfToken,
                        episode_id: episodeId,
                        titre: titre.innerText.trim(),
                        description: description.innerText.trim()
                    })
                })
                    .then(r => r.json())
                    .then(data => {
                        if (!data.success) {
                            alert(data.error || 'Erreur sauvegarde');
                            titre.innerText = original.titre;
                            description.innerText = original.description;
                        }
                        exitEditMode();
                    })
                    .catch(() => {
                        alert('Erreur réseau');
                        titre.innerText = original.titre;
                        exitEditMode();
                    });
            });

            function exitEditMode() {
                titre.contentEditable = false;
                description.contentEditable = false;
                window.isEditingEpisode = false;
                titre.classList.remove('bg-yellow-100');
                description.classList.remove('bg-yellow-100');

                saveBtn.replaceWith(btn);
                cancelBtn.remove();
            }
        });
    });
});
