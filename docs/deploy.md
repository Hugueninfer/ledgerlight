# Deploy runbook

Ledgerlight runs on one Oracle Cloud Always Free Ampere (arm64) VM with Ubuntu 24.04, behind a DuckDNS hostname. Caddy terminates TLS (Let's Encrypt, automatic). Images are built by GitHub Actions on a native arm64 runner and pulled from GHCR. Total cost: $0.

Pipeline: push to `main` -> `CI` passes -> `Deploy` builds `ledgerlight-api` and `ledgerlight-web` (tagged with the commit SHA and `latest`) -> SSH to the VM and run `TAG=<sha> scripts/deploy.sh`.

Replace `<host>` below with your DuckDNS name (for example `ledgerlight.duckdns.org`) and `<token>` with your DuckDNS token.

## 0. Prerequisites

- An Oracle VM (shape `VM.Standard.A1.Flex`, Ubuntu 24.04 aarch64) with a public IP, and SSH access as the `ubuntu` user.
- A DuckDNS subdomain pointing at that public IP (https://www.duckdns.org).

## 1. Install Docker on Ubuntu 24.04 (aarch64)

```bash
sudo apt-get update
sudo apt-get install -y ca-certificates curl git
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
sudo chmod a+r /etc/apt/keyrings/docker.asc
echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo $VERSION_CODENAME) stable" \
  | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null
sudo apt-get update
sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
sudo usermod -aG docker "$USER"
newgrp docker
docker version && docker compose version   # Compose must be v2.24 or newer
```

## 2. Open ports 80 and 443 (two places)

Oracle blocks traffic twice: in the cloud network and in the VM's own firewall. Both must be opened.

**a) OCI VCN security list** (web console): Networking -> Virtual cloud networks -> your VCN -> Security Lists -> Default Security List -> Add Ingress Rules. Add two rules:

| Source CIDR | IP protocol | Destination port |
|---|---|---|
| `0.0.0.0/0` | TCP | `80` |
| `0.0.0.0/0` | TCP | `443` |

**b) The VM's iptables.** Oracle's Ubuntu images ship a rule that REJECTs everything not explicitly allowed, so the ACCEPT rules must be inserted *before* it (not appended):

```bash
sudo iptables -L INPUT -n --line-numbers          # note the position of the REJECT rule
sudo iptables -I INPUT 5 -p tcp --dport 80 -j ACCEPT
sudo iptables -I INPUT 5 -p tcp --dport 443 -j ACCEPT
sudo apt-get install -y iptables-persistent       # accept the prompts
sudo netfilter-persistent save                    # persists across reboots
sudo iptables -L INPUT -n --line-numbers          # both ACCEPT rules sit above the REJECT line
```

If the REJECT rule is not at position 5, use its line number instead of `5`. Check from your own machine: `curl -I http://<host>` should connect (a Caddy response or a redirect, not a timeout) once the stack is running.

## 3. Keep DuckDNS pointed at the VM

Needed if the public IP can change (a reserved IP does not need it, but it is harmless).

```bash
mkdir -p ~/duckdns
cat > ~/duckdns/duck.sh <<'SCRIPT'
#!/bin/sh
echo url="https://www.duckdns.org/update?domains=<subdomain>&token=<token>&ip=" | curl -k -o ~/duckdns/duck.log -K -
SCRIPT
chmod 700 ~/duckdns/duck.sh
( crontab -l 2>/dev/null; echo '*/5 * * * * ~/duckdns/duck.sh >/dev/null 2>&1' ) | crontab -
~/duckdns/duck.sh && cat ~/duckdns/duck.log   # prints OK
```

`<subdomain>` is only the name (`ledgerlight`), without `.duckdns.org`. Confirm the record: `dig +short <host>` returns the VM's public IP.

## 4. Check out the repo and create the server `.env`

The VM needs the compose files, the Caddy config and `scripts/deploy.sh`; the repo is public, so a plain clone works.

```bash
sudo mkdir -p /opt/ledgerlight && sudo chown "$USER": /opt/ledgerlight
git clone https://github.com/Hugueninfer/ledgerlight.git /opt/ledgerlight
cd /opt/ledgerlight
cp deploy/production.env.example .env
chmod 600 .env
```

Fill in `/opt/ledgerlight/.env` (never commit it; it is gitignored):

```bash
echo "APP_KEY=base64:$(openssl rand -base64 32)"   # paste as APP_KEY
openssl rand -hex 24                               # run twice: DB_PASSWORD and DB_ROOT_PASSWORD
nano .env
```

Set at least: `APP_DOMAIN=<host>`, `APP_URL=https://<host>`, `APP_KEY`, `DB_PASSWORD`, `DB_ROOT_PASSWORD`. Keep `APP_ENV=production` and `APP_DEBUG=false`. The MySQL passwords are only applied when the database volume is first created; changing them later requires resetting the volume or changing them inside MySQL.

## 5. Make the GHCR packages public

The VM pulls images anonymously, so the packages must be public. They exist only after the first `Deploy` workflow run has pushed them (trigger it with Actions -> Deploy -> Run workflow, or push to `main`).

For each of `ledgerlight-api` and `ledgerlight-web`: GitHub -> your profile -> Packages -> the package -> Package settings -> Danger Zone -> Change visibility -> Public.

Verify from the VM without logging in:

```bash
docker pull ghcr.io/hugueninfer/ledgerlight-api:latest
docker pull ghcr.io/hugueninfer/ledgerlight-web:latest
```

## 6. Add the four repository secrets

Create a dedicated deploy key on your own machine (not on the VM):

```bash
ssh-keygen -t ed25519 -N "" -C "ledgerlight-deploy" -f ./ledgerlight-deploy
ssh-copy-id -i ./ledgerlight-deploy.pub ubuntu@<host>      # or append the .pub line to ~/.ssh/authorized_keys on the VM
```

GitHub -> repository -> Settings -> Secrets and variables -> Actions -> New repository secret:

| Name | Value |
|---|---|
| `DEPLOY_HOST` | `<host>` (the DuckDNS name or the public IP) |
| `DEPLOY_USER` | `ubuntu` |
| `DEPLOY_SSH_KEY` | the full contents of `ledgerlight-deploy` (the private key, including the BEGIN/END lines) |
| `DEPLOY_HOST_FINGERPRINT` | the VM's SSH host key fingerprint (`SHA256:...`), so the workflow refuses a spoofed host; get it with the command below |

Get the host key fingerprint (run it on your machine, then confirm it against `ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub` on the VM):

```bash
ssh-keyscan -t ed25519 <host> 2>/dev/null | ssh-keygen -lf - | awk '{print $2}'   # SHA256:...
```

`DEPLOY_HOST_FINGERPRINT` is a hard prerequisite: the `deploy` job fails in its first step if the secret is empty, and never connects without verifying the host key.

Then delete the local private key file. Until `DEPLOY_HOST` exists, the `deploy` job is skipped (shown as skipped, not failed), so the build keeps working without a VM.

## 7. First deploy and verification

Run the workflow (Actions -> Deploy -> Run workflow on `main`), or push to `main`. Both jobs must be green: `build` (arm64 images pushed) and `deploy` (SSH, `scripts/deploy.sh`).

The first deploy also issues the TLS certificate; `deploy.sh` retries the health check for about 75 seconds. Verify from anywhere:

```bash
curl -s https://<host>/api/health        # {"status":"ok",...}
curl -sI https://<host>/ | head -1       # HTTP/2 200
```

Manual deploy or rollback on the VM (any commit SHA that has images in GHCR):

```bash
TAG=<sha> /opt/ledgerlight/scripts/deploy.sh
```

## Troubleshooting

- `curl` times out from outside: ports are blocked; recheck both parts of step 2.
- Caddy cannot get a certificate: DNS must resolve to the VM (step 3) and port 80 must be reachable (Let's Encrypt HTTP challenge). Logs: `docker compose -f compose.yaml -f compose.prod.yaml logs caddy`.
- `pull access denied`: the GHCR packages are not public yet (step 5).
- Deploy job fails on the health check: `docker compose -f compose.yaml -f compose.prod.yaml ps` and `logs api` on the VM.

## Local check of the production overlay

Run the prod images on your machine under a separate project name, with Caddy's internal TLS for `localhost` (ports 80/443 may be busy, so override them):

```bash
docker build --target prod -t ghcr.io/hugueninfer/ledgerlight-api:local -f docker/php/Dockerfile .
docker build --target prod -t ghcr.io/hugueninfer/ledgerlight-web:local -f docker/web/Dockerfile .
cp deploy/production.env.example /tmp/prodcheck.env   # fill APP_KEY, DB_PASSWORD, DB_ROOT_PASSWORD, APP_DOMAIN=localhost
export ENV_FILE=/tmp/prodcheck.env TAG=local LEDGERLIGHT_HTTP_PORT_PROD=8081 LEDGERLIGHT_HTTPS_PORT_PROD=8443
docker compose -p ledgerlight-prodcheck --env-file "$ENV_FILE" -f compose.yaml -f compose.prod.yaml up -d --wait
curl -k https://localhost:8443/api/health
docker compose -p ledgerlight-prodcheck --env-file "$ENV_FILE" -f compose.yaml -f compose.prod.yaml down -v
```
