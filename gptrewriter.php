<?php
/**
 * Plugin Name: GPT-Rewriter 
 * Description: A plugin to rewrite posts using the ChatGPT 3.5Turbo API and change their status from draft to published.
 * Version: 1.0.0
 * Author: BradPerbs
 */

// Add the plugin menu to the WordPress admin dashboard
add_action('admin_menu', 'chatgpt_rewriter_menu');

function chatgpt_rewriter_menu() {
    add_menu_page(
        'ChatGPT Rewriter',
        'ChatGPT Rewriter',
        'manage_options',
        'chatgpt-rewriter',
        'chatgpt_rewriter_page'
    );
}

function chatgpt_rewriter_page() {
    // Check if the user has saved the API key
    $api_key = get_option('chatgpt_api_key');

    // Save the API key if the form is submitted
    if (isset($_POST['save_api_key'])) {
        $api_key = sanitize_text_field($_POST['api_key']);
        update_option('chatgpt_api_key', $api_key);
        echo '<div class="notice notice-success"><p>API key saved successfully.</p></div>';

        // Log API key saved
        chatgpt_log_message('API key saved successfully.');
    }

    // Rewrite posts when the rewrite button is clicked
    if (isset($_POST['rewrite_posts'])) {
        $api_key = get_option('chatgpt_api_key');
        if (empty($api_key)) {
            echo '<div class="notice notice-error"><p>Please save the API key first.</p></div>';


        } else {
            chatgpt_rewrite_posts();
        }
    }

    // Update the auto rewrite interval when the form is submitted
    if (isset($_POST['update_interval'])) {
        $interval = intval($_POST['interval']);
        update_option('chatgpt_auto_rewrite_interval', $interval);
        echo '<div class="notice notice-success"><p>Auto rewrite interval updated successfully.</p></div>';

        // Log interval updated
        chatgpt_log_message('Auto rewrite interval updated to ' . $interval . ' minutes.');
    }

    // Start auto rewriting when the button is clicked
    if (isset($_POST['start_auto_rewrite'])) {
        chatgpt_start_auto_rewrite();
    }

    // Stop auto rewriting when the button is clicked
    if (isset($_POST['stop_auto_rewrite'])) {
        chatgpt_stop_auto_rewrite();
    }

    // Display the plugin settings page
	echo '<img src="' . plugin_dir_url(__FILE__) . 'GPTRewriter.png" alt="GPTRewriter" style="margin-left: 36%;padding: 30px;">';
    echo '<div class="wrap">';
    echo '<form method="post">';
    echo '<h2>API Key</h2>';
	echo '<h4>Insert here your OpenAI api key, you can find it <a target="_blank" href="https://platform.openai.com/account/api-keys">here</a></h4>';
    echo '<input type="text" name="api_key" value="' . esc_attr($api_key) . '">';
    echo '<br><br>';
    echo '<input type="submit" class="button button-primary" name="save_api_key" value="Save API Key">';
    echo '<br><br>';
	echo '</div>';
	echo '<div class="wrap">';
    echo '<h2>Manual Rewriting</h2>';
	echo '<h4>Click the button below to rewrite 1 article manually</h4>';
    echo '<input type="submit" class="button button-primary" name="rewrite_posts" value="Rewrite Post">';
    echo '<br><br>';
	echo '</div>';
	echo '<div class="wrap">';
    echo '<h2>Auto Rewriting</h2>';
    $interval = get_option('chatgpt_auto_rewrite_interval', 60);
    echo '<label for="interval">Interval (in minutes): </label>';
    echo '<input type="number" id="interval" name="interval" min="1" value="' . esc_attr($interval) . '">';
    echo '<br><br>';
    echo '<input type="submit" class="button button-primary" name="update_interval" value="Update Interval">';
    echo '<br><br>';
	echo '</div>';
	echo '<div class="wrap">';
	echo '<h2>Status</h2>';
    $is_auto_rewrite_running = wp_next_scheduled('chatgpt_rewrite_event_hook');
    if ($is_auto_rewrite_running) {
        echo '<p style="font-size: 15px;color: #24ac4c;font-weight: 700;">Auto rewriting is currently running.</p>';
        echo '<br><br>';
        echo '<input type="submit" class="button button-primary" name="stop_auto_rewrite" value="Stop Auto Rewriting">';
    } else {
        echo '<p style="font-size: 15px;color: #d23737;font-weight: 700;">Auto rewriting is currently stopped.</p>';
        echo '<input type="submit" class="button button-primary" name="start_auto_rewrite" value="Start Auto Rewriting">';
    }
    echo '</form>';

	// Display the latest runs and article titles in a table
		echo '<h2>Latest Runs</h2>';
		echo '<table class="widefat">';
		echo '<thead><tr><th>Run ID</th><th>Article Title</th></tr></thead>';
		echo '<tbody>';

		// Get the latest runs and article titles
		$latest_runs = get_option('chatgpt_latest_runs', array());

		$latest_runs = array_slice($latest_runs, 0, 10); // Limit the array to the latest 10 runs

		$latest_runs = array_reverse($latest_runs); // Reverse the order of the array

		foreach ($latest_runs as $run) {
			$run_id = $run['run_id'];
			$article_title = $run['article_title'];

			echo '<tr><td>' . esc_html($run_id) . '</td><td>' . esc_html($article_title) . '</td></tr>';
		}

		echo '</tbody>';
		echo '</table>';
		echo '</div>';



    // Add custom CSS styles
    echo '<style>
        .wrap {
            max-width: 60%;
            margin: 20px auto;
            padding: 20px;
            background-color: #fff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        h1 {
            font-size: 24px;
            margin-bottom: 20px;
        }

        h2 {
            font-size: 20px;
            margin-bottom: 10px;
        }

        .notice {
            margin-bottom: 20px;
            padding: 10px;
            border-radius: 3px;
        }

        .notice-success {
            background-color: #dff0d8;
            border-color: #d6e9c6;
        }

        .notice-error {
            background-color: #f2dede;
            border-color: #ebccd1;
        }

        .button-primary {
            background-color: #0073aa;
            border-color: #0073aa;
            color: #fff;
        }

        input[type="text"],
        input[type="number"] {
            width: 50%;
            padding: 5px;
            border: 1px solid #ccc;
            border-radius: 3px;
        }

        table.widefat {
            width: 100%;
            margin-top: 20px;
            border-collapse: collapse;
        }

        table.widefat thead th {
            padding: 10px;
            background-color: #f1f1f1;
            border: 1px solid #e3e3e3;
        }

        table.widefat tbody td {
            padding: 10px;
            border: 1px solid #e3e3e3;
        }
    </style>';
}


function chatgpt_rewrite_posts() {
    // Get the list of draft posts
    $args = array(
        'post_type'      => 'post',
        'post_status'    => 'draft',
        'posts_per_page' => 1, // Limit to 1 post
    );

    $draft_posts = get_posts($args);

    // Check if any draft post is found
    if (empty($draft_posts)) {
        echo '<p>No draft posts found.</p>';
        return;
    }

    $post = $draft_posts[0]; // Get the first draft post

    // Initialize the ChatGPT API endpoint
    $api_endpoint = 'https://api.openai.com/v1/chat/completions';

    // Prepare messages array for API request
    $messages = array();

    // Get the post content
	$post_title = $post->post_title;
    $post_content = $post->post_content;

    // Add a message for the user content
    $messages[] = array('role' => 'system', 'content' => 'You are a helpful assistant that rewrites the post keeping the same lenght.');
	$messages[] = array('role' => 'user', 'content' => $post_title);
    $messages[] = array('role' => 'user', 'content' => $post_content);

    // Make the API request to rewrite the content
    $response = chatgpt_api_request($api_endpoint, $messages);

    if ($response['status'] === 'success') {
        $generated_text = $response['data']; // Get the generated text
		
		$generated_title = '';
		$generated_content = '';

		if (!empty($generated_text)) {
			// Split the generated text into title and content
			$generated_parts = explode("\n", $generated_text);
			$generated_title = $generated_parts[0];
			$generated_content = implode("\n", array_slice($generated_parts, 1));
}


        // Process the generated text for the draft post
        if (!empty($generated_text)) {
            // Update the post with the rewritten content
            $post->post_title = $generated_title;
			$post->post_content = $generated_content;
			wp_update_post($post);

            // Change the post status to "Published"
            wp_publish_post($post);
            echo '<p>Post "' . esc_html($post->post_title) . '" has been rewritten and published.</p>';

            // Log post rewritten and published
            chatgpt_log_message('Post "' . $post->post_title . '" has been rewritten and published.');
			
			 // Store the run ID and article title in the latest runs array
			$latest_runs = get_option('chatgpt_latest_runs', array());
			$latest_runs[] = array(
				'run_id' => $post->ID,
				'article_title' => $post->post_title,
			);
			update_option('chatgpt_latest_runs', $latest_runs);
        } else {
            echo '<p>Error rewriting the post. No generated text received.</p>';


        }
    } else {
        echo '<p>Error rewriting the post. Please check the logs for more details.</p>';


    }
}

function chatgpt_api_request($url, $messages) {
    // Retrieve the API key from the options table
    $api_key = get_option('chatgpt_api_key');

    // Prepare the request headers
    $headers = array(
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer ' . $api_key,
    );

    // Prepare the request data
    $data = array(
        'model' => 'gpt-3.5-turbo',
        'messages' => $messages,
    );

    // Prepare the request arguments
    $args = array(
        'headers' => $headers,
        'body' => wp_json_encode($data),
        'timeout' => 40,
    );

    // Send the API request
    $response = wp_remote_post($url, $args);

    // Log the API response
    chatgpt_log_message('API Response: ' . wp_json_encode($response));

    // Check for errors
    if (is_wp_error($response)) {
        // Log the error
        $error_message = $response->get_error_message();
        chatgpt_log_error($error_message);

        return array(
            'status' => 'error',
            'message' => $error_message,
        );
    }

    // Get the response body
    $body = wp_remote_retrieve_body($response);

    // Decode the JSON response
    $decoded_response = json_decode($body, true);

    // Check for API errors
    if (isset($decoded_response['error'])) {
        // Log the API error
        $api_error_message = $decoded_response['error']['message'];
        chatgpt_log_error($api_error_message);

        return array(
            'status' => 'error',
            'message' => $api_error_message,
        );
    }

    // Get the generated text from the API response
    $generated_text = $decoded_response['choices'][0]['message']['content'];

    // Log the generated text
    chatgpt_log_message('Generated Text: ' . $generated_text);

    return array(
        'status' => 'success',
        'data' => $generated_text,
    );
}

function chatgpt_log_message($message) {
    $log_file = WP_CONTENT_DIR . '/chatgpt-error.log';

    // Format the log message
    $log_message = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";

    // Append the log message to the log file
    file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
}

function chatgpt_log_error($message) {
    chatgpt_log_message('[ERROR] ' . $message);
}

// Start auto rewriting at the defined interval
function chatgpt_start_auto_rewrite() {
    $interval = get_option('chatgpt_auto_rewrite_interval', 60);
    wp_schedule_event(time(), 'chatgpt_rewrite_interval', 'chatgpt_rewrite_event_hook');
    echo '<div class="notice notice-success"><p>Auto rewriting started. Interval: ' . $interval . ' minutes.</p></div>';

    // Log auto rewriting started
    chatgpt_log_message('Auto rewriting started. Interval: ' . $interval . ' minutes.');
}

// Stop auto rewriting
function chatgpt_stop_auto_rewrite() {
    wp_clear_scheduled_hook('chatgpt_rewrite_event_hook');
    echo '<div class="notice notice-success"><p>Auto rewriting stopped.</p></div>';

    // Log auto rewriting stopped
    chatgpt_log_message('Auto rewriting stopped.');
}

// Add custom cron interval for auto rewriting
function chatgpt_add_custom_cron_interval($schedules) {
    $interval = get_option('chatgpt_auto_rewrite_interval', 60);
    $schedules['chatgpt_rewrite_interval'] = array(
        'interval' => $interval * 60,
        'display' => 'Every ' . $interval . ' Minutes',
    );
    return $schedules;
}
add_filter('cron_schedules', 'chatgpt_add_custom_cron_interval');

// Rewrite posts automatically at the defined interval
function chatgpt_rewrite_posts_cron() {
    chatgpt_rewrite_posts();
}
add_action('chatgpt_rewrite_event_hook', 'chatgpt_rewrite_posts_cron');


