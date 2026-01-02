=== VectorSearch - AI-Powered RAG Search for WordPress ===
Contributors: abhinavohri
Tags: ai, search, rag, gemini, chat, semantic search, vector search
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Transform your WordPress site into an intelligent knowledge base with AI-powered semantic search and a beautiful chat interface powered by Google Gemini.

== Description ==

**VectorSearch** brings cutting-edge AI technology to your WordPress site, enabling semantic search capabilities and an intuitive chat interface that helps visitors find exactly what they're looking for.

= Features =

* AI-Powered Semantic Search - Understands meaning, not just keywords
* Interactive Chat Widget - Beautiful Vue.js chat interface
* Hybrid Search - Combines semantic + keyword search with RRF ranking
* Google Gemini Integration - Latest AI models for embeddings and responses
* Mobile Responsive - Works perfectly on all devices
* Session-Based Conversations - Maintains context during chat
* Source Attribution - Always shows where answers come from
* Easy Setup - Just add your Google Gemini API key

= How It Works =

User Question → RAG Search → Top 3 Relevant Posts → Google Gemini → AI Answer
                    ↓
        (Semantic Search + Keyword Search)
                    ↓
            Reciprocal Rank Fusion

== Installation ==

= Automatic Installation =

1. Log in to your WordPress admin panel
2. Go to Plugins → Add New
3. Search for "VectorSearch"
4. Click "Install Now" and then "Activate"

= Manual Installation =

1. Download the plugin ZIP file
2. Go to Plugins → Add New → Upload Plugin
3. Choose the ZIP file and click "Install Now"
4. Activate the plugin

= After Activation =

1. Go to WordPress Admin → VectorSearch
2. Enter your Google Gemini API key (get free key at https://makersuite.google.com/app/apikey)
3. Click "Save Key"
4. Click "Index All Posts Now" to index your content
5. Test the search using the built-in tester
6. The chat widget will appear automatically on your site

= Requirements =

* WordPress 5.8 or higher
* PHP 7.4 or higher
* Google Gemini API Key (free at https://makersuite.google.com/app/apikey)

== Configuration ==

= Admin Dashboard =

The VectorSearch admin panel (/wp-admin → VectorSearch) provides:

1. API Key Configuration - Enter and save your Gemini API key
2. Indexing Tool - Index all posts and pages with one click
3. Search Tester - Test your RAG system before going live

= Modify Prompts =

Edit the prompt in index.php (line ~77):

`$prompt = "Your custom instructions here...";`

== Technical Architecture ==

= Tech Stack =

* Backend: PHP, WordPress APIs
* Frontend: Vue.js 3 (CDN)
* AI: Google Gemini (embedding-001 + gemini-2.5-flash)
* Search: Hybrid (Semantic + Keyword) with RRF

= File Structure =

vectorsearch/
├── assets/
│   ├── chat-widget.js      # Vue.js chat component
│   └── chat-widget.css     # Chat widget styles
├── index.php               # Main plugin file
├── readme.txt             # WordPress.org readme
├── README.md              # This file
└── LICENSE.txt            # GPL v2 license

= Database Schema =

Vector embeddings are stored as WordPress post meta:

wp_postmeta
├── meta_key: 'vs_embedding'
└── meta_value: JSON array of 768 floats

= API Calls =

**Indexing** (per post):
* POST to Gemini embedding-001 API

**Search** (per query):
* Embedding API call (query → vector)
* Cosine similarity calculation (in PHP)
* Gemini generative API call (context → answer)

== Frequently Asked Questions ==

= Do I need to pay for the Google Gemini API? =

No! Google Gemini offers a free tier that's generous enough for most WordPress sites. You can get your free API key at https://makersuite.google.com/app/apikey

= Will this work on my existing WordPress site? =

Yes! VectorSearch works with any WordPress 5.8+ site. It indexes your existing posts and pages without modifying them.

= Does the chat widget appear on all pages? =

Yes, by default the chat widget appears on all frontend pages. You can customize this by modifying the plugin code.

= How accurate is the AI search? =

VectorSearch uses hybrid search (semantic + keyword) with Reciprocal Rank Fusion to provide highly accurate results. It understands the meaning of queries, not just exact keyword matches.

= What happens if I deactivate the plugin? =

The chat widget will disappear, but your content remains unchanged. Vector embeddings are stored as post meta and can be cleaned up by deleting the plugin.

= Can I customize the AI responses? =

Yes! You can modify the prompt in index.php around line 68 to change how the AI responds to questions.

= Does this plugin slow down my site? =

No. Indexing happens only when you click "Index All Posts Now" in the admin panel. Frontend searches are fast - the chat widget loads asynchronously and doesn't block page rendering.

== Changelog ==

= 1.0.0 - 2026-01-02 =
* Initial release
