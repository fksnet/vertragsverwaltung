<?php
$user = $user ?? null;
?>

<div class="dropdown dropdown-end">
    <label tabindex="0" class="btn btn-ghost btn-circle avatar">
        <div class="avatar placeholder">
            <div class="bg-neutral-focus text-neutral-content rounded-full w-10">
                <span class="text-sm">
                    <?= $user ? strtoupper(substr($user->name, 0, 2)) : 'G' ?>
                </span>
            </div>
        </div>
    </label>
    
    <ul tabindex="0" class="mt-3 z-[1] p-2 shadow menu menu-sm dropdown-content bg-base-100 rounded-box w-64 border border-base-300">
        
        <!-- User Info Header -->
        <li class="menu-title">
            <div class="flex items-center space-x-3 p-2">
                <div class="avatar placeholder">
                    <div class="bg-primary text-primary-content rounded-full w-12">
                        <span class="text-lg font-bold">
                            <?= $user ? strtoupper(substr($user->name, 0, 2)) : 'G' ?>
                        </span>
                    </div>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-base-content truncate">
                        <?= $user ? e($user->name) : 'Gast-Benutzer' ?>
                    </p>
                    <p class="text-xs text-base-content/70 truncate">
                        <?= $user ? e($user->email) : 'Keine E-Mail' ?>
                    </p>
                    <div class="flex items-center mt-1">
                        <span class="badge badge-outline badge-xs">
                            <?= $user ? e($user->rolle_name) : 'Unbekannt' ?>
                        </span>
                        <?php if ($user && $user->has2FA()): ?>
                            <span class="badge badge-success badge-xs ml-1">
                                <i class="fas fa-shield-alt text-xs mr-1"></i> 2FA
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </li>
        
        <li><hr class="my-2"></li>
        
        <!-- Profile & Settings -->
        <li>
            <a href="/profile" class="flex items-center">
                <i class="fas fa-user w-4"></i>
                <span>Profil bearbeiten</span>
            </a>
        </li>
        
        <li>
            <a href="/profile/password" class="flex items-center">
                <i class="fas fa-key w-4"></i>
                <span>Passwort ändern</span>
            </a>
        </li>
        
        <?php if ($user && !$user->has2FA()): ?>
        <li>
            <a href="/2fa/setup" class="flex items-center text-warning">
                <i class="fas fa-shield-alt w-4"></i>
                <span>2FA einrichten</span>
                <div class="badge badge-warning badge-sm ml-auto">Neu</div>
            </a>
        </li>
        <?php else: ?>
        <li>
            <a href="/2fa/manage" class="flex items-center">
                <i class="fas fa-shield-alt w-4"></i>
                <span>2FA verwalten</span>
                <i class="fas fa-check text-success ml-auto"></i>
            </a>
        </li>
        <?php endif; ?>
        
        <li><hr class="my-2"></li>
        
        <!-- Quick Actions -->
        <li class="menu-title"><span>Schnellzugriff</span></li>
        
        <?php if (rbac_check('create', 'vertrag')): ?>
        <li>
            <a href="/vertraege/create" class="flex items-center">
                <i class="fas fa-plus-circle w-4 text-primary"></i>
                <span>Neuer Vertrag</span>
            </a>
        </li>
        <?php endif; ?>
        
        <?php if (rbac_check('create', 'korrespondent')): ?>
        <li>
            <a href="/korrespondenten/create" class="flex items-center">
                <i class="fas fa-plus-circle w-4 text-secondary"></i>
                <span>Neuer Korrespondent</span>
            </a>
        </li>
        <?php endif; ?>
        
        <li>
            <a href="/liquiditaet" class="flex items-center">
                <i class="fas fa-chart-line w-4 text-accent"></i>
                <span>Liquiditätsplanung</span>
            </a>
        </li>
        
        <li><hr class="my-2"></li>
        
        <!-- App Info & Actions -->
        <li>
            <a href="/help" class="flex items-center">
                <i class="fas fa-question-circle w-4"></i>
                <span>Hilfe & Support</span>
            </a>
        </li>
        
        <li>
            <a href="/about" class="flex items-center">
                <i class="fas fa-info-circle w-4"></i>
                <span>Über die App</span>
            </a>
        </li>
        
        <!-- Theme Selector (Alternative to floating button) -->
        <li class="menu-title"><span>Design</span></li>
        
        <li x-data="{ currentTheme: localStorage.getItem('theme') || 'light' }">
            <details>
                <summary class="flex items-center">
                    <i class="fas fa-palette w-4"></i>
                    <span>Design wechseln</span>
                    <i class="fas fa-chevron-down ml-auto text-xs"></i>
                </summary>
                <ul class="p-2 bg-base-200 rounded-box">
                    <li><a @click="$dispatch('set-theme', 'light')" class="text-sm">
                        <i class="fas fa-sun"></i> Hell
                    </a></li>
                    <li><a @click="$dispatch('set-theme', 'dark')" class="text-sm">
                        <i class="fas fa-moon"></i> Dunkel
                    </a></li>
                    <li><a @click="$dispatch('set-theme', 'cupcake')" class="text-sm">
                        <i class="fas fa-birthday-cake"></i> Cupcake
                    </a></li>
                    <li><a @click="$dispatch('set-theme', 'corporate')" class="text-sm">
                        <i class="fas fa-briefcase"></i> Corporate
                    </a></li>
                </ul>
            </details>
        </li>
        
        <li><hr class="my-2"></li>
        
        <!-- Logout -->
        <li>
            <form method="POST" action="/logout" class="w-full">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                <button type="submit" class="flex items-center w-full text-left text-error hover:bg-error hover:text-error-content rounded-lg">
                    <i class="fas fa-sign-out-alt w-4"></i>
                    <span>Abmelden</span>
                </button>
            </form>
        </li>
        
        <!-- Version Info -->
        <li class="menu-title mt-4">
            <div class="text-xs text-base-content/50 flex items-center justify-between w-full">
                <span>Version <?= config('app.version', '1.0.0') ?></span>
                <span><?= date('Y') ?></span>
            </div>
        </li>
        
    </ul>
</div>

<!-- Theme change event listener -->
<script>
document.addEventListener('set-theme', function(e) {
    const theme = e.detail;
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('theme', theme);
    
    // Show notification
    if (window.App && window.App.showToast) {
        window.App.showToast(`Design geändert zu: ${theme}`, 'success');
    }
});
</script>

<style>
/* User menu custom styling */
.dropdown-content {
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    border: 1px solid hsl(var(--bc) / 0.1);
}

/* User avatar glow effect */
.avatar .bg-primary {
    box-shadow: 0 0 20px rgba(59, 130, 246, 0.3);
}

/* Menu item hover effects */
.dropdown-content .menu li > a:hover {
    transform: translateX(2px);
    transition: all 0.2s ease;
}

/* Badge animations */
.badge {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: .8;
    }
}

/* Logout button special styling */
.text-error:hover {
    background-color: hsl(var(--er));
    color: hsl(var(--erc));
}

/* Version info styling */
.menu-title:last-child {
    opacity: 0.6;
    font-size: 0.7rem;
    margin-top: 1rem;
    padding-top: 0.5rem;
    border-top: 1px solid hsl(var(--bc) / 0.1);
}

/* Details/summary custom styling */
details summary {
    cursor: pointer;
    user-select: none;
}

details summary::-webkit-details-marker {
    display: none;
}

details[open] summary .fa-chevron-down {
    transform: rotate(180deg);
    transition: transform 0.2s ease;
}

/* Responsive adjustments */
@media (max-width: 1023px) {
    .dropdown-content {
        width: 16rem !important;
        right: 0;
        left: auto;
    }
}
</style>