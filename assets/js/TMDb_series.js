// TMDb_series.js
var tmdbApiKey = 'f75887c1f49c99a3abe4ff8a9c46c919';
var tmdbListener = null;

function initTMDbSeriesSearch() {
    console.log("📺 initTMDbSeriesSearch appelée");

    const searchInput = document.getElementById('serie-search');
    const resultsList = document.getElementById('search-results');
    if (!searchInput || !resultsList) return null;

    // Supprimer l'ancien listener
    if (tmdbListener) {
        searchInput.removeEventListener('input', tmdbListener);
    }

    let timeout;

    tmdbListener = function() {
        clearTimeout(timeout);
        const query = searchInput.value.trim();

        if (!query) {
            resultsList.innerHTML = '';
            resultsList.classList.add('hidden');
            return;
        }

        timeout = setTimeout(() => {
            console.log("🔎 Requête TMDb pour série :", query);

            fetch(`https://api.themoviedb.org/3/search/tv?api_key=${tmdbApiKey}&query=${encodeURIComponent(query)}&language=fr-FR`)
            .then(res => res.json())
            .then(data => {
                console.log("📥 Résultat TMDb :", data);
                resultsList.innerHTML = '';

                if (!data.results || data.results.length === 0) {
                    resultsList.classList.add('hidden');
                    return;
                }

                data.results.forEach(s => {
                    const li = document.createElement('li');
                    const year = s.first_air_date ? s.first_air_date.substring(0,4) : 'N/A';
                    li.textContent = `${s.name} (${year})`;
                    li.className = 'p-2 hover:bg-gray-200 cursor-pointer';
                    li.addEventListener('click', () => selectSerie_TMDb(s.id));
                    resultsList.appendChild(li);
                });

                resultsList.classList.remove('hidden');
            })
            .catch(err => console.error('Erreur TMDb:', err));
        }, 300);
    };

    searchInput.addEventListener('input', tmdbListener);
    return tmdbListener;
}

/* ======================================================
   MISE À JOUR DE LA SÉRIE VIA TMDb
====================================================== */
function selectSerie_TMDb(tmdbID) {
    console.log("Appel MAJ de la série TMDb :", tmdbID);

    fetch('update_serie_online_TMDb.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: sérieId, tmdbID })
    })
    .then(res => res.json())
    .then(data => {
        console.log("Réponse complète:", data);
        
        if (!data.success) {
            console.error("Erreur:", data.error || "Erreur inconnue");
            alert('Erreur lors de la mise à jour de la série: ' + (data.error || 'Erreur inconnue'));
            return;
        }

        const s = data.serie;
        console.log("✅ Série mise à jour depuis TMDb :", s.titre);

        // Stocker l'ID TMDb pour la recherche d'épisodes
        if (data.tmdb_id) {
            window.tmdbSerieId = data.tmdb_id;
            console.log("ID TMDb stocké pour les épisodes:", window.tmdbSerieId);
        }

        // Met à jour le DOM
        updateSerieDOM(s);

        // Réinitialise la recherche
        resetSearchUI();

        // Réactive la recherche proprement
        initTMDbSeriesSearch();
    })
    .catch(err => {
        console.error('Erreur MAJ TMDb :', err);
        alert('Erreur réseau: ' + err.message);
    });
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
    if (genreEl) genreEl.textContent = s.genre || 'Genre inconnu';

    const descEl = document.getElementById('serie-description');
    if (descEl) descEl.textContent = s.description || 'Aucune description disponible';

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