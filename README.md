# AAV Utilidades - Git Ignore File
# Version: 3.0

# OS generated files
.DS_Store
.DS_Store?
._*
.Spotlight-V100
.Trashes
ehthumbs.db
Thumbs.db

# Editor directories and files
.idea/
.vscode/
*.swp
*.swo
*~
.project
.settings/
*.sublime-project
*.sublime-workspace

# WordPress specific
wp-config.php
wp-content/advanced-cache.php
wp-content/backup-db/
wp-content/backups/
wp-content/blogs.dir/
wp-content/cache/
wp-content/upgrade/
wp-content/uploads/
wp-content/mu-plugins/
wp-content/wp-cache-config.php
wp-content/plugins/hello.php

# WordPress debug logs
*.log
wp-content/debug.log

# Environment files
.env
.env.local
.env.*.local

# Development
/vendor/
/node_modules/
/bower_components/

# Compiled files
*.min.css
*.min.js
*.map

# Backup files
*.bak
*.backup
*.old
*.orig
*.tmp

# Build directories
/build/
/dist/
/releases/

# Test files
/tests/coverage/
.phpunit.result.cache

# Local development
/local-config.php
*.sql
*.zip
*.tar.gz

# Plugin specific ignores
/aav-utilidades-dev/
/aav-utilidades-backup/

# Documentation builds
/docs/_build/
/docs/.doctrees/

# Temporary files
*.temp
*.cache
.sass-cache/

# Package files
composer.lock
package-lock.json
yarn.lock

# IDE Helper files
_ide_helper.php
.phpstorm.meta.php

# System files
desktop.ini

# Security - Never commit these
/secrets/
/private/
*.pem
*.key
*.crt