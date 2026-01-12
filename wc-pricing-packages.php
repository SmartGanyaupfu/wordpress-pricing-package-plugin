<?php
/**
 * Plugin Name: WooCommerce Pricing Table
 * Plugin URI: https://shanopx.com
 * Description: Creates pricing table with direct checkout for subscription packages
 * Version: 1.0.1
 * Author: Smart Ganyaupfu
 * Author URI: https://shanopx.com
 * License: GPL v2 or later
 * Text Domain: wc-pricing-table
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Pricing_Table {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        
        add_action('init', function () {
    if ( defined('DOING_AJAX') && DOING_AJAX ) {
        wc_load_cart();
    }
});

        add_action('plugins_loaded', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_notices', array($this, 'check_dependencies'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_add_to_cart_and_checkout', array($this, 'ajax_add_to_cart_and_checkout'));
        add_action('wp_ajax_nopriv_add_to_cart_and_checkout', array($this, 'ajax_add_to_cart_and_checkout'));
		add_action('before_woocommerce_init', array($this, 'declare_hpos_compatibility'));
        add_shortcode('pricing_table', array($this, 'pricing_table_shortcode'));
        register_activation_hook(__FILE__, array($this, 'activate'));
    }
    
    public function init() {
        if (!$this->check_woocommerce_active()) {
            return;
        }
    }
    public function declare_hpos_compatibility() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
}
    public function check_dependencies() {
        if (!$this->check_woocommerce_active()) {
            ?>
            <div class="notice notice-error">
                <p><?php _e('WooCommerce Pricing Table requires WooCommerce to be installed and active.', 'wc-pricing-table'); ?></p>
            </div>
            <?php
        }
    }
    
    private function check_woocommerce_active() {
        return class_exists('WooCommerce');
    }
    
    public function enqueue_scripts() {
        wp_enqueue_style('wc-pricing-table', plugin_dir_url(__FILE__) . 'assets/pricing-table.css', array(), '1.0.0');
        wp_enqueue_script('wc-pricing-table', plugin_dir_url(__FILE__) . 'assets/pricing-table.js', array('jquery'), '1.0.0', true);
        
        wp_localize_script('wc-pricing-table', 'wcPricingTable', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pricing_table_nonce')
        ));
    }
    
    public function activate() {
        if (!$this->check_woocommerce_active()) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('This plugin requires WooCommerce to be installed and active.', 'wc-pricing-table'));
        }
        
        $this->create_default_packages();
        $this->create_assets_directory();
    }
    
    private function create_assets_directory() {
        $upload_dir = wp_upload_dir();
        $plugin_dir = plugin_dir_path(__FILE__);
        $assets_dir = $plugin_dir . 'assets';
        
        if (!file_exists($assets_dir)) {
            wp_mkdir_p($assets_dir);
        }
        
        // Create CSS file
        $css_content = $this->get_css_content();
        file_put_contents($assets_dir . '/pricing-table.css', $css_content);
        
        // Create JS file
        $js_content = $this->get_js_content();
        file_put_contents($assets_dir . '/pricing-table.js', $js_content);
    }
    
    private function get_css_content() {
        return <<<CSS
.pricing-table-container {
    max-width: 1200px;
    margin: 40px auto;
    padding: 20px;
}

.pricing-table {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 30px;
    margin-top: 30px;
}

.pricing-package {
    background: #fff;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    padding: 30px;
    text-align: center;
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    position: relative;
}

.pricing-package:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
}

.pricing-package.featured {
    border-color: #2c3e50;
    border-width: 3px;
}

.pricing-package.featured::before {
    content: "MOST POPULAR";
    position: absolute;
    top: -15px;
    left: 50%;
    transform: translateX(-50%);
    background: #2c3e50;
    color: #fff;
    padding: 5px 20px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    letter-spacing: 1px;
}

.package-header {
    margin-bottom: 25px;
}

.package-name {
    font-size: 28px;
    font-weight: bold;
    color: #2c3e50;
    margin-bottom: 10px;
}

.package-price {
    font-size: 48px;
    font-weight: bold;
    color: #3498db;
    margin: 20px 0;
}

.package-price sup {
    font-size: 24px;
}

.package-price span {
    font-size: 18px;
    color: #666;
    font-weight: normal;
}

.setup-fee {
    font-size: 14px;
    color: #666;
    margin-bottom: 20px;
}

.package-description {
    font-size: 14px;
    color: #666;
    margin-bottom: 25px;
    line-height: 1.6;
}

.package-features {
    text-align: left;
    margin: 25px 0;
}

.feature-item {
    display: flex;
    align-items: flex-start;
    padding: 12px 0;
    border-bottom: 1px solid #f0f0f0;
}

.feature-item:last-child {
    border-bottom: none;
}

.feature-label {
    font-weight: 600;
    color: #2c3e50;
    flex: 1;
    font-size: 13px;
}

.feature-value {
    color: #555;
    flex: 0 0 45%;
    text-align: right;
    font-size: 13px;
}

.feature-value.included {
    color: #27ae60;
    font-weight: bold;
}

.feature-value.not-included {
    color: #999;
}

.feature-value.addon {
    color: #e67e22;
    font-size: 12px;
}

.package-button {
    width: 100%;
    padding: 15px 30px;
    font-size: 16px;
    font-weight: bold;
    color: #fff;
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-top: 25px;
}

.package-button:hover {
    background: linear-gradient(135deg, #2980b9 0%, #21618c 100%);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(52,152,219,0.3);
}

.package-button:active {
    transform: translateY(0);
}

.package-button:disabled {
    background: #ccc;
    cursor: not-allowed;
    transform: none;
}

.featured .package-button {
    background: linear-gradient(135deg, #2c3e50 0%, #1a252f 100%);
}

.featured .package-button:hover {
    background: linear-gradient(135deg, #1a252f 0%, #0d1419 100%);
    box-shadow: 0 5px 15px rgba(44,62,80,0.3);
}

.loading-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.7);
    z-index: 9999;
    align-items: center;
    justify-content: center;
}

.loading-overlay.active {
    display: flex;
}

.loading-content {
    background: #fff;
    padding: 30px;
    border-radius: 10px;
    text-align: center;
}

.spinner {
    border: 4px solid #f3f3f3;
    border-top: 4px solid #3498db;
    border-radius: 50%;
    width: 50px;
    height: 50px;
    animation: spin 1s linear infinite;
    margin: 0 auto 20px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

@media (max-width: 768px) {
    .pricing-table {
        grid-template-columns: 1fr;
    }
}
CSS;
    }
    
    private function get_js_content() {
        return <<<JS
jQuery(document).ready(function($) {
    $('.package-button').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var productId = button.data('product-id');
        
        // Disable button and show loading
        button.prop('disabled', true).text('Processing...');
        $('.loading-overlay').addClass('active');
        
        $.ajax({
            url: wcPricingTable.ajax_url,
            type: 'POST',
            data: {
                action: 'add_to_cart_and_checkout',
                product_id: productId,
                nonce: wcPricingTable.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Redirect to checkout
                    window.location.href = response.data.checkout_url;
                } else {
                    alert(response.data.message || 'An error occurred. Please try again.');
                    button.prop('disabled', false).text('Choose Plan');
                    $('.loading-overlay').removeClass('active');
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
                button.prop('disabled', false).text('Choose Plan');
                $('.loading-overlay').removeClass('active');
            }
        });
    });
});
JS;
    }
    
public function ajax_add_to_cart_and_checkout() {

    check_ajax_referer('pricing_table_nonce', 'nonce');

    // 🔥 ENSURE WC IS FULLY INITIALIZED (CRITICAL FOR GUEST USERS)
    if ( null === WC()->session ) {
        WC()->initialize_session();
    }

    if ( null === WC()->customer ) {
        WC()->customer = new WC_Customer( get_current_user_id(), true );
    }

    if ( null === WC()->cart ) {
        WC()->cart = new WC_Cart();
    }

    // 🌍 Ensure customer location exists (fixes geolocation pricing & taxes)
    $location = WC_Geolocation::geolocate_ip();
    $country  = $location['country'] ?? wc_get_base_location()['country'];
    $state    = $location['state'] ?? wc_get_base_location()['state'];

    WC()->customer->set_location($country, $state);
    WC()->customer->save();

    if ( ! isset($_POST['product_id']) ) {
        wp_send_json_error(['message' => 'Product ID is required.']);
    }

    $product_id = absint($_POST['product_id']);

    // Reset cart to ensure single-plan checkout
    WC()->cart->empty_cart();

    $added = WC()->cart->add_to_cart($product_id, 1);

    if ($added) {
        wp_send_json_success([
            'checkout_url' => wc_get_checkout_url()
        ]);
    }

    wp_send_json_error(['message' => 'Failed to add product to cart.']);
}

    
    public function add_admin_menu() {
        add_menu_page(
            __('Pricing Table', 'wc-pricing-table'),
            __('Pricing Table', 'wc-pricing-table'),
            'manage_woocommerce',
            'wc-pricing-table',
            array($this, 'admin_page'),
            'dashicons-cart',
            56
        );
    }
    
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Pricing Table Manager', 'wc-pricing-table'); ?></h1>
            
            <div class="card">
                <h2><?php _e('Create/Update Packages', 'wc-pricing-table'); ?></h2>
                <p><?php _e('Click the button below to create or update all pricing packages.', 'wc-pricing-table'); ?></p>
                
                <form method="post" action="">
                    <?php wp_nonce_field('create_packages', 'packages_nonce'); ?>
                    <button type="submit" name="create_packages" class="button button-primary">
                        <?php _e('Create/Update Packages', 'wc-pricing-table'); ?>
                    </button>
                </form>
                
                <?php
                if (isset($_POST['create_packages']) && check_admin_referer('create_packages', 'packages_nonce')) {
                    $this->create_default_packages();
                    echo '<div class="notice notice-success inline"><p>' . __('Packages created/updated successfully!', 'wc-pricing-table') . '</p></div>';
                }
                ?>
            </div>
            
            <div class="card" style="margin-top: 20px;">
                <h2><?php _e('Usage', 'wc-pricing-table'); ?></h2>
                <p><?php _e('Use this shortcode to display the pricing table on any page:', 'wc-pricing-table'); ?></p>
                <code style="display: block; padding: 10px; background: #f5f5f5; margin: 10px 0;">[pricing_table]</code>
            </div>
            
            <div class="card" style="margin-top: 20px;">
                <h2><?php _e('Existing Packages', 'wc-pricing-table'); ?></h2>
                <?php $this->display_existing_packages(); ?>
            </div>
        </div>
        <?php
    }
    
    private function display_existing_packages() {
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_is_pricing_package',
                    'value' => 'yes',
                    'compare' => '='
                )
            )
        );
        
        $products = get_posts($args);
        
        if (empty($products)) {
            echo '<p>' . __('No packages found. Click the button above to create them.', 'wc-pricing-table') . '</p>';
            return;
        }
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Package Name</th><th>Price</th><th>Setup Fee</th><th>Actions</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($products as $product) {
            $product_obj = wc_get_product($product->ID);
            $setup_fee = get_post_meta($product->ID, '_setup_fee', true);
            
            echo '<tr>';
            echo '<td><strong>' . esc_html($product->post_title) . '</strong></td>';
            echo '<td>$' . esc_html($product_obj->get_price()) . '/month</td>';
            echo '<td>$' . esc_html($setup_fee) . '</td>';
            echo '<td><a href="' . get_edit_post_link($product->ID) . '" class="button button-small">Edit</a></td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }
    
    public function pricing_table_shortcode($atts) {
        $packages = $this->get_package_products();
        
        if (empty($packages)) {
            return '<p>No pricing packages found. Please create packages from the admin panel.</p>';
        }
        
        ob_start();
        ?>
        <div class="pricing-table-container">
            <div class="pricing-table">
                <?php foreach ($packages as $index => $package): ?>
                    <div class="pricing-package <?php echo $index === 1 ? 'featured' : ''; ?>">
                        <div class="package-header">
                            <h3 class="package-name"><?php echo esc_html($package['name']); ?></h3>
                            <div class="package-price">
								<?php
$currency_symbol = get_woocommerce_currency_symbol();
?>
                                <sup><?php echo esc_html( $currency_symbol ); ?></sup><?php echo esc_html($package['price']); ?><span>/month</span>
                            </div>
                          
                            <p class="package-description"><?php echo esc_html($package['description']); ?></p>
                        </div>
                        
                        <div class="package-features">
                            <?php foreach ($package['features'] as $label => $value): ?>
                                <?php if ($label !== 'Monthly Fee' && $label !== 'One-Time Setup Fee'): ?>
                                    <div class="feature-item">
                                        <span class="feature-label"><?php echo esc_html($label); ?></span>
                                        <span class="feature-value <?php echo $this->get_feature_class($value); ?>">
                                            <?php echo wp_kses_post($value); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        
                        <button class="package-button" data-product-id="<?php echo esc_attr($package['id']); ?>">
                            Choose <?php echo esc_html($package['name']); ?>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="loading-overlay">
            <div class="loading-content">
                <div class="spinner"></div>
                <p>Adding to cart and redirecting to checkout...</p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private function get_feature_class($value) {
        if ($value === '✅' || strpos($value, '✅') !== false) {
            return 'included';
        } elseif ($value === '—') {
            return 'not-included';
        } elseif (strpos($value, '+$') !== false) {
            return 'addon';
        }
        return '';
    }
    
    private function get_package_products() {
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'meta_query' => array(
                array(
                    'key' => '_is_pricing_package',
                    'value' => 'yes',
                    'compare' => '='
                )
            ),
            'orderby' => 'meta_value_num',
            'meta_key' => '_price',
            'order' => 'ASC'
        );
        
        $products = get_posts($args);
        $packages = array();
        
        foreach ($products as $product) {
            $product_obj = wc_get_product($product->ID);
            $packages[] = array(
                'id' => $product->ID,
                'name' => $product->post_title,
                'price' => $product_obj->get_price(),
                'setup_fee' => get_post_meta($product->ID, '_setup_fee', true),
                'description' => $product->post_content,
                'features' => get_post_meta($product->ID, '_package_features', true)
            );
        }
        
        return $packages;
    }
    
    private function create_default_packages() {
        $packages = $this->get_package_definitions();
        
        foreach ($packages as $slug => $package) {
            $this->create_or_update_package($slug, $package);
        }
    }
    
    private function get_package_definitions() {
        return array(
            'starter' => array(
                'name' => 'Starter',
                'price' => 15,
               
                'features' => array(
                    'Monthly Fee' => '$15/month',
                    
                    'Number of Pages' => 'Up to 3',
                    'Mobile Responsive Design' => 'Yes',
                    'Contact Form' => 'Basic',
                    'SEO Optimization' => 'Basic',
                    'Blog / News Section' => '—',
                    'Google Analytics Integration' => 'Yes',
                    'E-commerce Functionality' => '—',
                    'SSL Certificate' => 'Yes',
                    'Monthly Updates' => '1 update',
                    'Backups & Maintenance' => 'Monthly',
                   
                    'Business Email' => '3 Mailboxes',
                    'Custom Forms / Booking' => '—',
                    'Performance Report' => '—',
                    'Support Level' => 'Standard Email',
                    'WhatsApp / Chat Integration' => '—',
                    'Social Media Integration' => 'Share & Business Links',
                      'Admin Access' => '-'
                    
                ),
                'description' => 'Perfect for individuals, small businesses and startups'
            ),
            'business' => array(
                'name' => 'Business',
                'price' => 25,
                
                'features' => array(
                    'Monthly Fee' => '$25/month',
                    
                    'Number of Pages' => 'Up to 7',
                    'Mobile Responsive Design' => 'Yes',
                    'Contact Form' => 'Customisable',
                    'SEO Optimization' => 'On-Page SEO',
                    'Blog / News Section' => 'Yes',
                    'Google Analytics Integration' => 'Yes',
                    'E-commerce Functionality' => 'Upto 5 Products',
                    'SSL Certificate' => 'Yes',
                    'Monthly Updates' => '5 updates',
                    'Backups & Maintenance' => 'Weekly',
                   
                    'Business Email' => '15 Mailboxes',
                    'Custom Forms / Booking' => '—',
                    'Performance Report' => 'Yes',
                    'Support Level' => 'Priority Email',
                    'WhatsApp / Chat Integration' => 'Yes',
                    'Social Media Integration' => 'Yes',
                      'Admin Access' => '-'
                    
                ),
                
                'description' => 'Ideal for growing businesses'
            ),
            'pro' => array(
                'name' => 'Pro',
                'price' => 35,
                'features' => array(
                    'Monthly Fee' => '$35/month',
                    
                    'Number of Pages' => 'Up to 15',
                    'Mobile Responsive Design' => 'Yes',
                    'Contact Form' => 'Advanced',
                    'SEO Optimization' => 'Advanced SEO',
                    'Blog / News Section' => 'Yes',
                    'Google Analytics Integration' => 'Yes',
                    'E-commerce Functionality' => 'Yes',
                    'SSL Certificate' => 'Yes',
                    'Monthly Updates' => '10 updates',
                    'Backups & Maintenance' => 'Daily',
                   
                    'Business Email' => 'Unlimited',
                    'Custom Forms / Booking' => 'Yes',
                    'Performance Report' => 'Monthly',
                    'Support Level' => 'Dedicated',
                    'WhatsApp / Chat Integration' => 'Yes',
                    'Social Media Integration' => 'Yes',
                     'Admin Access' => 'Yes'
                    
                ),
                
                'description' => 'Complete solution for established businesses'
            )
        );
    }
    
    private function create_or_update_package($slug, $package) {
        $existing = get_posts(array(
            'post_type' => 'product',
            'name' => sanitize_title($slug),
            'posts_per_page' => 1
        ));
        
        $product_id = !empty($existing) ? $existing[0]->ID : 0;
        
        $product_data = array(
            'ID' => $product_id,
            'post_title' => $package['name'],
            'post_name' => sanitize_title($slug),
            'post_content' => $package['description'],
            'post_status' => 'publish',
            'post_type' => 'product',
        );
        
        $product_id = wp_insert_post($product_data);
        
        if ($product_id) {
            wp_set_object_terms($product_id, 'simple', 'product_type');
            update_post_meta($product_id, '_regular_price', $package['price']);
            update_post_meta($product_id, '_price', $package['price']);
            update_post_meta($product_id, '_is_pricing_package', 'yes');
            update_post_meta($product_id, '_setup_fee', $package['setup_fee']);
            update_post_meta($product_id, '_package_features', $package['features']);
            update_post_meta($product_id, '_package_slug', $slug);
            update_post_meta($product_id, '_virtual', 'yes');
            update_post_meta($product_id, '_sold_individually', 'yes');
        }
        
        return $product_id;
    }
}

WC_Pricing_Table::get_instance();