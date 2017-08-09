<?php
/*
Plugin Name: Genesis Inline
Plugin URI: http://wpebooks.com/
Description: An inline front end post editor for Genesis
Author: Ron Rennick
Version: 0.1.2
Author URI: http://ronandandrea.com/

	License: GNU General Public License v2.0
	License URI: http://www.opensource.org/licenses/gpl-license.php

This post editor is a modified version of the post box from the P2 theme 1.1.8-wpcom (http://wordpress.org/extend/themes/p2) by Automattic (http://automattic.com)

Removed - Inline comments, category based post ypes, UX elements
Added - tag suggest, category dropdown, post format support, multiple save, inline preview, editor features based on user role, GenesisConnect & BuddyPress compatability
 
*/
//@todo: translation support

function genesisinline_init() {
	define( 'GENESISINLINE_VERSION', '0.1.2' );
	define( 'GENESISINLINE_DIR', plugin_dir_path( __FILE__ ) );
	define( 'GENESISINLINE_LIB', GENESISINLINE_DIR . 'lib/' );
	$url = plugin_dir_url( __FILE__ );
	define( 'GENESISINLINE_CSS', $url . 'css/' );
	define( 'GENESISINLINE_JS', $url . 'js/' );
	require( GENESISINLINE_LIB . 'class.theme.php' );
}
add_action( 'genesis_init', 'genesisinline_init' );

// from P2
function latest_post_permalink() {
	global $wpdb;
	$sql = "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish' ORDER BY post_date DESC LIMIT 1";
	$last_post_id = $wpdb->get_var($sql);
	$permalink = get_permalink($last_post_id);
	return $permalink;
}

?>
