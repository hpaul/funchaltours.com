#!/bin/sh
set -eu

APP_DIR=${CONTENT_SYNC_APP_DIR:-/var/www/html}
SYNC_DIR=${CONTENT_SYNC_WORKTREE:-/var/www/content-edits-sync}
REMOTE=${CONTENT_SYNC_REMOTE:-origin}
BRANCH=${CONTENT_SYNC_BRANCH:-site-live}
DEFAULT_BRANCH=${CONTENT_SYNC_DEFAULT_BRANCH:-main}
COMMIT_MESSAGE_PREFIX=${CONTENT_SYNC_COMMIT_MESSAGE_PREFIX:-Sync content edits}

cd "$APP_DIR"

get_watch_paths() {
    php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); foreach (config("statamic.git.paths", []) as $path) { if (file_exists($path)) echo $path, PHP_EOL; }'
}

get_git_identity() {
    php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); echo config("statamic.git.user.name", "Statamic Bot"), PHP_EOL, config("statamic.git.user.email", "bot@example.com"), PHP_EOL;'
}

WATCH_PATHS=$(get_watch_paths)

[ -n "$WATCH_PATHS" ] || exit 0

WATCH_ARGS=""
for path in $WATCH_PATHS; do
    rel=${path#"$APP_DIR"/}
    WATCH_ARGS="$WATCH_ARGS $rel"
done

STATUS_OUTPUT=$(git status --porcelain --untracked-files=all -- $WATCH_ARGS)

[ -n "$STATUS_OUTPUT" ] || exit 0

REMOTE_URL=$(git remote get-url "$REMOTE")

if [ ! -d "$SYNC_DIR/.git" ]; then
    rm -rf "$SYNC_DIR"
    git clone "$REMOTE_URL" "$SYNC_DIR"
fi

git -C "$SYNC_DIR" fetch "$REMOTE" --prune

if git -C "$SYNC_DIR" ls-remote --exit-code --heads "$REMOTE" "$BRANCH" >/dev/null 2>&1; then
    git -C "$SYNC_DIR" checkout -B "$BRANCH" "$REMOTE/$BRANCH"
else
    git -C "$SYNC_DIR" checkout -B "$BRANCH" "$REMOTE/$DEFAULT_BRANCH"
fi

printf '%s\n' "$STATUS_OUTPUT" | while IFS= read -r line; do
    [ -n "$line" ] || continue

    status=$(printf '%s' "$line" | cut -c1-2)
    path=$(printf '%s' "$line" | cut -c4-)

    case "$status" in
        R*|*R)
            old_path=${path%% -> *}
            new_path=${path##* -> }
            rm -rf "$SYNC_DIR/$old_path"
            mkdir -p "$(dirname "$SYNC_DIR/$new_path")"
            cp -a "$APP_DIR/$new_path" "$SYNC_DIR/$new_path"
            ;;
        D*|*D)
            rm -rf "$SYNC_DIR/$path"
            ;;
        *)
            mkdir -p "$(dirname "$SYNC_DIR/$path")"
            cp -a "$APP_DIR/$path" "$SYNC_DIR/$path"
            ;;
    esac
done

git -C "$SYNC_DIR" add -A .

if git -C "$SYNC_DIR" diff --cached --quiet; then
    exit 0
fi

GIT_IDENTITY=$(get_git_identity)
GIT_NAME=$(printf '%s\n' "$GIT_IDENTITY" | sed -n '1p')
GIT_EMAIL=$(printf '%s\n' "$GIT_IDENTITY" | sed -n '2p')
TIMESTAMP=$(date -u '+%Y-%m-%d %H:%M:%S UTC')

git -C "$SYNC_DIR" -c "user.name=$GIT_NAME" -c "user.email=$GIT_EMAIL" commit -m "$COMMIT_MESSAGE_PREFIX - $TIMESTAMP [BOT]"
git -C "$SYNC_DIR" push -u "$REMOTE" "$BRANCH"
