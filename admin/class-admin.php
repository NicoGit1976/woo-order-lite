<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Admin — registers the menu under Froggy Hub (no top-level WP menu) and
 * enqueues assets on Pelican pages.
 *
 * @package Pelican
 */
class Pelican_Admin {
    public function __construct() {
        /* v1.4.14 — prio 110 (was 11). See red-headed-pro v1.4.16 commit. Hub
           registers parent 'froggy-hub' at prio 98 — Pelican must fire AFTER. */
        add_action( 'admin_menu', array( $this, 'register_menu' ), 110 );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_ajax_pelican_save_profile', array( $this, 'ajax_save_profile' ) );
        add_action( 'wp_ajax_pelican_delete_profile', array( $this, 'ajax_delete_profile' ) );
        add_action( 'wp_ajax_pelican_run_profile', array( $this, 'ajax_run_profile' ) );
        add_action( 'wp_ajax_pelican_preview_profile', array( $this, 'ajax_preview_profile' ) );
        add_action( 'wp_ajax_pelican_preview_job',     array( $this, 'ajax_preview_job' ) );
    }
    public function register_menu() {
        /* v1.4.13 — Cap lowered to 'manage_options' (was 'manage_woocommerce'). Admin
           gate for the menu; WC-specific runtime checks stay in AJAX handlers. */
        $cap = 'manage_options';
        /* No top-level menu. All pages register under parent=null with a
           shared in-page nav at the top (Dashboard / Exports / Settings). */
        /* v1.4.12 — Dashboard registered under 'froggy-hub' parent so Hub placeholder
           dedup catches it (no double menu entry, no redirect loop). */
        add_submenu_page( 'froggy-hub', __( 'Red Headed Dashboard', 'pelican' ), 'Red Headed', $cap, 'red-headed-lite', array( $this, 'render_dashboard' ) );
        add_submenu_page( null, __( 'Red Headed Exports',   'pelican' ), '', $cap, 'red-headed-lite-exports',  array( $this, 'render_exports' ) );
        add_submenu_page( null, __( 'Red Headed Settings',  'pelican' ), '', $cap, 'red-headed-lite-settings', array( $this, 'render_settings' ) );

        /* Settings deep-links forced ?tab= */
        foreach ( array( 'profiles', 'destinations', 'cron', 'webhooks', 'general' ) as $tab ) {
            $self = $this;
            add_submenu_page( null, ucfirst( $tab ), '', $cap, 'red-headed-lite-settings-' . $tab, function () use ( $self, $tab ) {
                $_GET['tab'] = $tab; $self->render_settings();
            } );
        }
    }
    public function enqueue_assets( $hook ) {
        if ( strpos( (string) $hook, 'red-headed-lite' ) === false ) return;
        wp_enqueue_style( 'pelican', PELICAN_URL . 'assets/css/pelican.css', array(), PELICAN_VERSION );
        wp_enqueue_script( 'pelican', PELICAN_URL . 'assets/js/pelican.js', array( 'jquery' ), PELICAN_VERSION, true );
        wp_localize_script( 'pelican', 'PelicanData', array(
            'ajaxurl'   => admin_url( 'admin-ajax.php' ),
            'nonce'     => wp_create_nonce( 'pelican' ),
            'restUrl'   => rest_url(),
            'restNonce' => wp_create_nonce( 'wp_rest' ),
            'edition'   => Pelican_Soft_Lock::edition(),
        ) );
    }
    public function render_dashboard() { include PELICAN_PATH . 'partials/view-dashboard.php'; }
    public function render_exports()   { include PELICAN_PATH . 'partials/view-exports.php'; }
    public function render_settings()  { include PELICAN_PATH . 'partials/view-settings.php'; }

    /* ────────── AJAX ────────── */
    public function ajax_save_profile() {
        check_ajax_referer( 'pelican', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error( array( 'message' => 'Insufficient permissions.' ), 403 );
        $data = isset( $_POST['profile'] ) ? json_decode( wp_unslash( $_POST['profile'] ), true ) : null;
        if ( ! is_array( $data ) ) wp_send_json_error( array( 'message' => 'Invalid profile JSON.' ) );
        $id = Pelican_Profile_Repo::save( $data );
        if ( is_wp_error( $id ) ) wp_send_json_error( array( 'message' => $id->get_error_message() ) );
        wp_send_json_success( Pelican_Profile_Repo::get( $id ) );
    }
    public function ajax_delete_profile() {
        check_ajax_referer( 'pelican', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error( array( 'message' => 'Insufficient permissions.' ), 403 );
        $id = (int) ( $_POST['id'] ?? 0 );
        $ok = Pelican_Profile_Repo::delete( $id );
        wp_send_json_success( array( 'deleted' => $ok ) );
    }
    public function ajax_run_profile() {
        check_ajax_referer( 'pelican', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_send_json_error( array( 'message' => 'Insufficient permissions.' ), 403 );
        $id = (int) ( $_POST['id'] ?? 0 );
        $p  = Pelican_Profile_Repo::get( $id );
        if ( ! $p ) wp_send_json_error( array( 'message' => 'Profile not found.' ) );
        $job = Pelican_Export_Engine::run( $p, 'manual' );
        if ( is_wp_error( $job ) ) wp_send_json_error( array( 'message' => $job->get_error_message() ) );
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT records_count FROM {$wpdb->prefix}pl_jobs WHERE id = %d", $job ), ARRAY_A );
        $payload = array( 'job_id' => $job, 'records' => isset( $row['records_count'] ) ? (int) $row['records_count'] : 0 );
        if ( $payload['records'] === 0 ) {
            $payload['warning'] = __( 'Export ran but no orders matched your filters. Check the profile settings (statuses, date range).', 'pelican' );
        }
        wp_send_json_success( $payload );
    }
    public function ajax_preview_profile() {
        check_ajax_referer( 'pelican', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => 'Insufficient permissions.' ), 403 );
        $id = (int) ( $_POST['id'] ?? 0 );
        $p  = Pelican_Profile_Repo::get( $id );
        if ( ! $p ) wp_send_json_error( array( 'message' => 'Profile not found.' ) );
        $orders  = Pelican_Export_Engine::fetch_orders( isset( $p['filters'] ) ? (array) $p['filters'] : array() );
        $columns = Pelican_Export_Engine::normalize_columns( ! empty( $p['columns'] ) ? (array) $p['columns'] : Pelican_Export_Engine::default_columns() );
        $sample  = array_slice( $orders, 0, 5 );
        $rows = array_map( function ( $o ) use ( $columns ) {
            $assoc = Pelican_Export_Engine::map_row( $o, $columns );
            $vals = array();
            foreach ( $columns as $c ) {
                $k = is_array( $c ) ? ( $c['key'] ?? '' ) : (string) $c;
                $vals[] = isset( $assoc[ $k ] ) ? $assoc[ $k ] : '';
            }
            return $vals;
        }, $sample );
        wp_send_json_success( array(
            'count'   => count( $orders ),
            'columns' => array_map( function ( $c ) { return $c['label'] ?? ( $c['key'] ?? '' ); }, $columns ),
            'rows'    => $rows,
        ) );
    }
    public function ajax_preview_job() {
        check_ajax_referer( 'pelican', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( array( 'message' => 'Insufficient permissions.' ), 403 );
        $jid = (int) ( $_POST['id'] ?? 0 );
        global $wpdb;
        $j = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}pl_jobs WHERE id = %d", $jid ), ARRAY_A );
        if ( ! $j || empty( $j['file_path'] ) ) wp_send_json_error( array( 'message' => 'Job or file not found.' ) );
        $u   = wp_upload_dir();
        $abs = trailingslashit( $u['basedir'] ) . ltrim( $j['file_path'], '/\\' );
        if ( ! file_exists( $abs ) ) wp_send_json_error( array( 'message' => 'File missing on disk.' ) );
        $format = strtolower( $j['format'] );
        $rows = array(); $columns = array();
        if ( in_array( $format, array( 'csv', 'tsv' ), true ) ) {
            $sep = $format === 'tsv' ? "\t" : ',';
            if ( ( $fh = fopen( $abs, 'r' ) ) !== false ) {
                $header = fgetcsv( $fh, 0, $sep );
                if ( is_array( $header ) ) $columns = $header;
                $count = 0;
                while ( ( $r = fgetcsv( $fh, 0, $sep ) ) !== false && $count < 10 ) { $rows[] = $r; $count++; }
                fclose( $fh );
            }
        } else {
            wp_send_json_success( array( 'unsupported' => true, 'format' => $format ) );
        }
        wp_send_json_success( array( 'columns' => $columns, 'rows' => $rows, 'total' => (int) $j['records_count'] ) );
    }
}
