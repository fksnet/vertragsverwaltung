<?php $this->layout('layouts/app') ?>

<!-- Hero Section -->
<div class="hero min-h-screen bg-gradient-to-br from-primary/10 to-secondary/10">
    <div class="hero-content text-center">
        <div class="max-w-4xl">
            <div class="mb-8">
                <i class="fas fa-file-contract text-6xl text-primary mb-6"></i>
                <h1 class="text-5xl font-bold text-base-content mb-6">
                    Willkommen bei der
                    <span class="text-primary">Vertragsverwaltung</span>
                </h1>
                <p class="text-xl text-base-content/70 mb-8 leading-relaxed">
                    Die moderne, wunderschöne und nutzerfreundliche Web-Applikation zur 
                    professionellen Verwaltung Ihrer Familienverträge. Behalten Sie den 
                    Überblick über alle Verträge, Kündigungsfristen und Kosten.
                </p>
            </div>
            
            <div class="flex flex-col sm:flex-row gap-4 justify-center items-center">
                <a href="/login" class="btn btn-primary btn-lg">
                    <i class="fas fa-sign-in-alt mr-2"></i>
                    Jetzt anmelden
                </a>
                
                <?php if (config('auth.allow_registration', false)): ?>
                <a href="/register" class="btn btn-outline btn-lg">
                    <i class="fas fa-user-plus mr-2"></i>
                    Registrieren
                </a>
                <?php endif; ?>
                
                <a href="#features" class="btn btn-ghost btn-lg">
                    <i class="fas fa-info-circle mr-2"></i>
                    Mehr erfahren
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Features Section -->
<section id="features" class="py-20 bg-base-100">
    <div class="container mx-auto px-4">
        <div class="text-center mb-16">
            <h2 class="text-4xl font-bold text-base-content mb-4">
                Funktionen im Überblick
            </h2>
            <p class="text-xl text-base-content/70 max-w-3xl mx-auto">
                Alle Tools, die Sie für eine professionelle Vertragsverwaltung benötigen - 
                in einer benutzerfreundlichen und sicheren Anwendung.
            </p>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            
            <!-- Dashboard & KPIs -->
            <div class="card bg-base-200 shadow-xl hover:shadow-2xl transition-shadow duration-300">
                <div class="card-body text-center">
                    <div class="text-4xl text-primary mb-4">
                        <i class="fas fa-tachometer-alt"></i>
                    </div>
                    <h3 class="card-title justify-center text-xl mb-3">
                        Dashboard & KPIs
                    </h3>
                    <p class="text-base-content/70">
                        Übersichtliches Dashboard mit wichtigen Kennzahlen, 
                        anstehenden Kündigungsfristen und Kostenübersicht.
                    </p>
                </div>
            </div>
            
            <!-- Vertragsverwaltung -->
            <div class="card bg-base-200 shadow-xl hover:shadow-2xl transition-shadow duration-300">
                <div class="card-body text-center">
                    <div class="text-4xl text-secondary mb-4">
                        <i class="fas fa-file-contract"></i>
                    </div>
                    <h3 class="card-title justify-center text-xl mb-3">
                        Vertragsverwaltung
                    </h3>
                    <p class="text-base-content/70">
                        Vollständige CRUD-Verwaltung für Verträge mit allen 
                        wichtigen Feldern, Status-Tracking und Kostenberechnung.
                    </p>
                </div>
            </div>
            
            <!-- Sichere Zugangsdaten -->
            <div class="card bg-base-200 shadow-xl hover:shadow-2xl transition-shadow duration-300">
                <div class="card-body text-center">
                    <div class="text-4xl text-accent mb-4">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3 class="card-title justify-center text-xl mb-3">
                        Sichere Zugangsdaten
                    </h3>
                    <p class="text-base-content/70">
                        Verschlüsselte Speicherung von Login-Daten, 2FA-Codes 
                        und Recovery-Codes mit XChaCha20-Poly1305 Verschlüsselung.
                    </p>
                </div>
            </div>
            
            <!-- Liquiditätsplanung -->
            <div class="card bg-base-200 shadow-xl hover:shadow-2xl transition-shadow duration-300">
                <div class="card-body text-center">
                    <div class="text-4xl text-info mb-4">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3 class="card-title justify-center text-xl mb-3">
                        Liquiditätsplanung
                    </h3>
                    <p class="text-base-content/70">
                        Automatische Berechnung zukünftiger Zahlungen mit 
                        Zeitstrahl-Ansicht und CSV-Export für die Finanzplanung.
                    </p>
                </div>
            </div>
            
            <!-- Dokument-Management -->
            <div class="card bg-base-200 shadow-xl hover:shadow-2xl transition-shadow duration-300">
                <div class="card-body text-center">
                    <div class="text-4xl text-warning mb-4">
                        <i class="fas fa-file-pdf"></i>
                    </div>
                    <h3 class="card-title justify-center text-xl mb-3">
                        Dokument-Management
                    </h3>
                    <p class="text-base-content/70">
                        Sicherer Upload und Verwaltung von PDF-Dokumenten 
                        mit Inline-Viewer und organisierter Dateistruktur.
                    </p>
                </div>
            </div>
            
            <!-- Notfall-Modus -->
            <div class="card bg-base-200 shadow-xl hover:shadow-2xl transition-shadow duration-300">
                <div class="card-body text-center">
                    <div class="text-4xl text-error mb-4">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h3 class="card-title justify-center text-xl mb-3">
                        Notfall-Modus
                    </h3>
                    <p class="text-base-content/70">
                        Zeitlich limitierte Notfall-Pakete für Vertrauenspersonen 
                        mit wichtigen Vertragsinformationen und Kontaktdaten.
                    </p>
                </div>
            </div>
            
        </div>
    </div>
</section>

<!-- Security Section -->
<section class="py-20 bg-base-200">
    <div class="container mx-auto px-4">
        <div class="text-center mb-16">
            <h2 class="text-4xl font-bold text-base-content mb-4">
                Sicherheit steht an erster Stelle
            </h2>
            <p class="text-xl text-base-content/70 max-w-3xl mx-auto">
                Ihre Daten sind mit modernsten Sicherheitsstandards geschützt.
            </p>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            
            <div class="text-center">
                <div class="text-3xl text-primary mb-3">
                    <i class="fas fa-lock"></i>
                </div>
                <h4 class="font-bold mb-2">Verschlüsselung</h4>
                <p class="text-sm text-base-content/70">
                    XChaCha20-Poly1305 für sensitive Daten
                </p>
            </div>
            
            <div class="text-center">
                <div class="text-3xl text-secondary mb-3">
                    <i class="fas fa-user-shield"></i>
                </div>
                <h4 class="font-bold mb-2">2FA Support</h4>
                <p class="text-sm text-base-content/70">
                    Zwei-Faktor-Authentifizierung mit TOTP
                </p>
            </div>
            
            <div class="text-center">
                <div class="text-3xl text-accent mb-3">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <h4 class="font-bold mb-2">RBAC System</h4>
                <p class="text-sm text-base-content/70">
                    Rollenbasierte Zugriffskontrolle
                </p>
            </div>
            
            <div class="text-center">
                <div class="text-3xl text-info mb-3">
                    <i class="fas fa-history"></i>
                </div>
                <h4 class="font-bold mb-2">Audit Logging</h4>
                <p class="text-sm text-base-content/70">
                    Vollständige Aktivitätsverfolgung
                </p>
            </div>
            
        </div>
    </div>
</section>

<!-- Technology Section -->
<section class="py-20 bg-base-100">
    <div class="container mx-auto px-4">
        <div class="text-center mb-16">
            <h2 class="text-4xl font-bold text-base-content mb-4">
                Moderne Technologie
            </h2>
            <p class="text-xl text-base-content/70 max-w-3xl mx-auto">
                Entwickelt mit bewährten und zukunftssicheren Technologien.
            </p>
        </div>
        
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
            
            <div class="flex flex-col items-center">
                <div class="text-4xl text-primary mb-3">
                    <i class="fab fa-php"></i>
                </div>
                <h4 class="font-semibold">PHP 8.3+</h4>
                <p class="text-sm text-base-content/70">Modern & Sicher</p>
            </div>
            
            <div class="flex flex-col items-center">
                <div class="text-4xl text-secondary mb-3">
                    <i class="fas fa-database"></i>
                </div>
                <h4 class="font-semibold">MariaDB</h4>
                <p class="text-sm text-base-content/70">Zuverlässig</p>
            </div>
            
            <div class="flex flex-col items-center">
                <div class="text-4xl text-accent mb-3">
                    <i class="fab fa-css3-alt"></i>
                </div>
                <h4 class="font-semibold">TailwindCSS</h4>
                <p class="text-sm text-base-content/70">Responsive Design</p>
            </div>
            
            <div class="flex flex-col items-center">
                <div class="text-4xl text-info mb-3">
                    <i class="fab fa-js"></i>
                </div>
                <h4 class="font-semibold">htmx + Alpine</h4>
                <p class="text-sm text-base-content/70">Dynamisch</p>
            </div>
            
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="footer footer-center p-10 bg-base-200 text-base-content">
    <div>
        <div class="text-4xl text-primary mb-4">
            <i class="fas fa-file-contract"></i>
        </div>
        <p class="font-bold text-lg">
            <?= e($app_name) ?> <br>
            <span class="text-sm font-normal opacity-70">
                Moderne Familien-Vertragsverwaltung
            </span>
        </p>
        <p class="opacity-70">
            Copyright © <?= date('Y') ?> - Alle Rechte vorbehalten
        </p>
    </div>
    <div>
        <div class="grid grid-flow-col gap-4">
            <a href="/about" class="link link-hover">Über uns</a>
            <a href="/privacy" class="link link-hover">Datenschutz</a>
            <a href="/imprint" class="link link-hover">Impressum</a>
            <a href="/help" class="link link-hover">Hilfe</a>
        </div>
    </div>
</footer>

<!-- Additional Scripts for Landing Page -->
<?php $this->section('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // Intersection Observer for scroll animations
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-fade-in');
            }
        });
    }, observerOptions);
    
    // Observe all cards and sections
    document.querySelectorAll('.card, section').forEach(el => {
        observer.observe(el);
    });
    
    // Feature cards hover effect
    document.querySelectorAll('.card').forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.classList.add('scale-105');
        });
        
        card.addEventListener('mouseleave', function() {
            this.classList.remove('scale-105');
        });
    });
});
</script>

<style>
/* Custom animations */
@keyframes fade-in {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fade-in {
    animation: fade-in 0.6s ease-out forwards;
}

/* Card hover effects */
.card {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.card:hover {
    transform: translateY(-5px);
}

/* Hero gradient animation */
.hero {
    background: linear-gradient(-45deg, 
        hsl(var(--p) / 0.1), 
        hsl(var(--s) / 0.1), 
        hsl(var(--a) / 0.1), 
        hsl(var(--n) / 0.1));
    background-size: 400% 400%;
    animation: gradient-shift 15s ease infinite;
}

@keyframes gradient-shift {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}

/* Footer styling */
.footer {
    border-top: 1px solid hsl(var(--bc) / 0.1);
}

/* Responsive improvements */
@media (max-width: 768px) {
    .hero-content h1 {
        font-size: 2.5rem;
        line-height: 1.2;
    }
    
    .hero-content p {
        font-size: 1.1rem;
    }
    
    .btn-lg {
        padding: 0.75rem 1.5rem;
        font-size: 1rem;
    }
}
</style>
<?php $this->endSection() ?>