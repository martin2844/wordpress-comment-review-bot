# WordPress Review Bot - Modern Development Setup

A modern WordPress plugin development environment with hot reloading, Docker integration, and 2025 best practices.

## 🚀 Features

- **Hot Reloading**: Live CSS and JavaScript updates during development
- **Docker Environment**: Complete WordPress development stack with Docker Compose
- **Modern Tooling**: Vite for JavaScript bundling, Tailwind CSS for styling
- **WordPress 6.0+ Ready**: Compatible with the latest WordPress versions
- **PHP 8.0+**: Modern PHP code structure and standards
- **Database Management**: Includes phpMyAdmin for easy database access

## 📋 Prerequisites

- Docker and Docker Compose
- Node.js 18+ and npm
- Git

## 🛠️ Quick Start

1. **Clone and install dependencies:**
   ```bash
   git clone <your-repo-url>
   cd wordpress-review-bot
   npm install
   ```

2. **Start the development environment:**
   ```bash
   npm run dev
   ```

   This will:
   - Start Docker containers (WordPress, MySQL, phpMyAdmin)
   - Begin watching CSS and JavaScript files for changes
   - Enable hot reloading

3. **Access your development sites:**
   - WordPress: http://localhost:8080
   - phpMyAdmin: http://localhost:8081
   - Database credentials:
     - Host: `localhost:3306`
     - User: `wordpress`
     - Password: `wordpress`

## 📁 Project Structure

```
wordpress-review-bot/
├── plugin/                 # WordPress plugin files
│   ├── wordpress-review-bot.php
│   ├── assets/            # Compiled CSS/JS
│   ├── admin/             # Admin-specific files
│   ├── public/            # Frontend files
│   └── includes/          # Core plugin functions
├── src/                   # Development source files
│   ├── css/
│   └── js/
├── mysql/                 # Database initialization
├── docker-compose.yml     # Docker configuration
├── wp-config.php         # WordPress configuration
├── vite.config.js        # Vite bundler configuration
├── tailwind.config.js    # Tailwind CSS configuration
└── package.json          # Node dependencies and scripts
```

## 🔧 Development Workflow

### Making Changes

1. **CSS Changes**: Edit files in `src/css/input.css` - changes will be compiled to `plugin/assets/css/`
2. **JavaScript Changes**: Edit files in `src/js/` - changes will be bundled to `plugin/assets/js/`
3. **PHP Changes**: Edit files directly in `plugin/` - no build process needed

### Available Scripts

```bash
# Start full development environment
npm run dev

# Start/stop Docker containers
npm run docker:up
npm run docker:down

# View Docker logs
npm run docker:logs

# Build assets for production
npm run build

# Watch CSS changes only
npm run watch:css

# Watch JavaScript changes only
npm run watch:js

# WordPress CLI commands
npm run wp:cli <command>
npm run wp:plugins:list
npm run wp:plugin:activate
```

## 🐳 Docker Services

The development environment includes three services:

1. **WordPress** (port 8080): Latest WordPress with debug mode enabled
2. **MySQL 8.0** (port 3306): Database server with persistent storage
3. **phpMyAdmin** (port 8081): Web-based database management

## 🎨 Styling with Tailwind CSS

The project uses Tailwind CSS with WordPress-specific customizations:

- Custom colors for WordPress UI (`wp-blue`, `wp-dark`, `wp-gray`)
- WordPress system font stack
- Pre-built component classes for common plugin UI elements

## 📝 Plugin Development

### Basic Plugin Structure

The main plugin file (`plugin/wordpress-review-bot.php`) includes:

- Proper WordPress plugin header
- Class-based architecture
- Hook registration for initialization
- Asset loading for admin and frontend
- Activation/deactivation hooks

### Adding New Features

1. **Admin Pages**: Create PHP files in `plugin/admin/`
2. **Frontend Features**: Create PHP files in `plugin/public/`
3. **Core Functions**: Add to `plugin/includes/`
4. **Assets**: Place compiled CSS/JS in `plugin/assets/`

## 🔧 Configuration

### WordPress Configuration

- Debug mode enabled for development
- Development environment type set
- Error logging enabled
- Script debugging enabled

### Vite Configuration

- Builds JavaScript for admin and frontend separately
- Supports hot module replacement
- Outputs to plugin assets directory

### Tailwind Configuration

- Watches PHP files for class usage
- WordPress-specific color palette
- Custom component classes

## 🚀 Production Deployment

1. **Build assets:**
   ```bash
   npm run build
   ```

2. **Deploy plugin folder** to your WordPress site

3. **Activate plugin** in WordPress admin

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## 📚 Additional Resources

- [WordPress Plugin Developer Handbook](https://developer.wordpress.org/plugins/)
- [Tailwind CSS Documentation](https://tailwindcss.com/docs)
- [Vite Documentation](https://vitejs.dev/)
- [Docker Documentation](https://docs.docker.com/)

## 🐛 Troubleshooting

### Common Issues

**Port conflicts**: If ports 8080, 8081, or 3306 are in use, modify them in `docker-compose.yml`

**Permission issues**: Ensure Docker has proper permissions to bind mount volumes

**Hot reloading not working**: Check that the build processes are running and watch for errors in the terminal

**Database connection issues**: Ensure MySQL container is running and credentials are correct

### Getting Help

- Check Docker logs: `npm run docker:logs`
- Verify file permissions in plugin directory
- Ensure all Node dependencies are installed
- Check WordPress debug log for PHP errors