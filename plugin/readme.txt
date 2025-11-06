=== WordPress Review Bot ===
Contributors: martindev
Tags: comments, moderation, ai, openai, spam, automation, artificial intelligence
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 8.0
License: MIT
License URI: https://opensource.org/licenses/MIT

AI-powered comment moderation for WordPress using OpenAI. Automatically approve, reject, or mark comments as spam with intelligent AI analysis.

== Description ==

WordPress Review Bot is an intelligent comment moderation plugin that uses OpenAI's advanced AI models to automatically analyze and moderate comments on your WordPress site.

**Key Features:**

* **AI-Powered Moderation**: Uses OpenAI GPT models to intelligently analyze comments and make moderation decisions
* **Automatic Decisions**: Automatically approve, reject, or mark comments as spam based on AI analysis
* **Confidence Scoring**: Each decision includes a confidence score so you can set your own thresholds
* **Decision Tracking**: Complete log of all AI decisions with reasoning and confidence scores
* **Bulk Operations**: Bulk approve, mark as spam, or trash comments from the admin dashboard
* **Multiple AI Models**: Support for GPT-4, GPT-3.5-turbo, and other OpenAI models
* **Flexible Settings**: Configure moderation rules, confidence thresholds, and AI behavior
* **Export Functionality**: Export AI decisions as CSV or JSON for analysis
* **Non-Blocking**: Asynchronous processing ensures your site performance isn't impacted
* **Fallback Protection**: Multiple fallback mechanisms ensure comments are always processed

**How It Works:**

1. When a new comment is posted, the plugin sends it to OpenAI for analysis
2. The AI evaluates the comment for spam, appropriateness, and relevance
3. Based on your configured settings, the plugin automatically applies the moderation decision
4. All decisions are logged with full reasoning and confidence scores
5. You can review, override, and bulk manage decisions from the admin dashboard

**Perfect For:**

* High-traffic blogs with大量 comments to moderate
* Community sites that need consistent moderation
* Businesses wanting to reduce manual moderation workload
* Anyone looking for intelligent, automated comment management

== Installation ==

1. **Upload the plugin** to the `/wp-content/plugins/wordpress-review-bot` directory, or install the plugin through the WordPress plugins screen directly.
2. **Activate the plugin** through the 'Plugins' screen in WordPress
3. **Configure your OpenAI API key** in Settings → WordPress Review Bot
4. **Choose your AI model** and moderation settings
5. **Test the connection** using the built-in testing tool

**Requirements:**

* WordPress 6.0 or higher
* PHP 8.0 or higher
* OpenAI API key (sign up at https://platform.openai.com/)

**First Time Setup:**

1. After activation, go to **Settings → WordPress Review Bot**
2. Enter your OpenAI API key in the "API Configuration" section
3. Click "Test Connection" to verify your API key works
4. Select your preferred AI model (GPT-4 recommended for best results)
5. Set your confidence thresholds for automatic moderation
6. Save your settings and start enjoying automated comment moderation!

== Frequently Asked Questions ==

= Do I need an OpenAI API key to use this plugin? =

Yes, you need a valid OpenAI API key. You can sign up at https://platform.openai.com/ and get API access. The plugin uses the API for intelligent comment analysis.

= How much does it cost to use? =

Costs depend on your OpenAI API usage. OpenAI charges per token (word) processed. For most sites, this is very affordable - typically just a few dollars per month for thousands of comments. You can set usage limits in the plugin settings.

= Can I override AI decisions? =

Yes! All AI decisions are logged in the admin dashboard where you can review, approve, reject, or modify any automatic decision. You always have full control.

= What if the OpenAI API is down? =

The plugin includes multiple fallback mechanisms. If the API is temporarily unavailable, comments will be held for manual moderation to ensure you never lose legitimate comments.

= Is my data secure? =

Yes. Comments are sent to OpenAI for analysis but are not stored on their servers long-term. Your OpenAI API key is encrypted in your WordPress database, and all communications use secure HTTPS connections.

= Can I use different AI models? =

Yes! The plugin supports multiple OpenAI models including GPT-4, GPT-3.5-turbo, and others. You can choose based on your needs and budget - GPT-4 provides the most accurate moderation, while GPT-3.5 is more cost-effective for high-volume sites.

= Does this work with all comment types? =

Yes, the plugin works with standard WordPress comments, trackbacks, and pingbacks.

== Changelog ==

= 1.0.0 =
* Initial release of AI-powered comment moderation
* OpenAI GPT integration for intelligent comment analysis
* Automatic approve, reject, and spam detection
* Admin dashboard for reviewing AI decisions
* Bulk operations for comment management
* Confidence scoring and threshold settings
* Multiple AI model support (GPT-4, GPT-3.5-turbo)
* Export functionality for decision analysis
* Async processing with fallback mechanisms
* Comprehensive error handling and logging
* Modern admin interface with Tailwind CSS
* Built-in API connection testing tools

== Upgrade Notice ==

= 1.0.0 =
Initial release of WordPress Review Bot with AI-powered comment moderation.