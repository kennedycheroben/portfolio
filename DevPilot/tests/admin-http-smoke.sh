#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
PHP_BIN="${PHP_BIN:-php}"
TEMP_DIR="$(mktemp -d)"
PORT="$("$PHP_BIN" -r '$s=stream_socket_server("tcp://127.0.0.1:0", $errno, $error); echo substr(strrchr(stream_socket_get_name($s, false), ":"), 1); fclose($s);')"
APP_ROOT="$TEMP_DIR/app"
mkdir -p "$APP_ROOT"
rsync -a --exclude='.git' --exclude='.sofa' --exclude='.env' --exclude='.env.*' --exclude='DevPilot/vendor' --exclude='DevPilot/logs' "$ROOT/" "$APP_ROOT/"

cleanup() {
  if [[ -n "${SERVER_PID:-}" ]]; then kill "$SERVER_PID" 2>/dev/null || true; wait "$SERVER_PID" 2>/dev/null || true; fi
  rm -rf "$TEMP_DIR"
}
trap cleanup EXIT
mkdir -p "$TEMP_DIR/sessions"

"$PHP_BIN" -r '
$root = $argv[1];
$project = [
    "id" => 51,
    "name" => "Smoke Test Project",
    "path" => $root,
    "language" => "php",
    "framework" => "",
    "database" => null,
    "environment" => "development",
    "status" => "active",
    "adapter_status" => "pending",
    "hosting" => null,
    "created_at" => date("c"),
    "updated_at" => date("c"),
];
file_put_contents($root . "/DevPilot/registry/projects.json", json_encode(["projects" => [$project]], JSON_PRETTY_PRINT));
' "$APP_ROOT"

SESSION_SAVE_PATH="$TEMP_DIR/sessions" "$PHP_BIN" -r '
session_save_path(getenv("SESSION_SAVE_PATH"));
session_name("kennedy_portfolio");
session_id("devpilotsmoke1234567890");
session_start();
$_SESSION["user_id"] = 1;
$_SESSION["user_role"] = "admin";
session_write_close();
'

SESSION_SAVE_PATH="$TEMP_DIR/sessions" "$PHP_BIN" -S "127.0.0.1:$PORT" -t "$APP_ROOT" >"$TEMP_DIR/server.log" 2>&1 &
SERVER_PID=$!
for _ in $(seq 1 40); do
  if curl -fsS "http://127.0.0.1:$PORT/" -o /dev/null 2>/dev/null; then break; fi
  sleep 0.1
done

check_status() {
  local path="$1" cookie="$2" expected="$3"
  local status
  if [[ "$cookie" == "admin" ]]; then
    status="$(curl -sS -D "$TEMP_DIR/headers" -o "$TEMP_DIR/body" -w '%{http_code}' -H 'Cookie: kennedy_portfolio=devpilotsmoke1234567890' "http://127.0.0.1:$PORT$path")"
  else
    status="$(curl -sS -D "$TEMP_DIR/headers" -o "$TEMP_DIR/body" -w '%{http_code}' "http://127.0.0.1:$PORT$path")"
  fi
  if [[ "$status" != "$expected" ]]; then
    echo "FAIL $path expected HTTP $expected, got $status" >&2
    cat "$TEMP_DIR/server.log" >&2
    exit 1
  fi
  if [[ "$cookie" == "admin" && "$status" == 500 ]]; then
    echo "FAIL $path returned HTTP 500" >&2
    cat "$TEMP_DIR/server.log" >&2
    exit 1
  fi
  if [[ "$cookie" != "admin" && "$expected" == 303 ]] && ! grep -qi '^Location: .*login\.php' "$TEMP_DIR/headers"; then
    echo "FAIL $path did not redirect unauthenticated visitors to login" >&2
    cat "$TEMP_DIR/headers" >&2
    exit 1
  fi
}

protected_paths=(
  '/admin/devpilot.php'
  '/admin/devpilot-projects.php'
  '/admin/devpilot-project.php?id=999999'
  '/admin/devpilot-operations.php'
  '/admin/devpilot-device-delete.php'
  '/admin/api/devpilot.php'
  '/admin/devpilot-websites.php'
  '/admin/devpilot-website.php?id=1'
  '/admin/api/devpilot-websites.php'
)
for path in "${protected_paths[@]}"; do
  check_status "$path" anonymous 303
done

check_status '/admin/devpilot.php' admin 200
check_status '/admin/devpilot-projects.php' admin 200
check_status '/admin/devpilot-project.php?id=51' admin 200
check_status '/admin/devpilot-operations.php' admin 200
check_status '/admin/devpilot-device-delete.php' admin 302
check_status '/admin/api/devpilot.php' admin 200
check_status '/' anonymous 200
check_status '/admin/projects.php' anonymous 303

for path in '/admin/devpilot-device-delete.php' '/admin/devpilot-operations.php' '/admin/api/devpilot.php'; do
  status="$(curl -sS -o "$TEMP_DIR/body" -w '%{http_code}' -X POST -H 'Cookie: kennedy_portfolio=devpilotsmoke1234567890' "http://127.0.0.1:$PORT$path")"
  if [[ "$status" != 419 ]]; then
    echo "FAIL $path expected HTTP 419 without CSRF token, got $status" >&2
    cat "$TEMP_DIR/server.log" >&2
    exit 1
  fi
done

check_status '/admin/devpilot-website.php' admin 400
check_status '/admin/devpilot-website.php?id=not-an-id' admin 400
status="$(curl -sS -o "$TEMP_DIR/body" -w '%{http_code}' -X PATCH -H 'Cookie: kennedy_portfolio=devpilotsmoke1234567890' "http://127.0.0.1:$PORT/admin/api/devpilot-websites.php")"
if [[ "$status" != 405 ]] || ! grep -q 'Method is not supported' "$TEMP_DIR/body"; then
  echo "FAIL website API did not safely reject PATCH (HTTP $status)" >&2
  exit 1
fi

if [[ "${DEVPILOT_SMOKE_DB_READY:-0}" == "1" ]]; then
  check_status '/admin/devpilot-websites.php' admin 200
  check_status '/admin/devpilot-website.php?id=999999' admin 404
  check_status '/admin/api/devpilot-websites.php' admin 200
  check_status '/admin/api/devpilot-websites.php?id=not-an-id' admin 400

  status="$(curl -sS -o "$TEMP_DIR/body" -w '%{http_code}' -H 'Cookie: kennedy_portfolio=devpilotsmoke1234567890' -X POST \
    --data-urlencode 'environment=production' --data-urlencode 'username=phase1-sentinel-user' \
    --data-urlencode 'password=phase1-sentinel-password' "http://127.0.0.1:$PORT/admin/api/devpilot-websites.php")"
  if [[ "$status" != 419 ]] || grep -q 'phase1-sentinel-' "$TEMP_DIR/body"; then
    echo "FAIL website API did not reject mutation without CSRF (HTTP $status)" >&2
    exit 1
  fi

  curl -sS -H 'Cookie: kennedy_portfolio=devpilotsmoke1234567890' "http://127.0.0.1:$PORT/admin/devpilot-websites.php" -o "$TEMP_DIR/website-page.html"
  csrf_token="$(grep -o 'name="csrf_token" value="[a-f0-9]*"' "$TEMP_DIR/website-page.html" | head -n 1 | sed -E 's/.*value="([a-f0-9]+)"/\1/')"
  if [[ -z "$csrf_token" ]]; then echo 'FAIL website registration page did not provide a CSRF token' >&2; exit 1; fi

  for environment in production unknown; do
    status="$(curl -sS -o "$TEMP_DIR/body" -w '%{http_code}' -H 'Cookie: kennedy_portfolio=devpilotsmoke1234567890' -X POST \
      --data-urlencode "csrf_token=$csrf_token" --data-urlencode 'action=register' --data-urlencode 'name=Rejected environment test' \
      --data-urlencode 'admin_url=https://admin.example.org/' --data-urlencode "environment=$environment" \
      --data-urlencode 'username=phase1-sentinel-user' --data-urlencode 'password=phase1-sentinel-password' \
      "http://127.0.0.1:$PORT/admin/api/devpilot-websites.php")"
    if [[ "$status" != 422 ]] || ! grep -q 'demo or staging' "$TEMP_DIR/body" \
      || grep -q 'phase1-sentinel-' "$TEMP_DIR/body"; then
      echo "FAIL website API environment guard for $environment returned unsafe result (HTTP $status)" >&2
      exit 1
    fi
  done

  status="$(curl -sS -o "$TEMP_DIR/body" -w '%{http_code}' -H 'Cookie: kennedy_portfolio=devpilotsmoke1234567890' -X POST \
    --data-urlencode "csrf_token=$csrf_token" --data-urlencode 'action=register' --data-urlencode 'name=Failed registration field test' \
    --data-urlencode 'admin_url=https://admin.example.org/' --data-urlencode 'environment=demo' \
    --data-urlencode 'permissions[]=not_a_permission' --data-urlencode 'username=phase1-sentinel-user' \
    --data-urlencode 'password=phase1-sentinel-password' "http://127.0.0.1:$PORT/admin/devpilot-websites.php")"
  if [[ "$status" != 200 ]] || grep -q 'phase1-sentinel-' "$TEMP_DIR/body" \
    || ! grep -q 'name="username"[^>]*value=""' "$TEMP_DIR/body" \
    || ! grep -q 'name="password"[^>]*value=""' "$TEMP_DIR/body"; then
    echo "FAIL failed registration retained credential input (HTTP $status)" >&2
    exit 1
  fi
else
  echo 'SKIP website database-backed HTTP cases (set DEVPILOT_SMOKE_DB_READY=1 with an isolated local test database)'
fi

echo 'DevPilot admin HTTP smoke checks passed (unauthenticated redirects and authenticated page/API responses).'
