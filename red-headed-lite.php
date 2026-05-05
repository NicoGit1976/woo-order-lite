<?php
/**
 * Plugin Name:       Red Headed (Lite) — Simple Orders Export
 * Plugin URI:        https://thelionfrog.com
 * Description:       Exports WooCommerce orders everywhere, anytime — Lite edition. Manual + bulk to CSV via Email or SFTP. Mascot: Red-Headed Poison Frog. Part of Ultimate Woo Powertools (by The Lion Frog).
 * Version:           1.4.36
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

define( 'PELICAN_VERSION', '1.4.34' );
define( 'PELICAN_EDITION',  'lite' );
define( 'PELICAN_FILE',     __FILE__ );
define( 'PELICAN_PATH',     plugin_dir_path( __FILE__ ) );
define( 'PELICAN_URL',      plugin_dir_url( __FILE__ ) );
define( 'PELICAN_BASENAME', plugin_basename( __FILE__ ) );
define( 'PELICAN_SLUG',     'red-headed-lite' );
add_action( 'plugins_loaded', function () {
    if ( class_exists( 'FH_Plugin_Updater' ) ) {
        FH_Plugin_Updater::register( [
            'slug'        => 'red-headed-lite',
            'plugin_file' => __FILE__,
            'name'        => 'Red Headed Lite',
            'icon_url'    => PELICAN_URL . 'assets/img/mascot-redheaded-v1.svg',
        ] );
    }
    add_filter( 'the_froggy_hub_quick_actions', function ( $actions ) {
        $actions[] = [
            'label'       => __( 'Export orders', 'pelican' ),
            'icon'        => '📦',
            'url'         => admin_url( 'admin.php?page=red-headed-lite-exports&action=new' ),
            'tooltip'     => __( 'Run a manual order export', 'pelican' ),
            'plugin_slug' => 'red-headed-lite',
            'is_primary'  => true,
        ];
        $actions[] = [
            'label'       => __( 'Export history', 'pelican' ),
            'icon'        => '📋',
            'url'         => admin_url( 'admin.php?page=red-headed-lite-exports&tab=history' ),
            'tooltip'     => __( 'View past export jobs', 'pelican' ),
            'plugin_slug' => 'red-headed-lite',
        ];
        $actions[] = [
            'label'       => __( 'Settings', 'pelican' ),
            'icon'        => '⚙️',
            'url'         => admin_url( 'admin.php?page=red-headed-lite-settings' ),
            'tooltip'     => __( 'Open Red-Headed settings', 'pelican' ),
            'plugin_slug' => 'red-headed-lite',
        ];
        return $actions;
    } );
}, 5 );
/* v1.4.17 — Download handler. Fires at admin_init prio 1 BEFORE any HTML output. */
add_action( 'admin_init', function () {
    $dl = ! empty( $_GET['rh_dl'] ) ? (int) $_GET['rh_dl'] : ( ! empty( $_GET['pelican_dl'] ) ? (int) $_GET['pelican_dl'] : 0 );
    if ( ! $dl ) return;
    if ( ! current_user_can( 'manage_options' ) ) return;
    global $wpdb;
    $jobs_tbl = $wpdb->prefix . 'pl_jobs';
    $j = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$jobs_tbl} WHERE id = %d", $dl ), ARRAY_A );
    if ( ! $j || empty( $j['file_path'] ) ) return;
    $u = wp_upload_dir();
    $abs = trailingslashit( $u['basedir'] ) . ltrim( $j['file_path'], '/\\' );
    if ( ! file_exists( $abs ) ) return;
    while ( ob_get_level() ) ob_end_clean();
    nocache_headers();
    header( 'Content-Type: application/octet-stream' );
    header( 'Content-Disposition: attachment; filename="' . basename( $abs ) . '"' );
    header( 'Content-Length: ' . filesize( $abs ) );
    readfile( $abs );
    exit;
}, 1 );

/* Brand-rename — 301 from legacy 'pelican*' / 'woo-order-lite' admin URLs to new
   'red-headed-lite*' (preserves bookmarks). */
add_action( 'admin_init', function () {
    if ( ! isset( $_GET['page'] ) ) return;
    $p = (string) $_GET['page'];
    $map = [
        'woo-order-lite'                => 'red-headed-lite',
        'pelican'                       => 'red-headed-lite',
        'pelican-exports'               => 'red-headed-lite-exports',
        'pelican-settings'              => 'red-headed-lite-settings',
        'pelican-settings-profiles'     => 'red-headed-lite-settings-profiles',
        'pelican-settings-destinations' => 'red-headed-lite-settings-destinations',
        'pelican-settings-cron'         => 'red-headed-lite-settings-cron',
        'pelican-settings-webhooks'     => 'red-headed-lite-settings-webhooks',
        'pelican-settings-general'      => 'red-headed-lite-settings-general',
    ];
    if ( isset( $map[ $p ] ) ) {
        $url = add_query_arg( 'page', $map[ $p ], admin_url( 'admin.php' ) );
        $extras = $_GET; unset( $extras['page'] );
        if ( ! empty( $extras ) ) $url = add_query_arg( $extras, $url );
        wp_safe_redirect( $url, 301 );
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
