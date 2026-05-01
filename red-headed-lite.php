<?php
/**
 * Plugin Name:       Red-Headed Lite — Exports Orders Everywhere, Anytime
 * Plugin URI:        https://thelionfrog.com
 * Description:       Exports WooCommerce orders everywhere, anytime — Lite edition. Manual + bulk to CSV via Email or SFTP. Mascot: Red-Headed Poison Frog. Part of Ultimate Woo Powertools (by The Lion Frog).
 * Version:           1.4.3
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            The Lion Frog Team
 * Author URI:        https://thelionfrog.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pelican
 * Domain Path:       /languages
 *
 * WC requires at least: 8.0
 * WC tested up to:      9.5
 *
 * @package Pelican
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'PELICAN_VERSION', '1.4.3' );
define( 'PELICAN_EDITION',  'lite' );
define( 'PELICAN_FILE',     __FILE__ );
define( 'PELICAN_PATH',     plugin_dir_path( __FILE__ ) );
define( 'PELICAN_URL',      plugin_dir_url( __FILE__ ) );
define( 'PELICAN_BASENAME', plugin_basename( __FILE__ ) );
define( 'PELICAN_SLUG',     'woo-order-lite' );
/* Brand-rename — 301 from legacy admin URL to new (preserves bookmarks). */
add_action( 'admin_init', function () {
    if ( isset( $_GET['page'] ) && $_GET['page'] === 'woo-order-lite' ) {
        wp_safe_redirect( admin_url( 'admin.php?page=red-headed-lite' ), 301 );
        exit;
    }
}, 1 );


if ( file_exists( PELICAN_PATH . 'vendor/autoload.php' ) ) {
    require_once PELICAN_PATH . 'vendor/autoload.php';
}

require_once PELICAN_PATH . 'includes/class-engine.php';

add_action( 'before_woocommerce_init', function () {
    if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
} );

register_activation_hook( __FILE__, function () {
    require_once PELICAN_PATH . 'includes/class-installer.php';
    Pelican_Installer::activate();
} );
register_deactivation_hook( __FILE__, function () {
    require_once PELICAN_PATH . 'includes/class-installer.php';
    Pelican_Installer::deactivate();
} );

/* Boot — load classes, register filters/hooks (no admin yet). */
Pelican_Engine::instance();

/* Lite is free → no license check. Always boot the admin.
   Pro features visible inside are gated by Pelican_Soft_Lock. */
add_action( 'plugins_loaded', function () {
    Pelican_Engine::boot_admin();
}, 20 );
