<?php
/**
 * Plugin Name:       Ranau Simple Checkout for WooCommerce
 * Plugin URI:        https://ranau.uk/wordpress/ranau-simple-checkout/
 * Description:       A city-and-phone checkout flow for WooCommerce delivery stores.
 * Version:           0.1.1
 * Requires at least: 6.9
 * Requires PHP:      7.4
 * Author:            Ranau
 * Author URI:        https://ranau.uk/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ranau-simple-checkout
 * Requires Plugins:  woocommerce
 * WC requires at least: 8.9
 * WC tested up to:   10.8
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

define('RANAU_SIMPLE_CHECKOUT_VERSION', '0.1.1');
define('RANAU_SIMPLE_CHECKOUT_FILE', __FILE__);
define('RANAU_SIMPLE_CHECKOUT_PATH', plugin_dir_path(__FILE__));
define('RANAU_SIMPLE_CHECKOUT_URL', plugin_dir_url(__FILE__));

require_once RANAU_SIMPLE_CHECKOUT_PATH . 'src/Plugin.php';

add_action('before_woocommerce_init', static function (): void {
    $features = '\\Automattic\\WooCommerce\\Utilities\\FeaturesUtil';

    if (class_exists($features)) {
        $features::declare_compatibility('custom_order_tables', RANAU_SIMPLE_CHECKOUT_FILE, true);
        $features::declare_compatibility('cart_checkout_blocks', RANAU_SIMPLE_CHECKOUT_FILE, true);
    }
});

add_action('plugins_loaded', static function (): void {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', static function (): void {
            if (!current_user_can('activate_plugins')) {
                return;
            }

            echo '<div class="notice notice-error"><p>' . esc_html__(
                'Ranau Simple Checkout requires WooCommerce.',
                'ranau-simple-checkout'
            ) . '</p></div>';
        });
        return;
    }

    \Ranau\WooCommerce\SimpleCheckout\Plugin::instance()->init();
});
