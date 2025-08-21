class ThemeManager {
    constructor() {
        this.themeToggle = document.getElementById('theme-toggle');
        this.themeIcon = this.themeToggle.querySelector('i');
        this.initTheme();
        this.setupEventListeners();
    }

    initTheme() {
        // 1. Vérifier le thème sauvegardé
        const savedTheme = localStorage.getItem('theme');

        // 2. Vérifier les préférences système si aucun thème sauvegardé
        const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

        // 3. Appliquer le thème approprié
        const themeToApply = savedTheme || (systemPrefersDark ? 'dark' : 'light');
        this.applyTheme(themeToApply, false);
        this.refreshCSS();
    }

    applyTheme(theme, save = true) {
        // 1. Mettre à jour l'attribut data-theme
        document.documentElement.setAttribute('data-theme', theme);

        // 2. Forcer le recalcul des styles CSS
        this.forceStyleRecalculation();

        // 3. Mettre à jour l'icône
        if (theme === 'dark') {
            this.themeIcon.classList.replace('fa-moon', 'fa-sun');
        } else {
            this.themeIcon.classList.replace('fa-sun', 'fa-moon');
        }

        // 4. Sauvegarder si nécessaire
        if (save) {
            localStorage.setItem('theme', theme);
        }
    }

    forceStyleRecalculation() {
        // Technique pour forcer le recalcul des styles
        const html = document.documentElement;
        const style = html.style;
        const animation = style.animation;

        // Déclencher un reflow
        style.setProperty('animation', 'none', 'important');
        void html.offsetWidth; // Trigger reflow
        style.animation = animation;
    }

    setupEventListeners() {
        this.themeToggle.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            this.applyTheme(newTheme);
            this.refreshCSS();
        });

        // Réagir aux changements système (optionnel)
        window.matchMedia('(prefers-color-scheme: light)').addEventListener('change', (e) => {
            if (!localStorage.getItem('theme')) {
                this.applyTheme(e.matches ? 'dark' : 'light', false);
                this.refreshCSS();
            }
        });
    }
    refreshCSS() {
        const links = document.querySelectorAll('link[rel="stylesheet"]');
        links.forEach(link => {
            const url = new URL(link.href);
            url.searchParams.set('force-reload', Date.now());
            link.href = url.toString();
        });
    }
}

// Initialisation
document.addEventListener('DOMContentLoaded', () => {
    new ThemeManager();
});