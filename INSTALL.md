# Installation et Configuration - Time Slot Booking Plugin

## Installation WordPress

### 1. Installation du Plugin

1. **Télécharger les fichiers**
   ```bash
   git clone https://github.com/enji76200-del/Plugin-TPL.git
   ```

2. **Copier dans WordPress**
   - Copier le dossier entier dans `/wp-content/plugins/time-slot-booking/`
   - Ou zipper le dossier et l'installer via l'interface WordPress

3. **Activer le Plugin**
   - Aller dans WordPress Admin > Extensions
   - Activer "Time Slot Booking"

### 2. Configuration de Base

**Tables créées automatiquement :**
- `wp_tsb_time_slots` : Stockage des créneaux
- `wp_tsb_registrations` : Stockage des inscriptions

**Permissions requises :**
- Utilisateurs avec droits `manage_options` pour gérer les créneaux
- Utilisateurs connectés ou non pour s'inscrire

### 3. Utilisation

**Shortcode de base :**
```php
[time_slot_booking]
```

**Shortcode avec contrôles admin :**
```php
[time_slot_booking show_admin="true"]
```

**Dans un template PHP :**
```php
echo do_shortcode('[time_slot_booking]');
```

### 4. Fonctionnalités

#### Pour les Administrateurs
- ✅ Ajouter des créneaux horaires
- ✅ Supprimer des créneaux
- ✅ Voir toutes les inscriptions
- ✅ Navigation sur 8 jours

#### Pour les Utilisateurs
- ✅ Voir les créneaux disponibles
- ✅ S'inscrire aux créneaux
- ✅ Navigation par dates
- ✅ Interface responsive

### 5. Personnalisation

**CSS personnalisé :**
```css
/* Modifier les couleurs principales */
.tsb-btn-primary {
    background: #your-color !important;
}

/* Modifier la largeur du container */
#tsb-booking-container {
    max-width: 800px !important;
}
```

**JavaScript personnalisé :**
```javascript
// Ajouter des validations
jQuery(document).ready(function($) {
    // Votre code personnalisé
});
```

### 6. Dépannage

**Problèmes courants :**

1. **Tables non créées**
   - Désactiver et réactiver le plugin
   - Vérifier les permissions de base de données

2. **AJAX ne fonctionne pas**
   - Vérifier que jQuery est chargé
   - Vérifier les erreurs dans la console

3. **Styles non appliqués**
   - Vider le cache du navigateur
   - Vérifier que les fichiers CSS sont accessibles

**Debug WordPress :**
```php
// Dans wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### 7. Structure des Fichiers

```
time-slot-booking/
├── time-slot-booking.php          # Plugin principal
├── assets/
│   ├── css/
│   │   └── time-slot-booking.css   # Styles
│   └── js/
│       └── time-slot-booking.js    # Scripts
├── test-interface.html             # Test hors WordPress
├── INSTALL.md                      # Ce fichier
└── README.md                       # Documentation
```

### 8. Sécurité

**Mesures implémentées :**
- ✅ Nonces CSRF
- ✅ Sanitisation des données
- ✅ Validation des permissions
- ✅ Protection contre l'accès direct

### 9. Performance

**Optimisations :**
- ✅ Chargement conditionnel des scripts
- ✅ Requêtes SQL optimisées
- ✅ Cache-friendly
- ✅ Assets versionnés

### 10. Support

**Logs d'erreur :**
- Vérifier `/wp-content/debug.log`
- Console navigateur pour erreurs JavaScript
- Logs serveur pour erreurs PHP

**Contact :**
- Issues GitHub pour les bugs
- Documentation dans README.md