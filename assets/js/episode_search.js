// episode_search.js - Gestion de la recherche et mise à jour des épisodes

document.addEventListener('DOMContentLoaded', function () {
    console.log("📺 Initialisation de la recherche d'épisodes");

    // Récupérer l'ID TMDb depuis la variable globale ou la page
    if (typeof tmdbSerieId !== 'undefined' && tmdbSerieId > 0) {
        window.tmdbSerieId = tmdbSerieId;
        console.log("✅ ID TMDb de la série disponible:", window.tmdbSerieId);
    } else {
        console.warn("⚠️ ID TMDb de la série non disponible. Recherchez d'abord la série.");
    }

    // Écoute des boutons 🔍 MAJ épisode
    document.querySelectorAll('.btn-search-episode').forEach(btn => {
        btn.addEventListener('click', function (e) {

            // 🛑 Bloquer TMDb si édition manuelle en cours
            if (window.isEditingEpisode) {
                console.warn('⛔ TMDb bloqué : édition manuelle en cours');
                alert('✏️ Une édition manuelle est en cours.\n\nVeuillez enregistrer ou annuler avant d’utiliser TMDb.');
                return;
            }

            const episodeId = this.dataset.episodeId;
            const saison = this.dataset.saison;
            const numero = this.dataset.numero;

            // 🛡️ Sécurité : saison / numéro obligatoires
            if (!saison || !numero) {
                console.warn('⛔ TMDb annulé : épisode hors saisons', {
                    episodeId, saison, numero
                });
                alert('❌ Impossible de mettre à jour cet épisode.\n\nSaison ou numéro manquant.');
                return;
            }

            const currentTmdbId = window.tmdbSerieId || tmdbSerieId || 0;

            if (!currentTmdbId) {
                alert(
                    '⚠️ ID TMDb manquant.\n\n' +
                    'Veuillez d\'abord :\n' +
                    '1. Rechercher la série\n' +
                    '2. Ou vérifier le champ tmdb_id en base'
                );
                console.error("tmdbSerieId non défini");
                return;
            }

            searchAndUpdateEpisode(episodeId, saison, numero, this);
        });
    });
});

/**
 * Recherche et met à jour un épisode via TMDb
 */
async function searchAndUpdateEpisode(episodeId, saison, numero, buttonElement) {

    const currentTmdbId = window.tmdbSerieId || tmdbSerieId || 0;
    console.log(`🔍 Recherche épisode S${saison}E${numero} (ID ${episodeId})`);

    // Désactiver le bouton
    const originalText = buttonElement.textContent;
    buttonElement.disabled = true;
    buttonElement.textContent = '⏳';

    try {
        const response = await fetch('update_episode_online_TMDb.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                episode_id: episodeId,
                tmdb_serie_id: currentTmdbId,
                saison: saison,
                numero_episode: numero
            })
        });

        const data = await response.json();
        console.log("📦 Réponse TMDb:", data);

        if (!data.success) {
            throw new Error(data.error || 'Erreur TMDb inconnue');
        }

        updateEpisodeDOM(episodeId, data.episode);

        // Succès visuel
        buttonElement.textContent = '✓';
        buttonElement.classList.remove('bg-blue-500', 'hover:bg-blue-600');
        buttonElement.classList.add('bg-green-500');

        setTimeout(() => {
            buttonElement.textContent = originalText;
            buttonElement.classList.remove('bg-green-500');
            buttonElement.classList.add('bg-blue-500', 'hover:bg-blue-600');
        }, 2000);

    } catch (error) {
        console.error('❌ TMDb:', error.message);
        alert('❌ ' + error.message);
        buttonElement.textContent = originalText;
    } finally {
        buttonElement.disabled = false;
    }
}

/**
 * Met à jour l'affichage d'un épisode dans le DOM
 */
function updateEpisodeDOM(episodeId, episode) {
    const episodeElement = document.querySelector(`.border-t[data-episode-id="${episodeId}"]`);
    if (!episodeElement) return;

    const titreEl = episodeElement.querySelector('.episode-titre');
    const descEl = episodeElement.querySelector('.episode-description');

    if (titreEl && episode.titre_episode) {
        titreEl.textContent = episode.titre_episode;
    }

    if (descEl && episode.description_episode) {
        descEl.textContent = episode.description_episode;
    }

    episodeElement.classList.add('bg-green-50');
    setTimeout(() => episodeElement.classList.remove('bg-green-50'), 1500);
}

/**
 * Mise à jour de tous les épisodes d'une saison
 */
window.updateAllEpisodesInSeason = async function (saison) {

    if (window.isEditingEpisode) {
        alert('✏️ Une édition manuelle est en cours.\n\nVeuillez la terminer avant une mise à jour globale.');
        return;
    }

    const currentTmdbId = window.tmdbSerieId || tmdbSerieId || 0;
    if (!currentTmdbId) {
        alert('⚠️ ID TMDb manquant.');
        return;
    }

    const buttons = document.querySelectorAll(`.btn-search-episode[data-saison="${saison}"]`);
    if (!buttons.length) {
        alert('Aucun épisode trouvé.');
        return;
    }

    if (!confirm(`🔄 Mettre à jour ${buttons.length} épisodes de la saison ${saison} ?`)) {
        return;
    }

    const progressDiv = createProgressBar(buttons.length);

    for (let i = 0; i < buttons.length; i++) {
        const btn = buttons[i];

        updateProgressBar(
            progressDiv,
            i + 1,
            buttons.length,
            `S${btn.dataset.saison}E${btn.dataset.numero}`
        );

        await searchAndUpdateEpisode(
            btn.dataset.episodeId,
            btn.dataset.saison,
            btn.dataset.numero,
            btn
        );

        await new Promise(r => setTimeout(r, 500));
    }

    removeProgressBar(progressDiv);
    alert('✅ Mise à jour terminée');
};
