<?php
/**
 * Location Management Functions
 * State, District, and Block CRUD operations
 *
 * @package PMProNBSTUP
 * @subpackage Location
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create tables for states, districts, and blocks
 */
function pmpro_nbstup_create_location_tables() {
	global $wpdb;
	$charset_collate = $wpdb->get_charset_collate();

	// States table
	$table_states = $wpdb->prefix . 'pmpro_nbstup_states';
	$sql_states   = "CREATE TABLE IF NOT EXISTS $table_states (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		name varchar(100) NOT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		UNIQUE KEY name (name)
	) $charset_collate;";

	// Districts table
	$table_districts = $wpdb->prefix . 'pmpro_nbstup_districts';
	$sql_districts   = "CREATE TABLE IF NOT EXISTS $table_districts (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		state_id bigint(20) NOT NULL,
		name varchar(100) NOT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY state_id (state_id)
	) $charset_collate;";

	// Blocks table
	$table_blocks = $wpdb->prefix . 'pmpro_nbstup_blocks';
	$sql_blocks   = "CREATE TABLE IF NOT EXISTS $table_blocks (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		district_id bigint(20) NOT NULL,
		name varchar(100) NOT NULL,
		created_at datetime DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (id),
		KEY district_id (district_id)
	) $charset_collate;";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql_states );
	dbDelta( $sql_districts );
	dbDelta( $sql_blocks );
}

/**
 * Ensure location tables exist
 */
function pmpro_nbstup_ensure_location_tables() {
	global $wpdb;
	$table_states = $wpdb->prefix . 'pmpro_nbstup_states';
	
	// Check if states table exists
	$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_states'" );
	
	if ( $table_exists != $table_states ) {
		pmpro_nbstup_create_location_tables();
	}
}
add_action( 'admin_init', 'pmpro_nbstup_ensure_location_tables' );

// ========== STATE FUNCTIONS ==========

/**
 * Get all states
 */
function pmpro_nbstup_get_all_states() {
	global $wpdb;
	$table = $wpdb->prefix . 'pmpro_nbstup_states';
	return $wpdb->get_results( "SELECT * FROM $table ORDER BY name ASC" );
}

/**
 * Get state by ID
 */
function pmpro_nbstup_get_state( $id ) {
	global $wpdb;
	$table = $wpdb->prefix . 'pmpro_nbstup_states';
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
}

/**
 * Get state name by ID
 */
function pmpro_nbstup_get_state_name( $id ) {
	$state = pmpro_nbstup_get_state( $id );
	return $state ? $state->name : '';
}

/**
 * Add a new state
 */
function pmpro_nbstup_add_state( $name ) {
	global $wpdb;
	$table = $wpdb->prefix . 'pmpro_nbstup_states';
	
	$result = $wpdb->insert(
		$table,
		array( 'name' => sanitize_text_field( $name ) ),
		array( '%s' )
	);
	
	return $result !== false;
}

/**
 * Update a state
 */
function pmpro_nbstup_update_state( $id, $name ) {
	global $wpdb;
	$table = $wpdb->prefix . 'pmpro_nbstup_states';
	
	$result = $wpdb->update(
		$table,
		array( 'name' => sanitize_text_field( $name ) ),
		array( 'id' => $id ),
		array( '%s' ),
		array( '%d' )
	);
	
	return $result !== false;
}

/**
 * Delete a state (and cascade delete districts and blocks)
 */
function pmpro_nbstup_delete_state( $id ) {
	global $wpdb;
	
	// Delete all blocks in districts of this state
	$table_districts = $wpdb->prefix . 'pmpro_nbstup_districts';
	$districts       = $wpdb->get_results( $wpdb->prepare( "SELECT id FROM $table_districts WHERE state_id = %d", $id ) );
	
	foreach ( $districts as $district ) {
		pmpro_nbstup_delete_district( $district->id );
	}
	
	// Delete all districts of this state
	$wpdb->delete( $table_districts, array( 'state_id' => $id ), array( '%d' ) );
	
	// Delete the state
	$table_states = $wpdb->prefix . 'pmpro_nbstup_states';
	return $wpdb->delete( $table_states, array( 'id' => $id ), array( '%d' ) ) !== false;
}

// ========== DISTRICT FUNCTIONS ==========

/**
 * Get all districts (optionally by state)
 */
function pmpro_nbstup_get_districts( $state_id = null ) {
	global $wpdb;
	$table = $wpdb->prefix . 'pmpro_nbstup_districts';
	
	if ( $state_id ) {
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE state_id = %d ORDER BY name ASC", $state_id ) );
	}
	
	return $wpdb->get_results( "SELECT * FROM $table ORDER BY name ASC" );
}

/**
 * Get district by ID
 */
function pmpro_nbstup_get_district( $id ) {
	global $wpdb;
	$table = $wpdb->prefix . 'pmpro_nbstup_districts';
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
}

/**
 * Get district name by ID
 */
function pmpro_nbstup_get_district_name( $id ) {
	$district = pmpro_nbstup_get_district( $id );
	return $district ? $district->name : '';
}

/**
 * Add a new district
 */
function pmpro_nbstup_add_district( $state_id, $name ) {
	global $wpdb;
	$table = $wpdb->prefix . 'pmpro_nbstup_districts';
	
	$result = $wpdb->insert(
		$table,
		array(
			'state_id' => intval( $state_id ),
			'name'     => sanitize_text_field( $name ),
		),
		array( '%d', '%s' )
	);
	
	return $result !== false;
}

/**
 * Update a district
 */
function pmpro_nbstup_update_district( $id, $state_id, $name ) {
	global $wpdb;
	$table = $wpdb->prefix . 'pmpro_nbstup_districts';
	
	$result = $wpdb->update(
		$table,
		array(
			'state_id' => intval( $state_id ),
			'name'     => sanitize_text_field( $name ),
		),
		array( 'id' => $id ),
		array( '%d', '%s' ),
		array( '%d' )
	);
	
	return $result !== false;
}

/**
 * Delete a district (and cascade delete blocks)
 */
function pmpro_nbstup_delete_district( $id ) {
	global $wpdb;
	
	// Delete all blocks of this district
	$table_blocks = $wpdb->prefix . 'pmpro_nbstup_blocks';
	$wpdb->delete( $table_blocks, array( 'district_id' => $id ), array( '%d' ) );
	
	// Delete the district
	$table_districts = $wpdb->prefix . 'pmpro_nbstup_districts';
	return $wpdb->delete( $table_districts, array( 'id' => $id ), array( '%d' ) ) !== false;
}

// ========== BLOCK FUNCTIONS ==========

/**
 * Get all blocks (optionally by district)
 */
function pmpro_nbstup_get_blocks( $district_id = null ) {
	global $wpdb;
	$table = $wpdb->prefix . 'pmpro_nbstup_blocks';
	
	if ( $district_id ) {
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE district_id = %d ORDER BY name ASC", $district_id ) );
	}
	
	return $wpdb->get_results( "SELECT * FROM $table ORDER BY name ASC" );
}

/**
 * Get block by ID
 */
function pmpro_nbstup_get_block( $id ) {
	global $wpdb;
	$table = $wpdb->prefix . 'pmpro_nbstup_blocks';
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) );
}

/**
 * Get block name by ID
 */
function pmpro_nbstup_get_block_name( $id ) {
	$block = pmpro_nbstup_get_block( $id );
	return $block ? $block->name : '';
}

/**
 * Add a new block
 */
function pmpro_nbstup_add_block( $district_id, $name ) {
	global $wpdb;
	$table = $wpdb->prefix . 'pmpro_nbstup_blocks';
	
	$result = $wpdb->insert(
		$table,
		array(
			'district_id' => intval( $district_id ),
			'name'        => sanitize_text_field( $name ),
		),
		array( '%d', '%s' )
	);
	
	return $result !== false;
}

/**
 * Update a block
 */
function pmpro_nbstup_update_block( $id, $district_id, $name ) {
	global $wpdb;
	$table = $wpdb->prefix . 'pmpro_nbstup_blocks';
	
	$result = $wpdb->update(
		$table,
		array(
			'district_id' => intval( $district_id ),
			'name'        => sanitize_text_field( $name ),
		),
		array( 'id' => $id ),
		array( '%d', '%s' ),
		array( '%d' )
	);
	
	return $result !== false;
}

/**
 * Delete a block
 */
function pmpro_nbstup_delete_block( $id ) {
	global $wpdb;
	$table = $wpdb->prefix . 'pmpro_nbstup_blocks';
	return $wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) ) !== false;
}

// ========== AJAX HANDLERS ==========

/**
 * AJAX: Load districts by state
 */
add_action( 'wp_ajax_pmpro_nbstup_get_districts', 'pmpro_nbstup_ajax_get_districts' );
add_action( 'wp_ajax_nopriv_pmpro_nbstup_get_districts', 'pmpro_nbstup_ajax_get_districts' );
function pmpro_nbstup_ajax_get_districts() {
	$state_id = isset( $_POST['state_id'] ) ? intval( $_POST['state_id'] ) : 0;
	
	if ( ! $state_id ) {
		wp_send_json_error( array( 'message' => 'Invalid state ID' ) );
	}
	
	$districts = pmpro_nbstup_get_districts( $state_id );
	wp_send_json_success( $districts );
}

/**
 * AJAX: Load blocks by district
 */
add_action( 'wp_ajax_pmpro_nbstup_get_blocks', 'pmpro_nbstup_ajax_get_blocks' );
add_action( 'wp_ajax_nopriv_pmpro_nbstup_get_blocks', 'pmpro_nbstup_ajax_get_blocks' );
function pmpro_nbstup_ajax_get_blocks() {
	$district_id = isset( $_POST['district_id'] ) ? intval( $_POST['district_id'] ) : 0;
	
	if ( ! $district_id ) {
		wp_send_json_error( array( 'message' => 'Invalid district ID' ) );
	}
	
	$blocks = pmpro_nbstup_get_blocks( $district_id );
	wp_send_json_success( $blocks );
}

/**
 * Enqueue frontend scripts for cascading dropdowns
 */
add_action( 'wp_enqueue_scripts', 'pmpro_nbstup_enqueue_location_scripts' );
function pmpro_nbstup_enqueue_location_scripts() {
	// Only load on checkout page
	if ( ! function_exists( 'pmpro_is_checkout' ) || ! pmpro_is_checkout() ) {
		return;
	}
	
	wp_enqueue_script(
		'pmpro-nbstup-location',
		plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/frontend.js',
		array( 'jquery' ),
		'1.0.0',
		true
	);
	
	wp_localize_script(
		'pmpro-nbstup-location',
		'pmpro_nbstup_ajax',
		array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'pmpro_nbstup_ajax' ),
		)
	);
}
