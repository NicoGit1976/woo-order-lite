<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Shared in-page nav (Harlequin). Dashboard / Exports / Settings.
 *
 * @package Pelican
 */
$_pl_pages = array(
    'red-headed-lite'          => array( 'icon' => '📊', 'label' => __( 'Dashboard', 'pelican' ) ),
    'red-headed-lite-exports'  => array( 'icon' => '📦', 'label' => __( 'Exports',   'pelican' ) ),
    'red-headed-lite-settings' => array( 'icon' => '⚙️', 'label' => __( 'Settings',  'pelican' ) ),
);
$_pl_current = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : 'red-headed-lite';
if ( strpos( $_pl_current, 'red-headed-lite-settings' ) === 0 ) $_pl_current = 'red-headed-lite-settings';
?>
<nav class="pl-page-nav" role="navigation" aria-label="Red Headed sections">
    <?php foreach ( $_pl_pages as $slug => $meta ) :
        $url = admin_url( 'admin.php?page=' . $slug );
        $is_cur = ( $slug === $_pl_current );
    ?>
        <a href="<?php echo esc_url( $url ); ?>" class="pl-page-nav-item <?php echo $is_cur ? 'pl-page-nav-active' : ''; ?>">
            <span class="pl-page-nav-icon"><?php echo esc_html( $meta['icon'] ); ?></span>
            <span class="pl-page-nav-label"><?php echo esc_html( $meta['label'] ); ?></span>
        </a>
    <?php endforeach; ?>
</nav>
