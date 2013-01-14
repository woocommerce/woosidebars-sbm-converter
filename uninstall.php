<?php
/**
 * WooSidebars - Sidebar Manager to WooSidebars Converter Uninstall
 *
 * Uninstalls the plugin and associated data
 *
 * @package WordPress
 * @subpackage WooSidebars SBM Converter
 * @category Core
 * @author WooThemes
 * @since 1.0.0
 */
if( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit();

$token = 'woosidebars-sbm-converter';
delete_option( $token . '-converted' );
delete_option( $token . '-dependencies' );
delete_option( $token . '-not-converted' );
?>