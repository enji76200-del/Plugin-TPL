<?php
/**
 * Plugin Name: Registration Table Plugin
 * Plugin URI: https://github.com/enji76200-del/Plugin-TPL
 * Description: Plugin WordPress style tableau Excel d'inscription avec navigation par date et gestion des créneaux
 * Version: 1.0
 * Author: Plugin-TPL
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('RTP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RTP_PLUGIN_PATH', plugin_dir_path(__FILE__));

class RegistrationTablePlugin {
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_shortcode('registration_table', array($this, 'display_registration_table'));
        add_action('wp_ajax_add_time_slot', array($this, 'add_time_slot'));
        add_action('wp_ajax_nopriv_add_time_slot', array($this, 'add_time_slot'));
        add_action('wp_ajax_remove_time_slot', array($this, 'remove_time_slot'));
        add_action('wp_ajax_nopriv_remove_time_slot', array($this, 'remove_time_slot'));
        add_action('wp_ajax_register_user', array($this, 'register_user'));
        add_action('wp_ajax_nopriv_register_user', array($this, 'register_user'));
        add_action('wp_ajax_get_table_content', array($this, 'get_table_content'));
        add_action('wp_ajax_nopriv_get_table_content', array($this, 'get_table_content'));
        
        // Create database tables on activation
        register_activation_hook(__FILE__, array($this, 'create_tables'));
    }
    
    public function init() {
        // Plugin initialization
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script('jquery');
        wp_enqueue_script('rtp-script', RTP_PLUGIN_URL . 'assets/js/registration-table.js', array('jquery'), '1.0', true);
        wp_enqueue_style('rtp-style', RTP_PLUGIN_URL . 'assets/css/registration-table.css', array(), '1.0');
        
        // Localize script for AJAX
        wp_localize_script('rtp-script', 'rtp_ajax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rtp_nonce')
        ));
    }
    
    public function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'registration_time_slots';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            slot_date date NOT NULL,
            time_slot varchar(100) NOT NULL,
            user_id mediumint(9) DEFAULT NULL,
            user_name varchar(255) DEFAULT NULL,
            user_email varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_slot (slot_date, time_slot)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public function display_registration_table($atts) {
        $atts = shortcode_atts(array(
            'days' => 9 // Today + 8 days
        ), $atts);
        
        ob_start();
        ?>
        <div id="registration-table-container">
            <div class="date-navigation">
                <button id="prev-date" class="nav-button">&lt;</button>
                <span id="current-date"><?php echo date('Y-m-d'); ?></span>
                <button id="next-date" class="nav-button">&gt;</button>
            </div>
            
            <div class="table-container">
                <table id="registration-table" class="excel-style-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Horaires</th>
                            <th>Créneaux</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="table-body">
                        <?php echo $this->generate_table_rows(); ?>
                    </tbody>
                </table>
            </div>
            
            <div class="controls">
                <button id="add-slot-btn" class="control-button">Ajouter un créneau</button>
                <div id="add-slot-form" style="display:none;">
                    <input type="date" id="slot-date" />
                    <input type="time" id="slot-time" />
                    <input type="text" id="slot-description" placeholder="Description du créneau" />
                    <button id="save-slot">Sauvegarder</button>
                    <button id="cancel-slot">Annuler</button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private function generate_table_rows() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'registration_time_slots';
        
        $output = '';
        $current_date = new DateTime();
        
        for ($i = 0; $i < 9; $i++) {
            $date = $current_date->format('Y-m-d');
            $date_display = $current_date->format('d/m/Y');
            
            // Get time slots for this date
            $slots = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_name WHERE slot_date = %s ORDER BY time_slot",
                $date
            ));
            
            if (empty($slots)) {
                $output .= "<tr data-date='$date'>";
                $output .= "<td class='date-cell'>$date_display</td>";
                $output .= "<td class='time-cell'>-</td>";
                $output .= "<td class='slot-cell'>-</td>";
                $output .= "<td class='action-cell'><button class='add-slot-here' data-date='$date'>+</button></td>";
                $output .= "</tr>";
            } else {
                foreach ($slots as $slot) {
                    $is_registered = !empty($slot->user_id);
                    $class = $is_registered ? 'registered' : 'available';
                    $user_info = $is_registered ? $slot->user_name : 'Disponible';
                    
                    $output .= "<tr data-date='$date' data-slot-id='$slot->id' class='$class'>";
                    $output .= "<td class='date-cell'>$date_display</td>";
                    $output .= "<td class='time-cell'>$slot->time_slot</td>";
                    $output .= "<td class='slot-cell clickable' data-slot-id='$slot->id'>$user_info</td>";
                    $output .= "<td class='action-cell'>";
                    $output .= "<button class='remove-slot' data-slot-id='$slot->id'>×</button>";
                    $output .= "</td>";
                    $output .= "</tr>";
                }
            }
            
            $current_date->add(new DateInterval('P1D'));
        }
        
        return $output;
    }
    
    public function add_time_slot() {
        check_ajax_referer('rtp_nonce', 'nonce');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'registration_time_slots';
        
        $date = sanitize_text_field($_POST['date']);
        $time = sanitize_text_field($_POST['time']);
        $description = sanitize_text_field($_POST['description']);
        
        $time_slot = $time . ($description ? ' - ' . $description : '');
        
        $result = $wpdb->insert(
            $table_name,
            array(
                'slot_date' => $date,
                'time_slot' => $time_slot
            ),
            array('%s', '%s')
        );
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => 'Créneau ajouté avec succès',
                'slot_id' => $wpdb->insert_id
            ));
        } else {
            wp_send_json_error('Erreur lors de l\'ajout du créneau');
        }
    }
    
    public function remove_time_slot() {
        check_ajax_referer('rtp_nonce', 'nonce');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'registration_time_slots';
        
        $slot_id = intval($_POST['slot_id']);
        
        $result = $wpdb->delete(
            $table_name,
            array('id' => $slot_id),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success('Créneau supprimé avec succès');
        } else {
            wp_send_json_error('Erreur lors de la suppression du créneau');
        }
    }
    
    public function register_user() {
        check_ajax_referer('rtp_nonce', 'nonce');
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'registration_time_slots';
        
        $slot_id = intval($_POST['slot_id']);
        $user_name = sanitize_text_field($_POST['user_name']);
        $user_email = sanitize_email($_POST['user_email']);
        $user_id = get_current_user_id();
        
        // Check if slot exists and is available
        $slot = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $slot_id
        ));
        
        if (!$slot) {
            wp_send_json_error('Créneau introuvable');
        }
        
        if (!empty($slot->user_id)) {
            wp_send_json_error('Ce créneau est déjà réservé');
        }
        
        $result = $wpdb->update(
            $table_name,
            array(
                'user_id' => $user_id ?: null,
                'user_name' => $user_name,
                'user_email' => $user_email
            ),
            array('id' => $slot_id),
            array('%d', '%s', '%s'),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success('Inscription réussie');
        } else {
            wp_send_json_error('Erreur lors de l\'inscription');
        }
    }
    
    public function get_table_content() {
        check_ajax_referer('rtp_nonce', 'nonce');
        
        $start_date = sanitize_text_field($_POST['date']);
        $table_rows = $this->generate_table_rows_from_date($start_date);
        
        wp_send_json_success($table_rows);
    }
    
    private function generate_table_rows_from_date($start_date) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'registration_time_slots';
        
        $output = '';
        $current_date = new DateTime($start_date);
        
        for ($i = 0; $i < 9; $i++) {
            $date = $current_date->format('Y-m-d');
            $date_display = $current_date->format('d/m/Y');
            
            // Get time slots for this date
            $slots = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_name WHERE slot_date = %s ORDER BY time_slot",
                $date
            ));
            
            if (empty($slots)) {
                $output .= "<tr data-date='$date'>";
                $output .= "<td class='date-cell'>$date_display</td>";
                $output .= "<td class='time-cell'>-</td>";
                $output .= "<td class='slot-cell'>-</td>";
                $output .= "<td class='action-cell'><button class='add-slot-here' data-date='$date'>+</button></td>";
                $output .= "</tr>";
            } else {
                foreach ($slots as $slot) {
                    $is_registered = !empty($slot->user_id);
                    $class = $is_registered ? 'registered' : 'available';
                    $user_info = $is_registered ? $slot->user_name : 'Disponible';
                    
                    $output .= "<tr data-date='$date' data-slot-id='$slot->id' class='$class'>";
                    $output .= "<td class='date-cell'>$date_display</td>";
                    $output .= "<td class='time-cell'>$slot->time_slot</td>";
                    $output .= "<td class='slot-cell clickable' data-slot-id='$slot->id'>$user_info</td>";
                    $output .= "<td class='action-cell'>";
                    $output .= "<button class='remove-slot' data-slot-id='$slot->id'>×</button>";
                    $output .= "</td>";
                    $output .= "</tr>";
                }
            }
            
            $current_date->add(new DateInterval('P1D'));
        }
        
        return $output;
    }
}

// Initialize the plugin
new RegistrationTablePlugin();