# Plugin WordPress - Tableau d'inscription Excel

Un plugin WordPress qui affiche un tableau d'inscription style Excel avec navigation par date et gestion des créneaux.

## Fonctionnalités

### 📅 Navigation par Date
- Affichage de la date du jour avec flèches de navigation
- Période de 9 jours consécutifs (aujourd'hui + 8 jours)
- Navigation avec les touches fléchées du clavier (←/→)

### 📊 Tableau Excel-Style
- Design similaire à Microsoft Excel avec bordures et couleurs
- Colonnes : Date, Horaires, Créneaux, Actions
- Interface responsive pour mobile et desktop

### ⏰ Gestion des Créneaux
- **Ajout manuel** : Bouton "+" sur chaque ligne ou formulaire global
- **Suppression** : Bouton "×" sur chaque créneau
- **Horaires personnalisés** : Sélection d'heure avec description optionnelle

### 👥 Système d'Inscription
- **Clic sur les cases** : Inscription directe en cliquant sur "Disponible"
- **Modal d'inscription** : Formulaire avec nom et email
- **Statuts visuels** : Disponible (vert) / Réservé (rouge)

## Installation

1. **Téléchargez** le plugin
2. **Uploadez** le dossier dans `/wp-content/plugins/`
3. **Activez** le plugin dans l'administration WordPress
4. **Utilisez** le shortcode `[registration_table]` sur vos pages

## Utilisation

### Shortcode
```php
[registration_table]
```

### Intégration PHP
```php
echo do_shortcode('[registration_table]');
```

### Exemple d'utilisation
```html
<!-- Dans une page WordPress -->
<h2>Réservez votre créneau</h2>
[registration_table]
<p>Sélectionnez votre créneau préféré en cliquant sur "Disponible".</p>
```

## Structure des fichiers

```
Plugin-TPL/
├── registration-table-plugin.php  # Fichier principal du plugin
├── assets/
│   ├── css/
│   │   └── registration-table.css # Styles Excel
│   └── js/
│       └── registration-table.js  # Interactions JavaScript
├── demo.html                      # Démonstration visuelle
└── README.md                      # Documentation
```

## Base de données

Le plugin crée automatiquement la table `wp_registration_time_slots` avec :

- `id` : Identifiant unique
- `slot_date` : Date du créneau
- `time_slot` : Horaire et description
- `user_id` : ID de l'utilisateur WordPress (optionnel)
- `user_name` : Nom de l'utilisateur
- `user_email` : Email de l'utilisateur
- `created_at` : Date de création

## Personnalisation

### CSS
Modifiez `assets/css/registration-table.css` pour adapter le design :

```css
/* Couleurs personnalisées */
.excel-style-table th {
    background: #votre-couleur;
}

.available {
    background-color: #votre-vert;
}

.registered {
    background-color: #votre-rouge;
}
```

### JavaScript
Étendez `assets/js/registration-table.js` pour ajouter des fonctionnalités :

```javascript
// Validation personnalisée
function customValidation(userName, userEmail) {
    // Votre logique de validation
}
```

## Fonctionnalités avancées

### Raccourcis clavier
- **← (Flèche gauche)** : Date précédente
- **→ (Flèche droite)** : Date suivante  
- **Échap** : Fermer les modales/formulaires

### AJAX
- Toutes les interactions sont asynchrones
- Messages de confirmation visuels
- Actualisation automatique du tableau

### Sécurité
- Protection CSRF avec nonces WordPress
- Sanitisation des données utilisateur
- Vérification des permissions

## Configuration

### Options du shortcode
```php
[registration_table days="9"]  // Nombre de jours à afficher
```

### Hooks disponibles
```php
// Avant affichage du tableau
do_action('rtp_before_table_display');

// Après inscription
do_action('rtp_after_user_registration', $user_data);
```

## Démo

Ouvrez `demo.html` dans votre navigateur pour voir le plugin en action avec des données de démonstration.

## Support et contribution

- **Issues** : Signalez les problèmes dans l'onglet Issues
- **Pull Requests** : Contributions bienvenues
- **Documentation** : Améliorations de la documentation appréciées

## Licence

GPL v2 ou ultérieure - Compatible avec WordPress

## Changelog

### Version 1.0
- ✅ Tableau Excel-style avec navigation par date
- ✅ Gestion des créneaux (ajout/suppression)
- ✅ Système d'inscription utilisateur
- ✅ Interface responsive
- ✅ Raccourcis clavier
- ✅ Base de données automatique