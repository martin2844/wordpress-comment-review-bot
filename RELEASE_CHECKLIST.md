# WordPress Review Bot - WordPress.org Release Checklist

## Pre-Release Checklist ✅

### Code Quality & Standards
- [ ] Code follows WordPress Coding Standards
- [ ] All PHP files pass syntax checks (`php -l`)
- [ ] No PHP errors, warnings, or notices
- [ ] JavaScript is properly minified (production builds only)
- [ ] CSS is properly minified (production builds only)
- [ ] All text strings are internationalized (`__()`, `_e()`, etc.)
- [ ] Plugin headers are complete and accurate

### Security Review
- [ ] All user input is properly sanitized and escaped
- [ ] Database queries use `$wpdb->prepare()`
- [ ] Nonces are used for all forms and AJAX requests
- [ ] User capabilities are checked before performing actions
- [ ] File operations include proper validation
- [ ] API keys are stored securely (encrypted)

### Plugin Structure
- [ ] Main plugin file has proper headers
- [ ] `readme.txt` follows WordPress.org guidelines
- [ ] LICENSE file is included
- [ ] Uninstall function is implemented (if needed)
- [ ] No debug code or development tools left in
- [ ] All dependencies are properly declared

### Testing
- [ ] Tested on latest WordPress version
- [ ] Tested on minimum supported WordPress version
- [ ] Tested with PHP 8.0+
- [ ] Tested with different themes
- [ ] Tested with common plugins (no conflicts)
- [ ] All functionality works as expected

## WordPress.org Submission Requirements ✅

### Required Files
- [ ] Main plugin file with proper headers
- [ ] `readme.txt` in WordPress.org format
- [ ] LICENSE file
- [ ] All assets and dependencies included

### Plugin Headers (Complete)
```php
/**
 * Plugin Name: WordPress Review Bot
 * Plugin URI: https://github.com/martindev/wordpress-review-bot
 * Description: AI-powered comment moderation for WordPress using OpenAI
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Author: Martin Dev
 * Author URI: https://github.com/martindev
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: wordpress-review-bot
 * Domain Path: /languages
 */
```

### Readme.txt Requirements
- [ ] Short description (≤ 150 characters)
- [ ] Detailed description with features
- [ ] Installation instructions
- [ ] Frequently Asked Questions section
- [ ] Changelog with version information
- [ ] Screenshots (at least 1, up to 8)
- [ ] Compatible up to version
- [ ] Stable tag
- [ ] Tags (relevant keywords)

### Screenshots Required
1. **Admin Dashboard** - Main settings page showing OpenAI configuration
2. **AI Decisions List** - Table showing moderated comments with AI decisions
3. **Settings Panel** - Configuration options for AI models and thresholds
4. **Test Results** - Built-in testing tool showing successful API connection
5. **Bulk Actions** - Interface for bulk comment management
6. **Export Feature** - Export dialog for AI decisions
7. **Comment Analysis** - Detailed view of AI reasoning for a specific comment

## Internationalization & Translation

### Translation Ready
- [ ] All text strings wrapped in translation functions
- [ ] Text domain is `wordpress-review-bot`
- [ ] `.pot` file generated and included
- [ ] Languages directory structure exists (`/languages/`)
- [ ] Sample translation files included

### Translation Commands
```bash
# Generate .pot file
wp i18n make-pot plugin/ languages/wordpress-review-bot.pot --domain=wordpress-review-bot
```

## Performance & Compatibility

### Performance
- [ ] Plugin doesn't slow down site loading
- [ ] Database queries are optimized
- [ ] External API calls have proper timeout handling
- [ ] Asset loading is optimized (only load where needed)
- [ ] Caching implemented where appropriate

### Compatibility
- [ ] Works with standard WordPress themes
- [ ] No conflicts with common plugins
- [ ] Compatible with multisite installations
- [ ] PHP 8.0+ compatibility verified
- [ ] WordPress 6.0+ compatibility verified

## Security & Privacy

### Data Privacy
- [ ] Privacy policy included in documentation
- [ ] User data handling explained
- [ ] OpenAI API key storage is secure
- [ ] No sensitive data exposed in frontend
- [ ] GDPR compliance considerations

### Security Best Practices
- [ ] Input validation and sanitization
- [ ] Output escaping
- [ ] CSRF protection with nonces
- [ ] Capability checks for admin functions
- [ ] Rate limiting for API calls
- [ ] Error handling doesn't expose sensitive information

## Release Preparation

### Version Bumping
- [ ] Version number updated in main plugin file
- [ ] Version number updated in `readme.txt`
- [ ] Changelog updated with new features and fixes
- [ ] Git tag created for release

### Asset Preparation
- [ ] All production assets built
- [ ] Development files excluded (using `.distignore`)
- [ ] Release package tested on clean installation

## Post-Release Checklist

### WordPress.org
- [ ] Plugin submitted to WordPress.org repository
- [ ] Screenshots uploaded (max 600px wide)
- [ ] Plugin banner created (772x250px)
- [ ] Plugin icon created (128x128px)
- [ ] Support forum monitoring setup

### GitHub
- [ ] Release created on GitHub
- [ ] Downloadable zip file attached
- [ ] Release notes published
- [ ] Issues and PR templates ready

### Ongoing Maintenance
- [ ] Support monitoring plan
- [ ] Update schedule established
- [ ] User feedback collection system
- [ ] Bug tracking and prioritization

## Quick Release Commands

### Create Release Package
```bash
# Build production assets
npm run build

# Create release package
mkdir release
rsync -av --exclude-from=.distignore plugin/ release/wordpress-review-bot/
cd release
zip -r ../wordpress-review-bot.zip wordpress-review-bot/
cd ..

# Generate checksum
sha256sum wordpress-review-bot.zip > wordpress-review-bot.zip.sha256
```

### WordPress.org Submission
1. Go to https://wordpress.org/plugins/developers/
2. Upload your plugin zip file
3. Wait for review (typically 1-7 days)
4. Address any feedback from the review team
5. Once approved, manage updates through SVN

## Important Notes

- **API Key Requirement**: Users must provide their own OpenAI API key
- **Cost Transparency**: Be clear about potential OpenAI API costs
- **Error Handling**: Graceful degradation when OpenAI API is unavailable
- **User Control**: Always allow users to override AI decisions
- **Privacy**: Be transparent about how comment data is processed

## Support Documentation

Prepare these support resources:
- [ ] Installation guide with screenshots
- [ ] Configuration tutorial
- [ ] Troubleshooting guide
- [ ] FAQ documentation
- [ ] Video tutorial (optional but recommended)