#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# pull-prod-db.sh
# Downloads the production DB and imports it into your local XAMPP instance.
#
# Run from anywhere:
#   bash /Applications/XAMPP/xamppfiles/htdocs/schoolvault/sync/pull-prod-db.sh
# ─────────────────────────────────────────────────────────────────────────────

set -euo pipefail

# ── Config ────────────────────────────────────────────────────────────────────
PROD_URL="https://schoolvault.clemmyschools.com/sync/db_dump.php"
TOKEN_FILE="$(dirname "$0")/.token"
LOCAL_DB="schoolvault-4-jul-26"          # local DB name (from database.php)
MYSQL_BIN="/Applications/XAMPP/xamppfiles/bin/mysql"
DUMP_FILE="/tmp/schoolvault_prod_$(date +%Y%m%d_%H%M%S).sql"

# ── Read token ────────────────────────────────────────────────────────────────
if [[ ! -f "$TOKEN_FILE" ]]; then
    echo "ERROR: .token file not found at $TOKEN_FILE"
    exit 1
fi
TOKEN=$(tr -d '[:space:]' < "$TOKEN_FILE")

# ── Download dump ─────────────────────────────────────────────────────────────
echo "→ Downloading production DB dump…"
HTTP_STATUS=$(curl -s -w "%{http_code}" \
    --compressed \
    --max-time 600 \
    --retry 2 \
    -o "$DUMP_FILE" \
    "${PROD_URL}?token=${TOKEN}")

if [[ "$HTTP_STATUS" != "200" ]]; then
    echo "ERROR: HTTP $HTTP_STATUS from dump endpoint"
    cat "$DUMP_FILE"
    rm -f "$DUMP_FILE"
    exit 1
fi

DUMP_SIZE=$(du -sh "$DUMP_FILE" | cut -f1)
echo "   Downloaded: $DUMP_FILE ($DUMP_SIZE)"

# ── Sanity-check the dump ─────────────────────────────────────────────────────
if ! grep -q "SET FOREIGN_KEY_CHECKS" "$DUMP_FILE"; then
    echo "ERROR: dump file looks malformed (missing SQL header)"
    rm -f "$DUMP_FILE"
    exit 1
fi

# ── Import ────────────────────────────────────────────────────────────────────
echo "→ Dropping and recreating local DB: $LOCAL_DB"
"$MYSQL_BIN" -u root -e "
    DROP DATABASE IF EXISTS \`${LOCAL_DB}\`;
    CREATE DATABASE \`${LOCAL_DB}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
" 2>&1

echo "→ Importing dump (this may take a minute)…"
"$MYSQL_BIN" -u root "$LOCAL_DB" < "$DUMP_FILE"

echo "→ Cleaning up temp file"
rm -f "$DUMP_FILE"

# ── Post-import patches ───────────────────────────────────────────────────────
# Nothing to patch: the production DB already has the correct active session,
# and local config (database.php) already points to this DB name.
# If you need to adjust settings after import, add them here.

echo ""
echo "✓ Local DB '$LOCAL_DB' now mirrors production."
echo "  Reload http://localhost/schoolvault to test."
