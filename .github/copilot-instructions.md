# Copilot Instructions for Time Slot Booking Plugin

## Vue d'ensemble
Ce plugin WordPress gère la réservation de créneaux horaires via une interface Excel-like, avec navigation par dates et gestion des inscriptions. Il combine PHP (backend), JavaScript (interactivité/AJAX), et CSS (style responsive).

## Structure principale
- `time-slot-booking.php` : Point d'entrée du plugin, gère les hooks WordPress, la logique serveur, l'accès à la base de données, et l'affichage des shortcodes.
- `assets/css/time-slot-booking.css` : Styles pour l'interface Excel-like, responsive et animée.
- `assets/js/time-slot-booking.js` : Logique interactive, navigation par dates, gestion AJAX des créneaux et inscriptions.
- `test-interface.html` : Permet de tester l'UI sans WordPress (utile pour debug JS/CSS).

## Patterns et conventions spécifiques
- **Shortcodes** : Utilisez `[time_slot_booking]` pour l'affichage public, `[time_slot_booking show_admin="true"]` pour l'admin.
- **Base de données** : Deux tables créées automatiquement (`wp_tsb_time_slots`, `wp_tsb_registrations`). Les accès se font via les fonctions PHP du plugin.
- **AJAX** : Les interactions JS (inscription, navigation, admin) passent par AJAX WordPress (`admin-ajax.php`). Vérifiez l'utilisation des nonces pour la sécurité.
- **Sécurité** : Toujours valider les permissions (admin), utiliser les nonces WordPress, et assainir les entrées utilisateur.
- **Responsive** : Le CSS est conçu pour s'adapter à mobile/tablette. Vérifiez le rendu dans `test-interface.html`.

## Workflows critiques
- **Installation** : Copier le dossier dans `/wp-content/plugins/` puis activer dans WordPress. Les tables sont créées à l'activation.
- **Debug** : Utilisez `test-interface.html` pour tester l'UI hors WordPress. Pour le backend, activez le mode debug WordPress et vérifiez les logs PHP.
- **Personnalisation** : Modifiez le CSS/JS dans `assets/` pour adapter l'apparence ou ajouter des fonctionnalités. Étendez le PHP dans `time-slot-booking.php` pour la logique serveur.

## Exemples de patterns
- **AJAX côté JS** :
  ```js
  // ...existing code...
  jQuery.post(ajaxurl, { action: 'tsb_register', ... }, function(response) { /* ... */ });
  // ...existing code...
  ```
- **Shortcode côté PHP** :
  ```php
  // ...existing code...
  add_shortcode('time_slot_booking', 'tsb_render_booking_interface');
  // ...existing code...
  ```

## Points d'intégration
- **WordPress hooks** : Utilisez les hooks pour l'activation (`register_activation_hook`), AJAX (`add_action('wp_ajax_...')`), et shortcodes.
- **Front/Back** : Le JS interagit avec le PHP via AJAX, le PHP gère la persistance et la validation.

## À éviter
- Ne modifiez pas directement les tables WordPress hors du plugin.
- Ne dupliquez pas la logique entre JS et PHP : centralisez la validation côté serveur.

## Références clés
- `README.md` : Documentation complète sur l'installation, la structure et les workflows.
- `test-interface.html` : Pour tester l'UI sans WordPress.
- `assets/` : Pour le style et l'interactivité.
- `time-slot-booking.php` : Toute la logique serveur et l'intégration WordPress.

---

Adaptez ces instructions si la structure du projet évolue. Pour toute ambiguïté, demandez des précisions ou consultez le README.
