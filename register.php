<?php
/**
 * Page d'inscription IDEM
 */

require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/functions.php';

SessionManager::start();

// Si l'utilisateur est déjà connecté, rediriger vers le feed
if (SessionManager::isLoggedIn()) {
    redirect('feed.php');
}

$pageTitle = "Inscription";
$pageDescription = "Créez votre compte IDEM et rejoignez une communauté bienveillante";
$bodyClass = "register-page";

include 'includes/header.php';
?>

<div class="auth-container">
    <div class="auth-wrapper">
        <div class="auth-left">
            <div class="auth-brand">
                <h1>Rejoignez <span class="brand">IDEM</span></h1>
                <p>Créez votre compte et commencez à vous connecter avec des personnes formidables.</p>
            </div>
            
            <div class="auth-benefits">
                <div class="benefit-item">
                    <div class="benefit-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="benefit-text">
                        <h3>Gratuit pour toujours</h3>
                        <p>Aucun frais caché, toutes les fonctionnalités essentielles gratuites</p>
                    </div>
                </div>
                
                <div class="benefit-item">
                    <div class="benefit-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="benefit-text">
                        <h3>Sécurité garantie</h3>
                        <p>Vos données sont chiffrées et protégées selon les standards les plus élevés</p>
                    </div>
                </div>
                
                <div class="benefit-item">
                    <div class="benefit-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="benefit-text">
                        <h3>Communauté bienveillante</h3>
                        <p>Rejoignez des milliers d'utilisateurs qui partagent et découvrent ensemble</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="auth-right">
            <div class="auth-card">
                <div class="auth-header">
                    <h2>Créer un compte</h2>
                    <p>Quelques informations pour commencer</p>
                </div>
                
                <form action="api/auth.php" method="POST" class="auth-form" id="register-form">
                    <input type="hidden" name="action" value="register">
                    <input type="hidden" name="csrf_token" value="<?php echo SessionManager::getCsrfToken(); ?>">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">Prénom</label>
                            <input type="text" id="first_name" name="first_name" required 
                                   placeholder="Votre prénom">
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name">Nom</label>
                            <input type="text" id="last_name" name="last_name" required 
                                   placeholder="Votre nom">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="username">Nom d'utilisateur</label>
                        <input type="text" id="username" name="username" required 
                               placeholder="nomutilisateur" pattern="[a-zA-Z0-9_]{3,30}">
                        <small class="form-help">3-30 caractères, lettres, chiffres et _ uniquement</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Adresse email</label>
                        <input type="email" id="email" name="email" required 
                               placeholder="votre@email.com">
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Mot de passe</label>
                        <div class="password-input">
                            <input type="password" id="password" name="password" required 
                                   placeholder="Votre mot de passe">
                            <button type="button" class="password-toggle" onclick="togglePassword('password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="password-strength" id="password-strength"></div>
                        <small class="form-help">Au moins 8 caractères avec majuscule, minuscule et chiffre</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirmer le mot de passe</label>
                        <div class="password-input">
                            <input type="password" id="confirm_password" name="confirm_password" required 
                                   placeholder="Confirmez votre mot de passe">
                            <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="birth_date">Date de naissance (optionnel)</label>
                        <input type="date" id="birth_date" name="birth_date" 
                               max="<?php echo date('Y-m-d', strtotime('-13 years')); ?>">
                        <small class="form-help">Vous devez avoir au moins 13 ans</small>
                    </div>
                    
                    <div class="form-group checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="accept_terms" required>
                            <span class="checkmark"></span>
                            J'accepte les <a href="terms.php" target="_blank">conditions d'utilisation</a> 
                            et la <a href="privacy.php" target="_blank">politique de confidentialité</a>
                        </label>
                    </div>
                    
                    <div class="form-group checkbox-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="newsletter" value="1">
                            <span class="checkmark"></span>
                            Je souhaite recevoir les actualités et conseils IDEM par email
                        </label>
                    </div>
                    
                    <button type="submit" class="btn-primary" id="register-btn">
                        <span class="btn-text">Créer mon compte</span>
                        <span class="btn-loader" style="display: none;">
                            <i class="fas fa-spinner fa-spin"></i>
                        </span>
                    </button>
                </form>
                
                <div class="auth-divider">
                    <span>ou</span>
                </div>
                
                <div class="auth-footer">
                    <p>Vous avez déjà un compte ?</p>
                    <a href="" class="btn-secondary">Se connecter</a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Styles spécifiques à la page d'inscription */
.register-page .main-content {
    padding: 0;
    min-height: 100vh;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
}

.auth-container {
    width: 100%;
    padding: 20px;
}

.auth-wrapper {
    max-width: 1000px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: 1fr 500px;
    gap: 60px;
    align-items: center;
}

.auth-left {
    color: white;
}

.auth-brand h1 {
    font-size: 3rem;
    font-weight: 700;
    margin-bottom: 1rem;
    line-height: 1.2;
}

.brand {
    color: #ffd700;
}

.auth-brand p {
    font-size: 1.25rem;
    margin-bottom: 3rem;
    opacity: 0.9;
    line-height: 1.6;
}

.auth-benefits {
    display: flex;
    flex-direction: column;
    gap: 2rem;
}

.benefit-item {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
}

.benefit-icon {
    width: 48px;
    height: 48px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffd700;
    font-size: 1.25rem;
    flex-shrink: 0;
}

.benefit-text h3 {
    font-size: 1.125rem;
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.benefit-text p {
    opacity: 0.8;
    line-height: 1.5;
}

.auth-card {
    background: white;
    border-radius: 16px;
    padding: 2.5rem;
    box-shadow: 0 25px 50px rgba(0,0,0,0.15);
    color: #333;
    max-height: 90vh;
    overflow-y: auto;
}

.auth-header {
    text-align: center;
    margin-bottom: 2rem;
}

.auth-header h2 {
    font-size: 1.875rem;
    margin-bottom: 0.5rem;
    color: #333;
    font-weight: 600;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.form-group {
    margin-bottom: 1.25rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 500;
    color: #555;
    font-size: 0.9rem;
}

.form-group input {
    width: 100%;
    padding: 12px;
    border: 2px solid #e1e5e9;
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.3s;
    box-sizing: border-box;
}

.form-group input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-help {
    display: block;
    margin-top: 0.25rem;
    font-size: 0.8rem;
    color: #666;
}

.password-input {
    position: relative;
}

.password-toggle {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #999;
    cursor: pointer;
    padding: 4px;
}

.password-strength {
    height: 4px;
    background: #e1e5e9;
    border-radius: 2px;
    margin-top: 0.5rem;
    overflow: hidden;
}

.password-strength::after {
    content: '';
    display: block;
    height: 100%;
    background: #e74c3c;
    width: 0%;
    transition: all 0.3s;
    border-radius: 2px;
}

.password-strength.weak::after {
    width: 25%;
    background: #e74c3c;
}

.password-strength.fair::after {
    width: 50%;
    background: #f39c12;
}

.password-strength.good::after {
    width: 75%;
    background: #f1c40f;
}

.password-strength.strong::after {
    width: 100%;
    background: #27ae60;
}

.checkbox-group {
    margin-bottom: 1rem;
}

.checkbox-label {
    display: flex;
    align-items: flex-start;
    cursor: pointer;
    font-size: 0.9rem;
    line-height: 1.4;
}

.checkbox-label input[type="checkbox"] {
    margin-right: 0.75rem;
    margin-top: 0.125rem;
    flex-shrink: 0;
}

.checkbox-label a {
    color: #667eea;
    text-decoration: none;
}

.checkbox-label a:hover {
    text-decoration: underline;
}

.btn-primary, .btn-secondary {
    width: 100%;
    padding: 14px 24px;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
    display: inline-block;
    text-align: center;
    box-sizing: border-box;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
}

.btn-primary:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.btn-secondary {
    background: transparent;
    color: #667eea;
    border: 2px solid #667eea;
}

.auth-divider {
    text-align: center;
    margin: 1.5rem 0;
    position: relative;
    color: #999;
    font-size: 0.9rem;
}

.auth-divider::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 1px;
    background: #e1e5e9;
    z-index: 1;
}

.auth-divider span {
    background: white;
    padding: 0 1rem;
    position: relative;
    z-index: 2;
}

.auth-footer {
    text-align: center;
}

.auth-footer p {
    margin-bottom: 1rem;
    color: #666;
    font-size: 0.9rem;
}

/* Responsive */
@media (max-width: 768px) {
    .auth-wrapper {
        grid-template-columns: 1fr;
        gap: 2rem;
    }
    
    .auth-left {
        text-align: center;
    }
    
    .auth-brand h1 {
        font-size: 2.25rem;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .auth-card {
        padding: 1.5rem;
    }
}

@media (max-width: 480px) {
    .auth-container {
        padding: 10px;
    }
    
    .auth-card {
        padding: 1.25rem;
    }
}
</style>

<script>
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const icon = input.parentElement.querySelector('.password-toggle i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
    }
}

function checkPasswordStrength(password) {
    const strengthIndicator = document.getElementById('password-strength');
    let score = 0;
    
    // Critères de force
    if (password.length >= 8) score++;
    if (/[a-z]/.test(password)) score++;
    if (/[A-Z]/.test(password)) score++;
    if (/[0-9]/.test(password)) score++;
    if (/[^A-Za-z0-9]/.test(password)) score++;
    
    // Appliquer la classe appropriée
    strengthIndicator.className = 'password-strength';
    if (score <= 1) strengthIndicator.classList.add('weak');
    else if (score <= 2) strengthIndicator.classList.add('fair');
    else if (score <= 3) strengthIndicator.classList.add('good');
    else strengthIndicator.classList.add('strong');
}

// Vérification en temps réel de la force du mot de passe
document.getElementById('password').addEventListener('input', function() {
    checkPasswordStrength(this.value);
});

// Vérification de la confirmation du mot de passe
document.getElementById('confirm_password').addEventListener('input', function() {
    const password = document.getElementById('password').value;
    const confirmPassword = this.value;
    
    if (confirmPassword && password !== confirmPassword) {
        this.setCustomValidity('Les mots de passe ne correspondent pas');
    } else {
        this.setCustomValidity('');
    }
});

// Vérification du nom d'utilisateur
document.getElementById('username').addEventListener('input', async function() {
    const username = this.value;
    if (username.length >= 3) {
        // TODO: Vérifier la disponibilité du nom d'utilisateur
        // via AJAX
    }
});

// Gestion du formulaire d'inscription
document.getElementById('register-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const btn = document.getElementById('register-btn');
    const btnText = btn.querySelector('.btn-text');
    const btnLoader = btn.querySelector('.btn-loader');
    
    // Validation côté client
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    
    if (password !== confirmPassword) {
        alert('Les mots de passe ne correspondent pas');
        return;
    }
    
    if (!/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{8,}$/.test(password)) {
        alert('Le mot de passe doit contenir au moins 8 caractères avec une majuscule, une minuscule et un chiffre');
        return;
    }
    
    // Afficher le loader
    btnText.style.display = 'none';
    btnLoader.style.display = 'inline-block';
    btn.disabled = true;
    
    // Envoyer le formulaire
    const formData = new FormData(this);
    
    fetch('api/auth.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Compte créé avec succès ! Vérifiez votre email pour confirmer votre compte.');
            window.location.href = '../IdemNet';
        } else {
            alert(data.message || 'Erreur lors de la création du compte');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('Une erreur est survenue');
    })
    .finally(() => {
        // Masquer le loader
        btnText.style.display = 'inline-block';
        btnLoader.style.display = 'none';
        btn.disabled = false;
    });
});
</script>

<?php include 'includes/footer.php'; ?>