<?php
$currentPath = $_SERVER['REQUEST_URI'] ?? '/';
$user = $user ?? null;

function isActive(string $path, string $currentPath): string {
    return str_starts_with($currentPath, $path) ? 'bg-primary text-primary-content' : '';
}
?>

<!-- Mobile menu button -->
<div class="lg:hidden">
    <div class="navbar bg-base-100 shadow-lg" x-data="{ mobileMenuOpen: false }">
        <div class="navbar-start">
            <button @click="mobileMenuOpen = !mobileMenuOpen" class="btn btn-square btn-ghost">
                <i class="fas fa-bars text-xl"></i>
            </button>
            <a href="/dashboard" class="btn btn-ghost normal-case text-xl font-bold text-primary">
                <i class="fas fa-file-contract mr-2"></i>
                <?= e($app_name) ?>
            </a>
        </div>
        
        <div class="navbar-end">
            <!-- Global Search (Mobile) -->
            <div class="form-control">
                <input 
                    type="text" 
                    placeholder="Suchen..." 
                    class="input input-bordered input-sm w-24 md:w-auto"
                    hx-get="/dashboard/search"
                    hx-trigger="keyup changed delay:300ms"
                    hx-target="#search-results"
                    name="q"
                >
            </div>
            
            <!-- User Menu -->
            <?= $this->include('partials/user-menu') ?>
        </div>
        
        <!-- Mobile Menu -->
        <div x-show="mobileMenuOpen" 
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 transform -translate-y-2"
             x-transition:enter-end="opacity-100 transform translate-y-0"
             class="absolute top-16 left-0 right-0 bg-base-100 shadow-lg z-50 lg:hidden">
            
            <ul class="menu menu-compact p-2">
                <li><a href="/dashboard" class="<?= isActive('/dashboard', $currentPath) ?>">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a></li>
                
                <?php if (rbac_check('read', 'vertrag')): ?>
                <li><a href="/vertraege" class="<?= isActive('/vertraege', $currentPath) ?>">
                    <i class="fas fa-file-contract"></i> Verträge
                </a></li>
                <?php endif; ?>
                
                <?php if (rbac_check('read', 'korrespondent')): ?>
                <li><a href="/korrespondenten" class="<?= isActive('/korrespondenten', $currentPath) ?>">
                    <i class="fas fa-building"></i> Korrespondenten
                </a></li>
                <?php endif; ?>
                
                <?php if (rbac_check('read', 'zugangsdaten')): ?>
                <li><a href="/zugangsdaten" class="<?= isActive('/zugangsdaten', $currentPath) ?>">
                    <i class="fas fa-key"></i> Zugangsdaten
                </a></li>
                <?php endif; ?>
                
                <?php if (rbac_check('read', 'dokument')): ?>
                <li><a href="/dokumente" class="<?= isActive('/dokumente', $currentPath) ?>">
                    <i class="fas fa-file-pdf"></i> Dokumente
                </a></li>
                <?php endif; ?>
                
                <li><a href="/liquiditaet" class="<?= isActive('/liquiditaet', $currentPath) ?>">
                    <i class="fas fa-chart-line"></i> Liquiditätsplanung
                </a></li>
                
                <?php if (rbac_check('read', 'freigaben')): ?>
                <li><a href="/freigaben" class="<?= isActive('/freigaben', $currentPath) ?>">
                    <i class="fas fa-share-alt"></i> Freigaben
                </a></li>
                <?php endif; ?>
                
                <li><a href="/notfall" class="<?= isActive('/notfall', $currentPath) ?>">
                    <i class="fas fa-exclamation-triangle"></i> Notfall-Modus
                </a></li>
                
                <?php if ($user && $user->isAdmin()): ?>
                <li class="menu-title"><span>Administration</span></li>
                
                <li><a href="/admin/benutzer" class="<?= isActive('/admin/benutzer', $currentPath) ?>">
                    <i class="fas fa-users"></i> Benutzer
                </a></li>
                
                <li><a href="/admin/rollen" class="<?= isActive('/admin/rollen', $currentPath) ?>">
                    <i class="fas fa-shield-alt"></i> Rollen & Rechte
                </a></li>
                
                <li><a href="/admin/audit" class="<?= isActive('/admin/audit', $currentPath) ?>">
                    <i class="fas fa-history"></i> Audit-Log
                </a></li>
                
                <li><a href="/admin/settings" class="<?= isActive('/admin/settings', $currentPath) ?>">
                    <i class="fas fa-cog"></i> Einstellungen
                </a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<!-- Desktop Sidebar -->
<div class="hidden lg:flex lg:flex-shrink-0">
    <div class="flex flex-col w-64">
        <div class="flex flex-col flex-grow bg-base-200 pt-5 pb-4 overflow-y-auto">
            
            <!-- Logo -->
            <div class="flex items-center flex-shrink-0 px-4 mb-8">
                <a href="/dashboard" class="flex items-center text-2xl font-bold text-primary">
                    <i class="fas fa-file-contract mr-3"></i>
                    <span><?= e($app_name) ?></span>
                </a>
            </div>
            
            <!-- Global Search -->
            <div class="px-4 mb-6">
                <div class="relative" x-data="{ searchOpen: false, searchResults: [] }">
                    <input 
                        type="text" 
                        id="global-search"
                        placeholder="Suchen... (Strg+K)" 
                        class="input input-bordered w-full pl-10"
                        hx-get="/dashboard/search"
                        hx-trigger="keyup changed delay:300ms"
                        hx-target="#search-results"
                        @focus="searchOpen = true"
                        @blur="setTimeout(() => searchOpen = false, 200)"
                        name="q"
                    >
                    <i class="fas fa-search absolute left-3 top-3 text-base-content/50"></i>
                    
                    <!-- Search Results Dropdown -->
                    <div id="search-results" 
                         x-show="searchOpen" 
                         x-transition
                         class="absolute z-10 w-full mt-1 bg-base-100 border border-base-300 rounded-lg shadow-lg max-h-64 overflow-y-auto">
                        <!-- Results will be loaded here by htmx -->
                    </div>
                </div>
            </div>
            
            <!-- Navigation Menu -->
            <nav class="flex-1 px-2 space-y-1">
                <ul class="menu menu-compact">
                    
                    <!-- Dashboard -->
                    <li>
                        <a href="/dashboard" class="<?= isActive('/dashboard', $currentPath) ?>">
                            <i class="fas fa-tachometer-alt w-5"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    
                    <!-- Verträge -->
                    <?php if (rbac_check('read', 'vertrag')): ?>
                    <li>
                        <a href="/vertraege" class="<?= isActive('/vertraege', $currentPath) ?>">
                            <i class="fas fa-file-contract w-5"></i>
                            <span>Verträge</span>
                            <div class="badge badge-primary badge-sm">
                                <span hx-get="/api/stats/vertraege-count" hx-trigger="load">...</span>
                            </div>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <!-- Korrespondenten -->
                    <?php if (rbac_check('read', 'korrespondent')): ?>
                    <li>
                        <a href="/korrespondenten" class="<?= isActive('/korrespondenten', $currentPath) ?>">
                            <i class="fas fa-building w-5"></i>
                            <span>Korrespondenten</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <!-- Zugangsdaten -->
                    <?php if (rbac_check('read', 'zugangsdaten')): ?>
                    <li>
                        <a href="/zugangsdaten" class="<?= isActive('/zugangsdaten', $currentPath) ?>">
                            <i class="fas fa-key w-5"></i>
                            <span>Zugangsdaten</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <!-- Dokumente -->
                    <?php if (rbac_check('read', 'dokument')): ?>
                    <li>
                        <a href="/dokumente" class="<?= isActive('/dokumente', $currentPath) ?>">
                            <i class="fas fa-file-pdf w-5"></i>
                            <span>Dokumente</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <!-- Liquiditätsplanung -->
                    <li>
                        <a href="/liquiditaet" class="<?= isActive('/liquiditaet', $currentPath) ?>">
                            <i class="fas fa-chart-line w-5"></i>
                            <span>Liquiditätsplanung</span>
                        </a>
                    </li>
                    
                    <!-- Freigaben -->
                    <?php if (rbac_check('read', 'freigaben')): ?>
                    <li>
                        <a href="/freigaben" class="<?= isActive('/freigaben', $currentPath) ?>">
                            <i class="fas fa-share-alt w-5"></i>
                            <span>Freigaben</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <!-- Divider -->
                    <li class="menu-title mt-8">
                        <span>Spezial</span>
                    </li>
                    
                    <!-- Notfall-Modus -->
                    <li>
                        <a href="/notfall" class="<?= isActive('/notfall', $currentPath) ?> text-warning">
                            <i class="fas fa-exclamation-triangle w-5"></i>
                            <span>Notfall-Modus</span>
                        </a>
                    </li>
                    
                    <!-- 2FA Setup -->
                    <?php if ($user && !$user->has2FA()): ?>
                    <li>
                        <a href="/2fa/setup" class="text-info">
                            <i class="fas fa-shield-alt w-5"></i>
                            <span>2FA Einrichten</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <!-- Administration -->
                    <?php if ($user && $user->isAdmin()): ?>
                    <li class="menu-title mt-8">
                        <span>Administration</span>
                    </li>
                    
                    <li>
                        <a href="/admin/benutzer" class="<?= isActive('/admin/benutzer', $currentPath) ?>">
                            <i class="fas fa-users w-5"></i>
                            <span>Benutzer</span>
                        </a>
                    </li>
                    
                    <li>
                        <a href="/admin/rollen" class="<?= isActive('/admin/rollen', $currentPath) ?>">
                            <i class="fas fa-shield-alt w-5"></i>
                            <span>Rollen & Rechte</span>
                        </a>
                    </li>
                    
                    <li>
                        <a href="/admin/audit" class="<?= isActive('/admin/audit', $currentPath) ?>">
                            <i class="fas fa-history w-5"></i>
                            <span>Audit-Log</span>
                        </a>
                    </li>
                    
                    <li>
                        <a href="/admin/settings" class="<?= isActive('/admin/settings', $currentPath) ?>">
                            <i class="fas fa-cog w-5"></i>
                            <span>Einstellungen</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                </ul>
            </nav>
            
            <!-- User Info -->
            <div class="flex-shrink-0 flex border-t border-base-300 p-4">
                <div class="flex items-center">
                    <div class="avatar placeholder">
                        <div class="bg-neutral-focus text-neutral-content rounded-full w-10">
                            <span class="text-sm">
                                <?= $user ? strtoupper(substr($user->name, 0, 2)) : 'G' ?>
                            </span>
                        </div>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-base-content">
                            <?= $user ? e($user->name) : 'Gast' ?>
                        </p>
                        <p class="text-xs text-base-content/70">
                            <?= $user ? e($user->rolle_name) : 'Unbekannt' ?>
                        </p>
                    </div>
                    
                    <!-- User Menu Dropdown -->
                    <?= $this->include('partials/user-menu') ?>
                </div>
            </div>
            
        </div>
    </div>
</div>

<style>
    /* Fixed sidebar layout */
    .lg\:ml-64 {
        margin-left: 16rem; /* 64 * 0.25rem = 16rem */
    }
    
    /* Smooth transitions for mobile menu */
    .navbar {
        transition: all 0.3s ease;
    }
    
    /* Search results styling */
    #search-results {
        backdrop-filter: blur(8px);
    }
    
    /* Menu item hover effects */
    .menu li > a:hover {
        transform: translateX(2px);
        transition: transform 0.2s ease;
    }
    
    /* Active menu item indicator */
    .menu li > a.bg-primary {
        box-shadow: 0 4px 14px 0 rgba(59, 130, 246, 0.3);
    }
</style>