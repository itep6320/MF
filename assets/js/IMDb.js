// IMDb.js

var imdbListener = null; // 🔹 Pour éviter les doublons d’écouteurs

if (typeof initDescriptionToggle === 'function') initDescriptionToggle();
initIMDbSearch();

/* ======================================================
   INITIALISATION DE LA RECHERCHE IMDb
====================================================== */
function initIMDbSearch() {
    console.log("🎬 initIMDbSearch appelée");

    const searchInput = document.getElementById('film-search');
    const resultsList = document.getElementById('search-results');
    if (!searchInput || !resultsList) return;

    // ✅ Supprimer tout ancien listener avant d’en ajouter un nouveau
    if (imdbListener) {
        searchInput.removeEventListener('input', imdbListener);
    }

    let timeout;

    imdbListener = function () {
        clearTimeout(timeout);
        const query = searchInput.value.trim();

        if (!query) {
            resultsList.innerHTML = '';
            resultsList.classList.add('hidden');
            return;
        }

        timeout = setTimeout(() => {
            console.log("🔎 Requête IMDb pour :", query);
            fetch(`IMDb.php?q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(data => {
                    console.log("📥 Résultat IMDb :", data);
                    resultsList.innerHTML = '';

                    if (!data.d || data.d.length === 0) {
                        resultsList.classList.add('hidden');
                        return;
                    }

                    data.d.forEach(f => {
                        // Vérifie qu’il s’agit bien d’un film
                        if (f.qid && f.qid.startsWith('movie')) {
                            const li = document.createElement('li');
                            li.textContent = `${f.l} (${f.y || 'N/A'})`;
                            li.className = 'p-2 hover:bg-gray-200 cursor-pointer';
                            li.addEventListener('click', () => {
                                console.log("🖱️ Sélection IMDb :", f.id || f.qid);
                                selectFilm_IMDb(f.id || f.qid);
                            });
                            resultsList.appendChild(li);
                        }
                    });

                    resultsList.classList.remove('hidden');
                })
                .catch(err => console.error('Erreur IMDb:', err));
        }, 300);
    };

    // ✅ Ajoute un seul écouteur propre
    searchInput.addEventListener('input', imdbListener);
    return imdbListener;
}

/* ======================================================
   MISE À JOUR DU FILM VIA IMDb
====================================================== */
function selectFilm_IMDb(imdbID) {
    console.log("Appel MAJ du film IMDb :", imdbID);

    fetch('update_film_online_IMDb.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: filmId, imdbID })
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
                alert('Erreur lors de la mise à jour du film.');
                return;
            }

            const f = data.film;
            console.log("✅ Film mis à jour depuis IMDb :", f.titre);

            // 🔹 Met à jour les infos dans le DOM
            updateFilmDOM(f);

            // 🔹 Réinitialise la recherche
            resetSearchUI();

            // 🔹 Réactive la recherche IMDb proprement
            initIMDbSearch();
        })
        .catch(err => console.error('Erreur lors de la MAJ IMDb :', err));
}

/* ======================================================
   MISE À JOUR DU DOM APRÈS MÀJ DU FILM
====================================================== */
function updateFilmDOM(f) {
    const titreEl = document.querySelector('h2');
    if (titreEl) titreEl.textContent = `${f.titre || 'Titre inconnu'} (${f.annee || '????'})`;

    const genreEl = document.getElementById('film-genre');
    if (genreEl) genreEl.textContent = f.genre || '—';

    const descEl = document.getElementById('film-description');
    if (descEl) descEl.textContent = f.description || '—';

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
