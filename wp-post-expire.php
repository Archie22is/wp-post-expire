<?php
/*
Plugin Name: WordPress Post Expire
Description: Moves posts to an 'expired' category when it reaches the expiry date.
Author: Archie Makuwa
Version: 1.1.0
*/

// Register activation hook to create 'expired' category
function wp_post_expire_init() {
    if (!term_exists('expired')) {
        wp_create_category('expired');
    }
}
register_activation_hook(__FILE__, 'wp_post_expire_init');

// Enqueue necessary scripts and styles
function wp_post_expire_enqueue_assets() {
    wp_enqueue_script('jquery-ui-datepicker');
    wp_register_script('jquery-ui-timepicker-addon', plugins_url('js/jquery-ui-timepicker-addon.min.js', __FILE__), array('jquery-ui-datepicker'), '1.6.3', true);
    wp_register_script('wp-post-expire-script', plugins_url('js/wp-post-expire-script.js', __FILE__), array('jquery-ui-timepicker-addon'), '1.0.1', true);
    wp_enqueue_script('wp-post-expire-script');
    wp_enqueue_style('jquery-ui-css', plugins_url('css/jquery-ui.min.css', __FILE__), array(), false, 'all');
}
add_action('admin_enqueue_scripts', 'wp_post_expire_enqueue_assets');

// Get valid meta keys and their default values
function wp_post_expire_valid_meta_keys_and_defaults() {
    return array('_post_expiry_date' => '');
}

// Register meta box for post expiry
function wp_post_expire_add_meta_boxes() {
    add_meta_box(
        'wp-post-expire',
        __('Post Expiry', 'post-expiry'),
        'wp_post_expire_meta_box',
        'post',
        'side',
        'default'
    );
}

// Get input field for meta box
function get_post_expiry_metabox_input_field($args = null) {
    $defaults = array(
        'id' => get_the_ID(),
        'meta_key' => '',
        'label' => '',
        'class' => '',
    );

    $datepicker_classes = array('datetimepicker', 'datepicker', 'timepicker');
    $args = wp_parse_args($args, $defaults);
    extract($args);

    if (empty($id) || empty($meta_key)) {
        return;
    }

    $value = get_post_meta($id, $meta_key, true);
    if (in_array($class, $datepicker_classes)) {
        date_default_timezone_set('Africa/Johannesburg');
        $value = (!empty($value) ? date('j F Y H:i', $value) : $default);
    }

    $output = sprintf('<input type="text" name="%1$s" id="%1$s" value="%2$s" class="%3$s" />', $meta_key, $value, $class);
    if ($label !== '') {
        $output = sprintf('<label for="%1$s">%2$s</label>%3$s', $meta_key, $label, $output);
    }

    return apply_filters('get_post_expiry_metabox_input_field', $output, $args, $value);
}

// Meta box callback for post expiry settings
function wp_post_expire_meta_box($post, $box) {
    $defaults = wp_post_expire_valid_meta_keys_and_defaults();
    wp_nonce_field(basename(__FILE__), '_wp_post_expire_meta_box_nonce');

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

// Meta box setup function
function wp_post_expire_meta_boxes_setup() {
    add_action('add_meta_boxes', 'wp_post_expire_add_meta_boxes');
    add_action('save_post', 'wp_post_expire_save_meta', 10, 2);
}
add_action('load-post.php', 'wp_post_expire_meta_boxes_setup');
add_action('load-post-new.php', 'wp_post_expire_meta_boxes_setup');

// Save post meta data
function wp_post_expire_save_meta($post_id, $post) {
    if (!isset($_POST['_wp_post_expire_meta_box_nonce']) || !wp_verify_nonce($_POST['_wp_post_expire_meta_box_nonce'], basename(__FILE__))) {
        return $post_id;
    }

    $post_type = get_post_type_object($post->post_type);
    if (!current_user_can($post_type->cap->edit_post, $post_id)) {
        return $post_id;
    }

    $meta_fields = wp_post_expire_valid_meta_keys_and_defaults();
    foreach ($meta_fields as $meta_key => $meta_default) {
        $new_meta_value = isset($_POST[$meta_key]) ? htmlentities(strtotime($_POST[$meta_key]), ENT_QUOTES) : '';
        wp_post_expire_update_meta($post_id, $meta_key, $new_meta_value, $meta_default);
    }
}

// Update post meta data
function wp_post_expire_update_meta($post_id, $meta_key, $new_meta_value, $meta_default = '') {
    $parent_id = wp_is_post_revision($post_id);
    if ($parent_id) {
        $post_id = $parent_id;
    }

    $meta_value = get_post_meta($post_id, $meta_key, true);
    if ($new_meta_value && '' == $meta_value) {
        add_post_meta($post_id, $meta_key, $new_meta_value, true);
        wp_schedule_single_event($new_meta_value, 'wp_schedule_event', array($post_id));
    } elseif ($new_meta_value && $new_meta_value != $meta_value) {
        update_post_meta($post_id, $meta_key, $new_meta_value);
        wp_unschedule_event($meta_value, 'wp_schedule_event', array($post_id));
        wp_schedule_single_event($new_meta_value, 'wp_schedule_event', array($post_id));
    } elseif ($meta_default == $new_meta_value && $meta_value) {
        delete_post_meta($post_id, $meta_key, $meta_value);
        wp_unschedule_event($meta_value, 'wp_schedule_event', array($post_id));
    }
}

// Schedule event to move post to 'expired' category
function wp_schedule_event_post_expiry($post_ID) {
    $expired_cat_id = get_cat_ID('expired');
    wp_set_post_categories($post_ID, array($expired_cat_id), true);
}
add_action('wp_schedule_event', 'wp_schedule_event_post_expiry', 10, 1);

// Exclude 'expired' category from main query
function exclude_category($query) {
    if (is_admin() || !$query->is_main_query()) {
        return $query;
    }
    $expired_cat_id = get_cat_ID('expired');
    $query->set('cat', '-' . $expired_cat_id);
}
add_action('pre_get_posts', 'exclude_category');
