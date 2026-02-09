#!/bin/bash
set -e

# ──────────────────────────────────────────────
# Wait for MySQL to be ready
# ──────────────────────────────────────────────
echo "⏳ Waiting for MySQL at ${DB_HOST:-db}:3306..."

MAX_RETRIES=30
RETRY=0
until php -r "
  try {
    new PDO(
      'mysql:host=' . (getenv('DB_HOST') ?: 'db') . ';port=3306',
      getenv('DB_USER') ?: 'root',
      getenv('DB_PASS') ?: ''
    );
    echo 'ok';
  } catch (Exception \$e) {
    exit(1);
  }
" 2>/dev/null | grep -q "ok"; do
  RETRY=$((RETRY + 1))
  if [ "$RETRY" -ge "$MAX_RETRIES" ]; then
    echo "❌ MySQL not reachable after ${MAX_RETRIES} attempts. Exiting."
    exit 1
  fi
  echo "   Attempt $RETRY/$MAX_RETRIES – retrying in 2s..."
  sleep 2
done

echo "✅ MySQL is ready."

# ──────────────────────────────────────────────
# Initialize database schema
# ──────────────────────────────────────────────
echo "📦 Initializing database schema..."

php -r "
  \$host = getenv('DB_HOST') ?: 'db';
  \$name = getenv('DB_NAME') ?: 'sendrify';
  \$user = getenv('DB_USER') ?: 'root';
  \$pass = getenv('DB_PASS') ?: '';

  try {
    // Create database if it doesn't exist
    \$pdo = new PDO(\"mysql:host=\$host;charset=utf8mb4\", \$user, \$pass);
    \$pdo->exec(\"CREATE DATABASE IF NOT EXISTS \$name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci\");

    // Run schema
    \$pdo = new PDO(\"mysql:host=\$host;dbname=\$name;charset=utf8mb4\", \$user, \$pass);
    \$sql = file_get_contents('/var/www/html/schema.sql');
    \$pdo->exec(\$sql);
    echo \"✅ Schema applied.\\n\";
  } catch (Exception \$e) {
    echo '⚠️  Schema init: ' . \$e->getMessage() . \"\\n\";
  }
"

# ──────────────────────────────────────────────
# Run demo setup if DEMO_MODE is enabled
# ──────────────────────────────────────────────
if [ "${DEMO_MODE}" = "true" ]; then
  echo "🎯 Demo mode enabled – running setup_demo.php..."
  php /var/www/html/setup_demo.php || echo "⚠️  Demo setup had warnings (may already be initialized)"
fi

# ──────────────────────────────────────────────
# Set up cron for demo cleanup (if demo mode)
# ──────────────────────────────────────────────
if [ "${DEMO_MODE}" = "true" ]; then
  if command -v crontab &> /dev/null; then
    echo "*/5 * * * * /usr/local/bin/php /var/www/html/cleanup_cron.php >> /var/log/sendrify_cleanup.log 2>&1" | crontab -
    echo "🕐 Demo cleanup cron installed (every 5 min)"
  fi
fi

echo "🚀 Sendrify is starting..."
exec "$@"
