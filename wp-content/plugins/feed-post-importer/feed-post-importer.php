<?php
/*
Plugin Name: Custom Feed API Endpoint
Description: Fetch and insert the db and display content from an external RSS feed.
Version: 1.0
Author: Paramasivan Aththiappan
*/

// The schedule filter hook
function rss_cron_interval( $schedules ) {
    $schedules['every_ten_minutes'] = array(
            'interval'  => 600,
            'display'   => __( 'Every 10 Minutes', 'textdomain' )
    );
    return $schedules;
}
add_filter( 'cron_schedules', 'rss_cron_interval' );

// Cron Activation hook
register_activation_hook(__FILE__, 'rss_activation'); 
function rss_activation() {
    if (! wp_next_scheduled ( 'ten_minutes_event' )) {
    wp_schedule_event(time(), 'every_ten_minutes', 'ten_minutes_event');
    }
}

// Cron deactivation hook
function rss_deactivation(){
    if( wp_next_scheduled( 'ten_minutes_event' ) ){
        wp_clear_scheduled_hook( 'ten_minutes_event' );
    }
}
register_deactivation_hook( __FILE__, 'rss_deactivation' );
 
add_action('ten_minutes_event', 'import_posts_from_feed');

// The WP Cron event callback function
function import_posts_from_feed() {
    $maxItems = 15;
    $i = 0;
    $feed_url = 'https://www.nbcnewyork.com/?rss=y&most_recent=y';  
    $rss = fetch_feed($feed_url);
    // Check for errors
    if (is_wp_error($rss)) {
        echo 'Error fetching RSS feed: ' . esc_html($rss->get_error_message());
        return;
    }

    $posts = $rss->get_items();

        foreach ($posts as $post) {

            if ($i >= $maxItems) {
                break;
            }

            $title = $post->get_title();
            $content = $post->get_content();
            $link = $post->get_link();
            $pub_date = $post->get_date('Y-m-d H:i:s');

            // Check if a post with the same title and link already exists
            $existing_post = get_posts(array(
                'post_title' => $title,
                'meta_query' => array(
                    array(
                        'key'   => 'rss_feed_link', // Replace with your custom field name
                        'value' => $link,
                    ),
                ),
            ));

            if (empty($existing_post)) {
                // Prepare post data
                $post_data = array(
                    'post_title'   => $title,
                    'post_content' => $content,
                    'post_date'    => $pub_date,
                    'post_status'  => 'publish',
                );

                // Insert the post
                $post_id = wp_insert_post($post_data);

                // Check if the post was successfully inserted
                if (!is_wp_error($post_id)) {
                    // Save the link as custom field to mark this post as imported
                    update_post_meta($post_id, 'rss_feed_link', $link);
                    
                    // Optionally, set additional post meta or perform other actions
                }

                // Check if the post was successfully inserted
                if (!is_wp_error($post_id)) {

                    // Process media elements (enclosures)
                    foreach ($post->get_enclosures() as $enclosure) {                    
                        $img_url = esc_url($enclosure->get_link());     
                    }
                    $image_url = strtok($img_url, "?");

                    // Check if the image URL is valid
                    if ($image_url) {
                        // Get the image data
                        $image_data = file_get_contents($image_url);

                        // Generate a unique filename for the image
                        $filename = wp_unique_filename(wp_upload_dir()['path'], basename($image_url));

                        // Save the image to the uploads directory
                        $upload_dir = wp_upload_dir();
                        $upload_path = $upload_dir['path'] . '/' . $filename;
                        file_put_contents($upload_path, $image_data);

                        // Prepare attachment data
                        $attachment = array(
                            'post_title'     => sanitize_file_name(pathinfo($filename, PATHINFO_FILENAME)),
                            'post_mime_type' => wp_check_filetype($filename)['type'],
                            'post_content'   => '',
                            'post_status'    => 'inherit',
                        );

                        // Insert the attachment
                        $attachment_id = wp_insert_attachment($attachment, $upload_path);

                        // Check if the image was successfully added to the media library
                        if (!is_wp_error($attachment_id)) {
                            // Set the post thumbnail (featured image)
                            set_post_thumbnail($post_id, $attachment_id);
                        }
                            
                    }      

                }$i++;
            } else {
                $i--;                 
            }
        }

    $counts = $i;

    return new WP_REST_Response($counts.' Posts imported successfully', 200);
}



/*// Register custom WP API endpoint
add_action('rest_api_init', function () {
    register_rest_route('feed-post-importer/v1', '/import', array(
        'methods' => 'GET',
        'callback' => 'import_posts_from_feed',
    ));
});*/

?>
