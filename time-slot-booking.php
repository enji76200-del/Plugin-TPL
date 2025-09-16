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
            'messages' => array(
                'success' => __('Operation completed successfully', 'time-slot-booking'),
                'error' => __('An error occurred', 'time-slot-booking'),
                'confirm_remove' => __('Are you sure you want to remove this time slot?', 'time-slot-booking')
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
            FOREIGN KEY (planning_id) REFERENCES $table_plannings(id) ON DELETE CASCADE
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
            FOREIGN KEY (slot_id) REFERENCES $table_slots(id) ON DELETE CASCADE
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
            FOREIGN KEY (slot_id) REFERENCES $table_slots(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_plannings);
        dbDelta($sql_slots);
        dbDelta($sql_registrations);
        dbDelta($sql_unregistrations);
        
        // Insert default planning if none exists
        $existing_plannings = $wpdb->get_var("SELECT COUNT(*) FROM $table_plannings");
        if ($existing_plannings == 0) {
            $wpdb->insert(
                $table_plannings,
                array(
                    'name' => 'Planning Principal',
                    'description' => 'Planning de réservation par défaut',
                    'is_active' => 1,
                    'custom_css' => '',
                    'settings' => json_encode(array(
                        'max_advance_days' => 8,
                        'min_capacity' => 1,
                        'max_capacity' => 20,
                        'time_slot_duration' => 60
                    ))
                ),
                array('%s', '%s', '%d', '%s', '%s')
            );
        }
    }
    
    public function cleanup() {
        // Plugin deactivation cleanup if needed
    }
    
    public function render_booking_interface($atts) {
        $atts = shortcode_atts(array(
            'show_admin' => false
        ), $atts);
        
        ob_start();
        ?>
        <div id="tsb-booking-container">
            <div class="tsb-date-navigation">
                <button id="tsb-prev-date" class="tsb-nav-btn">&larr;</button>
                <span id="tsb-current-date"></span>
                <button id="tsb-next-date" class="tsb-nav-btn">&rarr;</button>
            </div>
            
            <?php if ($atts['show_admin'] || current_user_can('manage_options')): ?>
            <div class="tsb-admin-controls">
                <button id="tsb-add-slot-btn" class="tsb-btn tsb-btn-primary">
                    <?php _e('Add Time Slot', 'time-slot-booking'); ?>
                </button>
            </div>
            <?php endif; ?>
            
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
            
            <!-- Add Time Slot Modal -->
            <div id="tsb-add-slot-modal" class="tsb-modal" style="display: none;">
                <div class="tsb-modal-content">
                    <span class="tsb-close">&times;</span>
                    <h3><?php _e('Add Time Slot', 'time-slot-booking'); ?></h3>
                    <form id="tsb-add-slot-form">
                        <div class="tsb-form-group">
                            <label><?php _e('Start Time:', 'time-slot-booking'); ?></label>
                            <input type="time" id="tsb-start-time" required>
                        </div>
                        <div class="tsb-form-group">
                            <label><?php _e('End Time:', 'time-slot-booking'); ?></label>
                            <input type="time" id="tsb-end-time" required>
                        </div>
                        <div class="tsb-form-group">
                            <label><?php _e('Capacity:', 'time-slot-booking'); ?></label>
                            <input type="number" id="tsb-capacity" min="1" value="1" required>
                        </div>
                        <button type="submit" class="tsb-btn tsb-btn-primary"><?php _e('Add Slot', 'time-slot-booking'); ?></button>
                    </form>
                </div>
            </div>
            
            <!-- User Registration Modal -->
            <div id="tsb-register-modal" class="tsb-modal" style="display: none;">
                <div class="tsb-modal-content">
                    <span class="tsb-close">&times;</span>
                    <h3><?php _e('Register for Time Slot', 'time-slot-booking'); ?></h3>
                    <form id="tsb-register-form">
                        <input type="hidden" id="tsb-register-slot-id">
                        <div class="tsb-form-group">
                            <label><?php _e('First Name:', 'time-slot-booking'); ?></label>
                            <input type="text" id="tsb-user-first-name" required>
                        </div>
                        <div class="tsb-form-group">
                            <label><?php _e('Last Name:', 'time-slot-booking'); ?></label>
                            <input type="text" id="tsb-user-last-name" required>
                        </div>
                        <div class="tsb-form-group">
                            <label><?php _e('Email:', 'time-slot-booking'); ?></label>
                            <input type="email" id="tsb-user-email" required>
                        </div>
                        <div class="tsb-form-group">
                            <label><?php _e('Phone:', 'time-slot-booking'); ?></label>
                            <input type="tel" id="tsb-user-phone">
                        </div>
                        <button type="submit" class="tsb-btn tsb-btn-primary"><?php _e('Register', 'time-slot-booking'); ?></button>
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
            wp_die(__('Unauthorized', 'time-slot-booking'));
        }
        
        $date = sanitize_text_field($_POST['date']);
        $start_time = sanitize_text_field($_POST['start_time']);
        $end_time = sanitize_text_field($_POST['end_time']);
        $capacity = intval($_POST['capacity']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'tsb_time_slots';
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'date' => $date,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'capacity' => $capacity
            ),
            array('%s', '%s', '%s', '%d')
        );
        
        if ($result !== false) {
            wp_send_json_success(__('Time slot added successfully', 'time-slot-booking'));
        } else {
            wp_send_json_error(__('Failed to add time slot', 'time-slot-booking'));
        }
    }
    
    public function ajax_remove_time_slot() {
        check_ajax_referer('tsb_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'time-slot-booking'));
        }
        
        $slot_id = intval($_POST['slot_id']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'tsb_time_slots';
        
        $result = $wpdb->delete($table_name, array('id' => $slot_id), array('%d'));
        
        if ($result !== false) {
            wp_send_json_success(__('Time slot removed successfully', 'time-slot-booking'));
        } else {
            wp_send_json_error(__('Failed to remove time slot', 'time-slot-booking'));
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
            wp_send_json_error(__('Time slot not found', 'time-slot-booking'));
        }
        
        if ($slot->is_blocked) {
            wp_send_json_error(__('This time slot is blocked', 'time-slot-booking'));
        }
        
        // Check capacity
        $current_registrations = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM $registrations_table 
            WHERE slot_id = %d AND expires_at > NOW()
        ", $slot_id));
        
        if ($current_registrations >= $slot->capacity) {
            wp_send_json_error(__('This time slot is full', 'time-slot-booking'));
        }
        
        // Check if user is already registered for this slot
        $existing_registration = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM $registrations_table 
            WHERE slot_id = %d AND user_email = %s AND expires_at > NOW()
        ", $slot_id, $user_email));
        
        if ($existing_registration > 0) {
            wp_send_json_error(__('You are already registered for this time slot', 'time-slot-booking'));
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
            wp_send_json_success(__('Registration successful', 'time-slot-booking'));
        } else {
            wp_send_json_error(__('Registration failed', 'time-slot-booking'));
        }
    }
    
    public function ajax_get_date_slots() {
        check_ajax_referer('tsb_nonce', 'nonce');
        
        $date = sanitize_text_field($_POST['date']);
        
        global $wpdb;
        $slots_table = $wpdb->prefix . 'tsb_time_slots';
        $registrations_table = $wpdb->prefix . 'tsb_registrations';
        
        $slots = $wpdb->get_results($wpdb->prepare("
            SELECT s.*, COUNT(r.id) as registered_count
            FROM $slots_table s
            LEFT JOIN $registrations_table r ON s.id = r.slot_id
            WHERE s.date = %s
            GROUP BY s.id
            ORDER BY s.start_time
        ", $date));
        
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
            wp_send_json_error(__('Registration not found', 'time-slot-booking'));
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
            wp_send_json_success(__('Unregistration successful', 'time-slot-booking'));
        } else {
            wp_send_json_error(__('Unregistration failed', 'time-slot-booking'));
        }
    }
    
    public function ajax_toggle_slot_block() {
        check_ajax_referer('tsb_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized', 'time-slot-booking'));
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
            $message = $is_blocked ? __('Time slot blocked successfully', 'time-slot-booking') : __('Time slot unblocked successfully', 'time-slot-booking');
            wp_send_json_success($message);
        } else {
            wp_send_json_error(__('Failed to update time slot', 'time-slot-booking'));
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
}

// Initialize the plugin
new TimeSlotBooking();