# Time Slot Booking Plugin

Un plugin WordPress pour la gestion de créneaux horaires avec une interface Excel-style et navigation par dates.

## Fonctionnalités

### ✅ Fonctionnalités Implémentées

- **📅 Navigation par Dates**: Navigation avec flèches pour les 8 prochains jours à partir d'aujourd'hui
- **📊 Interface Excel-style**: Tableau avec colonnes "Horaires" et "Créneaux" pour un affichage professionnel
- **➕ Gestion des Créneaux**: Ajout et suppression manuelle des créneaux horaires (admin uniquement)
- **👥 Système d'Inscription**: Système d'inscription des utilisateurs pour les créneaux disponibles
- **🎨 Style CSS**: Apparence Excel-like responsive avec modales et animations
- **⚡ JavaScript Interactif**: Fonctionnalités interactives avec AJAX pour une expérience fluide
- **🔧 Administration**: Interface d'administration pour gérer les créneaux
- **📱 Responsive Design**: Compatible mobile et tablette

## Installation

1. **Télécharger le Plugin**
   ```bash
   git clone https://github.com/enji76200-del/Plugin-TPL.git
   ```

2. **Installer dans WordPress**
   - Copier le dossier du plugin dans `/wp-content/plugins/`
   - Activer le plugin dans l'administration WordPress

3. **Configuration de la Base de Données**
   - Les tables sont créées automatiquement lors de l'activation :
     - `wp_tsb_time_slots`: Stockage des créneaux horaires
     - `wp_tsb_registrations`: Stockage des inscriptions utilisateurs

## Utilisation

### Affichage Public

Utilisez le shortcode pour afficher l'interface de réservation :

```php
[time_slot_booking]
```

Pour afficher les contrôles administrateur :

```php
[time_slot_booking show_admin="true"]
```

### Interface Administrateur

Les utilisateurs avec les droits d'administration peuvent :
- Ajouter des créneaux horaires
- Supprimer des créneaux existants
- Voir toutes les inscriptions

### Fonctionnalités Utilisateurs

Les utilisateurs peuvent :
- Naviguer entre les dates (jour actuel + 8 jours)
- Voir les créneaux disponibles
- S'inscrire aux créneaux disponibles
- Voir le nombre d'inscrits par créneau

## Structure du Projet

```
Plugin-TPL/
├── time-slot-booking.php      # Fichier principal du plugin
├── assets/
│   ├── css/
│   │   └── time-slot-booking.css    # Styles Excel-like
│   └── js/
│       └── time-slot-booking.js     # Fonctionnalités interactives
├── test-interface.html        # Interface de test
└── README.md                  # Documentation
```

## Test du Plugin

Un fichier de test (`test-interface.html`) est inclus pour visualiser l'interface sans WordPress :

1. Ouvrir `test-interface.html` dans un navigateur
2. Tester les fonctionnalités de l'interface
3. Vérifier le responsive design

## Fonctionnalités Techniques

### Base de Données

**Table `tsb_time_slots`:**
- `id`: Identifiant unique
- `date`: Date du créneau
- `start_time`: Heure de début
- `end_time`: Heure de fin
- `capacity`: Capacité maximale
- `created_at`: Date de création

**Table `tsb_registrations`:**
- `id`: Identifiant unique
- `slot_id`: Référence au créneau
- `user_name`: Nom de l'utilisateur
- `user_email`: Email de l'utilisateur
- `user_phone`: Téléphone (optionnel)
- `registered_at`: Date d'inscription

### Sécurité

- Validation CSRF avec nonces WordPress
- Sanitisation des données utilisateur
- Vérification des permissions administrateur
- Protection contre l'accès direct aux fichiers

### Performance

- Requêtes optimisées avec JOIN
- Chargement conditionnel des scripts
- Cache-friendly avec versioning des assets
- Responsive design pour tous les appareils

## Personnalisation

### CSS

Modifiez `assets/css/time-slot-booking.css` pour personnaliser l'apparence :
- Couleurs du thème
- Tailles et espacements
- Animations et transitions

### JavaScript

Étendez `assets/js/time-slot-booking.js` pour ajouter :
- Validations personnalisées
- Fonctionnalités supplémentaires
- Intégrations tierces

## Support

Pour les questions ou problèmes :
1. Vérifiez la console JavaScript pour les erreurs
2. Activez le mode debug WordPress
3. Consultez les logs d'erreur du serveur

## Licence

GPL v2 or later

## Auteur

Plugin développé pour la gestion de créneaux horaires avec interface Excel-style.