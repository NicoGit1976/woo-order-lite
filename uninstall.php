<?php
/**
 * Red-Headed Lite — Uninstall
 *
 * Fired when the plugin is deleted from wp-admin > Plugins > Delete.
 * Default behaviour: full data cleanup. Users can opt out via the
 * "Clean on uninstall" toggle in Settings > General > Data hygiene.
 *
 * Owned data: identical option set + pl_profiles / pl_jobs tables as
 * Red-Headed Pro (Lite → Pro upgrade preserves history).
 *
 * Tables are preserved if the sister edition (red-headed-pro) is still
 * installed.
 *
 * @package RedHeadedLite
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

wp_clear_scheduled_hook( 'pelican_cron_tick' );

$clean = (int) get_option( 'pelican_uninstall_clean', 1 );
if ( ! $clean ) {
    return;
}

global $wpdb;

$options = [
    'pelican_settings',
    'pelican_webhooks',
    'pelican_decimal_separator',
    'pelican_default_email_body',
    'pelican_default_email_subject',
    'pelican_default_email_to',
    'pelican_default_filename_pattern',
    'pelican_default_sftp_host',
    'pelican_default_sftp_pass_enc',
    'pelican_default_sftp_path',
    'pelican_default_sftp_port',
    'pelican_default_sftp_user',
    'pelican_email_body',
    'pelican_email_subject',
    'pelican_notify_on_failure',
    'pelican_notify_recipients',
    'pelican_notify_subject',
    'pelican_register_wc_status_exported',
    'pelican_retention_days',
    'pelican_uninstall_clean',
    'pelican_db_version',
];
foreach ( $options as $opt ) {
    delete_option( $opt );
}

$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        $wpdb->esc_like( 'pelican_last_run_' ) . '%'
    )
);

$sister = WP_PLUGIN_DIR . '/red-headed-pro/red-headed-pro.php';
if ( ! file_exists( $sister ) ) {
    $wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'pl_jobs' );
    $wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'pl_profiles' );
}
