php artisan serve
php artisan queue:work
yarn build --watch

yarn dev:all


tree -I "node_modules|vendor|storage|.git|*.json|*.log|*.lock|bootstrap/cache" -L 4

tar -czf repo-clean.tar.gz \
--exclude=.git \
--exclude='**/.git' \
--exclude=.history \
--exclude='**/.history' \
--exclude=node_modules \
--exclude=vendor \
--exclude=storage \
--exclude=bootstrap/cache \
--exclude=.env \
--exclude='.env*' \
--exclude=.DS_Store \
--exclude='**/.DS_Store' \
--exclude=*.log \
--exclude=*.lock \
--exclude=*.tar.gz \
--exclude=*.gz \
--exclude=*.zip \
--exclude=*.tmp \
--exclude=*.bak \
--exclude=*.swp \
--exclude=*.swo \
--exclude=*.pid \
--exclude=*.seed \
--exclude=*.sqlite \
.
