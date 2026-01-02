<?php
/*
Plugin Name: VectorSearch
Description: A RAG-based search engine that connects WordPress to Google Gemini.
Version: 1.0
Author: Senior Dev
*/

if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_init', 'vs_register_settings');
add_action('admin_menu', 'vs_create_menu');
add_action('wp_ajax_vs_ajax_search', 'vs_handle_search');

add_action('wp_enqueue_scripts', 'vs_enqueue_chat_widget');
add_action('wp_ajax_vs_chat', 'vs_handle_chat');
add_action('wp_ajax_nopriv_vs_chat', 'vs_handle_chat');
add_action('wp_footer', 'vs_render_chat_widget');

function vs_register_settings() {
    register_setting('vs_settings_group', 'vs_gemini_api_key');
}

function vs_create_menu() {
    add_menu_page(
        'VectorSearch Configuration', 
        'VectorSearch',               
        'manage_options',             
        'vectorsearch',               
        'vs_render_settings_page',    
        'dashicons-database',         
        100                           
    );
}

function vs_get_embedding($text, $api_key, $task_type = 'RETRIEVAL_DOCUMENT') {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-embedding-001:embedContent?key=" . $api_key;

    $body = [
        "model" => "models/gemini-embedding-001",
        "content" => [
            "parts" => [
                ["text" => substr($text, 0, 9000)]
            ]
        ],
        "task_type" => $task_type
    ];

    $response = wp_remote_post($url, [
        'headers' => ['Content-Type' => 'application/json'],
        'body'    => json_encode($body),
        'timeout' => 15
    ]);

    if (is_wp_error($response)) {
        return false;
    }
    $data = json_decode(wp_remote_retrieve_body($response), true);

    return $data['embedding']['values'] ?? false;
}

function vs_generate_answer($query, $context, $api_key) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $api_key;

    $prompt = "You are a helpful assistant for this website. Answer questions based ONLY on the context provided below. If the answer is not in the context, say 'I don't have that information in the website content.'\n\n";
    $prompt .= "Context:\n" . $context . "\n\n";
    $prompt .= "Question: " . $query . "\n\n";
    $prompt .= "Answer:";

    $body = [
        "contents" => [
            [
                "parts" => [
                    ["text" => $prompt]
                ]
            ]
        ],
        "generationConfig" => [
            "temperature" => 0.7,
            "maxOutputTokens" => 2048
        ]
    ];

    $response = wp_remote_post($url, [
        'headers' => ['Content-Type' => 'application/json'],
        'body'    => json_encode($body),
        'timeout' => 30
    ]);

    if (is_wp_error($response)) {
        return "Error: Could not generate answer.";
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);

    return $data['candidates'][0]['content']['parts'][0]['text'] ?? "Error: No response from AI.";
}

function vs_render_settings_page() {
    $stored_key = get_option('vs_gemini_api_key');

    if (isset($_POST['vs_run_indexer'])) {
        if (empty($stored_key)) {
            echo '<div class="notice notice-error"><p>❌ <strong>Error:</strong> No API Key found.</p></div>';
        } else {
            $posts = get_posts([
                'numberposts' => -1,
                'post_status' => 'publish',
                'post_type' => ['post', 'page']
            ]);
            $count = 0;

            echo '<div class="notice notice-info"><p><strong>Starting Indexing...</strong></p><div style="max-height: 200px; overflow-y: scroll; background: #fff; padding: 10px; border: 1px solid #ddd;">';

            foreach($posts as $post) {
                $text = strip_tags($post->post_title . " " . $post->post_content);
                $vector = vs_get_embedding($text, $stored_key, 'RETRIEVAL_DOCUMENT');

                if ($vector) {
                    update_post_meta($post->ID, 'vs_embedding', json_encode($vector));
                    $type_label = $post->post_type == 'page' ? '[Page]' : '[Post]';
                    echo "<p style='margin: 0; color: green;'>✅ Indexed {$type_label}: {$post->post_title}</p>";
                    $count++;
                } else {
                    echo "<p style='margin: 0; color: red;'>❌ Failed: {$post->post_title} (API Error)</p>";
                }
            }
            echo "</div><p><strong>✨ Done! Processed $count posts & pages.</strong></p></div>";
        }
    }
    ?>

    <div class="wrap">
        <h1>🚀 VectorSearch Dashboard</h1>
        
        <div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
            <h2>1. Configuration</h2>
            <form action="options.php" method="post">
                <?php settings_fields('vs_settings_group'); ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Gemini API Key</th>
                        <td>
                            <input type="password" name="vs_gemini_api_key" value="<?php echo esc_attr($stored_key); ?>" class="regular-text" />
                            <p class="description">Status: <?php echo $stored_key ? '<span style="color:green">✅ Connected</span>' : '<span style="color:red">❌ Disconnected</span>'; ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button('Save Key'); ?>
            </form>
        </div>

        <div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px;">
            <h2>2. Knowledge Base Indexer</h2>
            <p>Scan your posts and save their vectors to the database.</p>
            <form method="post">
                <?php 
                    $btn_attr = empty($stored_key) ? 'disabled' : '';
                    submit_button('Index All Posts Now', 'primary', 'vs_run_indexer', true, $btn_attr); 
                ?>
            </form>
        </div>

        <div class="card" style="max-width: 800px; padding: 20px; margin-top: 20px; background: #f0f6fc; border-left: 4px solid #72aee6;">
            <h2>3. 🔍 Test Your Search</h2>
            <p>Try asking a question to see if your RAG system works.</p>
            
            <div style="display: flex; gap: 10px;">
                <input type="text" id="vp-admin-query" placeholder="e.g. 'safety protocols'" style="flex: 1; padding: 8px;">
                <button type="button" id="vp-admin-search-btn" class="button button-primary">Search</button>
            </div>
            
            <div id="vp-admin-results" style="margin-top: 15px; background: #fff; padding: 15px; border: 1px solid #ccd0d4; min-height: 50px;">
                <em style="color: #666;">Results will appear here...</em>
            </div>
        </div>
    </div>

    <script>
    document.getElementById('vp-admin-search-btn').addEventListener('click', function() {
        var query = document.getElementById('vp-admin-query').value;
        var resultBox = document.getElementById('vp-admin-results');
        
        if (!query) { alert('Please type a query'); return; }

        resultBox.innerHTML = 'Thinking... 🧠';

        var formData = new FormData();
        formData.append('action', 'vs_ajax_search');
        formData.append('term', query);

        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(html => {
            resultBox.innerHTML = html;
        });
    });
    </script>
    <?php
}

function vs_handle_search() {
    $query = sanitize_text_field($_POST['term']);
    $api_key = get_option('vs_gemini_api_key');

    $query_vector = vs_get_embedding($query, $api_key, 'RETRIEVAL_QUERY');

    if (!$query_vector) {
        echo "Error: Could not generate vector.";
        wp_die();
    }

    $posts = get_posts([
        'numberposts' => -1,
        'post_status' => 'publish',
        'post_type' => ['post', 'page']
    ]);
    $semantic_results = [];

    foreach ($posts as $post) {
        $json = get_post_meta($post->ID, 'vs_embedding', true);
        if ($json) {
            $db_vector = json_decode($json);
            if (is_array($db_vector)) {
                $score = vs_cosine_similarity($query_vector, $db_vector);
                if ($score > 0.55) {
                    $semantic_results[] = [
                        'post_id' => $post->ID,
                        'score' => $score,
                        'title' => $post->post_title,
                        'excerpt' => wp_trim_words($post->post_content, 15)
                    ];
                }
            }
        }
    }

    usort($semantic_results, function($a, $b) {
        return $b['score'] <=> $a['score'];
    });

    $keyword_results = vs_keyword_search($query, 20);
    $merged_results = vs_merge_results_rrf($semantic_results, $keyword_results);

    if (empty($merged_results)) {
        echo "<div style='padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107;'>";
        echo "<p style='margin: 0;'>I couldn't find any relevant information in the website content to answer your question.</p>";
        echo "</div>";
    } else {
        $top_results = array_slice($merged_results, 0, 5, true);
        $context = "";
        $sources = [];

        foreach ($top_results as $post_id => $result) {
            $post = get_post($result['data']['post_id']);
            if ($post) {
                $full_content = strip_tags($post->post_content);
                $context .= "=== " . $post->post_title . " ===\n";
                $context .= $full_content . "\n\n";

                $sources[] = [
                    'title' => $post->post_title,
                    'url' => get_permalink($post->ID),
                    'type' => $post->post_type
                ];
            }
        }

        $answer = vs_generate_answer($query, $context, $api_key);

        echo "<div style='background: #f0f6fc; padding: 15px; border-left: 4px solid #0073aa; margin-bottom: 15px;'>";
        echo "<p style='margin: 0 0 5px 0; font-weight: 600; color: #0073aa;'>Answer:</p>";
        echo "<p style='margin: 0; line-height: 1.6;'>" . nl2br(esc_html($answer)) . "</p>";
        echo "</div>";

        if (!empty($sources)) {
            echo "<div style='background: #fff; padding: 15px; border: 1px solid #ddd;'>";
            echo "<p style='margin: 0 0 10px 0; font-weight: 600;'>Sources:</p>";
            echo "<ul style='margin: 0; padding-left: 20px;'>";

            foreach ($sources as $source) {
                $type_badge = $source['type'] == 'page' ? '<span style="background:#e0e0e0; font-size:9px; padding:2px 4px; border-radius:2px; margin-left:5px;">PAGE</span>' : '<span style="background:#e0e0e0; font-size:9px; padding:2px 4px; border-radius:2px; margin-left:5px;">POST</span>';
                echo "<li style='margin: 5px 0;'>";
                echo "<a href='{$source['url']}' target='_blank' style='color: #0073aa; text-decoration: none;'>{$source['title']}</a>";
                echo $type_badge;
                echo "</li>";
            }

            echo "</ul>";
            echo "</div>";
        }
    }

    wp_die();
}

function vs_cosine_similarity($vec1, $vec2) {
    $dot = 0; $mag1 = 0; $mag2 = 0;
    foreach ($vec1 as $i => $val) {
        $dot += $val * $vec2[$i];
        $mag1 += $val * $val;
        $mag2 += $vec2[$i] * $vec2[$i];
    }
    if ($mag1 * $mag2 == 0) return 0;
    return $dot / (sqrt($mag1) * sqrt($mag2));
}

function vs_keyword_search($query, $limit = 20) {
    $wp_query = new WP_Query([
        's' => $query,
        'post_type' => ['post', 'page'],
        'post_status' => 'publish',
        'posts_per_page' => $limit,
        'orderby' => 'relevance'
    ]);

    $results = [];
    $rank = 1;

    while ($wp_query->have_posts()) {
        $wp_query->the_post();
        $results[] = [
            'post_id' => get_the_ID(),
            'rank' => $rank,
            'title' => get_the_title(),
            'excerpt' => get_the_excerpt()
        ];
        $rank++;
    }

    wp_reset_postdata();
    return $results;
}

function vs_merge_results_rrf($semantic_results, $keyword_results, $k = 60) {
    $scores = [];

    foreach ($semantic_results as $rank => $result) {
        $post_id = $result['post_id'];
        if (!isset($scores[$post_id])) {
            $scores[$post_id] = [
                'rrf_score' => 0,
                'data' => $result
            ];
        }
        $scores[$post_id]['rrf_score'] += 1 / ($k + $rank + 1);
    }

    foreach ($keyword_results as $result) {
        $post_id = $result['post_id'];
        if (!isset($scores[$post_id])) {
            $scores[$post_id] = [
                'rrf_score' => 0,
                'data' => $result
            ];
        }
        $scores[$post_id]['rrf_score'] += 1 / ($k + $result['rank']);
    }

    uasort($scores, function($a, $b) {
        return $b['rrf_score'] <=> $a['rrf_score'];
    });

    return $scores;
}

function vs_enqueue_chat_widget() {
    wp_enqueue_script('vue', 'https://unpkg.com/vue@3/dist/vue.global.js', [], '3.0', true);
    wp_enqueue_script('vs-chat-widget',
        plugin_dir_url(__FILE__) . 'assets/chat-widget.js',
        ['vue'], '1.0', true
    );

    wp_localize_script('vs-chat-widget', 'vsChat', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('vs_chat_nonce')
    ]);

    wp_enqueue_style('vs-chat-widget',
        plugin_dir_url(__FILE__) . 'assets/chat-widget.css'
    );
}

function vs_handle_chat() {
    check_ajax_referer('vs_chat_nonce', 'nonce');

    $user_message = sanitize_text_field($_POST['message']);
    $api_key = get_option('vs_gemini_api_key');

    if (!session_id()) {
        session_start();
    }

    if(!isset($_SESSION['vs_chat_history'])) {
        $_SESSION['vs_chat_history'] = [];
    }

    $_SESSION['vs_chat_history'][] = [
        'role' => 'user',
        'message' => $user_message
    ];

    $_SESSION['vs_chat_history'] = array_slice($_SESSION['vs_chat_history'], -10);

    $query_vector = vs_get_embedding($user_message, $api_key, 'RETRIEVAL_QUERY');
    $posts = get_posts([
        'numberposts' => -1,
        'post_status' => 'publish',
        'post_type' => ['post', 'page']
    ]);
    $semantic_results = [];

    foreach($posts as $post){
        $json = get_post_meta($post->ID, 'vs_embedding', true);
        if ($json) {
            $db_vector = json_decode($json);
            if (is_array($db_vector)){
                $score = vs_cosine_similarity($query_vector, $db_vector);
                if($score > 0.55) {
                    $semantic_results[] = [
                        'post_id' => $post->ID,
                        'score' => $score,
                    ];
                }
            }
        }
    }

    usort($semantic_results, function($a, $b) {
        return $b['score'] <=> $a['score'];
    });

    $keyword_results = vs_keyword_search($user_message);
    $merged_results = vs_merge_results_rrf($semantic_results, $keyword_results);

    $context = "";

    $history_context = "";
    foreach ($_SESSION['vs_chat_history'] as $msg) {
        $history_context .= $msg['role'] . ": " . $msg['message'] . "\n";
    }
    $top_results = array_slice($merged_results, 0, 3, true);
    foreach ($top_results as $result) {
        $post = get_post($result['data']['post_id']);
        if ($post) {
            $context .= "\n=== " . $post->post_title . " ===\n";
            $context .= strip_tags($post->post_content) . "\n";
        }
    }
    $full_context = $history_context . "\n\nRelevant website content:\n" . $context;
    $answer = vs_generate_answer($user_message, $full_context, $api_key);

    $_SESSION['vs_chat_history'][] = [
        'role' => 'assistant',
        'message' => $answer
    ];

    wp_send_json_success([
        'message' => $answer
    ]);
}

function vs_render_chat_widget() {
    ?>
    <div id="vs-chat-widget">
        <div v-if="!isOpen" class="vs-chat-button" @click="toggleChat">
            💬
        </div>

        <div v-if="isOpen" class="vs-chat-window">
            <div class="vs-chat-header">
                <span>Chat with us</span>
                <button @click="toggleChat" class="vs-close-btn">✕</button>
            </div>

            <div class="vs-chat-messages" ref="messages">
                <div v-for="(msg, index) in messages" :key="index"
                     :class="['vs-message', msg.role === 'user' ? 'vs-user' : 'vs-assistant']">
                    <div class="vs-message-bubble">{{ msg.text }}</div>
                </div>

                <div v-if="isTyping" class="vs-message vs-assistant">
                    <div class="vs-message-bubble vs-typing">
                        <span></span><span></span><span></span>
                    </div>
                </div>
            </div>

            <div class="vs-chat-input">
                <input
                    v-model="userInput"
                    @keyup.enter="sendMessage"
                    type="text"
                    placeholder="Type your message..."
                />
                <button @click="sendMessage" :disabled="!userInput.trim()">Send</button>
            </div>
        </div>
    </div>
    <?php
}