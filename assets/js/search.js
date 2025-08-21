/**
 * Gestionnaire de recherche avancée pour IDEM
 */

class SearchManager {
    constructor() {
        this.searchInput = null;
        this.searchResults = null;
        this.searchFilters = null;
        this.currentQuery = '';
        this.currentFilters = {
            type: 'all',
            sortBy: 'relevance',
            dateRange: 'all'
        };
        this.searchTimeout = null;
        this.isSearching = false;

        this.init();
    }

    init() {
        this.setupElements();
        this.setupEventListeners();
        this.setupAdvancedFilters();
        // this.loadSearchHistory();
    }

    setupElements() {
        this.searchInput = document.getElementById('global-search');
        this.searchResults = document.getElementById('search-results');
        this.searchFilters = document.getElementById('search-filters');

        if (!this.searchInput) {
            console.log('Search input not found');

        }
    }

    setupEventListeners() {
        if (!this.searchInput) return;

        // Input de recherche avec debounce
        this.searchInput.addEventListener('input', utils.debounce((e) => {
            this.handleSearchInput(e.target.value);

        }, 300));

        // Focus et blur
        this.searchInput.addEventListener('focus', () => {
            this.showSearchInterface();
        });

        // Recherche à la soumission du formulaire
        const searchForm = this.searchInput.closest('form');
        if (searchForm) {
            searchForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.performSearch(this.searchInput.value);
            });
        }

        // Filtres
        document.addEventListener('change', (e) => {
            if (e.target.matches('.search-filter')) {
                this.handleFilterChange(e.target);
            }
        });

        // Boutons de tri
        document.addEventListener('click', (e) => {
            if (e.target.matches('.sort-btn')) {
                this.handleSortChange(e.target);
            }
        });

        // Fermer les résultats en cliquant à l'extérieur
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.search-container')) {
                this.hideSearchInterface();
            }
        });

        // Navigation clavier
        this.searchInput.addEventListener('keydown', (e) => {
            this.handleKeyNavigation(e);
        });
    }

    handleSearchInput(query) {
        this.currentQuery = query.trim();

        if (this.currentQuery.length < 2) {
            this.showSearchSuggestions();
            return;
        }

        this.performSearch(this.currentQuery);
    }

    async performSearch(query) {
        if (this.isSearching) return;
        if (query.trim().length < 2) {
            this.showSearchSuggestions();
            return;
        }

        this.isSearching = true;
        this.showSearchLoading();

        try {
            const params = new URLSearchParams({
                q: query,
                type: this.currentFilters.type,
                sort: this.currentFilters.sortBy,
                date: this.currentFilters.dateRange,
                limit: 20
            });

            const response = await apiRequest(`/search.php?${params}`);

            if (response.success) {
                // Normaliser les résultats pour avoir une structure cohérente
                const normalizedResults = response.results.map(result => ({
                    ...result,
                    type: result.result_type || 'unknown',
                    url: this.getResultUrl(result.result_type, result.id)
                }));

                this.displaySearchResults(normalizedResults, query);
                this.saveSearchHistory(query);
            } else {
                this.showSearchError(response.message || 'Erreur de recherche');
            }
        } catch (error) {
            console.error('Erreur recherche:', error);
            this.showSearchError('Erreur de connexion');
        } finally {
            this.isSearching = false;
        }
    }

    displaySearchResults(results, query) {
        if (!this.searchResults) return;

        if (results.length === 0) {
            this.showNoResults(query);
            return;
        }

        const html = `
            <div class="search-results-header">
                <h3>Résultats pour "${utils.escapeHtml(query)}" (${results.length})</h3>
                <div class="search-sort-options">
                    <button class="sort-btn ${this.currentFilters.sortBy === 'relevance' ? 'active' : ''}" 
                            data-sort="relevance">Pertinence</button>
                    <button class="sort-btn ${this.currentFilters.sortBy === 'date' ? 'active' : ''}" 
                            data-sort="date">Date</button>
                    <button class="sort-btn ${this.currentFilters.sortBy === 'popularity' ? 'active' : ''}" 
                            data-sort="popularity">Popularité</button>
                </div>
            </div>
            <div class="search-results-list">
                ${results.map(result => this.createResultHTML(result)).join('')}
            </div>
        `;

        this.searchResults.innerHTML = html;
        this.searchResults.style.display = 'block';
    }

    createResultHTML(result) {
        // Corriger le type (supprimer le 's' final si nécessaire)
        const type = result.result_type || result.type;
        const cleanType = type.endsWith('s') ? type.slice(0, -1) : type;

        const typeIcon = this.getResultTypeIcon(cleanType);
        const timestamp = result.created_at ? formatTimeAgo(result.created_at) : '';

        // Générer l'URL en fonction du type
        const resultUrl = this.getResultUrl(cleanType, result.id);

        return `
        <div class="search-result-item" data-type="${cleanType}" data-id="${result.id}">
            <div class="search-result-icon">
                <i class="fas ${typeIcon}"></i>
            </div>
            <div class="search-result-content">
                <h4 class="search-result-title">
                    <a href="../${resultUrl}" onclick="searchManager.trackClick('${cleanType}', ${result.id})">
                        ${this.highlightSearchTerm(result.title, this.currentQuery)}
                    </a>
                </h4>
                <p class="search-result-description">
                    ${this.highlightSearchTerm(result.description || result.subtitle || '', this.currentQuery)}
                </p>
                <div class="search-result-meta">
                    ${result.author ? `
                        <span class="search-result-author">
                            <img src="${result.author_avatar || result.image || 'assets/images/default-avatar.svg'}" 
                                 alt="${result.author_name}" class="search-result-avatar">
                            ${utils.escapeHtml(result.author_name)}
                        </span>
                    ` : ''}
                    ${timestamp ? `<span class="search-result-time">${timestamp}</span>` : ''}
                </div>
            </div>
        </div>
    `;
    }

    getResultTypeIcon(type) {
        const icons = {
            'user': 'fa-user',
            'post': 'fa-file-text',
            'group': 'fa-users',
            'hashtag': 'fa-hashtag'
        };
        return icons[type] || 'fa-search';
    }

    highlightSearchTerm(text, term) {
        if (!term || !text) return utils.escapeHtml(text);

        const regex = new RegExp(`(${utils.escapeHtml(term)})`, 'gi');
        return utils.escapeHtml(text).replace(regex, '<mark>$1</mark>');
    }

    showSearchSuggestions() {
        const suggestions = this.getSearchSuggestions();
        const history = this.getSearchHistory();

        const html = `
            <div class="search-suggestions">
                ${history.length > 0 ? `
                    <div class="search-history">
                        <h4>Recherches récentes</h4>
                        ${history.slice(0, 5).map(item => `
                            <div class="search-suggestion-item" onclick="searchManager.selectSuggestion('${item}')">
                                <i class="fas fa-clock"></i>
                                <span>${utils.escapeHtml(item)}</span>
                                <button class="remove-history" onclick="event.stopPropagation(); searchManager.removeFromHistory('${item}')">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        `).join('')}
                    </div>
                ` : ''}
                
                ${suggestions.length > 0 ? `
                    <div class="search-trending">
                        <h4>Tendances</h4>
                        ${suggestions.map(item => `
                            <div class="search-suggestion-item" onclick="searchManager.selectSuggestion('${item.term}')">
                                <i class="fas fa-fire"></i>
                                <span>${utils.escapeHtml(item.term)}</span>
                                <small class="suggestion-count">${item.count}</small>
                            </div>
                        `).join('')}
                    </div>
                ` : ''}
            </div>
        `;

        if (this.searchResults) {
            this.searchResults.innerHTML = html;
            this.searchResults.style.display = 'block';
        }
    }

    showSearchLoading() {
        if (!this.searchResults) return;

        this.searchResults.innerHTML = `
            <div class="search-loading">
                <i class="fas fa-spinner fa-spin"></i>
                <span>Recherche en cours...</span>
            </div>
        `;
        this.searchResults.style.display = 'block';
    }

    showSearchError(message) {
        if (!this.searchResults) return;

        this.searchResults.innerHTML = `
            <div class="search-error">
                <i class="fas fa-exclamation-triangle"></i>
                <h4>Erreur de recherche</h4>
                <p>${utils.escapeHtml(message)}</p>
                <button class="btn btn-primary" onclick="searchManager.retrySearch()">Réessayer</button>
            </div>
        `;
    }

    showNoResults(query) {
        if (!this.searchResults) return;

        this.searchResults.innerHTML = `
            <div class="search-no-results">
                <i class="fas fa-search"></i>
                <h4>Aucun résultat trouvé</h4>
                <p>Aucun résultat pour "${utils.escapeHtml(query)}"</p>
                <div class="search-suggestions-alt">
                    <h5>Suggestions :</h5>
                    <ul>
                        <li>Vérifiez l'orthographe des mots-clés</li>
                        <li>Essayez des mots-clés différents</li>
                        <li>Utilisez des termes plus généraux</li>
                        <li>Réduisez le nombre de filtres</li>
                    </ul>
                </div>
            </div>
        `;
    }

    setupAdvancedFilters() {
        const filtersHTML = `
            <div class="search-filters-container">
                <div class="filter-group">
                    <label>Type de contenu</label>
                    <select class="search-filter" data-filter="type">
                        <option value="all">Tout</option>
                        <option value="user">Utilisateurs</option>
                        <option value="post">Publications</option>
                        <option value="group">Groupes</option>
                        <option value="photo">Photos</option>
                        <option value="video">Vidéos</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Période</label>
                    <select class="search-filter" data-filter="dateRange">
                        <option value="all">Tout</option>
                        <option value="today">Aujourd'hui</option>
                        <option value="week">Cette semaine</option>
                        <option value="month">Ce mois</option>
                        <option value="year">Cette année</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label>Trier par</label>
                    <select class="search-filter" data-filter="sortBy">
                        <option value="relevance">Pertinence</option>
                        <option value="date">Date</option>
                        <option value="popularity">Popularité</option>
                        <option value="engagement">Engagement</option>
                    </select>
                </div>
                
                <button class="btn btn-sm btn-outline" onclick="searchManager.resetFilters()">
                    Réinitialiser
                </button>
            </div>
        `;

        if (this.searchFilters) {
            this.searchFilters.innerHTML = filtersHTML;
        }
    }

    handleFilterChange(filterElement) {
        const filterType = filterElement.dataset.filter;
        const value = filterElement.value;

        this.currentFilters[filterType] = value;

        if (this.currentQuery) {
            this.performSearch(this.currentQuery);
        }
    }

    handleSortChange(sortButton) {
        const sortType = sortButton.dataset.sort;

        // Mettre à jour les boutons actifs
        document.querySelectorAll('.sort-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        sortButton.classList.add('active');

        this.currentFilters.sortBy = sortType;

        if (this.currentQuery) {
            this.performSearch(this.currentQuery);
        }
    }

    handleKeyNavigation(e) {
        const results = document.querySelectorAll('.search-suggestion-item, .search-result-item');

        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            this.navigateResults(results, e.key === 'ArrowDown' ? 1 : -1);
        } else if (e.key === 'Enter') {
            const active = document.querySelector('.search-item-active');
            if (active) {
                e.preventDefault();
                active.click();
            }
        } else if (e.key === 'Escape') {
            this.hideSearchInterface();
        }
    }

    navigateResults(results, direction) {
        const current = document.querySelector('.search-item-active');
        let index = current ? Array.from(results).indexOf(current) : -1;

        if (current) {
            current.classList.remove('search-item-active');
        }

        index += direction;

        if (index < 0) index = results.length - 1;
        if (index >= results.length) index = 0;

        if (results[index]) {
            results[index].classList.add('search-item-active');
            results[index].scrollIntoView({block: 'nearest'});
        }
    }

    selectSuggestion(suggestion) {
        this.searchInput.value = suggestion;
        this.performSearch(suggestion);
    }

    showSearchInterface() {
        if (this.searchResults) {
            if (this.currentQuery.length >= 2) {
                this.searchResults.style.display = 'block';
            } else {
                this.showSearchSuggestions();
            }
        }

        if (this.searchFilters) {
            this.searchFilters.style.display = 'block';
        }
    }

    hideSearchInterface() {
        if (this.searchResults) {
            this.searchResults.style.display = 'none';
        }
    }

    resetFilters() {
        this.currentFilters = {
            type: 'all',
            sortBy: 'relevance',
            dateRange: 'all'
        };

        // Réinitialiser les sélecteurs
        document.querySelectorAll('.search-filter').forEach(filter => {
            const filterType = filter.dataset.filter;
            filter.value = this.currentFilters[filterType];
        });

        if (this.currentQuery) {
            this.performSearch(this.currentQuery);
        }
    }

    async trackClick(type, id) {
        try {
            await apiRequest('/search.php', {
                method: 'POST',
                body: JSON.stringify({
                    action: 'track_click',
                    type: type,
                    id: id,
                    query: this.currentQuery
                })
            });
        } catch (error) {
            // Silencieux - le tracking n'est pas critique
        }
    }

    async saveResult(id, type) {
        try {
            const response = await apiRequest('/search.php', {
                method: 'POST',
                body: JSON.stringify({
                    action: 'save_result',
                    type: type,
                    id: id
                })
            });

            if (response.success) {
                showToast('Résultat sauvegardé', 'success');
            }
        } catch (error) {
            showToast('Erreur lors de la sauvegarde', 'error');
        }
    }

    async shareResult(id, type) {
        try {
            const url = window.location.origin + this.getResultUrl(type, id);
            await utils.copyToClipboard(url);
            showToast('Lien copié dans le presse-papiers', 'success');
        } catch (error) {
            showToast('Erreur lors du partage', 'error');
        }
    }

    getResultUrl(type, id) {
        const urls = {
            'user': `IdemV1/profile.php?id=${id}`,
            'post': `IdemV1/post.php?id=${id}`,
            'group': `IdemV1/group.php?id=${id}`,
            'hashtag': `IdemV1/hashtag.php?tag=${encodeURIComponent(id)}` // Note: pour les hashtags, l'ID est le tag lui-même
        };
        return urls[type] || '';
    }

    // Gestion de l'historique
    saveSearchHistory(query) {
        let history = JSON.parse(localStorage.getItem('search_history') || '[]');

        // Supprimer si déjà présent
        history = history.filter(item => item !== query);

        // Ajouter en début
        history.unshift(query);

        // Limiter à 10 éléments
        history = history.slice(0, 10);

        localStorage.setItem('search_history', JSON.stringify(history));
    }

    getSearchHistory() {
        return JSON.parse(localStorage.getItem('search_history') || '[]');
    }

    loadSearchHistory() {
        const history = this.getSearchHistory();

        if (!this.searchResults) return;

        if (history.length > 0) {
            const html = `
            <div class="search-history">
                <h4>Recherches récentes</h4>
                ${history.map(item => `
                    <div class="search-suggestion-item" onclick="searchManager.selectSuggestion('${item}')">
                        <i class="fas fa-clock"></i>
                        <span>${utils.escapeHtml(item)}</span>
                        <button class="remove-history" onclick="event.stopPropagation(); searchManager.removeFromHistory('${item}')">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `).join('')}
            </div>
        `;

            this.searchResults.innerHTML = html;
            this.searchResults.style.display = 'block';
        } else {
            this.searchResults.innerHTML = `
            <div class="search-no-history">
                <p>Aucune recherche récente</p>
            </div>
        `;
            this.searchResults.style.display = 'block';
        }
    }


    removeFromHistory(query) {
        let history = this.getSearchHistory();
        history = history.filter(item => item !== query);
        localStorage.setItem('search_history', JSON.stringify(history));

        // Rafraîchir les suggestions
        this.showSearchSuggestions();
    }

    async getSearchSuggestions() {
        try {
            const response = await apiRequest('/search.php?action=trending');
            return response.success ? response.trending : [];
        } catch (error) {
            return [];
        }
    }

    retrySearch() {
        if (this.currentQuery) {
            this.performSearch(this.currentQuery);
        }
    }
}

// CSS pour la recherche
const searchStyles = `
<style>
.search-container {
    position: relative;
    width: 100%;
    max-width: 600px;
}

.search-results-container {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: var(--bg-primary);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-lg);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
    max-height: 80vh;
    overflow-y: auto;
    z-index: 1000;
    margin-top: var(--spacing-xs);
}

.search-results-header {
    padding: var(--spacing-md);
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.search-results-header h3 {
    margin: 0;
    font-size: var(--text-lg);
}

.search-sort-options {
    display: flex;
    gap: var(--spacing-xs);
}

.sort-btn {
    padding: var(--spacing-xs) var(--spacing-sm);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    background: transparent;
    color: var(--text-secondary);
    font-size: var(--text-sm);
    cursor: pointer;
    transition: all var(--transition-fast);
}

.sort-btn:hover,
.sort-btn.active {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

.search-result-item {
    padding: var(--spacing-md);
    border-bottom: 1px solid var(--border-light);
    display: flex;
    gap: var(--spacing-md);
    transition: background-color var(--transition-fast);
}

.search-result-item:hover,
.search-result-item.search-item-active {
    background: var(--bg-secondary);
}

.search-result-item:last-child {
    border-bottom: none;
}

.search-result-icon {
    width: 40px;
    height: 40px;
    border-radius: var(--radius-full);
    background: var(--bg-secondary);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--primary-color);
    flex-shrink: 0;
}

.search-result-content {
    flex: 1;
    min-width: 0;
}

.search-result-title {
    margin: 0 0 var(--spacing-xs) 0;
    font-size: var(--text-base);
    font-weight: 600;
}

.search-result-title a {
    color: var(--text-primary);
    text-decoration: none;
}

.search-result-title a:hover {
    color: var(--primary-color);
}

.search-result-description {
    margin: 0 0 var(--spacing-sm) 0;
    font-size: var(--text-sm);
    color: var(--text-secondary);
    line-height: 1.4;
}

.search-result-meta {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
    font-size: var(--text-xs);
    color: var(--text-muted);
}

.search-result-author {
    display: flex;
    align-items: center;
    gap: var(--spacing-xs);
}

.search-result-avatar {
    width: 16px;
    height: 16px;
    border-radius: var(--radius-full);
}

.search-result-engagement {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
}

.search-result-actions {
    display: flex;
    gap: var(--spacing-xs);
    flex-shrink: 0;
}

.search-suggestions {
    padding: var(--spacing-md);
}

.search-suggestions h4 {
    margin: 0 0 var(--spacing-sm) 0;
    font-size: var(--text-sm);
    font-weight: 600;
    color: var(--text-primary);
}

.search-suggestion-item {
    padding: var(--spacing-sm);
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
    cursor: pointer;
    border-radius: var(--radius-sm);
    transition: background-color var(--transition-fast);
}

.search-suggestion-item:hover {
    background: var(--bg-secondary);
}

.suggestion-count {
    margin-left: auto;
    color: var(--text-muted);
}

.remove-history {
    background: none;
    border: none;
    color: var(--text-muted);
    cursor: pointer;
    padding: var(--spacing-xs);
    border-radius: var(--radius-sm);
}

.remove-history:hover {
    color: var(--danger-color);
    background: var(--danger-bg);
}

.search-loading,
.search-error,
.search-no-results {
    padding: var(--spacing-xl);
    text-align: center;
}

.search-loading i {
    font-size: var(--text-xl);
    color: var(--primary-color);
    margin-bottom: var(--spacing-sm);
}

.search-error i {
    font-size: var(--text-xl);
    color: var(--danger-color);
    margin-bottom: var(--spacing-sm);
}

.search-no-results i {
    font-size: var(--text-xl);
    color: var(--text-muted);
    margin-bottom: var(--spacing-sm);
}

.search-suggestions-alt ul {
    text-align: left;
    margin-top: var(--spacing-sm);
}

.search-filters-container {
    padding: var(--spacing-md);
    border-bottom: 1px solid var(--border-color);
    display: flex;
    gap: var(--spacing-md);
    align-items: end;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-xs);
}

.filter-group label {
    font-size: var(--text-sm);
    font-weight: 500;
    color: var(--text-secondary);
}

.search-filter {
    padding: var(--spacing-xs) var(--spacing-sm);
    border: 1px solid var(--border-color);
    border-radius: var(--radius-sm);
    background: var(--bg-primary);
    color: var(--text-primary);
    font-size: var(--text-sm);
}

mark {
    background: var(--warning-bg);
    color: var(--warning-color);
    padding: 2px 4px;
    border-radius: var(--radius-xs);
}

@media (max-width: 768px) {
    .search-filters-container {
        flex-direction: column;
        align-items: stretch;
    }
    
    .search-sort-options {
        flex-direction: column;
        width: 100%;
    }
    
    .search-results-header {
        flex-direction: column;
        align-items: stretch;
        gap: var(--spacing-sm);
    }
}
</style>
`;

// Injecter les styles
document.head.insertAdjacentHTML('beforeend', searchStyles);

// Initialisation
let searchManager;
document.addEventListener('DOMContentLoaded', () => {
    searchManager = new SearchManager();
    window.searchManager = searchManager;
});
