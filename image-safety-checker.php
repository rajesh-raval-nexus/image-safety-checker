<?php
/*
Plugin Name: Image Safety Checker
Description: Checks the safety of uploaded images using an external API.
Version: 1.0
Author: Your Name
*/

// Hook into the media upload process
add_filter( 'wp_handle_upload', 'check_image_safety', 10, 2 );

function check_image_safety( $file ) {
    // Only check for images
    if ( strpos( $file['type'], 'image' ) !== false ) {
        $api_url    = 'http://192.168.1.207:8080/detect';
        $image_path = $file['file'];
        $boundary   = wp_generate_password( 24, false );

        // Read the file content
        $file_content = file_get_contents( $image_path );
        $file_name = basename($image_path);

        // Prepare the data for the API request
        $body = "--$boundary\r\n";
        $body .= "Content-Disposition: form-data; name=\"image\"; filename=\"$file_name\"\r\n";
        $body .= "Content-Type: " . mime_content_type($image_path) . "\r\n\r\n";
        $body .= $file_content . "\r\n";
        $body .= "--$boundary--";

        $response = wp_remote_post(
            $api_url,
            array(
                'headers' => array(
                    'accept' => 'application/json',
                    'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
                ),
                'body'    => $body,
            )
        );
	
        if ( is_wp_error( $response ) ) {
            // Handle error appropriately
            return array(
                'error' => $response->get_error_message(),
            );
        }

        $safety_prediction = wp_remote_retrieve_header( $response, 'safety-prediction' );

        // Check if the image is safe
        if ( $safety_prediction === 'Not Safe' ) {
            // Delete the uploaded file
            @unlink( $file['file'] );

            // Add admin notice
            add_action(
                'admin_notices',
                function () {
                    echo '<div class="notice notice-error is-dismissible"><p>Image upload failed: The image is not safe.</p></div>';
                }
            );

            return array(
                'error' => 'Image upload failed: The image is not safe.',
            );
        }
    }

    return $file;
}
?>
