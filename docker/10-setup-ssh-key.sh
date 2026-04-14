#!/bin/sh

# Write SSH deploy key from env var if provided
if [ -n "$SSH_DEPLOY_KEY" ]; then
    mkdir -p /var/www/.ssh
    echo "$SSH_DEPLOY_KEY" | base64 -d > /var/www/.ssh/id_ed25519
    chmod 700 /var/www/.ssh
    chmod 600 /var/www/.ssh/id_ed25519
    unset SSH_DEPLOY_KEY
fi

mkdir -p \
    /var/www/html/bootstrap/cache \
    /var/www/html/storage/framework/cache/data \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/logs \
    /var/www/html/storage/statamic

if [ ! -f /var/www/html/bootstrap/cache/.gitignore ]; then
    cat <<'EOF' > /var/www/html/bootstrap/cache/.gitignore
*
!.gitignore
EOF
fi

if [ ! -f /var/www/html/storage/framework/cache/.gitignore ]; then
    cat <<'EOF' > /var/www/html/storage/framework/cache/.gitignore
*
!data/
!.gitignore
EOF
fi

for file in \
    /var/www/html/storage/framework/cache/data/.gitignore \
    /var/www/html/storage/framework/sessions/.gitignore \
    /var/www/html/storage/framework/views/.gitignore \
    /var/www/html/storage/logs/.gitignore \
    /var/www/html/storage/statamic/.gitignore
do
    if [ ! -f "$file" ]; then
        cat <<'EOF' > "$file"
*
!.gitignore
EOF
    fi
done
