<?php
/*
Plugin Name: WordPress Post Expire
Description: Moves posts to an 'expired' category when it reaches the expiry date.
Author: Archie Makuwa
Version: 1.0.0
*/


/**
 * Create an 'expired' category on activiation of plugin if it doesn't exist
 */
function wp_post_expire_init () {

    if (!term_exists('expired')){
         wp_create_category( 'expired' );
    }

}
register_activation_hook( __FILE__, 'wp_post_expire_init' );


/**
 * Enqueue's DateTime scripts for post expiry.
 */
function wp_post_expire_enqueue_scripts(){
    wp_enqueue_script( 'jquery-ui-datepicker' );
    wp_register_script( 'jquery-ui-timepicker-addon', plugins_url( 'js/jquery-ui-timepicker-addon.min.js' , __FILE__ ), array('jquery-ui-datepicker'), '1.3', true );
    wp_register_script( 'wp-post-expire-script', plugins_url( 'js/wp-post-expire-script.js' , __FILE__ ), array('jquery-ui-timepicker-addon'), '1.3', true );
    wp_enqueue_script( 'wp-post-expire-script' );
}
add_action('admin_enqueue_scripts', 'wp_post_expire_enqueue_scripts', 10);



function wp_post_expire_enqueue_style () {
    wp_enqueue_style( 'jquery-ui-css',  plugins_url('css/jquery-ui.min.css' , __FILE__ ), array(), false, 'all' );
}
// Add the action to the wp_head
add_action('admin_enqueue_scripts', 'wp_post_expire_enqueue_style', 10);


/**
 * Returns an array of valid meta keys and their default values, if any
 *
 * @author Archie22is
 *
 * @since 1.0.0
 *
 * @return array
 */
function wp_post_expire_valid_meta_keys_and_defaults(){
    return array(
        '_post_expiry_date'      => '',
    );
}


/**
 * Registers the Post Expiry meta box to be displayed on the post editor screen.
 *
 * @author Archie22is
 *
 * @since 1.0.0
 *
 * @uses add_meta_box
 *
 * @return void
 */
function wp_post_expire_add_meta_boxes() {

    add_meta_box(
        'wp-post-expire',
        __( 'Post Expiry', 'post-expiry' ),
        'wp_post_expire_meta_box',
        'post',
        'side',
        'default'
    );
}


/**
 * Creates a pre-populated input field
 *
 * @author Archie22is
 *
 * @since 1.0.0
 *
 * @uses get_the_ID
 * @uses wp_parse_args
 * @uses get_post_meta
 * @uses apply_filters
 *
 * @param array $args An array containing the `id`, `meta_key`, `label` and `class`
 *
 * @return string $output which can be filtered using `get_post_expiry_metabox_input_field`
 */
function get_post_expiry_metabox_input_field( $args = null ){
    $defaults = array(
        'id' => get_the_ID(),
        'meta_key' => '',
        'label' => '',
        'class' => '',
    );

    $datepicker_classes = array(
        'datetimepicker',
        'datepicker',
        'timepicker',
    );

    $args = wp_parse_args( $args, $defaults );

    extract($args);

    if ( empty($id) || empty($meta_key) ){
        return;
    }

    $value = get_post_meta($id, $meta_key, true);

    // process if datepicker
    if (in_array( $class, $datepicker_classes )){
        date_default_timezone_set('Africa/Johannesburg');
        $value = ( !empty( $value ) ? date ('j F Y H:i', $value) : $default );
    }

    $output = sprintf(
        '<input type="text" name="%1$s" id="%1$s" value="%2$s" class="%3$s" />',
        $meta_key,
        $value,
        $class
    );

    if ( $label !== '' ){
        $output = sprintf(
            '<label for="%1$s">%2$s</label>%3$s',
            $meta_key,
            $label,
            $output
        );
    }

    return apply_filters( 'get_post_expiry_metabox_input_field', $output, $args, $value );

}


/**
 * Metabox callback for post expiry settings
 *
 * @author Archie22is
 *
 * @since 1.0.0
 *
 * @uses wp_nonce_field
 * @uses wp_post_expire_valid_meta_keys_and_defaults
 *
 * @param object $post Context on which edit page this metabox is being applied
 * @param array $box Parameters for this metabox
 *
 * @return void
 */
function wp_post_expire_meta_box( $post, $box ) {

    $defaults = wp_post_expire_valid_meta_keys_and_defaults();

    wp_nonce_field( basename( __FILE__ ), '_wp_post_expire_meta_box_nonce' );

    echo '<div class="expiry-date cf">';
    echo '<div class="left">Expiry Date</div>';
    echo '<div class="right">';

    echo get_post_expiry_metabox_input_field(
            array(
                'meta_key' => '_post_expiry_date',
                'default' => $defaults['_post_expiry_date'],
                'class' => 'datetimepicker',
            )
        );

    echo '</div>';
    echo '</div>';

}


// Meta box setup function.
function wp_post_expire_meta_boxes_setup() {

    // Add meta boxes on the 'add_meta_boxes' hook.
    add_action( 'add_meta_boxes', 'wp_post_expire_add_meta_boxes' );

    // Save post meta on the 'save_post' hook.
    add_action( 'save_post', 'wp_post_expire_save_meta', 10, 2 );
}

// Fire our meta box setup function on the post editor screen.
add_action( 'load-post.php', 'wp_post_expire_meta_boxes_setup' );
add_action( 'load-post-new.php', 'wp_post_expire_meta_boxes_setup' );


// Save the meta box's post metadata.
function wp_post_expire_save_meta( $post_id, $post ) {

    // Verify the nonce before proceeding.
    if ( !isset( $_POST['_wp_post_expire_meta_box_nonce'] ) || !wp_verify_nonce( $_POST['_wp_post_expire_meta_box_nonce'], basename( __FILE__ ) ) )
        return $post_id;

    // Get the post type object.
    $post_type = get_post_type_object( $post->post_type );

    // Check if the current user has permission to edit the post.
    if ( !current_user_can( $post_type->cap->edit_post, $post_id ) )
        return $post_id;

    // An array of all the meta data to save and their defaults
    $meta_fields = wp_post_expire_valid_meta_keys_and_defaults();

    // Loop through the meta data and update accordingly
    foreach ($meta_fields as  $meta_key => $meta_default){

        if ($meta_key == '_post_expiry_date'){
            date_default_timezone_set('Africa/Johannesburg');
            $new_meta_value = ( isset( $_POST[$meta_key] ) ? htmlentities(strtotime($_POST[$meta_key]), ENT_QUOTES) : '' );
        } else {
            // Get the posted data and sanitize it for use as an HTML class.
            $new_meta_value = ( isset( $_POST[$meta_key] ) ? htmlentities($_POST[$meta_key], ENT_QUOTES) : '' );
        }
        wp_post_expire_update_meta( $post_id, $meta_key, $new_meta_value, $meta_default );

    }
}


function wp_schedule_event_post_expiry($post_ID) {
    // get expired category ID
    $expired_cat_id = get_cat_ID( 'expired' );
    // on event, assign expired category for this post
    wp_set_post_categories( $post_ID, array($expired_cat_id), true );
}
add_action( 'wp_schedule_event','wp_schedule_event_post_expiry', 10, 1 );


function wp_post_expire_update_meta( $post_id, $meta_key, $new_meta_value, $meta_default = '' ){
    $parent_id = wp_is_post_revision( $post_id );
    if ($parent_id) {
        $post_id = $parent_id;
    }
    // Get the meta value of the custom field key.
    $meta_value = get_post_meta( $post_id, $meta_key, true );

    // If a new meta value was added and there was no previous value, add it.
    if ( $new_meta_value && '' == $meta_value ){
        add_post_meta( $post_id, $meta_key, $new_meta_value, true );
        // schedule event

        wp_schedule_single_event( $new_meta_value, 'wp_schedule_event', array($post_id) );
    }
    // If the new meta value does not match the old value, update it.
    elseif ( $new_meta_value && $new_meta_value != $meta_value ){
        update_post_meta( $post_id, $meta_key, $new_meta_value );
        // reschedule event
        $original_timestamp = $meta_value;
        wp_unschedule_event( $original_timestamp, 'wp_schedule_event', array($post_id) );

        reschedule_event($new_meta_value, $post_id);
    }
    // If there is no new meta value but an old value exists, delete it.
    elseif ( $meta_default == $new_meta_value && $meta_value ){
        delete_post_meta( $post_id, $meta_key, $meta_value );
        // unschedule event
        $original_timestamp = $meta_value;
        wp_unschedule_event( $original_timestamp, 'wp_schedule_event', array($post_id) );
    }
}


function reschedule_event($new_meta_value, $post_id){
    wp_schedule_single_event( $new_meta_value, 'wp_schedule_event', array($post_id) );
}


/**
 * Excludes 'expired' category from main wp query
 *
 * @author Archie22is
 *
 * @since 1.0.0
 *
 * @return void
 */
function exclude_category( $query ) {
    // don't modify on admin
    if ( is_admin() ){
        return $query;
    }
    if ( !is_singular() || (is_singular() && !$query->is_main_query()) ) {
        // get expired category ID
        $expired_cat_id = get_cat_ID( 'expired' );
        $query->set( 'cat', '-'.$expired_cat_id );
    }
}
add_action( 'pre_get_posts', 'exclude_category' );
