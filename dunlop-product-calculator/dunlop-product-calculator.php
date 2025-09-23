<?php
/**
 * Plugin Name: Dunlop Product Calculator
 * Plugin URI: https://dunlop-adhesives.co.uk/
 * Description: Flexible product calculator system for Dunlop adhesives, levellers, grouts, and other products
 * Version: 1.1.0
 * Author: Dunlop Adhesives
 * License: GPL v2 or later
 * Text Domain: dunlop-calculator
  * GitHub Plugin URI: markdunlop-web/dunlop-product-calculator
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

// Define plugin constants
define('DUNLOP_CALC_VERSION', '1.1.0');
define('DUNLOP_CALC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DUNLOP_CALC_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main plugin class
 */
class DunlopProductCalculator {
    
    private static $instance = null;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Hook into WordPress
        add_action('init', array($this, 'init'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_scripts'));
        
        // Admin hooks
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_calculator_fields'));
        add_action('woocommerce_process_product_meta', array($this, 'save_calculator_fields'));
        
        // Frontend hooks - use a late priority to ensure it loads
        add_action('woocommerce_after_single_product_summary', array($this, 'display_calculator'), 50);
        
        // AJAX handlers
        add_action('wp_ajax_dunlop_calculate', array($this, 'ajax_calculate'));
        add_action('wp_ajax_nopriv_dunlop_calculate', array($this, 'ajax_calculate'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Any initialization code here
    }
    
    /**
     * Get product attributes for colors and weights
     */
    public function get_product_attributes($product_id, $attribute_type) {
        $values = array();
        
        // Get the terms for the specified attribute
        $taxonomy = 'pa_' . $attribute_type;
        $terms = get_the_terms($product_id, $taxonomy);
        
        if ($terms && !is_wp_error($terms)) {
            foreach ($terms as $term) {
                // Check if the term name contains multiple values separated by |
                if (strpos($term->name, '|') !== false) {
                    $split_values = explode('|', $term->name);
                    foreach ($split_values as $value) {
                        $values[] = trim($value);
                    }
                } else {
                    $values[] = trim($term->name);
                }
            }
        }
        
        return $values;
    }
    
    /**
     * Get available pack sizes from weight attributes
     */
    public function get_pack_sizes($product_id) {
        $sizes = array();
        $weight_values = $this->get_product_attributes($product_id, 'weight');
        
        foreach ($weight_values as $weight) {
            // Extract numeric value (e.g., "5kg" -> 5, "15 kg" -> 15)
            $numeric_weight = floatval(preg_replace('/[^0-9.]/', '', $weight));
            if ($numeric_weight > 0) {
                $sizes[] = $numeric_weight;
            }
        }
        
        sort($sizes); // Sort from smallest to largest
        return $sizes;
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function admin_scripts($hook) {
        if ('post.php' !== $hook && 'post-new.php' !== $hook) {
            return;
        }
        
        global $post;
        if ($post && 'product' === $post->post_type) {
            wp_enqueue_script(
                'dunlop-calc-admin',
                DUNLOP_CALC_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery'),
                DUNLOP_CALC_VERSION,
                true
            );
            
            wp_enqueue_style(
                'dunlop-calc-admin',
                DUNLOP_CALC_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                DUNLOP_CALC_VERSION
            );
        }
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function frontend_scripts() {
        if (!is_product()) {
            return;
        }
        
        global $product;
        
        // Ensure we have a valid product object
        if (!$product || !is_object($product)) {
            $product = wc_get_product(get_the_ID());
        }
        
        if (!$product) {
            return;
        }
        
        $calculator_enabled = get_post_meta($product->get_id(), '_enable_calculator', true);
        if ('yes' !== $calculator_enabled) {
            return;
        }
        
        wp_enqueue_script(
            'dunlop-calc-frontend',
            DUNLOP_CALC_PLUGIN_URL . 'assets/js/calculator.js',
            array('jquery'),
            DUNLOP_CALC_VERSION,
            true
        );
        
        wp_enqueue_style(
            'dunlop-calc-frontend',
            DUNLOP_CALC_PLUGIN_URL . 'assets/css/calculator.css',
            array(),
            DUNLOP_CALC_VERSION
        );
        
        // Pass configuration to JavaScript
        wp_localize_script('dunlop-calc-frontend', 'dunlopCalc', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dunlop_calc_nonce'),
            'config' => $this->get_calculator_config($product->get_id()),
            'product_id' => $product->get_id()
        ));
    }
    
    /**
     * Add calculator fields to product admin
     */
    public function add_calculator_fields() {
        echo '<div class="options_group dunlop-calculator-fields">';
        echo '<h4 style="padding-left: 10px;">Dunlop Calculator Settings</h4>';
        
        // Enable calculator
        woocommerce_wp_checkbox(array(
            'id' => '_enable_calculator',
            'label' => __('Enable Calculator', 'dunlop-calculator'),
            'description' => __('Show calculator on this product page', 'dunlop-calculator')
        ));
        
        // Calculator type
        woocommerce_wp_select(array(
            'id' => '_calculator_type',
            'label' => __('Calculator Type', 'dunlop-calculator'),
            'options' => array(
                '' => __('Select type...', 'dunlop-calculator'),
                'adhesive_powder' => __('Powdered Adhesive', 'dunlop-calculator'),
                'adhesive_ready' => __('Ready Mixed Adhesive', 'dunlop-calculator'),
                'leveller' => __('Self-Levelling Compound', 'dunlop-calculator'),
                'grout' => __('Grout', 'dunlop-calculator'),
                'silicone' => __('Silicone Sealant', 'dunlop-calculator'),
                'waterproofing' => __('Waterproofing', 'dunlop-calculator')
            )
        ));
        
        // Attribute settings
        echo '<div class="attribute-settings" style="margin-top: 15px; padding: 10px; background: #f1f1f1;">';
        echo '<h5 style="margin: 0 0 10px 0;">Product Attribute Settings</h5>';
        
        woocommerce_wp_checkbox(array(
            'id' => '_use_colour_attributes',
            'label' => __('Use Colour Attributes', 'dunlop-calculator'),
            'description' => __('Pull available colours from product colour attributes', 'dunlop-calculator')
        ));
        
        woocommerce_wp_checkbox(array(
            'id' => '_use_weight_attributes',
            'label' => __('Use Weight Attributes', 'dunlop-calculator'),
            'description' => __('Pull pack sizes from product weight attributes for automatic optimization', 'dunlop-calculator')
        ));
        
        echo '</div>';
        
        // Basic fields
        echo '<div class="calc-basic-fields">';
        echo '<h5 style="padding-left: 10px; margin-top: 15px;">' . __('Basic Settings', 'dunlop-calculator') . '</h5>';
        
        woocommerce_wp_text_input(array(
            'id' => '_pack_size',
            'label' => __('Pack Size', 'dunlop-calculator'),
            'description' => __('Default pack size (kg/L)', 'dunlop-calculator'),
            'type' => 'number',
            'custom_attributes' => array('step' => '0.1'),
            'placeholder' => '20'
        ));
        
        woocommerce_wp_text_input(array(
            'id' => '_pack_unit',
            'label' => __('Pack Unit', 'dunlop-calculator'),
            'description' => __('Unit of measurement (bag, tub, tube, etc)', 'dunlop-calculator'),
            'placeholder' => 'bag'
        ));
        
        echo '</div>';
        
        // Type-specific fields (keeping your existing structure)
        // Leveller fields
        echo '<div class="calc-type-fields" data-type="leveller" style="display:none;">';
        echo '<h5 style="padding-left: 10px; margin-top: 15px;">' . __('Leveller Settings', 'dunlop-calculator') . '</h5>';
        
        woocommerce_wp_text_input(array(
            'id' => '_leveller_density',
            'label' => __('Density Factor', 'dunlop-calculator'),
            'description' => __('kg per m² per mm depth', 'dunlop-calculator'),
            'type' => 'number',
            'custom_attributes' => array('step' => '0.01'),
            'placeholder' => '1.67'
        ));
        
        woocommerce_wp_text_input(array(
            'id' => '_leveller_min_depth',
            'label' => __('Minimum Depth (mm)', 'dunlop-calculator'),
            'type' => 'number',
            'placeholder' => '1'
        ));
        
        woocommerce_wp_text_input(array(
            'id' => '_leveller_max_depth',
            'label' => __('Maximum Depth (mm)', 'dunlop-calculator'),
            'type' => 'number',
            'placeholder' => '20'
        ));
        
        echo '</div>';
        
        // Adhesive fields
        echo '<div class="calc-type-fields" data-type="adhesive" style="display:none;">';
        echo '<h5 style="padding-left: 10px; margin-top: 15px;">' . __('Adhesive Settings', 'dunlop-calculator') . '</h5>';
        
        woocommerce_wp_text_input(array(
            'id' => '_adhesive_coverage_walls',
            'label' => __('Wall Coverage (m²/kg)', 'dunlop-calculator'),
            'type' => 'number',
            'custom_attributes' => array('step' => '0.1'),
            'placeholder' => '4'
        ));
        
        woocommerce_wp_text_input(array(
            'id' => '_adhesive_coverage_floors',
            'label' => __('Floor Coverage (m²/kg)', 'dunlop-calculator'),
            'type' => 'number',
            'custom_attributes' => array('step' => '0.1'),
            'placeholder' => '3'
        ));
        
        echo '</div>';
        
        // Grout fields
        echo '<div class="calc-type-fields" data-type="grout" style="display:none;">';
        echo '<h5 style="padding-left: 10px; margin-top: 15px;">' . __('Grout Settings', 'dunlop-calculator') . '</h5>';
        
        woocommerce_wp_text_input(array(
            'id' => '_grout_density',
            'label' => __('Grout Density', 'dunlop-calculator'),
            'description' => __('Density factor for calculations', 'dunlop-calculator'),
            'type' => 'number',
            'custom_attributes' => array('step' => '0.01'),
            'placeholder' => '1.6'
        ));
        
        echo '</div>';
        
        // Silicone fields
        echo '<div class="calc-type-fields" data-type="silicone" style="display:none;">';
        echo '<h5 style="padding-left: 10px; margin-top: 15px;">' . __('Silicone Settings', 'dunlop-calculator') . '</h5>';
        
        woocommerce_wp_text_input(array(
            'id' => '_silicone_coverage',
            'label' => __('Linear Coverage', 'dunlop-calculator'),
            'description' => __('Meters per tube (310ml)', 'dunlop-calculator'),
            'type' => 'number',
            'custom_attributes' => array('step' => '0.1'),
            'placeholder' => '12.4'
        ));
        
        echo '</div>';
        
        echo '</div>';
    }
    
    /**
     * Save calculator fields
     */
    public function save_calculator_fields($post_id) {
        // Basic fields
        $checkbox_fields = array('_enable_calculator', '_use_colour_attributes', '_use_weight_attributes');
        foreach ($checkbox_fields as $field) {
            update_post_meta($post_id, $field, isset($_POST[$field]) ? 'yes' : 'no');
        }
        
        // Text fields
        $text_fields = array(
            '_calculator_type', '_pack_size', '_pack_unit',
            '_leveller_density', '_leveller_min_depth', '_leveller_max_depth',
            '_adhesive_coverage_walls', '_adhesive_coverage_floors',
            '_grout_density', '_silicone_coverage'
        );
        
        foreach ($text_fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
            }
        }
    }
    
    /**
     * Get calculator configuration
     */
    public function get_calculator_config($product_id) {
        $config = array(
            'type' => get_post_meta($product_id, '_calculator_type', true),
            'pack_size' => floatval(get_post_meta($product_id, '_pack_size', true)),
            'pack_unit' => get_post_meta($product_id, '_pack_unit', true),
            'use_colour_attributes' => get_post_meta($product_id, '_use_colour_attributes', true) === 'yes',
            'use_weight_attributes' => get_post_meta($product_id, '_use_weight_attributes', true) === 'yes',
            'available_colours' => array(),
            'available_weights' => array()
        );
        
        // Get available colours if using attributes
        if ($config['use_colour_attributes']) {
            $config['available_colours'] = $this->get_product_attributes($product_id, 'colour');
        }
        
        // Get available weights if using attributes
        if ($config['use_weight_attributes']) {
            $config['available_weights'] = $this->get_pack_sizes($product_id);
        }
        
        // Add type-specific configuration
        switch ($config['type']) {
            case 'leveller':
                $config['leveller'] = array(
                    'density_factor' => floatval(get_post_meta($product_id, '_leveller_density', true)) ?: 1.67,
                    'min_depth' => floatval(get_post_meta($product_id, '_leveller_min_depth', true)) ?: 1,
                    'max_depth' => floatval(get_post_meta($product_id, '_leveller_max_depth', true)) ?: 20
                );
                break;
            
            case 'adhesive_powder':
            case 'adhesive_ready':
                $config['adhesive'] = array(
                    'coverage_walls' => floatval(get_post_meta($product_id, '_adhesive_coverage_walls', true)) ?: 4,
                    'coverage_floors' => floatval(get_post_meta($product_id, '_adhesive_coverage_floors', true)) ?: 3
                );
                break;
            
            case 'grout':
                $config['grout'] = array(
                    'density' => floatval(get_post_meta($product_id, '_grout_density', true)) ?: 1.6
                );
                break;
            
            case 'silicone':
                $config['silicone'] = array(
                    'linear_coverage' => floatval(get_post_meta($product_id, '_silicone_coverage', true)) ?: 12.4
                );
                break;
        }
        
        return $config;
    }
    
    /**
     * Display calculator on frontend
     */
    public function display_calculator() {
        global $product;
        
        if (!$product) {
            return;
        }
        
        $calculator_enabled = get_post_meta($product->get_id(), '_enable_calculator', true);
        if ('yes' !== $calculator_enabled) {
            return;
        }
        
        $config = $this->get_calculator_config($product->get_id());
        
        include DUNLOP_CALC_PLUGIN_DIR . 'templates/calculator-display.php';
    }
    
    /**
     * AJAX calculation handler
     */
    public function ajax_calculate() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'dunlop_calc_nonce')) {
            wp_die('Security check failed');
        }
        
        $product_id = intval($_POST['product_id']);
        $calc_type = sanitize_text_field($_POST['calc_type']);
        $input_data = $_POST['input_data'];
        
        $config = $this->get_calculator_config($product_id);
        $result = array();
        
        // Perform calculation based on type
        switch ($calc_type) {
            case 'leveller':
                $area = floatval($input_data['area']);
                $depth = floatval($input_data['depth']);
                $density = floatval($config['leveller']['density_factor']);
                
                $total_kg = $area * $depth * $density;
                
                // Handle pack size optimization
                if ($config['use_weight_attributes'] && !empty($config['available_weights'])) {
                    $pack_combination = $this->optimize_pack_sizes($total_kg, $config['available_weights']);
                    $result = array(
                        'success' => true,
                        'quantity' => $pack_combination['total_packs'],
                        'pack_breakdown' => $pack_combination['breakdown'],
                        'unit' => $config['pack_unit'],
                        'total_kg' => $total_kg,
                        'details' => sprintf(
                            'Total material needed: %.1fkg<br>Coverage: %sm² × %smm × %skg/m²/mm',
                            $total_kg, $area, $depth, $density
                        )
                    );
                } else {
                    $pack_size = floatval($config['pack_size']);
                    $bags_needed = ceil($total_kg / $pack_size);
                    
                    $result = array(
                        'success' => true,
                        'quantity' => $bags_needed,
                        'unit' => $config['pack_unit'],
                        'total_kg' => $total_kg,
                        'details' => sprintf(
                            'Total material needed: %.1fkg<br>Coverage: %sm² × %smm × %skg/m²/mm',
                            $total_kg, $area, $depth, $density
                        )
                    );
                }
                break;
            
            case 'grout':
                $area = floatval($input_data['area']);
                $tile_length = floatval($input_data['tile_length']);
                $tile_width = floatval($input_data['tile_width']);
                $joint_width = floatval($input_data['joint_width']);
                $joint_depth = floatval($input_data['joint_depth']);
                $density = floatval($config['grout']['density']) ?: 1.6;
                
                // Calculate grout needed (formula from original calculator)
                $grout_per_sqm = (($tile_length + $tile_width) / ($tile_length * $tile_width)) * $joint_width * $joint_depth * $density / 1000;
                $total_kg = $grout_per_sqm * $area;
                
                $pack_size = floatval($config['pack_size']) ?: 3.5;
                $packs_needed = ceil($total_kg / $pack_size);
                
                $result = array(
                    'success' => true,
                    'quantity' => $packs_needed,
                    'unit' => $config['pack_unit'] ?: 'pack',
                    'total_kg' => $total_kg,
                    'colour' => sanitize_text_field($input_data['colour']),
                    'details' => sprintf(
                        'Total grout needed: %.1fkg<br>Tile size: %s×%smm, Joint: %s×%smm',
                        $total_kg, $tile_length, $tile_width, $joint_width, $joint_depth
                    )
                );
                break;
            
            case 'adhesive_ready':
            case 'adhesive_powder':
                $area = floatval($input_data['area']);
                $application = sanitize_text_field($input_data['application']);
                
                // Get coverage rate based on application
                $coverage_rate = ($application === 'walls') 
                    ? floatval($config['adhesive']['coverage_walls']) 
                    : floatval($config['adhesive']['coverage_floors']);
                
                $total_kg = $area / $coverage_rate;
                
                // Handle pack size optimization for ready mixed
                if ($calc_type === 'adhesive_ready' && $config['use_weight_attributes'] && !empty($config['available_weights'])) {
                    $pack_combination = $this->optimize_pack_sizes($total_kg, $config['available_weights']);
                    $result = array(
                        'success' => true,
                        'quantity' => $pack_combination['total_packs'],
                        'pack_breakdown' => $pack_combination['breakdown'],
                        'unit' => $config['pack_unit'] ?: 'tub',
                        'total_kg' => $total_kg,
                        'details' => sprintf(
                            'Total adhesive needed: %.1fkg<br>Application: %s, Coverage rate: %sm²/kg',
                            $total_kg, ucfirst($application), $coverage_rate
                        )
                    );
                } else {
                    $pack_size = floatval($config['pack_size']) ?: 20;
                    $packs_needed = ceil($total_kg / $pack_size);
                    
                    $result = array(
                        'success' => true,
                        'quantity' => $packs_needed,
                        'unit' => $config['pack_unit'] ?: 'bag',
                        'total_kg' => $total_kg,
                        'details' => sprintf(
                            'Total adhesive needed: %.1fkg<br>Application: %s, Coverage rate: %sm²/kg',
                            $total_kg, ucfirst($application), $coverage_rate
                        )
                    );
                }
                break;
            
            case 'silicone':
                $length = floatval($input_data['length']);
                $linear_coverage = floatval($config['silicone']['linear_coverage']) ?: 12.4;
                
                $tubes_needed = ceil($length / $linear_coverage);
                
                $result = array(
                    'success' => true,
                    'quantity' => $tubes_needed,
                    'unit' => 'tube',
                    'colour' => sanitize_text_field($input_data['colour']),
                    'details' => sprintf(
                        'Coverage per tube: %sm (6mm bead)',
                        $linear_coverage
                    )
                );
                break;
        }
        
        wp_send_json($result);
    }
    
    /**
     * Optimize pack sizes to minimize total packs
     */
    private function optimize_pack_sizes($total_needed, $available_sizes) {
        if (empty($available_sizes)) {
            return array(
                'total_packs' => 0,
                'breakdown' => array()
            );
        }
        
        // Sort sizes from largest to smallest for optimization
        rsort($available_sizes);
        
        $breakdown = array();
        $remaining = $total_needed;
        $total_packs = 0;
        
        // Use largest packs first
        foreach ($available_sizes as $size) {
            if ($remaining >= $size) {
                $packs_of_this_size = floor($remaining / $size);
                $breakdown[] = array(
                    'size' => $size,
                    'quantity' => $packs_of_this_size
                );
                $total_packs += $packs_of_this_size;
                $remaining -= ($packs_of_this_size * $size);
            }
        }
        
        // If there's any remaining, add one more of the smallest size
        if ($remaining > 0) {
            $smallest_size = end($available_sizes);
            
            // Check if we already have this size in breakdown
            $found = false;
            foreach ($breakdown as &$item) {
                if ($item['size'] == $smallest_size) {
                    $item['quantity']++;
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $breakdown[] = array(
                    'size' => $smallest_size,
                    'quantity' => 1
                );
            }
            $total_packs++;
        }
        
        return array(
            'total_packs' => $total_packs,
            'breakdown' => $breakdown
        );
    }
}

// Initialize the plugin
add_action('plugins_loaded', array('DunlopProductCalculator', 'get_instance'));

// Activation hook
register_activation_hook(__FILE__, 'dunlop_calculator_activate');
function dunlop_calculator_activate() {
    // Any activation tasks here
    flush_rewrite_rules();
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'dunlop_calculator_deactivate');
function dunlop_calculator_deactivate() {
    // Any cleanup tasks here
    flush_rewrite_rules();
}
