// assets/js/admin-login-face-guard.js
// Ce script empêche le bypass du système de reconnaissance faciale

document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const emailField = document.querySelector('input[name="_username"]');
    
    if (!loginForm || !emailField) return;
    
    // Si l'email est en lecture seule, c'est que la face a été vérifiée → OK
    if (emailField.hasAttribute('readonly')) {
        console.log('✅ Face déjà vérifiée');
        return;
    }
    
    // Sinon, vérifier lors de la soumission du formulaire
    loginForm.addEventListener('submit', async function(e) {
        const email = emailField.value.trim();
        
        if (!email) {
            return; // Laisser la validation normale se faire
        }
        
        // Vérifier si cet email nécessite la reconnaissance faciale
        try {
            const response = await fetch('/face-pre-check', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ email: email })
            });
            
            const result = await response.json();
            
            if (result.requiresFace && result.redirect) {
                e.preventDefault();
                console.log('🔒 Reconnaissance faciale requise pour cet utilisateur');
                window.location.href = result.redirect;
            }
        } catch (error) {
            console.error('Erreur lors de la vérification:', error);
            // En cas d'erreur, laisser le formulaire se soumettre normalement
        }
    });
});