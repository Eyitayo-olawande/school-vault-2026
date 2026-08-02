#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# pull-prod-db.sh
# Syncs production DB to local XAMPP via SSH + mysqldump.
# No PHP endpoint needed — reads prod credentials from the server's own config.
#
# Run:
#   bash /Applications/XAMPP/xamppfiles/htdocs/schoolvault/sync/pull-prod-db.sh
# ─────────────────────────────────────────────────────────────────────────────

set -euo pipefail

# ── Config — edit SSH_HOST to your server's SSH hostname or IP ───────────────
SSH_USER="clemmyschools"
SSH_HOST="${SCHOOLVAULT_SSH_HOST:-}"          # set env var, or hardcode below
# SSH_HOST="your-server-hostname-or-ip"       # uncomment and fill in if needed
SSH_KEY="/Users/eyitayofalana/.ssh/id_ed25519"
REMOTE_APP_ROOT="~"                           # web root (home dir on this host)

LOCAL_DB="schoolvault-4-jul-26"
MYSQL_BIN="/Applications/XAMPP/xamppfiles/bin/mysql"
DUMP_FILE="/tmp/schoolvault_prod_$(date +%Y%m%d_%H%M%S).sql"

# ── Validate SSH host ─────────────────────────────────────────────────────────
if [[ -z "$SSH_HOST" ]]; then
    echo "ERROR: Set your SSH hostname."
    echo "  Option 1 — env var:  SCHOOLVAULT_SSH_HOST=your-host bash pull-prod-db.sh"
    echo "  Option 2 — edit SSH_HOST in this script directly"
    exit 1
fi

SSH_CMD="ssh -i $SSH_KEY -o StrictHostKeyChecking=no ${SSH_USER}@${SSH_HOST}"

# ── Read prod DB credentials from server's CI config ─────────────────────────
echo "→ Reading production DB credentials…"
DB_INFO=$($SSH_CMD "php -r \"
  define('BASEPATH','dummy');
  \\\$db=[]; \\\$active_group='default'; \\\$query_builder=true;
  require '${REMOTE_APP_ROOT}/application/config/database.php';
  \\\$c=\\\$db['default'];
  echo \\\$c['username'].'|'.\\\$c['password'].'|'.\\\$c['database'];
\"")

IFS='|' read -r DB_USER DB_PASS DB_NAME <<< "$DB_INFO"
if [[ -z "$DB_NAME" ]]; then
    echo "ERROR: Could not read production DB credentials"
    exit 1
fi
echo "   Database: $DB_NAME (user: $DB_USER)"

# ── Dump production DB over SSH ───────────────────────────────────────────────
echo "→ Dumping production DB via SSH (this may take a minute)…"
$SSH_CMD "mysqldump \
    --single-transaction \
    --routines \
    --triggers \
    --add-drop-table \
    -u \"$DB_USER\" \
    -p\"$DB_PASS\" \
    \"$DB_NAME\"" > "$DUMP_FILE"

DUMP_SIZE=$(du -sh "$DUMP_FILE" | cut -f1)
echo "   Dump complete: $DUMP_FILE ($DUMP_SIZE)"

# ── Sanity check ──────────────────────────────────────────────────────────────
if ! grep -q "DROP TABLE" "$DUMP_FILE" 2>/dev/null; then
    echo "ERROR: dump looks empty or malformed"
    rm -f "$DUMP_FILE"
    exit 1
fi

# ── Import into local DB ──────────────────────────────────────────────────────
echo "→ Dropping and recreating local DB: $LOCAL_DB"
"$MYSQL_BIN" -u root -e "
    DROP DATABASE IF EXISTS \`${LOCAL_DB}\`;
    CREATE DATABASE \`${LOCAL_DB}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
"

echo "→ Importing…"
"$MYSQL_BIN" -u root "$LOCAL_DB" < "$DUMP_FILE"

echo "→ Cleaning up"
rm -f "$DUMP_FILE"

echo ""
echo "✓ Done. Local '$LOCAL_DB' now mirrors production."
echo "  Reload http://localhost/schoolvault to test."
