<?php
// Example local overrides. Do not commit real secrets.

define('APP_ENV', 'development');
define('APP_URL', 'http://localhost/fataknews_complete_2/fataknews');
define('APP_ALLOWED_HOSTS', ['localhost', '127.0.0.1']);
define('APP_FORCE_HTTPS', false);
define('APP_TRUST_PROXY_HEADERS', false);
define('GOOGLE_CLIENT_ID', 'your-google-client-id.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'your-google-client-secret');
define('GTM_CONTAINER_ID', 'GTM-XXXXXXX');
define('GA_MEASUREMENT_ID', 'G-XXXXXXXXXX');
define('AI_PROVIDER', 'xai'); // or 'groq' or 'auto'
define('XAI_API_KEY', 'your-xai-api-key');
define('XAI_MODEL', 'grok-4-fast-non-reasoning');
define('GROQ_API_KEY', '');
define('GROQ_MODEL', 'llama-3.3-70b-versatile');
define('TAG_INDEX_WHITELIST', ['modi', 'rahul-gandhi', 'budget-2026']);
define('TAG_NOINDEX_BLACKLIST', ['test', 'news', 'updates', 'temp']);
