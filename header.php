<?php
// Valeurs par défaut
$activeMenu = $activeMenu ?? 'films'; // films | series
$showScan = $showScan ?? false;
$scanId = $scanId ?? 'scan-films';
$scanTitle = $scanTitle ?? 'Scanner les nouveaux films';
$scanLabel = $scanLabel ?? 'Scan';
?>
<!-- Headear PC -->
<header class="hidden md:block p-3 bg-white shadow sticky top-0 z-10">
    <div class="container mx-auto flex items-center justify-between">

        <!-- Navigation -->
        <div class="flex items-center gap-6">
            <h1 class="font-bold">
                <a href="index.php" class="<?= $activeMenu === 'films' ? 'text-blue-600' : '' ?>">
                    🎬 Mes Films
                </a>
            </h1>
            <h1 class="font-bold">
                <a href="index_series.php" class="<?= $activeMenu === 'series' ? 'text-blue-600' : '' ?>">
                    📺 Mes Séries
                </a>
            </h1>
        </div>

        <!-- User / actions -->
        <div class="flex items-center gap-3 text-sm">
            <?php if (is_logged_in()): ?>
                <div class="flex items-center gap-1">
                    Bonjour <strong><?= e($_SESSION['username']) ?></strong>
                    <?php if (is_admin()): ?>
                        <span class="ml-1 px-2 py-0.5 text-xs bg-red-600 text-white rounded-full">
                            admin
                        </span>
                    <?php endif; ?>
                </div>
                <?php if ($activeMenu === 'films'): ?>
                    <span class="text-gray-400">|</span>
                    <a href="favoris.php" class="text-yellow-600 font-semibold">★ Favoris</a>
                <?php endif; ?>
                <span class="text-gray-400">|</span>

                <a href="logout.php" class="text-blue-600 hover:underline">Se déconnecter</a>

                <!-- Notifications DESKTOP (toujours visible) -->
                <div id="notif-wrapper-desktop" class="inline relative ml-4">
                    <button id="notif-btn-desktop" class="relative">
                        🔔
                        <span id="notif-count-desktop"
                            class="absolute -top-2 -right-2 bg-red-600 text-white rounded-full text-xs px-1 hidden"></span>
                    </button>

                    <div id="notif-panel-desktop"
                        class="hidden absolute right-0 mt-2 w-80 bg-white border shadow-lg rounded-lg z-50">
                        <div class="p-2 border-b font-bold flex justify-between">
                            <span>Notifications</span>
                            <?php if (is_admin()): ?>
                                <button id="add-note-btn-desktop" class="text-blue-600 text-sm">＋ Ajouter</button>
                            <?php endif; ?>
                        </div>
                        <div id="notif-list-desktop" class="max-h-64 overflow-y-auto"></div>
                    </div>
                </div>

                <!-- Bouton Scan DESKTOP (conditionnel pour admin) -->
                <?php if (is_admin() && $showScan): ?>
                    <button id="<?= $scanId ?>-desktop" title="<?= $scanTitle ?>"
                        class="ml-3 p-2 bg-green-600 text-white rounded flex items-center hover:bg-green-700 transition">
                        <svg xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 10h18M3 6h18M3 14h18M3 18h18" />
                        </svg>
                        <?= $scanLabel ?>
                    </button>
                <?php endif; ?>

            <?php else: ?>
                <a href="login.php" class="text-blue-600 hover:underline">Se connecter</a>
                <span class="text-gray-400">|</span>
                <a href="register.php" class="text-blue-600 hover:underline">S'inscrire</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<?php
$mobileTitle = '🎬 Mes Films';

if ($activeMenu === 'series') {
    $mobileTitle = '📺 Mes Séries';
}

if ($activeMenu === 'films' && basename($_SERVER['PHP_SELF']) === 'favoris.php') {
    $mobileTitle = '⭐ Mes Favoris';
}
?>

<!-- Headear MOBILE -->
<header class="md:hidden bg-white shadow sticky top-0 z-10">
    <div class="px-3 h-14 flex items-center justify-between">

        <!-- Logo / Titre mobile -->
        <span class="font-bold text-lg flex items-center gap-1"><?= $mobileTitle ?></span>
        <div class="flex items-center gap-3">

            <?php if (is_logged_in()): ?>
                <!-- Notifications MOBILE (toujours visible) -->
                <div id="notif-wrapper-mobile" class="inline relative">
                    <button id="notif-btn-mobile" class="relative">
                        🔔
                        <span id="notif-count-mobile"
                            class="absolute -top-2 -right-2 bg-red-600 text-white rounded-full text-xs px-1 hidden"></span>
                    </button>

                    <div id="notif-panel-mobile"
                        class="hidden absolute right-0 mt-2 w-80 bg-white border shadow-lg rounded-lg z-50">
                        <div class="p-2 border-b font-bold flex justify-between">
                            <span>Notifications</span>
                            <?php if (is_admin()): ?>
                                <button id="add-note-btn-mobile" class="text-blue-600 text-sm">＋ Ajouter</button>
                            <?php endif; ?>
                        </div>
                        <div id="notif-list-mobile" class="max-h-64 overflow-y-auto"></div>
                    </div>
                </div>

                <!-- Bouton Scan MOBILE (conditionnel pour admin) -->
                <?php if (is_admin() && $showScan): ?>
                    <button id="<?= $scanId ?>-mobile" title="<?= $scanTitle ?>"
                        class="p-2 bg-green-600 text-white rounded flex items-center hover:bg-green-700 transition">
                        <svg xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 10h18M3 6h18M3 14h18M3 18h18" />
                        </svg>
                        <?= $scanLabel ?>
                    </button>
                <?php endif; ?>

                <!-- Menu burger -->
                <button id="mobile-menu-btn" class="text-2xl ml-3">☰</button>

            <?php else: ?>
                <a href="login.php" class="text-blue-600">Connexion</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- MENU DÉROULANT MOBILE -->
    <?php if (is_logged_in()): ?>
        <div id="mobile-menu" class="hidden border-t px-3 py-3 space-y-3 text-sm">

            <!-- Navigation -->
            <nav class="flex gap-4 font-semibold">
                <a href="index.php" class="<?= $activeMenu === 'films' ? 'text-blue-600' : '' ?>">🎬 Films</a>
                <a href="index_series.php" class="<?= $activeMenu === 'series' ? 'text-blue-600' : '' ?>">📺 Séries</a>
                <a href="favoris.php" class="text-yellow-600">★ Favoris</a>
                <a href="logout.php" class="text-blue-600">Se déconnecter</a>
            </nav>

        </div>
    <?php endif; ?>
</header>

<!-- Scripts Communs -->
<script>
    document.getElementById('mobile-menu-btn')?.addEventListener('click', () => {
        document.getElementById('mobile-menu')?.classList.toggle('hidden');
    });
</script>