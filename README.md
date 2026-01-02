# VectorSearch - AI-Powered RAG Search for WordPress

Transform your WordPress site into an intelligent knowledge base with AI-powered semantic search and a beautiful chat interface powered by Google Gemini.

## Features

- 🤖 **AI-Powered Semantic Search** - Understands meaning, not just keywords
- 💬 **Interactive Chat Widget** - Beautiful Vue.js chat interface
- 🔍 **Hybrid Search** - Combines semantic + keyword search with RRF ranking
- ⚡  **Google Gemini Integration** - Latest AI models for embeddings and responses
- 📱 **Mobile Responsive** - Works perfectly on all devices
- 🔒 **Session-Based Conversations** - Maintains context during chat
- 🎯 **Source Attribution** - Always shows where answers come from
- ⚙️ **Easy Setup** - Just add your Google Gemini API key

## How It Works

```
User Question → RAG Search → Top 3 Relevant Posts → Google Gemini → AI Answer
                    ↓
        (Semantic Search + Keyword Search)
                    ↓
            Reciprocal Rank Fusion
```

## Installation

### Prerequisites
- WordPress 5.8 or higher
- PHP 7.4 or higher
- Google Gemini API Key ([Get one free](https://makersuite.google.com/app/apikey))

## Configuration

### Admin Dashboard

The VectorSearch admin panel (`/wp-admin → VectorSearch`) provides:

1. **API Key Configuration** - Enter and save your Gemini API key
2. **Indexing Tool** - Index all posts and pages with one click
3. **Search Tester** - Test your RAG system before going live


#### Modify Prompts

Edit the prompt in `index.php` (line ~77):

```php
$prompt = "Your custom instructions here...";
```

## Technical Architecture

### Tech Stack

- **Backend**: PHP, WordPress APIs
- **Frontend**: Vue.js 3 (CDN)
- **AI**: Google Gemini (embedding-001 + gemini-2.5-flash)
- **Search**: Hybrid (Semantic + Keyword) with RRF

### File Structure

```
vectorsearch/
├── assets/
│   ├── chat-widget.js      # Vue.js chat component
│   └── chat-widget.css     # Chat widget styles
├── index.php               # Main plugin file
├── readme.txt             # WordPress.org readme
├── README.md              # This file
└── LICENSE.txt            # GPL v2 license
```

### Database Schema

Vector embeddings are stored as WordPress post meta:

```
wp_postmeta
├── meta_key: 'vs_embedding'
└── meta_value: JSON array of 768 floats
```

### API Calls

**Indexing** (per post):
- POST to Gemini embedding-001 API

**Search** (per query):
- Embedding API call (query → vector)
- Cosine similarity calculation (in PHP)
- Gemini generative API call (context → answer)
