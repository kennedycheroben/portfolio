#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
MYSQL_BIN="${MYSQL_BIN:-/opt/lampp/bin/mysql}"
MYSQLADMIN_BIN="${MYSQLADMIN_BIN:-/opt/lampp/bin/mysqladmin}"
MYSQL_INSTALL_DB="${MYSQL_INSTALL_DB:-/opt/lampp/bin/mysql_install_db}"
MYSQLD_BIN="${MYSQLD_BIN:-/opt/lampp/sbin/mysqld}"
TEMP_DIR="$(mktemp -d)"
DATA_DIR="$TEMP_DIR/data"
SOCKET="$TEMP_DIR/mysql.sock"
PID_FILE="$TEMP_DIR/mysql.pid"
LOG_FILE="$TEMP_DIR/mysql.log"
CURRENT_USER="$(id -un)"

cleanup() {
  if [[ -S "$SOCKET" ]]; then "$MYSQLADMIN_BIN" --protocol=SOCKET --socket="$SOCKET" -uroot shutdown >/dev/null 2>&1 || true; fi
  if [[ -n "${SERVER_PID:-}" ]]; then kill "$SERVER_PID" 2>/dev/null || true; wait "$SERVER_PID" 2>/dev/null || true; fi
  rm -rf "$TEMP_DIR"
}
trap cleanup EXIT

mkdir -p "$DATA_DIR"
"$MYSQL_INSTALL_DB" --no-defaults --datadir="$DATA_DIR" --basedir=/opt/lampp --auth-root-authentication-method=normal --user="$CURRENT_USER" --skip-test-db >/dev/null
"$MYSQLD_BIN" --no-defaults --user="$CURRENT_USER" --basedir=/opt/lampp --datadir="$DATA_DIR" --socket="$SOCKET" --pid-file="$PID_FILE" --skip-networking --log-error="$LOG_FILE" &
SERVER_PID=$!
for _ in $(seq 1 100); do
  if "$MYSQLADMIN_BIN" --protocol=SOCKET --socket="$SOCKET" -uroot ping >/dev/null 2>&1; then break; fi
  if ! kill -0 "$SERVER_PID" 2>/dev/null; then cat "$LOG_FILE" >&2; exit 1; fi
  sleep 0.1
done
"$MYSQLADMIN_BIN" --protocol=SOCKET --socket="$SOCKET" -uroot ping >/dev/null

mysql_root() { "$MYSQL_BIN" --protocol=SOCKET --socket="$SOCKET" -uroot "$@"; }
create_db() {
  local db="$1" id_type="${2:-INT}"
  mysql_root -e "CREATE DATABASE \`$db\`; CREATE TABLE \`$db\`.users (id $id_type NOT NULL AUTO_INCREMENT, username VARCHAR(50) NOT NULL, email VARCHAR(100) NOT NULL, password_hash VARCHAR(255) NOT NULL, role VARCHAR(16) NOT NULL DEFAULT 'user', created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
}
run_migration() { mysql_root "$1" < "$ROOT/migrations/003_devpilot_websites.sql"; }
expect_migration_failure() {
  local db="$1" output="$TEMP_DIR/failure.log"
  if run_migration "$db" >"$output" 2>&1; then
    echo "FAIL migration unexpectedly accepted incompatible schema in $db" >&2
    exit 1
  fi
  if ! grep -q '__devpilot_migration_003_schema_incompatible__' "$output"; then
    echo "FAIL migration did not report the schema incompatibility sentinel for $db" >&2
    cat "$output" >&2
    exit 1
  fi
}
assert_table_count() {
  local db="$1" expected="$2" actual
  actual="$(mysql_root -N -e "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='$db' AND TABLE_NAME LIKE 'devpilot_%'")"
  [[ "$actual" == "$expected" ]] || { echo "FAIL $db expected $expected DevPilot tables, got $actual" >&2; exit 1; }
}

create_db devpilot_migration_fresh
run_migration devpilot_migration_fresh >/dev/null
assert_table_count devpilot_migration_fresh 4
run_migration devpilot_migration_fresh >/dev/null
assert_table_count devpilot_migration_fresh 4
echo 'PASS fresh migration, structural verification, and idempotent rerun'

create_db devpilot_migration_bad_engine
mysql_root -e 'CREATE TABLE devpilot_migration_bad_engine.devpilot_websites (id INT) ENGINE=MyISAM'
expect_migration_failure devpilot_migration_bad_engine
assert_table_count devpilot_migration_bad_engine 1
echo 'PASS incompatible pre-existing engine rejected before other DDL'

create_db devpilot_migration_bad_user 'BIGINT UNSIGNED'
expect_migration_failure devpilot_migration_bad_user
assert_table_count devpilot_migration_bad_user 0
echo 'PASS incompatible users.id type rejected before DDL'

create_db devpilot_migration_bad_user_key
mysql_root -e 'ALTER TABLE devpilot_migration_bad_user_key.users DROP PRIMARY KEY, MODIFY id INT NOT NULL'
expect_migration_failure devpilot_migration_bad_user_key
assert_table_count devpilot_migration_bad_user_key 0
echo 'PASS users.id without a primary key/auto-increment contract rejected before DDL'

create_db devpilot_migration_bad_column
run_migration devpilot_migration_bad_column >/dev/null
mysql_root -e 'ALTER TABLE devpilot_migration_bad_column.devpilot_websites MODIFY name INT NOT NULL'
expect_migration_failure devpilot_migration_bad_column
assert_table_count devpilot_migration_bad_column 4
echo 'PASS expected column names with an incompatible type rejected'

create_db devpilot_migration_bad_index
run_migration devpilot_migration_bad_index >/dev/null
mysql_root -e 'ALTER TABLE devpilot_migration_bad_index.devpilot_websites DROP INDEX idx_devpilot_websites_owner_status'
expect_migration_failure devpilot_migration_bad_index
echo 'PASS missing expected index rejected'

create_db devpilot_migration_bad_fk
run_migration devpilot_migration_bad_fk >/dev/null
mysql_root -e 'ALTER TABLE devpilot_migration_bad_fk.devpilot_websites DROP FOREIGN KEY fk_devpilot_websites_owner'
expect_migration_failure devpilot_migration_bad_fk
echo 'PASS missing expected foreign key rejected'
