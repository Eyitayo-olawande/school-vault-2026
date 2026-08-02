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
SSH_HOST="nc-ph-1918-75.designstreamsltd.com"
SSH_KEY="/Users/eyitayofalana/.ssh/id_ed25519_schoolvault"
REMOTE_APP_ROOT="~"                           # web root is home dir on this host

LOCAL_DB="schoolvault-4-jul-26"
MYSQL_BIN="/Applications/XAMPP/xamppfiles/bin/mysql"
DUMP_FILE="/tmp/schoolvault_prod_$(date +%Y%m%d_%H%M%S).sql"

SSH_CMD="ssh -i $SSH_KEY -o StrictHostKeyChecking=no ${SSH_USER}@${SSH_HOST}"

# ── Read prod DB credentials by fetching and parsing CI config locally ────────
echo "→ Reading production DB credentials…"
DB_CONFIG=$($SSH_CMD "cat ~/application/config/database.php")
DB_USER=$(echo "$DB_CONFIG" | sed -n "s/.*'username'[[:space:]]*=>[[:space:]]*'\([^']*\)'.*/\1/p" | head -1)
DB_PASS=$(echo "$DB_CONFIG" | sed -n "s/.*'password'[[:space:]]*=>[[:space:]]*'\([^']*\)'.*/\1/p" | head -1)
DB_NAME=$(echo "$DB_CONFIG" | sed -n "s/.*'database'[[:space:]]*=>[[:space:]]*'\([^']*\)'.*/\1/p" | head -1)

if [[ -z "$DB_NAME" ]]; then
    echo "ERROR: Could not parse production DB credentials from config"
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
# Strip MariaDB-specific /*M!...*/ comments that MySQL client rejects
grep -v '^/\*M!' "$DUMP_FILE" | "$MYSQL_BIN" -u root "$LOCAL_DB"

echo "→ Cleaning up"
rm -f "$DUMP_FILE"

echo ""
echo "✓ Done. Local '$LOCAL_DB' now mirrors production."
echo "  Reload http://localhost/schoolvault to test."
