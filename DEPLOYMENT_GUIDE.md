# WordPress Review Bot - Deployment Guide

This guide covers both GitHub repository setup and WordPress.org plugin release deployment.

## GitHub Repository Setup

### 1. Initialize Git Repository

```bash
# Navigate to your project directory
cd /path/to/wordpress-review-bot

# Initialize git repository
git init

# Add all files
git add .

# Initial commit
git commit -m "Initial commit: WordPress Review Bot v1.0.0

🚀 Features:
- AI-powered comment moderation using OpenAI
- Admin dashboard for managing AI decisions
- Bulk operations for comment management
- Multiple AI model support
- Export functionality
- Comprehensive settings panel

🔧 Technical:
- Modern PHP 8.0+ architecture
- WordPress coding standards compliant
- Tailwind CSS admin interface
- Vite build system
- Docker development environment"

# Add remote origin (replace with your repository URL)
git remote add origin https://github.com/martindev/wordpress-review-bot.git
git branch -M main
git push -u origin main
```

### 2. Create First Release

```bash
# Tag the release
git tag -a v1.0.0 -m "Release v1.0.0: AI-Powered Comment Moderation

✨ Initial release of WordPress Review Bot with:
- OpenAI GPT integration for intelligent comment analysis
- Automatic approve, reject, and spam detection
- Admin dashboard for reviewing AI decisions
- Bulk operations for comment management
- Multiple AI model support
- Export functionality for decision analysis"

# Push tag to GitHub
git push origin v1.0.0
```

## WordPress.org Plugin Release

### Prerequisites

1. **WordPress.org Account**: You need a wordpress.org account
2. **Plugin Name Check**: Verify plugin name is available
3. **Plugin Assets**: Prepare banners and screenshots
4. **SVN Client**: Install Subversion client

### Step 1: Submit to WordPress.org

1. Go to [WordPress Plugin Developer Center](https://wordpress.org/plugins/developers/)
2. Click "Add Your Plugin"
3. Upload your plugin zip file
4. Wait for initial review (usually 1-7 days)
5. Address any feedback from the review team

### Step 2: Set Up SVN Repository

Once approved, you'll get access to an SVN repository:

```bash
# SVN repository structure
https://plugins.svn.wordpress.org/wordpress-review-bot/
├── trunk/          # Development version
├── tags/           # Stable releases
└── assets/         # Plugin banners and screenshots
```

### Step 3: Deploy Using SVN

```bash
# Checkout SVN repository
svn checkout https://plugins.svn.wordpress.org/wordpress-review-bot/ svn-repo

# Copy plugin files to trunk
rsync -av --exclude-from=.distignore plugin/ svn-repo/trunk/

# Add new files to SVN
cd svn-repo
svn add --force trunk/*
svn status

# Commit to trunk
svn commit -m "Initial commit to WordPress.org repository"

# Create tag for v1.0.0
svn copy trunk/ tags/1.0.0/
svn commit -m "Tagging version 1.0.0"
```

### Step 4: Add Plugin Assets

Create and upload assets to the `assets/` directory:

```bash
# Navigate to assets directory
cd svn-repo/assets/

# Add plugin assets
# Plugin banner: 772x250px
# Plugin icon: 128x128px or 256x256px
# Screenshots: up to 8 screenshots, max 600px wide

# Example files:
# - banner-772x250.png
# - banner-1544x500.png (retina)
# - icon-128x128.png
# - icon-256x256.png
# - screenshot-1.png
# - screenshot-2.png
# ...

svn add *
svn commit -m "Add plugin assets"
```

## Automated Deployment with GitHub Actions

### Setup GitHub Actions Secrets

Add these secrets to your GitHub repository:

1. `WORDPRESS_SVN_USERNAME`: Your WordPress.org username
2. `WORDPRESS_SVN_PASSWORD`: Your WordPress.org password (use app password)

### Create WordPress.org Deployment Workflow

Create `.github/workflows/wordpress-org.yml`:

```yaml
name: Deploy to WordPress.org

on:
  push:
    tags:
      - 'v*'

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
    - uses: actions/checkout@v4

    - name: Setup Node.js
      uses: actions/setup-node@v4
      with:
        node-version: '18'
        cache: 'npm'

    - name: Install and build
      run: |
        npm ci
        npm run build

    - name: WordPress Plugin Deploy
      uses: 10up/action-wordpress-plugin-deploy@stable
      with:
        generate-zip: false
        svn_username: ${{ secrets.WORDPRESS_SVN_USERNAME }}
        svn_password: ${{ secrets.WORDPRESS_SVN_PASSWORD }}
        path: plugin
        slug: wordpress-review-bot
```

## Release Cadence & Version Management

### Version Numbering

Use [Semantic Versioning](https://semver.org/):
- **Major (X.0.0)**: Breaking changes, major new features
- **Minor (X.Y.0)**: New features, improvements
- **Patch (X.Y.Z)**: Bug fixes, security updates

### Release Schedule

#### Major Releases (Quarterly)
- New AI model integrations
- Major feature additions
- UI/UX improvements
- Breaking changes (if any)

#### Minor Releases (Monthly)
- New features and improvements
- Performance optimizations
- Enhanced admin interface
- Additional export options

#### Patch Releases (As Needed)
- Security updates
- Bug fixes
- Compatibility issues
- API changes

### Release Process

1. **Development** (on `develop` branch)
2. **Testing** (staging environment)
3. **Release Preparation** (update version, changelog)
4. **Release** (merge to `main`, tag, deploy)
5. **Post-Release** (monitor, support)

## Development Workflow

### Branch Strategy

```
main                 # Production-ready code
├── develop         # Development branch
├── feature/*       # New features
├── hotfix/*        # Emergency fixes
└── release/*       # Release preparation
```

### Git Workflow

```bash
# Start new feature
git checkout develop
git pull origin develop
git checkout -b feature/new-ai-model

# Work on feature
# ... make changes ...
git add .
git commit -m "feat: Add support for GPT-4 Turbo"

# Push and create PR
git push origin feature/new-ai-model
# Create pull request to develop branch

# After merge, update develop
git checkout develop
git pull origin develop

# Prepare release
git checkout release/v1.1.0
git merge develop

# Test and fix issues
# ... testing ...

# Release
git checkout main
git merge release/v1.1.0
git tag v1.1.0
git push origin main v1.1.0

# Update develop
git checkout develop
git merge main
git push origin develop
```

## Monitoring & Support

### Post-Release Monitoring

1. **GitHub Issues**: Monitor bug reports and feature requests
2. **WordPress.org Forum**: Monitor support requests
3. **Analytics**: Track plugin downloads and usage
4. **Error Logs**: Monitor for critical issues

### Support Resources

1. **Documentation**: Keep README and inline docs updated
2. **FAQ**: Maintain comprehensive FAQ section
3. **Video Tutorials**: Create walkthrough videos
4. **Community**: Foster user community and contributions

### Update Announcements

1. **WordPress Admin**: Show update notices in dashboard
2. **Email Newsletter**: Notify users of major updates
3. **Blog Posts**: Announce new features and improvements
4. **Social Media**: Share release announcements

## Emergency Procedures

### Critical Bug Fix

```bash
# Create hotfix branch from main
git checkout main
git pull origin main
git checkout -b hotfix/critical-security-fix

# Fix the issue
# ... make changes ...
git add .
git commit -m "fix: Critical security vulnerability in API handling"

# Test thoroughly
# ... testing ...

# Merge and release
git checkout main
git merge hotfix/critical-security-fix
git tag v1.0.1
git push origin main v1.0.1

# Deploy to WordPress.org
# Use SVN or GitHub Actions workflow

# Merge back to develop
git checkout develop
git merge main
git push origin develop
```

### Rollback Procedure

If a critical issue is discovered post-release:

1. **Identify Issue**: Confirm scope and impact
2. **Emergency Fix**: Create hotfix release
3. **Communicate**: Notify users of issue and fix
4. **Monitor**: Watch for related issues
5. **Review**: Conduct post-mortem

## Best Practices

### Code Quality
- Always follow WordPress coding standards
- Include comprehensive tests
- Document new features thoroughly
- Review security implications

### Release Management
- Test thoroughly on multiple WordPress versions
- Verify backwards compatibility
- Update changelog with user-friendly descriptions
- Include upgrade notices for breaking changes

### User Communication
- Be transparent about issues and fixes
- Provide clear migration instructions
- Offer timely support for upgrade problems
- Collect and act on user feedback

This deployment guide ensures a smooth release process for both GitHub and WordPress.org distributions while maintaining high quality and security standards.