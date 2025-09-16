<?php
/**
 * Plugin Name: Time Slot Booking
 * Plugin URI: https://github.com/enji76200-del/Plugin-TPL
 * Description: A WordPress plugin for time slot booking with Excel-style table layout and date navigation.
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: time-slot-booking
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('TSB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TSB_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('TSB_PLUGIN_VERSION', '1.0.0');

class TimeSlotBooking {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_shortcode('time_slot_booking', array($this, 'render_booking_interface'));
        
        // Database hooks
        register_activation_hook(__FILE__, array($this, 'create_tables'));
        register_deactivation_hook(__FILE__, array($this, 'cleanup'));
        
        // AJAX handlers
        add_action('wp_ajax_add_time_slot', array($this, 'ajax_add_time_slot'));
        add_action('wp_ajax_remove_time_slot', array($this, 'ajax_remove_time_slot'));
        add_action('wp_ajax_register_user_slot', array($this, 'ajax_register_user_slot'));
        add_action('wp_ajax_nopriv_register_user_slot', array($this, 'ajax_register_user_slot'));
        add_action('wp_ajax_get_date_slots', array($this, 'ajax_get_date_slots'));
        add_action('wp_ajax_nopriv_get_date_slots', array($this, 'ajax_get_date_slots'));
        
        // New AJAX handlers for enhanced features
        add_action('wp_ajax_unregister_user_slot', array($this, 'ajax_unregister_user_slot'));
        add_action('wp_ajax_nopriv_unregister_user_slot', array($this, 'ajax_unregister_user_slot'));
        add_action('wp_ajax_toggle_slot_block', array($this, 'ajax_toggle_slot_block'));
        add_action('wp_ajax_get_week_slots', array($this, 'ajax_get_week_slots'));
        add_action('wp_ajax_nopriv_get_week_slots', array($this, 'ajax_get_week_slots'));
        add_action('wp_ajax_get_user_registrations', array($this, 'ajax_get_user_registrations'));
        add_action('wp_ajax_nopriv_get_user_registrations', array($this, 'ajax_get_user_registrations'));
        add_action('wp_ajax_generate_weekly_slots', array($this, 'ajax_generate_weekly_slots'));
        add_action('wp_ajax_get_plannings', array($this, 'ajax_get_plannings'));
    }
    
    public function init() {
        // Initialize plugin
        load_plugin_textdomain('time-slot-booking', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function enqueue_scripts() {
        wp_enqueue_style('tsb-style', TSB_PLUGIN_URL . 'assets/css/time-slot-booking.css', array(), TSB_PLUGIN_VERSION);
        wp_enqueue_script('tsb-script', TSB_PLUGIN_URL . 'assets/js/time-slot-booking.js', array('jquery'), TSB_PLUGIN_VERSION, true);
        
        // Localize script for AJAX
        wp_localize_script('tsb-script', 'tsb_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tsb_nonce'),
            'current_planning' => 1, // Will be updated by JS
            'is_admin' => current_user_can('manage_options') ? 1 : 0,
            'messages' => array(
                'success' => __('Opération terminée avec succès', 'time-slot-booking'),
                'error' => __('Une erreur est survenue', 'time-slot-booking'),
                'confirm_remove' => __('Êtes-vous sûr de vouloir supprimer ce créneau ?', 'time-slot-booking'),
                'generate_weekly_success' => __('Créneaux de la semaine type générés avec succès', 'time-slot-booking'),
                'generate_weekly_error' => __('Erreur lors de la génération des créneaux', 'time-slot-booking'),
                'planning_change_success' => __('Planning changé avec succès', 'time-slot-booking')
            )
        ));
    }
    
    public function enqueue_admin_scripts($hook) {
        // Only load on relevant admin pages
        if (strpos($hook, 'time-slot-booking') !== false) {
            wp_enqueue_style('tsb-admin-style', TSB_PLUGIN_URL . 'assets/css/admin-style.css', array(), TSB_PLUGIN_VERSION);
            wp_enqueue_script('tsb-admin-script', TSB_PLUGIN_URL . 'assets/js/admin-script.js', array('jquery'), TSB_PLUGIN_VERSION, true);
        }
    }
    
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Planning templates table for multiple plannings
        $table_plannings = $wpdb->prefix . 'tsb_plannings';
        $sql_plannings = "CREATE TABLE $table_plannings (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(200) NOT NULL,
            description text,
            is_active tinyint(1) DEFAULT 1,
            custom_css text,
            settings longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        // Time slots table - enhanced with planning support and blocking
        $table_slots = $wpdb->prefix . 'tsb_time_slots';
        $sql_slots = "CREATE TABLE $table_slots (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            planning_id mediumint(9) NOT NULL DEFAULT 1,
            date date NOT NULL,
            start_time time NOT NULL,
            end_time time NOT NULL,
            capacity int(10) NOT NULL DEFAULT 1,
            is_blocked tinyint(1) DEFAULT 0,
            block_reason varchar(255),
            is_recurring tinyint(1) DEFAULT 0,
            recurring_pattern varchar(50),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_slot (planning_id, date, start_time, end_time),
            KEY idx_planning_date (planning_id, date)
        ) $charset_collate;";
        
        // User registrations table - enhanced with first/last name separation
        $table_registrations = $wpdb->prefix . 'tsb_registrations';
        $sql_registrations = "CREATE TABLE $table_registrations (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            slot_id mediumint(9) NOT NULL,
            user_first_name varchar(50) NOT NULL,
            user_last_name varchar(50) NOT NULL,
            user_email varchar(100) NOT NULL,
            user_phone varchar(20),
            registered_at datetime DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime DEFAULT (DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 14 DAY)),
            PRIMARY KEY (id),
            KEY idx_slot_id (slot_id),
            KEY idx_user_email (user_email)
        ) $charset_collate;";
        
        // Unregistration audit table
        $table_unregistrations = $wpdb->prefix . 'tsb_unregistrations';
        $sql_unregistrations = "CREATE TABLE $table_unregistrations (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            slot_id mediumint(9) NOT NULL,
            user_first_name varchar(50) NOT NULL,
            user_last_name varchar(50) NOT NULL,
            user_email varchar(100) NOT NULL,
            user_phone varchar(20),
            original_registration_id mediumint(9),
            unregistered_at datetime DEFAULT CURRENT_TIMESTAMP,
            reason varchar(255),
            PRIMARY KEY (id),
            KEY idx_slot_id (slot_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_plannings);
        dbDelta($sql_slots);
        dbDelta($sql_registrations);
        dbDelta($sql_unregistrations);
        
        // Insert default plannings if none exists
        $existing_plannings = $wpdb->get_var("SELECT COUNT(*) FROM $table_plannings");
        if ($existing_plannings == 0) {
            $default_plannings = array(
                array(
                    'name' => 'Gare-Станция',
                    'description' => 'Planning de la Gare',
                    'is_active' => 1,
                    'custom_css' => '',
                    'settings' => json_encode(array(
                        'max_advance_days' => 8,
                        'min_capacity' => 1,
                        'max_capacity' => 20,
                        'time_slot_duration' => 60
                    ))
                ),
                array(
                    'name' => 'Centre ville Центр города',
                    'description' => 'Planning du Centre ville',
                    'is_active' => 1,
                    'custom_css' => '',
                    'settings' => json_encode(array(
                        'max_advance_days' => 8,
                        'min_capacity' => 1,
                        'max_capacity' => 20,
                        'time_slot_duration' => 60
                    ))
                ),
                array(
                    'name' => 'Citadelle Цитадель',
                    'description' => 'Planning de la Citadelle',
                    'is_active' => 1,
                    'custom_css' => '',
                    'settings' => json_encode(array(
                        'max_advance_days' => 8,
                        'min_capacity' => 1,
                        'max_capacity' => 20,
                        'time_slot_duration' => 60
                    ))
                ),
                array(
                    'name' => 'Marché Nord Маркет Север',
                    'description' => 'Planning du Marché Nord',
                    'is_active' => 1,
                    'custom_css' => '',
                    'settings' => json_encode(array(
                        'max_advance_days' => 8,
                        'min_capacity' => 1,
                        'max_capacity' => 20,
                        'time_slot_duration' => 60
                    ))
                )
            );
            
            $planning_ids = array();
            foreach ($default_plannings as $planning) {
                $result = $wpdb->insert(
                    $table_plannings,
                    $planning,
                    array('%s', '%s', '%d', '%s', '%s')
                );
                if ($result !== false) {
                    $planning_ids[] = $wpdb->insert_id;
                }
            }
            
            // Generate default weekly time slots for all created plannings
            if (!empty($planning_ids)) {
                foreach ($planning_ids as $planning_id) {
                    $this->generate_weekly_slots_for_planning($planning_id);
                }
            }
        }
    }
    
    public function cleanup() {
        // Plugin deactivation cleanup if needed
    }
    
    public function render_booking_interface($atts) {
        $atts = shortcode_atts(array(
            'show_admin' => false,
            'planning_id' => 1
        ), $atts);
        
        global $wpdb;
        $plannings_table = $wpdb->prefix . 'tsb_plannings';
        $plannings = $wpdb->get_results("SELECT * FROM $plannings_table WHERE is_active = 1 ORDER BY id");
        $current_planning = $wpdb->get_row($wpdb->prepare("SELECT * FROM $plannings_table WHERE id = %d", intval($atts['planning_id'])));
        
        if (!$current_planning && !empty($plannings)) {
            $current_planning = $plannings[0];
        }
        
        ob_start();
        ?>
        <div id="tsb-booking-container" data-current-planning="<?php echo esc_attr($current_planning ? $current_planning->id : 1); ?>">
            
            <!-- Planning Selection -->
            <?php if (count($plannings) > 1): ?>
            <div class="tsb-planning-controls">
                <div class="tsb-planning-navigation">
                    <button id="tsb-prev-planning" class="tsb-nav-btn">&larr;</button>
                    <div class="tsb-planning-selector">
                        <select id="tsb-planning-select" class="tsb-planning-select">
                            <?php foreach ($plannings as $planning): ?>
                                <option value="<?php echo esc_attr($planning->id); ?>" <?php selected($current_planning->id, $planning->id); ?>>
                                    <?php echo esc_html($planning->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button id="tsb-next-planning" class="tsb-nav-btn">&rarr;</button>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="tsb-view-controls">
                <div class="tsb-view-toggle">
                    <button class="tsb-view-btn active" data-view="daily"><?php _e('Vue journalière', 'time-slot-booking'); ?></button>
                    <button class="tsb-view-btn" data-view="weekly"><?php _e('Vue hebdomadaire', 'time-slot-booking'); ?></button>
                </div>
                
                <div class="tsb-date-navigation">
                    <button id="tsb-prev-date" class="tsb-nav-btn">&larr;</button>
                    <span id="tsb-current-date"></span>
                    <button id="tsb-next-date" class="tsb-nav-btn">&rarr;</button>
                </div>
            </div>
            
            <?php if (current_user_can('manage_options')): ?>
            <div class="tsb-admin-controls">
                <button id="tsb-add-slot-btn" class="tsb-btn tsb-btn-primary">
                    <?php _e('Ajouter un créneau', 'time-slot-booking'); ?>
                </button>
                <button id="tsb-generate-weekly-btn" class="tsb-btn tsb-btn-success">
                    <?php _e('Générer la semaine type', 'time-slot-booking'); ?>
                </button>
                <button id="tsb-block-mode-btn" class="tsb-btn tsb-btn-warning">
                    <?php _e('Mode blocage', 'time-slot-booking'); ?>
                </button>
            </div>
            <?php endif; ?>
            
            <!-- Daily View -->
            <div id="tsb-daily-view" class="tsb-daily-view">
                <div id="tsb-booking-table">
                    <table class="tsb-table">
                        <thead>
                            <tr>
                                <th class="tsb-th"><?php _e('Horaires', 'time-slot-booking'); ?></th>
                                <th class="tsb-th"><?php _e('Créneaux', 'time-slot-booking'); ?></th>
                            </tr>
                        </thead>
                        <tbody id="tsb-table-body">
                            <!-- Dynamic content will be loaded here -->
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Weekly View -->
            <div id="tsb-weekly-view" class="tsb-weekly-view" style="display: none;">
                <table class="tsb-weekly-table">
                    <thead>
                        <tr>
                            <th class="time-header"><?php _e('Horaires', 'time-slot-booking'); ?></th>
                            <th><?php _e('Lundi', 'time-slot-booking'); ?></th>
                            <th><?php _e('Mardi', 'time-slot-booking'); ?></th>
                            <th><?php _e('Mercredi', 'time-slot-booking'); ?></th>
                            <th><?php _e('Jeudi', 'time-slot-booking'); ?></th>
                            <th><?php _e('Vendredi', 'time-slot-booking'); ?></th>
                            <th><?php _e('Samedi', 'time-slot-booking'); ?></th>
                            <th><?php _e('Dimanche', 'time-slot-booking'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="tsb-weekly-table-body">
                        <!-- Dynamic weekly content will be loaded here -->
                    </tbody>
                </table>
            </div>
            
            <!-- User Registrations Panel -->
            <div id="tsb-my-registrations" class="tsb-my-registrations" style="display: none;">
                <h4><?php _e('Mes inscriptions', 'time-slot-booking'); ?></h4>
                <div id="tsb-registrations-list">
                    <!-- User registrations will be loaded here -->
                </div>
            </div>
            
            <!-- Add Time Slot Modal -->
            <div id="tsb-add-slot-modal" class="tsb-modal" style="display: none;">
                <div class="tsb-modal-content">
                    <span class="tsb-close">&times;</span>
                    <h3><?php _e('Ajouter un créneau', 'time-slot-booking'); ?></h3>
                    <form id="tsb-add-slot-form">
                        <div class="tsb-form-group">
                            <label><?php _e('Heure de début :', 'time-slot-booking'); ?></label>
                            <input type="time" id="tsb-start-time" required>
                        </div>
                        <div class="tsb-form-group">
                            <label><?php _e('Heure de fin :', 'time-slot-booking'); ?></label>
                            <input type="time" id="tsb-end-time" required>
                        </div>
                        <div class="tsb-form-group">
                            <label><?php _e('Capacité :', 'time-slot-booking'); ?></label>
                            <input type="number" id="tsb-capacity" min="1" value="1" required>
                        </div>
                        <button type="submit" class="tsb-btn tsb-btn-primary"><?php _e('Ajouter le créneau', 'time-slot-booking'); ?></button>
                    </form>
                </div>
            </div>
            
            <!-- User Registration Modal -->
            <div id="tsb-register-modal" class="tsb-modal" style="display: none;">
                <div class="tsb-modal-content">
                    <span class="tsb-close">&times;</span>
                    <h3><?php _e('S\'inscrire au créneau', 'time-slot-booking'); ?></h3>
                    <form id="tsb-register-form">
                        <input type="hidden" id="tsb-register-slot-id">
                        <div class="tsb-form-row">
                            <div class="tsb-form-group">
                                <label><?php _e('Prénom :', 'time-slot-booking'); ?></label>
                                <input type="text" id="tsb-user-first-name" required>
                            </div>
                            <div class="tsb-form-group">
                                <label><?php _e('Nom :', 'time-slot-booking'); ?></label>
                                <input type="text" id="tsb-user-last-name" required>
                            </div>
                        </div>
                            <button type="submit" class="tsb-btn tsb-btn-primary"><?php _e('S\'inscrire', 'time-slot-booking'); ?></button>
                    </form>
                </div>
            </div>
            
            <!-- User Unregistration Modal -->
            <div id="tsb-unregister-modal" class="tsb-modal" style="display: none;">
                <div class="tsb-modal-content">
                    <span class="tsb-close">&times;</span>
                    <h3><?php _e('Se désinscrire du créneau', 'time-slot-booking'); ?></h3>
                    <form id="tsb-unregister-form">
                        <input type="hidden" id="tsb-unregister-slot-id">
                            <button type="submit" class="tsb-btn tsb-btn-danger"><?php _e('Me désinscrire', 'time-slot-booking'); ?></button>
                    </form>
                </div>
            </div>
            
            <!-- Block Slot Modal -->
            <div id="tsb-block-slot-modal" class="tsb-modal" style="display: none;">
                <div class="tsb-modal-content">
                    <span class="tsb-close">&times;</span>
                    <h3><?php _e('Bloquer/Débloquer le créneau', 'time-slot-booking'); ?></h3>
                    <form id="tsb-block-slot-form">
                        <input type="hidden" id="tsb-block-slot-id">
                        <input type="hidden" id="tsb-block-action">
                        <div class="tsb-form-group">
                            <label>
                                <input type="checkbox" id="tsb-block-checkbox"> 
                                <?php _e('Bloquer ce créneau', 'time-slot-booking'); ?>
                            </label>
                        </div>
                        <div class="tsb-form-group" id="tsb-block-reason-group">
                            <label><?php _e('Raison du blocage:', 'time-slot-booking'); ?></label>
                            <input type="text" id="tsb-block-reason" placeholder="<?php _e('Ex: Maintenance, Congés, etc.', 'time-slot-booking'); ?>">
                        </div>
                        <button type="submit" class="tsb-btn tsb-btn-warning"><?php _e('Appliquer', 'time-slot-booking'); ?></button>
                    </form>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    // AJAX handlers
    public function ajax_add_time_slot() {
        check_ajax_referer('tsb_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Non autorisé', 'time-slot-booking'));
        }
        
        $date = sanitize_text_field($_POST['date']);
        $start_time = sanitize_text_field($_POST['start_time']);
        $end_time = sanitize_text_field($_POST['end_time']);
        $capacity = intval($_POST['capacity']);
        $planning_id = intval($_POST['planning_id'] ?? 1);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'tsb_time_slots';
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'planning_id' => $planning_id,
                'date' => $date,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'capacity' => $capacity
            ),
            array('%d', '%s', '%s', '%s', '%d')
        );
        
        if ($result !== false) {
            wp_send_json_success(__('Créneau ajouté avec succès', 'time-slot-booking'));
        } else {
            wp_send_json_error(__('Échec de l\'ajout du créneau', 'time-slot-booking'));
        }
    }
    
    public function ajax_remove_time_slot() {
        check_ajax_referer('tsb_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Non autorisé', 'time-slot-booking'));
        }
        
        $slot_id = intval($_POST['slot_id']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'tsb_time_slots';
        
        $result = $wpdb->delete($table_name, array('id' => $slot_id), array('%d'));
        
        if ($result !== false) {
            wp_send_json_success(__('Créneau supprimé avec succès', 'time-slot-booking'));
        } else {
            wp_send_json_error(__('Échec de la suppression du créneau', 'time-slot-booking'));
        }
    }
    
    public function ajax_register_user_slot() {
        check_ajax_referer('tsb_nonce', 'nonce');
        
        $slot_id = intval($_POST['slot_id']);
        $user_first_name = sanitize_text_field($_POST['user_first_name']);
        $user_last_name = sanitize_text_field($_POST['user_last_name']);
        $user_email = sanitize_email($_POST['user_email']);
        $user_phone = sanitize_text_field($_POST['user_phone']);
        
        global $wpdb;
        $slots_table = $wpdb->prefix . 'tsb_time_slots';
        $registrations_table = $wpdb->prefix . 'tsb_registrations';
        
        // Check if slot exists and is not blocked
        $slot = $wpdb->get_row($wpdb->prepare("SELECT * FROM $slots_table WHERE id = %d", $slot_id));
        if (!$slot) {
            wp_send_json_error(__('Créneau non trouvé', 'time-slot-booking'));
        }
        
        if ($slot->is_blocked) {
            wp_send_json_error(__('Ce créneau est bloqué', 'time-slot-booking'));
        }
        
        // Check capacity
        $current_registrations = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM $registrations_table 
            WHERE slot_id = %d AND expires_at > NOW()
        ", $slot_id));
        
        if ($current_registrations >= $slot->capacity) {
            wp_send_json_error(__('Ce créneau est complet', 'time-slot-booking'));
        }
        
        // Check if user is already registered for this slot
        $existing_registration = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM $registrations_table 
            WHERE slot_id = %d AND user_email = %s AND expires_at > NOW()
        ", $slot_id, $user_email));
        
        if ($existing_registration > 0) {
            wp_send_json_error(__('Vous êtes déjà inscrit à ce créneau', 'time-slot-booking'));
        }
        
        $result = $wpdb->insert(
            $registrations_table,
            array(
                'slot_id' => $slot_id,
                'user_first_name' => $user_first_name,
                'user_last_name' => $user_last_name,
                'user_email' => $user_email,
                'user_phone' => $user_phone
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );
        
        if ($result !== false) {
            wp_send_json_success(__('Inscription réussie', 'time-slot-booking'));
        } else {
            wp_send_json_error(__('Échec de l\'inscription', 'time-slot-booking'));
        }
    }
    
    public function ajax_get_date_slots() {
        check_ajax_referer('tsb_nonce', 'nonce');
        
        $date = sanitize_text_field($_POST['date']);
        $planning_id = intval($_POST['planning_id'] ?? 1);
        
        global $wpdb;
        $slots_table = $wpdb->prefix . 'tsb_time_slots';
        $registrations_table = $wpdb->prefix . 'tsb_registrations';
        
        $slots = $wpdb->get_results($wpdb->prepare("
            SELECT s.*, COUNT(r.id) as registered_count
            FROM $slots_table s
            LEFT JOIN $registrations_table r ON s.id = r.slot_id AND r.expires_at > NOW()
            WHERE s.date = %s AND s.planning_id = %d
            GROUP BY s.id
            ORDER BY s.start_time
        ", $date, $planning_id));
        
        // Ajout des inscrits formatés pour chaque créneau
        foreach ($slots as &$slot) {
            $registrations = $wpdb->get_results($wpdb->prepare(
                "SELECT user_first_name, user_last_name FROM $registrations_table WHERE slot_id = %d AND expires_at > NOW()",
                $slot->id
            ));
            $slot->registrations = array();
            foreach ($registrations as $reg) {
                $slot->registrations[] = array(
                    'user_first_name' => $reg->user_first_name,
                    'user_last_name' => $reg->user_last_name,
                    'display_name' => $reg->user_first_name . ' ' . mb_substr($reg->user_last_name, 0, 1) . '.'
                );
            }
        }
        wp_send_json_success($slots);
    }
    
    public function ajax_unregister_user_slot() {
        check_ajax_referer('tsb_nonce', 'nonce');
        
        $slot_id = intval($_POST['slot_id']);
        $user_email = sanitize_email($_POST['user_email']);
        
        global $wpdb;
        $registrations_table = $wpdb->prefix . 'tsb_registrations';
        $unregistrations_table = $wpdb->prefix . 'tsb_unregistrations';
        
        // Get the registration to audit
        $registration = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM $registrations_table 
            WHERE slot_id = %d AND user_email = %s AND expires_at > NOW()
        ", $slot_id, $user_email));
        
        if (!$registration) {
            wp_send_json_error(__('Inscription non trouvée', 'time-slot-booking'));
        }
        
        // Create audit record
        $wpdb->insert(
            $unregistrations_table,
            array(
                'slot_id' => $registration->slot_id,
                'user_first_name' => $registration->user_first_name,
                'user_last_name' => $registration->user_last_name,
                'user_email' => $registration->user_email,
                'user_phone' => $registration->user_phone,
                'original_registration_id' => $registration->id,
                'reason' => sanitize_text_field($_POST['reason'] ?? 'User unregistered')
            ),
            array('%d', '%s', '%s', '%s', '%s', '%d', '%s')
        );
        
        // Remove the registration
        $result = $wpdb->delete(
            $registrations_table,
            array('id' => $registration->id),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success(__('Désinscription réussie', 'time-slot-booking'));
        } else {
            wp_send_json_error(__('Échec de la désinscription', 'time-slot-booking'));
        }
    }
    
    public function ajax_toggle_slot_block() {
        check_ajax_referer('tsb_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Non autorisé', 'time-slot-booking'));
        }
        
        $slot_id = intval($_POST['slot_id']);
        $is_blocked = intval($_POST['is_blocked']);
        $block_reason = sanitize_text_field($_POST['block_reason'] ?? '');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'tsb_time_slots';
        
        $result = $wpdb->update(
            $table_name,
            array(
                'is_blocked' => $is_blocked,
                'block_reason' => $block_reason
            ),
            array('id' => $slot_id),
            array('%d', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            $message = $is_blocked ? __('Créneau bloqué avec succès', 'time-slot-booking') : __('Créneau débloqué avec succès', 'time-slot-booking');
            wp_send_json_success($message);
        } else {
            wp_send_json_error(__('Échec de la mise à jour du créneau', 'time-slot-booking'));
        }
    }
    
    public function ajax_get_week_slots() {
        check_ajax_referer('tsb_nonce', 'nonce');
        
        $start_date = sanitize_text_field($_POST['start_date']);
        $planning_id = intval($_POST['planning_id'] ?? 1);
        
        global $wpdb;
        $slots_table = $wpdb->prefix . 'tsb_time_slots';
        $registrations_table = $wpdb->prefix . 'tsb_registrations';
        
        // Get slots for the week (7 days from start_date)
        $end_date = date('Y-m-d', strtotime($start_date . ' +6 days'));
        
        $slots = $wpdb->get_results($wpdb->prepare("
            SELECT s.*, COUNT(r.id) as registered_count
            FROM $slots_table s
            LEFT JOIN $registrations_table r ON s.id = r.slot_id AND r.expires_at > NOW()
            WHERE s.date BETWEEN %s AND %s AND s.planning_id = %d
            GROUP BY s.id
            ORDER BY s.date, s.start_time
        ", $start_date, $end_date, $planning_id));
        
        // Ajout des inscrits formatés pour chaque créneau
        foreach ($slots as &$slot) {
            $registrations = $wpdb->get_results($wpdb->prepare(
                "SELECT user_first_name, user_last_name FROM $registrations_table WHERE slot_id = %d AND expires_at > NOW()",
                $slot->id
            ));
            $slot->registrations = array();
            foreach ($registrations as $reg) {
                $slot->registrations[] = array(
                    'user_first_name' => $reg->user_first_name,
                    'user_last_name' => $reg->user_last_name,
                    'display_name' => $reg->user_first_name . ' ' . mb_substr($reg->user_last_name, 0, 1) . '.'
                );
            }
        }
        wp_send_json_success($slots);
    }
    
    public function ajax_get_user_registrations() {
        check_ajax_referer('tsb_nonce', 'nonce');
        
        $user_email = sanitize_email($_POST['user_email']);
        
        global $wpdb;
        $slots_table = $wpdb->prefix . 'tsb_time_slots';
        $registrations_table = $wpdb->prefix . 'tsb_registrations';
        
        $registrations = $wpdb->get_results($wpdb->prepare("
            SELECT r.*, s.date, s.start_time, s.end_time
            FROM $registrations_table r
            JOIN $slots_table s ON r.slot_id = s.id
            WHERE r.user_email = %s AND r.expires_at > NOW() AND s.date >= CURDATE()
            ORDER BY s.date, s.start_time
        ", $user_email));
        
        wp_send_json_success($registrations);
    }
    

    /**
     * Generate weekly slots for a specific planning
     */
    public function generate_weekly_slots_for_planning($planning_id, $weekly_template = null) {
        global $wpdb;
        $slots_table = $wpdb->prefix . 'tsb_time_slots';
        
        if (!$weekly_template) {
            // Use default template if none provided
            $weekly_template = $this->get_default_weekly_template();
        }
        
        // Generate slots for next 7 days starting from today
        $start_date = new DateTime();
        $generated_count = 0;
        
        for ($i = 0; $i < 7; $i++) {
            $current_date = clone $start_date;
            $current_date->add(new DateInterval('P' . $i . 'D'));
            $date_string = $current_date->format('Y-m-d');
            
            // Get day name in French
            $day_names = array(
                'Monday' => 'lundi',
                'Tuesday' => 'mardi', 
                'Wednesday' => 'mercredi',
                'Thursday' => 'jeudi',
                'Friday' => 'vendredi',
                'Saturday' => 'samedi',
                'Sunday' => 'dimanche'
            );
            
            $day_name = $day_names[$current_date->format('l')];
            
            if (isset($weekly_template[$day_name]) && is_array($weekly_template[$day_name])) {
                foreach ($weekly_template[$day_name] as $slot_data) {
                    if (!is_array($slot_data) || count($slot_data) < 3) continue;
                    
                    list($start_time, $end_time, $capacity) = $slot_data;
                    
                    // Check if slot already exists
                    $existing_slot = $wpdb->get_var($wpdb->prepare("
                        SELECT id FROM $slots_table 
                        WHERE planning_id = %d AND date = %s AND start_time = %s AND end_time = %s
                    ", $planning_id, $date_string, $start_time, $end_time));
                    
                    if (!$existing_slot) {
                        $result = $wpdb->insert(
                            $slots_table,
                            array(
                                'planning_id' => $planning_id,
                                'date' => $date_string,
                                'start_time' => $start_time,
                                'end_time' => $end_time,
                                'capacity' => $capacity,
                                'is_blocked' => 0,
                                'is_recurring' => 1,
                                'recurring_pattern' => 'weekly'
                            ),
                            array('%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s')
                        );
                        
                        if ($result !== false) {
                            $generated_count++;
                        }
                    }
                }
            }
        }
        
        return $generated_count;
    }
    
    /**
     * Get default weekly template
     */
    private function get_default_weekly_template() {
        return array(
            'lundi' => array(
                array('06:00', '07:00', 3),
                array('07:30', '08:30', 3),
                array('08:30', '10:00', 2),
                array('10:00', '11:30', 2),
                array('11:30', '13:00', 2),
                array('13:00', '14:30', 2),
                array('14:30', '16:00', 2),
                array('16:00', '17:30', 2),
                array('17:30', '19:00', 3),
                array('19:00', '20:00', 3)
            ),
            'mardi' => array(
                array('07:30', '08:30', 3),
                array('08:30', '10:00', 2),
                array('10:00', '11:30', 2),
                array('11:30', '13:00', 2),
                array('13:00', '14:30', 2),
                array('14:30', '16:00', 2),
                array('16:00', '17:30', 2),
                array('17:30', '19:00', 3),
                array('19:00', '20:00', 3)
            ),
            'mercredi' => array(
                array('07:30', '08:30', 3),
                array('08:30', '10:00', 2),
                array('10:00', '11:30', 2),
                array('11:30', '13:00', 2),
                array('13:00', '14:30', 2),
                array('14:30', '16:00', 2),
                array('16:00', '17:30', 2),
                array('17:30', '19:00', 3),
                array('19:00', '20:00', 3)
            ),
            'jeudi' => array(
                array('07:30', '08:30', 3),
                array('08:30', '10:00', 2),
                array('10:00', '11:30', 2),
                array('11:30', '13:00', 2),
                array('13:00', '14:30', 2),
                array('14:30', '16:00', 2),
                array('16:00', '17:30', 2),
                array('17:30', '19:00', 3),
                array('19:00', '20:00', 3)
            ),
            'vendredi' => array(
                array('07:30', '08:30', 3),
                array('08:30', '10:00', 2),
                array('10:00', '11:30', 2),
                array('11:30', '13:00', 2),
                array('13:00', '14:30', 2),
                array('14:30', '16:00', 2),
                array('16:00', '17:30', 2),
                array('17:30', '19:00', 3),
                array('19:00', '20:00', 3)
            ),
            'samedi' => array(
                array('13:00', '14:30', 2),
                array('14:30', '16:00', 2),
                array('16:00', '17:30', 2),
                array('17:30', '19:00', 3),
                array('19:00', '20:00', 3)
            ),
            'dimanche' => array()
        );
    }
    
    /**
     * AJAX handler for generating weekly slots
     */
    public function ajax_generate_weekly_slots() {
        check_ajax_referer('tsb_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Non autorisé', 'time-slot-booking'));
        }
        
        $planning_id = intval($_POST['planning_id'] ?? 1);
        
        try {
            $generated_count = $this->generate_weekly_slots_for_planning($planning_id);
            if ($generated_count > 0) {
                wp_send_json_success(sprintf(__('%d créneaux de la semaine type générés avec succès', 'time-slot-booking'), $generated_count));
            } else {
                wp_send_json_success(__('Aucun nouveau créneau à générer (créneaux déjà existants)', 'time-slot-booking'));
            }
        } catch (Exception $e) {
            wp_send_json_error(__('Erreur lors de la génération des créneaux: ', 'time-slot-booking') . $e->getMessage());
        }
    }
    
    /**
     * AJAX handler for getting plannings
     */
    public function ajax_get_plannings() {
        check_ajax_referer('tsb_nonce', 'nonce');
        
        global $wpdb;
        $plannings_table = $wpdb->prefix . 'tsb_plannings';
        
        $plannings = $wpdb->get_results("SELECT * FROM $plannings_table WHERE is_active = 1 ORDER BY id");
        
        wp_send_json_success($plannings);
    }
}

// Initialize the plugin
new TimeSlotBooking();
