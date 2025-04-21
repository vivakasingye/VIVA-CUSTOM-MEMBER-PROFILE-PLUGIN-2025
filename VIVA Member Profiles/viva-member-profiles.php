<?php
/*
Plugin Name: VIVA Member Profiles
Description: Create and display beautiful team member profiles with custom fields and categories. 
Shortcodes: 
- [viva_members_grid] - Display members in a responsive grid
- [viva_members_slider] - Display members in a slider/carousel
Version: 1.6.2
Author: KASINGYE VIVA
Text Domain: viva-member-profiles
Domain Path: /languages
*/

if (!defined('ABSPATH')) exit;

class VivaMemberProfiles {

    public function __construct() {
        // Initialize plugin on plugins_loaded hook
        add_action('plugins_loaded', array($this, 'init_plugin'));
    }

    public function init_plugin() {
        // Register custom post type and taxonomy
        add_action('init', array($this, 'register_custom_post_type'));
        add_action('init', array($this, 'register_member_taxonomy'));
        
        // Other hooks
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_member_data'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        
        // Register shortcodes
        add_shortcode('viva_members_grid', array($this, 'members_grid_shortcode'));
        add_shortcode('viva_members_slider', array($this, 'members_slider_shortcode'));
        
        // Load translations
        load_plugin_textdomain(
            'viva-member-profiles',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );
    }

    // Register custom post type
    public function register_custom_post_type() {
        $labels = array(
            'name' => __('Member Profiles', 'viva-member-profiles'),
            'singular_name' => __('Member Profile', 'viva-member-profiles'),
            'menu_name' => __('VIVA Members', 'viva-member-profiles'),
            'name_admin_bar' => __('Member Profile', 'viva-member-profiles'),
            'add_new' => __('Add New', 'viva-member-profiles'),
            'add_new_item' => __('Add New Member', 'viva-member-profiles'),
            'new_item' => __('New Member', 'viva-member-profiles'),
            'edit_item' => __('Edit Member', 'viva-member-profiles'),
            'view_item' => __('View Member', 'viva-member-profiles'),
            'all_items' => __('All Members', 'viva-member-profiles'),
            'search_items' => __('Search Members', 'viva-member-profiles'),
            'parent_item_colon' => __('Parent Members:', 'viva-member-profiles'),
            'not_found' => __('No members found.', 'viva-member-profiles'),
            'not_found_in_trash' => __('No members found in Trash.', 'viva-member-profiles')
        );

        $args = array(
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'member'),
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => 20,
            'menu_icon' => 'dashicons-groups',
            'supports' => array('title', 'thumbnail', 'editor'),
            'taxonomies' => array('member_category'),
            'show_in_rest' => true
        );

        register_post_type('viva_member', $args);
    }

    // Register member category taxonomy
    public function register_member_taxonomy() {
        $labels = array(
            'name' => __('Member Categories', 'viva-member-profiles'),
            'singular_name' => __('Member Category', 'viva-member-profiles'),
            'search_items' => __('Search Categories', 'viva-member-profiles'),
            'all_items' => __('All Categories', 'viva-member-profiles'),
            'parent_item' => __('Parent Category', 'viva-member-profiles'),
            'parent_item_colon' => __('Parent Category:', 'viva-member-profiles'),
            'edit_item' => __('Edit Category', 'viva-member-profiles'),
            'update_item' => __('Update Category', 'viva-member-profiles'),
            'add_new_item' => __('Add New Category', 'viva-member-profiles'),
            'new_item_name' => __('New Category Name', 'viva-member-profiles'),
            'menu_name' => __('Categories', 'viva-member-profiles')
        );

        $args = array(
            'hierarchical' => true,
            'labels' => $labels,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'member-category'),
            'show_in_rest' => true
        );

        register_taxonomy('member_category', array('viva_member'), $args);
    }

    // Add meta boxes
    public function add_meta_boxes() {
        add_meta_box(
            'viva_member_details',
            __('Member Details', 'viva-member-profiles'),
            array($this, 'member_details_callback'),
            'viva_member',
            'normal',
            'high'
        );
    }

    // Meta box callback
    public function member_details_callback($post) {
        wp_nonce_field('viva_member_save_data', 'viva_member_meta_nonce');

        $full_name = get_post_meta($post->ID, '_viva_full_name', true);
        $courses = get_post_meta($post->ID, '_viva_courses', true);
        $position = get_post_meta($post->ID, '_viva_position', true);

        echo '<div style="display: grid; grid-template-columns: max-content 1fr; grid-gap: 12px; align-items: center;">';
        echo '<label for="viva_full_name" style="font-weight: 600;">' . __('Full Names:', 'viva-member-profiles') . '</label>';
        echo '<input type="text" id="viva_full_name" name="viva_full_name" value="' . esc_attr($full_name) . '" style="padding: 8px; width: 100%;" />';
        echo '<label for="viva_courses" style="font-weight: 600;">' . __('Courses:', 'viva-member-profiles') . '</label>';
        echo '<input type="text" id="viva_courses" name="viva_courses" value="' . esc_attr($courses) . '" style="padding: 8px; width: 100%;" />';
        echo '<label for="viva_position" style="font-weight: 600;">' . __('Position:', 'viva-member-profiles') . '</label>';
        echo '<input type="text" id="viva_position" name="viva_position" value="' . esc_attr($position) . '" style="padding: 8px; width: 100%;" />';
        echo '</div>';
    }

    // Save meta data
    public function save_member_data($post_id) {
        if (!isset($_POST['viva_member_meta_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['viva_member_meta_nonce'], 'viva_member_save_data')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (isset($_POST['viva_full_name'])) {
            update_post_meta($post_id, '_viva_full_name', sanitize_text_field($_POST['viva_full_name']));
        }

        if (isset($_POST['viva_courses'])) {
            update_post_meta($post_id, '_viva_courses', sanitize_text_field($_POST['viva_courses']));
        }

        if (isset($_POST['viva_position'])) {
            update_post_meta($post_id, '_viva_position', sanitize_text_field($_POST['viva_position']));
        }
    }

    // Enqueue styles
    public function enqueue_styles() {
        wp_enqueue_style(
            'viva-member-styles',
            plugin_dir_url(__FILE__) . 'css/viva-member-styles.css',
            array(),
            '1.6.2'
        );
        
        // Add Google Fonts
        wp_enqueue_style('viva-member-fonts', 'https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap');
    }

    /**
     * Grid Display Shortcode
     * 
     * Usage: [viva_members_grid count="4" columns="3" category="leadership" show_filter="true"]
     * 
     * Parameters:
     * - count: Number of members to show (-1 for all)
     * - columns: Number of columns (2, 3, or 4)
     * - category: Slug of category to filter by
     * - show_filter: "true" to show category filter buttons
     */
    public function members_grid_shortcode($atts) {
        $atts = shortcode_atts(array(
            'count' => -1,
            'order' => 'ASC',
            'orderby' => 'menu_order',
            'category' => '',
            'columns' => 3,
            'show_filter' => 'true'
        ), $atts, 'viva_members_grid');

        // Get categories for filter
        $categories = get_terms(array(
            'taxonomy' => 'member_category',
            'hide_empty' => true,
        ));

        ob_start();
        
        // Category filter
        if ($atts['show_filter'] === 'true' && !empty($categories) && !$atts['category']) {
            echo '<div class="viva-filter">';
            echo '<a href="#" class="viva-filter-btn active" data-category="all">' . __('All Members', 'viva-member-profiles') . '</a>';
            foreach ($categories as $category) {
                echo '<a href="#" class="viva-filter-btn" data-category="' . esc_attr($category->slug) . '">' . esc_html($category->name) . '</a>';
            }
            echo '</div>';
            
            // Filter JavaScript
            echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                const filterLinks = document.querySelectorAll(".viva-filter-btn");
                const memberCards = document.querySelectorAll(".viva-card");
                
                filterLinks.forEach(link => {
                    link.addEventListener("click", function(e) {
                        e.preventDefault();
                        filterLinks.forEach(l => l.classList.remove("active"));
                        this.classList.add("active");
                        const category = this.dataset.category;
                        memberCards.forEach(card => {
                            card.style.display = (category === "all" || card.dataset.categories.includes(category)) ? "block" : "none";
                        });
                    });
                });
            });
            </script>';
        }

        // Query arguments
        $args = array(
            'post_type' => 'viva_member',
            'posts_per_page' => $atts['count'],
            'order' => $atts['order'],
            'orderby' => $atts['orderby']
        );

        if ($atts['category']) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'member_category',
                    'field' => 'slug',
                    'terms' => $atts['category']
                )
            );
        }

        $members = new WP_Query($args);

        if ($members->have_posts()) {
            echo '<div class="viva-grid viva-grid-' . esc_attr($atts['columns']) . '">';
            
            while ($members->have_posts()) {
                $members->the_post();
                $full_name = get_post_meta(get_the_ID(), '_viva_full_name', true);
                $courses = get_post_meta(get_the_ID(), '_viva_courses', true);
                $position = get_post_meta(get_the_ID(), '_viva_position', true);
                $member_categories = get_the_terms(get_the_ID(), 'member_category');
                $category_slugs = $member_categories ? implode(' ', wp_list_pluck($member_categories, 'slug')) : '';
                
                echo '<div class="viva-card" data-categories="' . esc_attr($category_slugs) . '">';
                
                if ($member_categories && !is_wp_error($member_categories)) {
                    echo '<div class="viva-badge">' . esc_html($member_categories[0]->name) . '</div>';
                }
                
                echo '<div class="viva-img-container">';
                echo wp_get_attachment_image(
                    get_post_thumbnail_id(),
                    'full',
                    false,
                    array(
                        'class' => 'viva-img',
                        'alt' => esc_attr($full_name ?: get_the_title()),
                        'loading' => 'eager'
                    )
                );
                echo '</div>';
                echo '<div class="viva-info">';
                echo '<h3 class="viva-title">' . esc_html($full_name ?: get_the_title()) . '</h3>';
                if ($position) echo '<div class="viva-role">' . esc_html($position) . '</div>';
                if ($courses) echo '<div class="viva-courses">' . esc_html($courses) . '</div>';
                echo '<div class="viva-description">' . get_the_content() . '</div>';
                echo '</div></div>';
            }
            
            echo '</div>';
            wp_reset_postdata();
        } else {
            echo '<p>' . __('No members found.', 'viva-member-profiles') . '</p>';
        }

        return ob_get_clean();
    }

    /**
     * Slider Display Shortcode
     * 
     * Usage: [viva_members_slider count="6" slides_to_show="4" category="team"]
     * 
     * Parameters:
     * - count: Number of members to show (-1 for all)
     * - slides_to_show: Number of slides visible at once
     * - category: Slug of category to filter by
     */
    public function members_slider_shortcode($atts) {
        $atts = shortcode_atts(array(
            'count' => -1,
            'order' => 'ASC',
            'orderby' => 'menu_order',
            'category' => '',
            'slides_to_show' => 3
        ), $atts, 'viva_members_slider');

        ob_start();
        
        // Enqueue Slick slider assets
        wp_enqueue_style('slick-slider', 'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css');
        wp_enqueue_style('slick-theme', 'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick-theme.css');
        wp_enqueue_script('slick-slider', 'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js', array('jquery'), '1.8.1', true);
        
        echo '<div class="viva-slider">';
        
        $args = array(
            'post_type' => 'viva_member',
            'posts_per_page' => $atts['count'],
            'order' => $atts['order'],
            'orderby' => $atts['orderby']
        );

        if ($atts['category']) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'member_category',
                    'field' => 'slug',
                    'terms' => $atts['category']
                )
            );
        }

        $members = new WP_Query($args);

        if ($members->have_posts()) {
            while ($members->have_posts()) {
                $members->the_post();
                $full_name = get_post_meta(get_the_ID(), '_viva_full_name', true);
                $courses = get_post_meta(get_the_ID(), '_viva_courses', true);
                $position = get_post_meta(get_the_ID(), '_viva_position', true);
                $member_categories = get_the_terms(get_the_ID(), 'member_category');
                
                echo '<div class="viva-slide">';
                echo '<div class="viva-card">';
                
                if ($member_categories && !is_wp_error($member_categories)) {
                    echo '<div class="viva-badge">' . esc_html($member_categories[0]->name) . '</div>';
                }
                
                echo '<div class="viva-img-container">';
                echo wp_get_attachment_image(
                    get_post_thumbnail_id(),
                    'full',
                    false,
                    array(
                        'class' => 'viva-img',
                        'alt' => esc_attr($full_name ?: get_the_title()),
                        'loading' => 'eager'
                    )
                );
                echo '</div>';
                echo '<div class="viva-info">';
                echo '<h3 class="viva-title">' . esc_html($full_name ?: get_the_title()) . '</h3>';
                if ($position) echo '<div class="viva-role">' . esc_html($position) . '</div>';
                if ($courses) echo '<div class="viva-courses">' . esc_html($courses) . '</div>';
                echo '</div></div></div>';
            }
        }
        
        echo '</div>';
        
        echo '<script>
        jQuery(document).ready(function($) {
            $(".viva-slider").slick({
                slidesToShow: ' . absint($atts['slides_to_show']) . ',
                slidesToScroll: 1,
                arrows: true,
                prevArrow: "<button type=\"button\" class=\"viva-prev\" aria-label=\"' . __('Previous', 'viva-member-profiles') . '\"></button>",
                nextArrow: "<button type=\"button\" class=\"viva-next\" aria-label=\"' . __('Next', 'viva-member-profiles') . '\"></button>",
                responsive: [
                    {
                        breakpoint: 1024,
                        settings: {
                            slidesToShow: 3
                        }
                    },
                    {
                        breakpoint: 768,
                        settings: {
                            slidesToShow: 2
                        }
                    },
                    {
                        breakpoint: 480,
                        settings: {
                            slidesToShow: 1
                        }
                    }
                ]
            });
        });
        </script>';
        
        wp_reset_postdata();
        return ob_get_clean();
    }
}

// Initialize the plugin
new VivaMemberProfiles();