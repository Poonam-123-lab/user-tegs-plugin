<?php
/**
 * Plugin Name: User Tags
 * Plugin URI: https://example.com/user-tags
 * Description: Allows categorizing users with custom taxonomies
 * Version: 1.0.0
 * Author: BR
 * Author URI: https://example.com
 * Text Domain: user-tags
 * Domain Path: /languages
 * License: GPL v2 or later
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class User_Tags {
    /**
     * The single instance of the class
     */
    private static $instance = null;

    /**
     * Main plugin instance
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        $this->define_constants();
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Define plugin constants
     */
    private function define_constants() {
        define('USER_TAGS_PLUGIN_DIR', plugin_dir_path(__FILE__));
        define('USER_TAGS_PLUGIN_URL', plugin_dir_url(__FILE__));
        define('USER_TAGS_VERSION', '1.0.0');
    }

    /**
     * Include required files
     */
    private function includes() {
        // No includes needed for now
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register taxonomy
        add_action('init', array($this, 'register_taxonomy'));
        
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Add user profile fields
        add_action('show_user_profile', array($this, 'add_user_taxonomy_fields'));
        add_action('edit_user_profile', array($this, 'add_user_taxonomy_fields'));
        
        // Save user profile fields
        add_action('personal_options_update', array($this, 'save_user_taxonomy_fields'));
        add_action('edit_user_profile_update', array($this, 'save_user_taxonomy_fields'));
        
        // Add filter dropdown to users list
        add_action('restrict_manage_users', array($this, 'add_user_filter_dropdown'));
        
        // Filter users by taxonomy
        add_filter('pre_get_users', array($this, 'filter_users_by_taxonomy'));
        
        // Enqueue scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // AJAX handler for user tags search
        add_action('wp_ajax_search_user_tags', array($this, 'ajax_search_user_tags'));
        
        
        // Add User Tags column to users table
    add_filter('manage_users_columns', array($this, 'add_user_tags_column'));
    add_filter('manage_users_custom_column', array($this, 'display_user_tags_column_content'), 10, 3);
    add_filter('manage_users_sortable_columns', array($this, 'make_user_tags_column_sortable'));

    }

    /**
     * Register the User Tags taxonomy
     */
    public function register_taxonomy() {
        $labels = array(
            'name'              => _x('User Tags', 'taxonomy general name', 'user-tags'),
            'singular_name'     => _x('User Tag', 'taxonomy singular name', 'user-tags'),
            'search_items'      => __('Search User Tags', 'user-tags'),
            'all_items'         => __('All User Tags', 'user-tags'),
            'parent_item'       => __('Parent User Tag', 'user-tags'),
            'parent_item_colon' => __('Parent User Tag:', 'user-tags'),
            'edit_item'         => __('Edit User Tag', 'user-tags'),
            'update_item'       => __('Update User Tag', 'user-tags'),
            'add_new_item'      => __('Add New User Tag', 'user-tags'),
            'new_item_name'     => __('New User Tag Name', 'user-tags'),
            'menu_name'         => __('User Tags', 'user-tags'),
        );

        $args = array(
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => false,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'user-tag'),
        );

        register_taxonomy('user_tag', null, $args);
    }

    /**
     * Add User Tags submenu under Users
     */
    public function add_admin_menu() {
        add_users_page(
            __('User Tags', 'user-tags'),
            __('User Tags', 'user-tags'),
            'manage_options',
            'edit-tags.php?taxonomy=user_tag'
        );
    }

    /**
     * Add taxonomy fields to user profile
     */
    public function add_user_taxonomy_fields($user) {
        $tax = get_taxonomy('user_tag');
        
        if (!current_user_can($tax->cap->assign_terms)) {
            return;
        }
        
        $user_tags = wp_get_object_terms($user->ID, 'user_tag', array('fields' => 'ids'));
        $user_tags = is_wp_error($user_tags) ? array() : $user_tags;
        ?>
        <h3><?php _e('User Tags', 'user-tags'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="user_tags"><?php _e('Select User Tags', 'user-tags'); ?></label></th>
                <td>
                    <select name="user_tags[]" id="user_tags" class="user-tags-select" multiple="multiple" style="width: 100%;">
                        <?php
                        $all_tags = get_terms(array(
                            'taxonomy' => 'user_tag',
                            'hide_empty' => false,
                        ));
                        
                        if (!is_wp_error($all_tags)) {
                            foreach ($all_tags as $tag) {
                                $selected = in_array($tag->term_id, $user_tags) ? 'selected="selected"' : '';
                                echo '<option value="' . esc_attr($tag->term_id) . '" ' . $selected . '>' . esc_html($tag->name) . '</option>';
                            }
                        }
                        ?>
                    </select>
                    <p class="description"><?php _e('Select tags to assign to this user.', 'user-tags'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save user taxonomy fields
     */

public function save_user_taxonomy_fields($user_id) {
    $tax = get_taxonomy('user_tag');
    
    if (!current_user_can($tax->cap->assign_terms) || !current_user_can('edit_user', $user_id)) {
        return;
    }
    
    $user_tags = isset($_POST['user_tags']) ? array_map('intval', $_POST['user_tags']) : array();
    
    // Delete all existing terms for this user
    wp_delete_object_term_relationships($user_id, 'user_tag');
    
    // Add selected terms
    if (!empty($user_tags)) {
        wp_set_object_terms($user_id, $user_tags, 'user_tag');
    }
}

/**
 * Add filter dropdown to users list
 */

public function add_user_filter_dropdown() {
    global $pagenow;
    
    if (!is_admin() || $pagenow !== 'users.php') {
        return;
    }
    
    $selected = isset($_GET['user_tag']) ? intval($_GET['user_tag']) : 0;
    ?>
    <form method="get">
        <!-- Keep any existing query parameters -->
        <?php foreach ($_GET as $key => $value) : ?>
            <?php if ($key !== 'user_tag' && $key !== 'paged') : ?>
                <input type="hidden" name="<?php echo esc_attr($key); ?>" value="<?php echo esc_attr($value); ?>">
            <?php endif; ?>
        <?php endforeach; ?>
        
        <label class="screen-reader-text" for="user_tag_filter"><?php _e('Filter by User Tag', 'user-tags'); ?></label>
        <select name="user_tag" id="user_tag_filter" class="user-tags-filter-select">
            <option value="0" <?php selected($selected, 0); ?>><?php _e('All User Tags', 'user-tags'); ?></option>
            <?php
            $tags = get_terms(array(
                'taxonomy' => 'user_tag',
                'hide_empty' => false,
            ));
            
            if (!is_wp_error($tags)) {
                foreach ($tags as $tag) {
                    ?>
                    <option value="<?php echo esc_attr($tag->term_id); ?>" <?php selected($selected, $tag->term_id); ?>>
                        <?php echo esc_html($tag->name); ?>
                    </option>
                    <?php
                }
            }
            ?>
        </select>
        <input type="submit" class="button" value="Filter">
    </form>
    <?php
}


public function filter_users_by_taxonomy($query) {
    global $pagenow;
    
    // Only run on the users.php admin page
    if (!is_admin() || $pagenow !== 'users.php') {
        return;
    }
    
    // Check if we're filtering by user tag
    if (!isset($_GET['user_tag']) || $_GET['user_tag'] == '0') {
        return;
    }
    
    $tag_id = intval($_GET['user_tag']);
    
    if ($tag_id <= 0) {
        return;
    }
    
    // Get users with this tag
    $users_with_tag = $this->get_users_by_tag($tag_id);
    
    // Debug output
    error_log('Filter applied: User Tag ID ' . $tag_id . ' - Found ' . count($users_with_tag) . ' users');
    
    if (empty($users_with_tag)) {
        // If no users have this tag, set impossible condition to return no results
        $query->set('include', array(0));
    } else {
        $query->set('include', $users_with_tag);
    }
}

/**
 * Add User Tags column to users table
 */
public function add_user_tags_column($columns) {
    $columns['user_tags'] = __('User Tags', 'user-tags');
    return $columns;
}

/**
 * Display User Tags in the column
 */
public function display_user_tags_column_content($value, $column_name, $user_id) {
    if ($column_name !== 'user_tags') {
        return $value;
    }
    
    $user_tags = wp_get_object_terms($user_id, 'user_tag', array('fields' => 'names'));
    
    if (is_wp_error($user_tags) || empty($user_tags)) {
        return '—';
    }
    
    return implode(', ', $user_tags);
}

/**
 * Make User Tags column sortable
 */
public function make_user_tags_column_sortable($columns) {
    $columns['user_tags'] = 'user_tags';
    return $columns;
}

/**
 * Get users by tag ID 
 */
private function get_users_by_tag($tag_id) {
    global $wpdb;
    
    // Get the term_taxonomy_id for this tag
    $term_taxonomy_id = $wpdb->get_var($wpdb->prepare(
        "SELECT tt.term_taxonomy_id FROM {$wpdb->term_taxonomy} tt 
         WHERE tt.taxonomy = 'user_tag' AND tt.term_id = %d",
        $tag_id
    ));
    
    if (empty($term_taxonomy_id)) {
        return array();
    }
    
    // Get users with this term_taxonomy_id
    $user_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT tr.object_id FROM {$wpdb->term_relationships} tr
         WHERE tr.term_taxonomy_id = %d",
        $term_taxonomy_id
    ));
    
    return $user_ids;
}

    /**
     * Enqueue scripts
     */
    public function enqueue_scripts($hook) {
        if (in_array($hook, array('profile.php', 'user-edit.php', 'users.php'))) {
            // Enqueue Select2 CSS
            wp_enqueue_style(
                'select2',
                'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css',
                array(),
                '4.0.13'
            );
            
            // Enqueue Select2 JS
            wp_enqueue_script(
                'select2',
                'https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js',
                array('jquery'),
                '4.0.13',
                true
            );
            
            // Enqueue our custom JS
            wp_enqueue_script(
                'user-tags-js',
                USER_TAGS_PLUGIN_URL . 'assets/js/user-tags.js',
                array('jquery', 'select2'),
                USER_TAGS_VERSION,
                true
            );
            
            // Add script data
            wp_localize_script('user-tags-js', 'userTagsParams', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('user-tags-ajax-nonce'),
            ));
        }
    }

    /**
     * AJAX handler for user tags search
     */
    public function ajax_search_user_tags() {
        check_ajax_referer('user-tags-ajax-nonce', 'nonce');
        
        $search_term = isset($_GET['term']) ? sanitize_text_field($_GET['term']) : '';
        
        $terms = get_terms(array(
            'taxonomy' => 'user_tag',
            'hide_empty' => false,
            'search' => $search_term,
        ));
        
        $results = array();
        
        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $results[] = array(
                    'id' => $term->term_id,
                    'text' => $term->name,
                );
            }
        }
        
        wp_send_json($results);
    }
}

// Initialize the plugin
function user_tags_plugin() {
    return User_Tags::instance();
}

// Start the plugin
user_tags_plugin();




// Disable WordPress.org updates for this plugin
add_filter('site_transient_update_plugins', function($transient) {
    if (isset($transient->response['user-tags/user-tags.php'])) {
        unset($transient->response['user-tags/user-tags.php']);
    }
    return $transient;
});

// Remove the update notification
add_action('admin_init', function() {
    remove_action('admin_notices', 'update_nag', 3);
});



// Clear update transients on plugin activation
register_activation_hook(__FILE__, function() {
    delete_site_transient('update_plugins');
    delete_site_transient('update_themes');
    delete_site_transient('update_core');
});



