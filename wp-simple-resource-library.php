<?php
/*
Plugin Name: WP Simple Resource Library
Description: This plugin creates a custom post type named 'Resource'.
Version: 1.0
Author: Andrew Drake <andrew@drake.nz>
License: MIT License
*/

function create_resource_post_type()
{
	$labels = array(
		'name' => __('Resources'),
		'singular_name' => __('Resource')
	);

	$args = array(
		'labels' => $labels,
		'public' => true,
		'has_archive' => true,
		'rewrite' => array('slug' => 'resources'),
		'show_in_rest' => true,
		'supports' => array('title', 'thumbnail', 'excerpt', 'categories'), //'editor',
		'taxonomies' => array('category'),

		'hierarchical' => false,
        'public' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_position' => 5,
        'show_in_admin_bar' => true,
        'show_in_nav_menus' => true,
        'can_export' => true,
        'has_archive' => true,
        'exclude_from_search' => false,
        'publicly_queryable' => true,
        'capability_type' => 'post',
	);

	register_post_type('resource', $args);
}

add_action('init', 'create_resource_post_type');

function add_resource_meta_boxes()
{
	//make this a box in the middle, but lower than Excerpt

	add_meta_box("resource_details_meta", "Resource details", "resource_details_meta_box_markup", "resource", "normal", "high", null);
	add_meta_box("audit_log_meta", "Audit Log", "audit_log_meta_box_markup", "resource", "normal", "low", null);
}

add_action("add_meta_boxes", "add_resource_meta_boxes");

function resource_details_meta_box_markup($object)
{
	wp_nonce_field(basename(__FILE__), "resource-details-nonce");

?>
	<label for="resource-url">Resource URL</label><br />
	<input name="resource-url" type="text" class="large-text" value="<?php echo esc_html(get_post_meta($object->ID, "resource-url", true)); ?>">
	<br />
	<br />

<?php
}

function audit_log_meta_box_markup($object)
{
    wp_nonce_field(basename(__FILE__), "audit-log-nonce");

?>
    <label for="dated">First Published</label><br />
    <input name="created" type="date" value="<?php echo esc_html(get_post_meta($object->ID, "created", true)); ?>">
    <br />
    <br />

    <label for="last-updated">Last Updated</label><br />
    <input name="last-updated" type="date" value="<?php echo esc_html(get_post_meta($object->ID, "last-updated", true)); ?>">
    <br />
    <br />

    <label for="last-review-date">Last Date of Review</label><br />
    <input name="last-review-date" type="date" value="<?php echo esc_html(get_post_meta($object->ID, "last-review-date", true)); ?>">
    <br />
    <br />

    <label for="next-review-date">Next Date of Review</label><br />
    <input name="next-review-date" type="date" value="<?php echo esc_html(get_post_meta($object->ID, "next-review-date", true)); ?>">
    <br />
    <br />

    <label for="repealed-policies">Changelog</label><br />
    <textarea name="changes" rows="5" class="large-text"><?php echo esc_html(get_post_meta($object->ID, "changelog", true)); ?></textarea><br />
    eg. major changes, repealed policies, etc.
    <br />
    <br />
<?php
}

function save_resource_metadata($post_id, $post, $update)
{
	$meta_boxes = array(
		'resource-details' => array('resource-url'),
		'audit-log' => array('created', 'last-updated', 'last-review-date', 'next-review-date', 'changelog'),
	);

	if (!current_user_can("edit_post", $post_id))
		return $post_id;

	if (defined("DOING_AUTOSAVE") && DOING_AUTOSAVE)
		return $post_id;

	foreach ($meta_boxes as $nonce => $fields) {
		if (!isset($_POST["{$nonce}-nonce"]) || !wp_verify_nonce($_POST["{$nonce}-nonce"], basename(__FILE__)))
			return $post_id;

		foreach ($fields as $field) {

			$slug = "resource";
			if ($slug != $post->post_type)
				return $post_id;

			$meta_value = "";

			if (isset($_POST[$field])) {
				$meta_value = $_POST[$field];
			}
			update_post_meta($post_id, $field, $meta_value);
		}
	}
}

add_action("save_post", "save_resource_metadata", 10, 3);

function display_resources()
{
	$args = array(
		'post_type' => 'resource',
		'posts_per_page' => -1, // get all posts
	);

	$the_query = new WP_Query($args);
	$output = '';

	if ($the_query->have_posts()) {
		while ($the_query->have_posts()) {
			$the_query->the_post();
			$output .= '<h2>' . get_the_title() . '</h2>';
			$output .= '<p>Date: ' . get_the_date() . '</p>'; // Output the date
			$output .= '<p>' . get_the_content() . '</p>';
			$output .= '<p>Resource URL: ' . esc_html(get_post_meta(get_the_ID(), 'resource-url', true)) . '</p>';
		}
		/* Restore original Post Data */
		wp_reset_postdata();
	} else {
		$output = '<p>No resources found.</p>';
	}

	return $output;
}

function display_resources_shortcode($atts)
{
	return display_resources();
}

add_shortcode('display_resources', 'display_resources_shortcode');


function display_resource_shortcode() {
    ob_start();

    $args = array(
        'post_type' => 'resource',
        'posts_per_page' => -1
    );
    $query = new WP_Query($args);

    while ( $query->have_posts() ) : $query->the_post(); ?>

        <h1><?php the_title(); ?></h1>
        <p>Categories: <?php echo get_the_category_list(', '); ?></p>

        <?php the_excerpt(); ?>

        <a href="<?php echo esc_html(get_post_meta(get_the_ID(), 'resource-url', true)); ?>" target="_blank">Open Resource</a>

        <?php
        $created = get_post_meta(get_the_ID(), 'created', true);
        $last_updated = get_post_meta(get_the_ID(), 'last-updated', true);
        $last_review_date = get_post_meta(get_the_ID(), 'last-review-date', true);
        $next_review_date = get_post_meta(get_the_ID(), 'next-review-date', true);
        $changelog = get_post_meta(get_the_ID(), 'changelog', true);

        if (!empty($created) || !empty($last_updated) || !empty($last_review_date) || !empty($next_review_date) || !empty($changelog)) : ?>
            <hr>
            <div class="audit-details">
                <?php if (!empty($created)) : ?>
                    <p>First Published: <?php echo esc_html($created); ?></p>
                <?php endif; ?>
                <?php if (!empty($last_updated)) : ?>
                    <p>Last Updated: <?php echo esc_html($last_updated); ?></p>
                <?php endif; ?>
                <?php if (!empty($last_review_date)) : ?>
                    <p>Last Date of Review: <?php echo esc_html($last_review_date); ?></p>
                <?php endif; ?>
                <?php if (!empty($next_review_date)) : ?>
                    <p>Next Date of Review: <?php echo esc_html($next_review_date); ?></p>
                <?php endif; ?>
                <?php if (!empty($changelog)) : ?>
                    <p>Changelog: <?php echo esc_html($changelog); ?></p>
                <?php endif; ?>
            </div>
        <?php endif;

    endwhile;
    wp_reset_postdata();

    return ob_get_clean();
}
add_shortcode('display_resource', 'display_resource_shortcode');