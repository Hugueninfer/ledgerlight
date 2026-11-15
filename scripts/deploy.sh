#!/usr/bin/env bash
# Runs on the VM: TAG=<commit sha> /opt/ledgerlight/scripts/deploy.sh
# Optional overrides (local checks): ENV_FILE, SKIP_PULL=1, HEALTH_URL, HEALTH_CURL_OPTS.
# Pulls the images for TAG, restarts the stack, migrates, warms caches and checks health.
set -euo pipefail

# Everything lives in main() so bash has parsed the whole script before `git checkout`
# can replace this file with the deployed commit's version.
main() {
  cd "$(dirname "$0")/.."

  : "${TAG:?Set TAG to the image tag (commit sha) to deploy}"
  ENV_FILE="${ENV_FILE:-.env}"
  [ -f "$ENV_FILE" ] || { echo "Missing $ENV_FILE (see deploy/production.env.example)" >&2; exit 1; }

  # Keep compose files, Caddyfile and this script in sync with the deployed commit.
  if [ -d .git ] && [[ "$TAG" =~ ^[0-9a-f]{7,40}$ ]]; then
    git fetch --quiet origin
    git checkout --quiet --detach "$TAG"
  fi

  export TAG ENV_FILE
  APP_DOMAIN="$(sed -n 's/^APP_DOMAIN=//p' "$ENV_FILE" | tail -n1)"
  : "${APP_DOMAIN:?APP_DOMAIN missing in $ENV_FILE}"

  dc() { docker compose --env-file "$ENV_FILE" -f compose.yaml -f compose.prod.yaml "$@"; }

  echo "Deploying $TAG to $APP_DOMAIN"
  [ "${SKIP_PULL:-0}" = 1 ] || dc pull api web
  dc up -d --wait --remove-orphans
  dc exec -T api php artisan migrate --force
  # Demo tenants and personas (idempotent) until sandbox sessions replace them.
  dc exec -T api php artisan db:seed --class=DemoTenantsSeeder --force
  dc exec -T api php artisan optimize

  # Health check through the public edge (retries cover certificate issuance on first deploy).
  CURL_OPTS=(-fsS --max-time 10)
  [ -n "${HEALTH_CURL_OPTS:-}" ] && read -r -a CURL_OPTS <<<"$HEALTH_CURL_OPTS"
  HEALTH_URL="${HEALTH_URL:-https://$APP_DOMAIN/api/health}"
  for attempt in $(seq 1 15); do
    if body="$(curl "${CURL_OPTS[@]}" "$HEALTH_URL")"; then
      echo "$body"
      # Housekeeping must never fail a healthy deploy: drop dangling images and SHA-tagged ones older than a week.
      docker image prune -af --filter "until=168h" >/dev/null || true
      echo "Deploy of $TAG OK"
      exit 0
    fi
    echo "Health check attempt $attempt failed, retrying in 5s..."
    sleep 5
  done

  echo "Health check failed for $HEALTH_URL" >&2
  dc ps >&2
  exit 1
}

main "$@"
