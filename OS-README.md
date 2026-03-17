php artisan serve
php artisan queue:work
yarn build --watch

yarn dev:all


tree -I "node_modules|vendor|storage|.git|*.json|*.log|*.lock|bootstrap/cache" -L 4

tar -czf repo-clean.tar.gz \
--exclude=.git \
--exclude=node_modules \
--exclude=vendor \
--exclude=storage \
--exclude=bootstrap/cache \
--exclude=*.json \
--exclude=*.log \
--exclude=*.lock \
--exclude=*.tar.gz \
--exclude=*.gz \
.
