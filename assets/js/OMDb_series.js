// OMDb_series.js
var omdbApiKey = '5cf17b3b';
var omdbSeriesListener = null;

function initOMDbSeriesSearch() {
    console.log("📺 initOMDbSeriesSearch appelée");

    const searchInput = document.getElementById('serie-search');
    const resultsList = document.getElementById('search-results');
    if (!searchInput || !resultsList) return;

    // Supprimer tout ancien listener
    if (omdbSeriesListener) {
        searchInput.removeEventListener('input', omdbSeriesListener);
    }

    let timeout;

    omdbSeriesListener = function () {
        clearTimeout(timeout);
        const query = searchInput.value.trim();

        if (!query) {
            resultsList.innerHTML = '';
            resultsList.classList.add('hidden');
            return;
        }

        timeout = setTimeout(() => {
            console.log("🔎 Requête OMDb pour série :", query);
            fetch(`https://www.omdbapi.com/?apikey=${omdbApiKey}&s=${encodeURIComponent(query)}&type=series`)
                .then(res => res.json())
                .then(data => {
                    console.log("📥 Résultat OMDb :", data);
                    resultsList.innerHTML = '';
                    if (!data.Search) {
                        resultsList.classList.add('hidden');
                        return;
                    }

                    data.Search.forEach(s => {
                        const li = document.createElement('li');
                        li.textContent = `${s.Title} (${s.Year})`;
                        li.className = 'p-2 hover:bg-gray-200 cursor-pointer';
                        li.addEventListener('click', () => {
                            console.log("🖱️ Sélection :", s.imdbID);
                            selectSerie_OMDb(s.imdbID);
                        });
                        resultsList.appendChild(li);
                    });

                    resultsList.classList.remove('hidden');
                })
                .catch(err => console.error('Erreur OMDb:', err));
        }, 300);
    };

    searchInput.addEventListener('input', omdbSeriesListener);
    return omdbSeriesListener;
}

/* ======================================================
   MISE À JOUR DE LA SÉRIE APRÈS SÉLECTION
====================================================== */
function selectSerie_OMDb(imdbID) {
    console.log("Appel MAJ de la série OMDb :", imdbID);

    fetch('update_serie_online_OMDb.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: sérieId, imdbID })
    })
        .then(res => res.text())
        .then(text => {
            let data;
            try { data = JSON.parse(text); }
            catch (err) { console.error('Erreur JSON :', text); return; }

            if (!data.success) {
                alert('Erreur lors de la mise à jour de la série.');
                return;
            }

            const s = data.serie;
            console.log("✅ Série mise à jour :", s.titre);

            // Met à jour le DOM
            updateSerieDOM(s);

            // Réinitialise la recherche
            resetSearchUI();

            // Réactive la recherche proprement
            initOMDbSeriesSearch();
        })
        .catch(err => console.error('Erreur lors de la MAJ de la série :', err));
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