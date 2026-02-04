/**
 * Initialise le tracking de visionnage pour une vidéo
 * @param {string} type - 'film' ou 'episode'
 * @param {number} id - ID du film ou de l'épisode
 * @param {number} threshold - Pourcentage de visionnage pour enregistrer (0.9 = 90%)
 */
function initVideoTracking(type, id, threshold = 0.9) {
    const video = document.querySelector('video');
    let viewRecorded = false;
    
    if (!video) {
        console.warn('⚠️ Aucune balise <video> trouvée');
        return;
    }
    
    console.log(`🎬 Tracking activé pour ${type} #${id} (seuil: ${threshold * 100}%)`);

    // Enregistrer au démarrage (optionnel, peut être retiré)
    video.addEventListener('play', function() {
        console.log('▶️ Lecture démarrée');
    });

    // Enregistrer quand le seuil est atteint
    video.addEventListener('timeupdate', function() {
        if (viewRecorded || !video.duration) return;
        
        const percentWatched = video.currentTime / video.duration;
        
        if (percentWatched >= threshold) {
            recordView(type, id);
            viewRecorded = true;
        }
    });

    // Enregistrer à la fin si pas encore fait
    video.addEventListener('ended', function() {
        if (!viewRecorded) {
            recordView(type, id);
            viewRecorded = true;
        }
    });
}

/**
 * Enregistre la lecture dans la base de données
 */
function recordView(type, id) {
    fetch('record_view.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `type=${encodeURIComponent(type)}&id=${encodeURIComponent(id)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log(`✅ Lecture de ${type} #${id} enregistrée`);
        } else {
            console.error('❌ Erreur:', data.error);
        }
    })
    .catch(error => console.error('❌ Erreur réseau:', error));
}