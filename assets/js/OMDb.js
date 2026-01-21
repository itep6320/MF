// OMDb.js
var omdbApiKey = '5cf17b3b';
var omdbListener = null; // pour éviter les doublons d’écouteurs

if (typeof initDescriptionToggle === 'function') initDescriptionToggle();
initOmdbSearch();

/* ======================================================
   INITIALISATION GLOBALE DE LA RECHERCHE OMDb
====================================================== */
function initOmdbSearch() {
    console.log("🎬 initOmdbSearch appelée");

    const searchInput = document.getElementById('film-search');
    const resultsList = document.getElementById('search-results');
    if (!searchInput || !resultsList) return;

    // ✅ Supprimer tout ancien listener
    if (omdbListener) {
        searchInput.removeEventListener('input', omdbListener);
    }

    let timeout;

    omdbListener = function () {
        clearTimeout(timeout);
        const query = searchInput.value.trim();

        if (!query) {
            resultsList.innerHTML = '';
            resultsList.classList.add('hidden');
            return;
        }

        timeout = setTimeout(() => {
            console.log("🔎 Requête OMDb pour :", query);
            fetch(`https://www.omdbapi.com/?apikey=${omdbApiKey}&s=${encodeURIComponent(query)}&type=movie`)
                .then(res => res.json())
                .then(data => {
                    console.log("📥 Résultat OMDb :", data);
                    resultsList.innerHTML = '';
                    if (!data.Search) {
                        resultsList.classList.add('hidden');
                        return;
                    }

                    data.Search.forEach(f => {
                        const li = document.createElement('li');
                        li.textContent = `${f.Title} (${f.Year})`;
                        li.className = 'p-2 hover:bg-gray-200 cursor-pointer';
                        li.addEventListener('click', () => {
                            console.log("🖱️ Sélection :", f.imdbID);
                            selectFilm(f.imdbID);
                        });
                        resultsList.appendChild(li);
                    });

                    resultsList.classList.remove('hidden');
                })
                .catch(err => console.error('Erreur OMDb:', err));
        }, 300);
    };

    // ✅ Ajouter un seul écouteur "input"
    searchInput.addEventListener('input', omdbListener);
    return omdbListener;
}

/* ======================================================
   MISE À JOUR DU FILM APRÈS SÉLECTION
====================================================== */
function selectFilm(imdbID) {
    console.log("Appel MAJ du film OMDb :", imdbID);

    fetch('update_film_online_OMDb.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: filmId, imdbID })
    })
        .then(res => res.text())
        .then(text => {
            let data;
            try { data = JSON.parse(text); }
            catch (err) { console.error('Erreur JSON :', text); return; }

            if (!data.success) {
                alert('Erreur lors de la mise à jour du film.');
                return;
            }

            const f = data.film;
            console.log("✅ Film mis à jour :", f.titre);

            // 🔹 Met à jour le DOM
            const titreEl = document.querySelector('h2');
            if (titreEl) titreEl.textContent = `${f.titre} (${f.annee})`;

            const genreEl = document.getElementById('film-genre');
            if (genreEl) genreEl.textContent = f.genre || 'Genre inconnu';

            const descEl = document.getElementById('film-description');
            if (descEl) {
                descEl.textContent = f.description || 'Aucune description disponible';
                if (typeof initDescriptionToggle === 'function') initDescriptionToggle();
            }

            const imgEl = document.querySelector('img');
            if (imgEl) imgEl.src = f.affiche || 'assets/img/no-poster.jpg';

            // 🔹 Réinitialise la recherche
            const resultsList = document.getElementById('search-results');
            const searchInput = document.getElementById('film-search');
            if (resultsList) {
                resultsList.innerHTML = '';
                resultsList.classList.add('hidden');
            }
            if (searchInput) {
                searchInput.value = '';
                searchInput.focus();
            }

            // ✅ Réactive la recherche proprement
            initOmdbSearch();
        })
        .catch(err => console.error('Erreur lors de la MAJ du film :', err));
}
