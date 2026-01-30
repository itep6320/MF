// IMDb_series.js
var imdbSeriesListener = null;

function initIMDbSeriesSearch() {
    console.log("📺 initIMDbSeriesSearch appelée");

    const searchInput = document.getElementById('serie-search');
    const resultsList = document.getElementById('search-results');
    if (!searchInput || !resultsList) return;

    // Supprimer tout ancien listener avant d'en ajouter un nouveau
    if (imdbSeriesListener) {
        searchInput.removeEventListener('input', imdbSeriesListener);
    }

    let timeout;

    imdbSeriesListener = function () {
        clearTimeout(timeout);
        const query = searchInput.value.trim();

        if (!query) {
            resultsList.innerHTML = '';
            resultsList.classList.add('hidden');
            return;
        }

        timeout = setTimeout(() => {
            console.log("🔎 Requête IMDb pour série :", query);
            fetch(`IMDb.php?q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(data => {
                    console.log("📥 Résultat IMDb :", data);
                    resultsList.innerHTML = '';

                    if (!data.d || data.d.length === 0) {
                        resultsList.classList.add('hidden');
                        return;
                    }

                    data.d.forEach(s => {
                        // Vérifie qu'il s'agit bien d'une série TV
                        if (s.qid && (s.qid.startsWith('tvSeries') || s.qid.startsWith('tvMiniSeries'))) {
                            const li = document.createElement('li');
                            li.textContent = `${s.l} (${s.y || 'N/A'})`;
                            li.className = 'p-2 hover:bg-gray-200 cursor-pointer';
                            li.addEventListener('click', () => {
                                console.log("🖱️ Sélection IMDb :", s.id || s.qid);
                                selectSerie_IMDb(s.id || s.qid);
                            });
                            resultsList.appendChild(li);
                        }
                    });

                    resultsList.classList.remove('hidden');
                })
                .catch(err => console.error('Erreur IMDb:', err));
        }, 300);
    };

    searchInput.addEventListener('input', imdbSeriesListener);
    return imdbSeriesListener;
}

/* ======================================================
   MISE À JOUR DE LA SÉRIE VIA IMDb
====================================================== */
function selectSerie_IMDb(imdbID) {
    console.log("Appel MAJ de la série IMDb :", imdbID);

    fetch('update_serie_online_IMDb.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: sérieId, imdbID })
    })
        .then(res => res.text())
        .then(text => {
            let data;
            try { data = JSON.parse(text); }
            catch (err) {
                console.error('Erreur JSON IMDb :', text);
                return;
            }

            if (!data.success) {
                alert('Erreur lors de la mise à jour de la série.');
                return;
            }

            const s = data.serie;
            console.log("✅ Série mise à jour depuis IMDb :", s.titre);

            // Met à jour les infos dans le DOM
            updateSerieDOM(s);

            // Réinitialise la recherche
            resetSearchUI();

            // Réactive la recherche IMDb proprement
            initIMDbSeriesSearch();
        })
        .catch(err => console.error('Erreur lors de la MAJ IMDb :', err));
}

/* ======================================================
   MISE À JOUR DU DOM APRÈS MÀJ DE LA SÉRIE
====================================================== */
function updateSerieDOM(s) {
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
}

/* ======================================================
   RÉINITIALISE L'UI APRÈS UNE MÀJ
====================================================== */
function resetSearchUI() {
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
}