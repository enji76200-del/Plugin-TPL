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
        
        // Time slots table
        $table_slots = $wpdb->prefix . 'tsb_time_slots';
        $sql_slots = "CREATE TABLE $table_slots (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            date date NOT NULL,
            start_time time NOT NULL,
            end_time time NOT NULL,
            capacity int(10) NOT NULL DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_slot (date, start_time, end_time)
        ) $charset_collate;";
        
        // User registrations table
        $table_registrations = $wpdb->prefix . 'tsb_registrations';
        $sql_registrations = "CREATE TABLE $table_registrations (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            slot_id mediumint(9) NOT NULL,
            user_name varchar(100) NOT NULL,
            user_email varchar(100) NOT NULL,
            user_phone varchar(20),
            registered_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            FOREIGN KEY (slot_id) REFERENCES $table_slots(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_slots);
        dbDelta($sql_registrations);
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
                            <label><?php _e('Name:', 'time-slot-booking'); ?></label>
                            <input type="text" id="tsb-user-name" required>
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
        $user_name = sanitize_text_field($_POST['user_name']);
        $user_email = sanitize_email($_POST['user_email']);
        $user_phone = sanitize_text_field($_POST['user_phone']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'tsb_registrations';
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'slot_id' => $slot_id,
                'user_name' => $user_name,
                'user_email' => $user_email,
                'user_phone' => $user_phone
            ),
            array('%d', '%s', '%s', '%s')
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
}

// Initialize the plugin
new TimeSlotBooking();