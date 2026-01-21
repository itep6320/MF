// TMDb.js
var tmdbApiKey = 'f75887c1f49c99a3abe4ff8a9c46c919';
var tmdbApiJeton = 'eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiJmNzU4ODdjMWY0OWM5OWEzYWJlNGZmOGE5YzQ2YzkxOSIsIm5iZiI6MTUwNTExMTAxOC4wMTYsInN1YiI6IjU5YjYyYmU4YzNhMzY4MmIzZDAwZDdjMiIsInNjb3BlcyI6WyJhcGlfcmVhZCJdLCJ2ZXJzaW9uIjoxfQ.b3EecPxuq466bqWrGFNcBnKp9iC1XBf5ZL7qeFMxris';

function initTMDbSearch() {
    console.log("🎬 initTMDbSearch appelée");

    const searchInput = document.getElementById('film-search');
    const resultsList = document.getElementById('search-results');
    if (!searchInput || !resultsList) return null;

    let timeout;

    const listener = function() {
        clearTimeout(timeout);
        const query = searchInput.value.trim();

        if (!query) {
            resultsList.innerHTML = '';
            resultsList.classList.add('hidden');
            return;
        }

        timeout = setTimeout(() => {
            console.log("🔎 Requête TMDb pour :", query);

            fetch(`https://api.themoviedb.org/3/search/movie?api_key=${tmdbApiKey}&query=${encodeURIComponent(query)}&language=fr-FR`)
            .then(res => res.json())
            .then(data => {
                console.log("📥 Résultat TMDb :", data);
                resultsList.innerHTML = '';

                if (!data.results || data.results.length === 0) {
                    resultsList.classList.add('hidden');
                    return;
                }

                data.results.forEach(f => {
                    const li = document.createElement('li');
                    li.textContent = `${f.title} (${f.release_date?.substring(0,4) || 'N/A'})`;
                    li.className = 'p-2 hover:bg-gray-200 cursor-pointer';
                    li.addEventListener('click', () => selectFilm_TMDb(f.id));
                    resultsList.appendChild(li);
                });

                resultsList.classList.remove('hidden');
            })
            .catch(err => console.error('Erreur TMDb:', err));
        }, 300);
    };

    searchInput.addEventListener('input', listener);
    return listener;
}

/* ======================================================
   MISE À JOUR DU FILM VIA TMDb
====================================================== */
function selectFilm_TMDb(tmdbID) {
    console.log("Appel MAJ du film TMDb :", tmdbID);

    fetch('update_film_online_TMDb.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: filmId, tmdbID })
    })
    .then(res => res.json())
    .then(data => {
        if (!data.success) {
            alert('Erreur lors de la mise à jour du film.');
            return;
        }

        const f = data.film;
        console.log("✅ Film mis à jour depuis TMDb :", f.titre);

        // Met à jour le DOM
        updateFilmDOM(f);

        // Réinitialise la recherche
        resetSearchUI();

        // Réactive la recherche proprement
        initTMDbSearch();
    })
    .catch(err => console.error('Erreur MAJ TMDb :', err));
}

/* ======================================================
   MISE À JOUR DU DOM APRÈS MÀJ DU FILM
====================================================== */
function updateFilmDOM(f) {
    const titreEl = document.querySelector('h2');
    if (titreEl) titreEl.textContent = `${f.titre || 'Titre inconnu'} (${f.annee || '????'})`;

    const genreEl = document.getElementById('film-genre');
    if (genreEl) genreEl.textContent = f.genre || 'Genre inconnu';

    const descEl = document.getElementById('film-description');
    if (descEl) {
        descEl.textContent = f.description || 'Aucune description disponible';
        if (typeof initDescriptionToggle === 'function') initDescriptionToggle();
    }

    const imgEl = document.querySelector('img');
    if (imgEl) imgEl.src = f.affiche || 'assets/img/no-poster.jpg';
}

/* ======================================================
   RÉINITIALISE L’UI APRÈS UNE MÀJ
====================================================== */
function resetSearchUI() {
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
}
