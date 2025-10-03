<!DOCTYPE html>
<html lang="de" data-theme="<?= config('app.theme', 'light') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= $csrf_token ?>">
    
    <title><?= isset($page_title) ? e($page_title) . ' - ' . e($app_name) : e($app_name) ?></title>
    
    <!-- Favicons -->
    <link rel="icon" href="/assets/favicon.ico" type="image/x-icon">
    
    <!-- CSS -->
    <link rel="stylesheet" href="/assets/app.css">
    
    <!-- htmx -->
    <script src="https://unpkg.com/htmx.org@1.9.10" defer></script>
    
    <!-- Alpine.js -->
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    
    <!-- Additional styles -->
    <style>
        .htmx-indicator { opacity: 0; }
        .htmx-request .htmx-indicator { opacity: 1; transition: opacity 200ms ease-in; }
        .htmx-request.htmx-indicator { opacity: 1; transition: opacity 200ms ease-in; }
        
        /* Loading animation */
        .loading-spinner {
            border: 2px solid #f3f3f3;
            border-top: 2px solid #3498db;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
            display: inline-block;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: hsl(var(--b2));
        }
        
        ::-webkit-scrollbar-thumb {
            background: hsl(var(--bc) / 0.3);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: hsl(var(--bc) / 0.5);
        }
    </style>
</head>
<body class="min-h-screen bg-base-100">
    
    <!-- Navigation -->
    <?php if (isset($user) && $user): ?>
        <?= $this->include('partials/navigation') ?>
    <?php endif; ?>
    
    <!-- Main Content -->
    <main class="<?= isset($user) && $user ? 'lg:ml-64' : '' ?> min-h-screen">
        
        <!-- Flash Messages -->
        <?php if (isset($_SESSION['flash_message'])): ?>
            <div class="alert alert-<?= $_SESSION['flash_type'] ?? 'info' ?> mx-4 mt-4 shadow-lg">
                <div>
                    <i class="fas fa-info-circle"></i>
                    <span><?= e($_SESSION['flash_message']) ?></span>
                </div>
            </div>
            <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
        <?php endif; ?>
        
        <!-- Page Header -->
        <?php if (isset($page_title)): ?>
            <div class="bg-base-200 border-b border-base-300">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-2xl font-bold text-base-content">
                                <?= e($page_title) ?>
                            </h1>
                            <?php if (isset($page_subtitle)): ?>
                                <p class="mt-1 text-base-content/70">
                                    <?= e($page_subtitle) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Page Actions -->
                        <?php if (isset($page_actions)): ?>
                            <div class="flex items-center space-x-3">
                                <?= $page_actions ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Page Content -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <?= $content ?? '' ?>
        </div>
        
    </main>
    
    <!-- Global Loading Indicator -->
    <div id="global-loading" class="htmx-indicator fixed top-4 right-4 z-50">
        <div class="bg-primary text-primary-content px-4 py-2 rounded-lg shadow-lg flex items-center space-x-2">
            <div class="loading-spinner"></div>
            <span>Laden...</span>
        </div>
    </div>
    
    <!-- Theme Switcher -->
    <div class="fixed bottom-4 right-4 z-40" x-data="themeSwitcher">
        <div class="dropdown dropdown-top dropdown-end">
            <label tabindex="0" class="btn btn-circle btn-outline">
                <i class="fas fa-palette text-lg"></i>
            </label>
            <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow bg-base-100 rounded-box w-52">
                <li><a @click="setTheme('light')" :class="theme === 'light' ? 'active' : ''">
                    <i class="fas fa-sun"></i> Hell
                </a></li>
                <li><a @click="setTheme('dark')" :class="theme === 'dark' ? 'active' : ''">
                    <i class="fas fa-moon"></i> Dunkel
                </a></li>
                <li><a @click="setTheme('cupcake')" :class="theme === 'cupcake' ? 'active' : ''">
                    <i class="fas fa-birthday-cake"></i> Cupcake
                </a></li>
                <li><a @click="setTheme('corporate')" :class="theme === 'corporate' ? 'active' : ''">
                    <i class="fas fa-briefcase"></i> Corporate
                </a></li>
            </ul>
        </div>
    </div>
    
    <!-- Modal Container for htmx -->
    <div id="modal-container"></div>
    
    <!-- Scripts -->
    <script>
        // Global htmx configuration
        document.body.addEventListener('htmx:configRequest', function(evt) {
            evt.detail.headers['X-CSRFToken'] = document.querySelector('meta[name="csrf-token"]').content;
        });
        
        // Global loading indicator
        document.body.addEventListener('htmx:beforeRequest', function() {
            document.getElementById('global-loading').style.opacity = '1';
        });
        
        document.body.addEventListener('htmx:afterRequest', function() {
            document.getElementById('global-loading').style.opacity = '0';
        });
        
        // Error handling
        document.body.addEventListener('htmx:responseError', function(evt) {
            console.error('HTMX Error:', evt.detail);
            
            // Show error message
            const errorDiv = document.createElement('div');
            errorDiv.className = 'alert alert-error fixed top-4 left-1/2 transform -translate-x-1/2 z-50 max-w-md';
            errorDiv.innerHTML = `
                <div>
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Fehler beim Laden der Daten</span>
                </div>
            `;
            document.body.appendChild(errorDiv);
            
            // Remove after 5 seconds
            setTimeout(() => errorDiv.remove(), 5000);
        });
        
        // Theme switcher Alpine.js component
        document.addEventListener('alpine:init', () => {
            Alpine.data('themeSwitcher', () => ({
                theme: localStorage.getItem('theme') || 'light',
                
                init() {
                    this.setTheme(this.theme);
                },
                
                setTheme(newTheme) {
                    this.theme = newTheme;
                    document.documentElement.setAttribute('data-theme', newTheme);
                    localStorage.setItem('theme', newTheme);
                }
            }));
        });
        
        // Global utility functions
        window.App = {
            // Copy to clipboard
            copyToClipboard: function(text) {
                navigator.clipboard.writeText(text).then(() => {
                    this.showToast('In Zwischenablage kopiert', 'success');
                }).catch(err => {
                    console.error('Copy failed:', err);
                    this.showToast('Kopieren fehlgeschlagen', 'error');
                });
            },
            
            // Show toast notification
            showToast: function(message, type = 'info') {
                const toast = document.createElement('div');
                toast.className = `alert alert-${type} fixed bottom-4 left-4 z-50 max-w-sm shadow-lg`;
                toast.innerHTML = `
                    <div>
                        <i class="fas ${type === 'success' ? 'fa-check' : type === 'error' ? 'fa-times' : 'fa-info'}"></i>
                        <span>${message}</span>
                    </div>
                `;
                document.body.appendChild(toast);
                
                // Fade in
                setTimeout(() => toast.classList.add('animate-fade-in'), 10);
                
                // Remove after 3 seconds
                setTimeout(() => {
                    toast.classList.add('animate-fade-out');
                    setTimeout(() => toast.remove(), 300);
                }, 3000);
            },
            
            // Confirm dialog
            confirm: function(message, callback) {
                if (window.confirm(message)) {
                    callback();
                }
            },
            
            // Format currency
            formatCurrency: function(cents) {
                const euros = cents / 100;
                return new Intl.NumberFormat('de-DE', {
                    style: 'currency',
                    currency: 'EUR'
                }).format(euros);
            },
            
            // Format date
            formatDate: function(dateString) {
                return new Date(dateString).toLocaleDateString('de-DE');
            }
        };
        
        // Global keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+K or Cmd+K for global search
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                const searchInput = document.querySelector('#global-search');
                if (searchInput) {
                    searchInput.focus();
                }
            }
            
            // Escape to close modals
            if (e.key === 'Escape') {
                const modals = document.querySelectorAll('.modal.modal-open');
                modals.forEach(modal => {
                    modal.classList.remove('modal-open');
                });
            }
        });
        
        // Auto-submit forms with data-auto-submit after delay
        document.addEventListener('input', function(e) {
            const form = e.target.closest('form[data-auto-submit]');
            if (form) {
                const delay = parseInt(form.dataset.autoSubmit) || 300;
                
                clearTimeout(form._submitTimeout);
                form._submitTimeout = setTimeout(() => {
                    htmx.trigger(form, 'submit');
                }, delay);
            }
        });
    </script>
    
    <!-- Additional page scripts -->
    <?= $scripts ?? '' ?>
    
</body>
</html>