<?php
require_once __DIR__ . '/config.php';

// 🔒 Protection : seuls les admins peuvent lancer le scan
if (!is_logged_in() || !is_admin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Accès interdit']);
    exit;
}

header('Content-Type: text/plain; charset=utf-8');

// ⚙️ Chargement du chemin depuis .env
$envPath = __DIR__ . '/../.env-mf';
if (!file_exists($envPath)) {
    $envPath = __DIR__ . '/.env-mf';
}
$env = parse_ini_file($envPath);
$rootPath = rtrim($env['SERIES_PATH'] ?? '/volume2/Séries', '/');

// 🎥 Extensions vidéo autorisées
$videoExtensions = ['mp4', 'mkv', 'avi', 'mov', 'wmv', 'm4v'];

// Fonctions utilitaires
function getVideoType(string $filename): ?string
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, ['mp4', 'mkv', 'avi', 'mov', 'wmv', 'm4v']) ? $ext : null;
}
function isVideoFile(string $file): bool
{
    return preg_match('/\.(mp4|mkv|avi|mov|wmv|m4v)$/i', $file);
}

function parseEpisodeInfo(string $filename): array
{
    $name = strtolower($filename);

    $patterns = [
        '/s(\d{1,2})[\.\-_ ]?e(\d{1,3})/i',
        '/(\d{1,2})x(\d{1,3})/i',
        '/(\d{1,2})[\.\-_ ](\d{1,3})/'
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $name, $m)) {
            return [
                'saison'  => (int)$m[1],
                'episode' => (int)$m[2]
            ];
        }
    }

    return ['saison' => null, 'episode' => null];
}

function cleanEpisodeTitle(string $filename): string
{
    $title = pathinfo($filename, PATHINFO_FILENAME);

    // Normalisation
    $title = str_replace(['.', '_'], ' ', $title);

    // Supprimer infos épisode (S01E02, 1x02, E18)
    $title = preg_replace('/\bS\d{1,2}E\d{1,3}\b/i', '', $title);
    $title = preg_replace('/\b\d{1,2}x\d{1,3}\b/i', '', $title);
    $title = preg_replace('/\bE\d{1,3}\b/i', '', $title);

    // Supprimer tags techniques
    $title = preg_replace(
        '/\b(vostfr|french|multi|1080p|720p|hdtv|webrip|bluray|x264|x265|aac|dvdrip)\b/i',
        '',
        $title
    );

    // Nettoyage final
    $title = trim(preg_replace('/\s+/', ' ', $title));

    return $title;
}

// --------------------------------------------------
// Chargement de l'existant
// --------------------------------------------------

echo "=== 📺 SCAN DES SÉRIES ===\n";

// Séries existantes indexées PAR CHEMIN
$existingSeriesStmt = $pdo->query('SELECT id, titre, chemin FROM series');
$existingSeriesByPath = [];

foreach ($existingSeriesStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $existingSeriesByPath[$row['chemin']] = $row;
}

// Épisodes existants indexés PAR CHEMIN COMPLET
$existingEpisodesStmt = $pdo->query('SELECT id, chemin FROM episodes');
$existingEpisodesByPath = [];

foreach ($existingEpisodesStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $existingEpisodesByPath[$row['chemin']] = $row['id'];
}

// Stats
$stats = [
    'series_ajoutees'    => 0,
    'series_existantes' => 0,
    'episodes_ajoutes'  => 0,
    'episodes_ignores'  => 0,
    'episodes_supprimes' => 0,
];

// Pour détecter les fichiers supprimés
$foundEpisodePaths = [];

// --------------------------------------------------
// Scan du filesystem
// --------------------------------------------------

if (!is_dir($rootPath)) {
    exit("❌ Répertoire introuvable : $rootPath\n");
}

$seriesIterator = new DirectoryIterator($rootPath);

foreach ($seriesIterator as $serieDir) {
    if ($serieDir->isDot() || !$serieDir->isDir()) continue;
    if (str_starts_with($serieDir->getFilename(), '@')) continue;

    $seriePath  = $serieDir->getPathname();
    $serieTitle = $serieDir->getFilename();

    echo "\n🔍 Série : $serieTitle\n";

    // ➤ Série existante ou nouvelle ?
    if (isset($existingSeriesByPath[$seriePath])) {
        $serieId = $existingSeriesByPath[$seriePath]['id'];
        $stats['series_existantes']++;
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO series (titre, chemin) VALUES (?, ?)'
        );
        $stmt->execute([$serieTitle, $seriePath]);

        $serieId = (int)$pdo->lastInsertId();
        $existingSeriesByPath[$seriePath] = [
            'id'     => $serieId,
            'titre'  => $serieTitle,
            'chemin' => $seriePath
        ];

        $stats['series_ajoutees']++;
        echo "   ➕ Série ajoutée\n";
    }

    // --------------------------------------------------
    // Scan des épisodes
    // --------------------------------------------------

    $rii = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($seriePath, FilesystemIterator::SKIP_DOTS)
    );

    $docEpisodeCounter = 1;

    foreach ($rii as $file) {
        if (!$file->isFile()) continue;
        if (!isVideoFile($file->getFilename())) continue;

        $fullPath = $file->getPathname();
        $foundEpisodePaths[] = $fullPath;

        // Épisode déjà en base
        if (isset($existingEpisodesByPath[$fullPath])) {
            $stats['episodes_ignores']++;
            $videoType = getVideoType($file->getFilename());

            $stmt = $pdo->prepare(
                'UPDATE episodes
                        SET fichier_existe = 1, video_type = ?
                         WHERE id = ?'
            );
            $stmt->execute([
                $videoType,
                $existingEpisodesByPath[$fullPath]
            ]);

            $stats['episodes_ignores']++;
            continue;
        }

        $info = parseEpisodeInfo($file->getFilename());

        if ($info['saison'] !== null) {
            $saison  = $info['saison'];
            $episode = $info['episode'];
            $cleanTitle = cleanEpisodeTitle($file->getFilename());

            if (!empty($cleanTitle)) {
                $titreEp = "S{$saison}E{$episode} — {$cleanTitle}";
            } else {
                $titreEp = "S{$saison}E{$episode}";
            }
        } else {
            // Mode documentaire
            $saison  = 1;
            $episode = $docEpisodeCounter++;
            $titreClean = cleanEpisodeTitle($file->getFilename());
            $titreEp = $titreClean !== '' ? $titreClean : "Épisode {$episode}";
        }

        $videoType = getVideoType($file->getFilename());
        $fichierExiste = file_exists($fullPath) ? 1 : 0;

        $stmt = $pdo->prepare(
            'INSERT INTO episodes
     (serie_id, saison, numero_episode, chemin, titre_episode, fichier_existe, video_type)
     VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $serieId,
            $saison,
            $episode,
            $fullPath,
            $titreEp,
            $fichierExiste,
            $videoType
        ]);

        $stats['episodes_ajoutes']++;
        echo "   ➕ Épisode ajouté : $titreEp\n";
    }

    // Mise à jour du nombre de saisons
    $stmt = $pdo->prepare(
        'UPDATE series
         SET nb_saisons = (
             SELECT COUNT(DISTINCT saison)
             FROM episodes
             WHERE serie_id = ?
         )
         WHERE id = ?'
    );
    $stmt->execute([$serieId, $serieId]);
}

// --------------------------------------------------
// Suppression des épisodes dont le fichier n'existe plus
// --------------------------------------------------

echo "\n=== 🧹 Nettoyage des fichiers supprimés ===\n";

foreach ($existingEpisodesByPath as $path => $episodeId) {
    if (!in_array($path, $foundEpisodePaths, true) && !file_exists($path)) {
        $stmt = $pdo->prepare('DELETE FROM episodes WHERE id = ?');
        $stmt->execute([$episodeId]);

        echo "🗑️ Supprimé : $path\n";
        $stats['episodes_supprimes']++;
    }
}

// --------------------------------------------------
// Résumé
// --------------------------------------------------

echo "\n=== ✅ SCAN TERMINÉ ===\n";
echo "Séries ajoutées     : {$stats['series_ajoutees']}\n";
echo "Séries existantes   : {$stats['series_existantes']}\n";
echo "Épisodes ajoutés    : {$stats['episodes_ajoutes']}\n";
echo "Épisodes ignorés    : {$stats['episodes_ignores']}\n";
echo "Épisodes supprimés  : {$stats['episodes_supprimes']}\n";
