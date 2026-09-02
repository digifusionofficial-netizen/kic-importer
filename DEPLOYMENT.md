# Staging deployment

Pushing to `develop` runs [`.github/workflows/deploy-develop.yml`](.github/workflows/deploy-develop.yml),
which rsyncs the plugin's runtime files (`kic-importer.php`, `uninstall.php`,
`README.md`, `src/`, `assets/`, `templates/`) over SSH into
`wp-content/plugins/kic-importer/` on your staging host. `main` never
auto-deploys — treat it as the stable checkpoint.

Nothing in this file is a secret. The actual host, username, and key live only
in this repo's GitHub Actions secrets, entered directly on GitHub — never in
chat, never committed.

## 1. Enable SSH in cPanel

1. cPanel → **Security → SSH Access → Manage SSH Keys**.
2. **Generate a New Key** (or skip to step 2 below and import one instead).
3. Under **Manage Keys**, click **Authorize** next to the public key so it's
   added to `~/.ssh/authorized_keys` for that cPanel user.
4. Note the **SSH port** cPanel shows you — shared cPanel hosts frequently use
   a non-default port (commonly `2222` or `21098`), not `22`. Check
   cPanel → **Home → General Information**, or ask your host.

If your host instead generates the keypair for you and lets you download the
private key, that's fine too — skip to step 3.

## 2. Generate a deploy keypair yourself (alternative to step 1's generator)

From any terminal:

```sh
ssh-keygen -t ed25519 -C "kic-importer-deploy" -f kic_deploy_key -N ""
```

This produces `kic_deploy_key` (private) and `kic_deploy_key.pub` (public).
Paste the **public** key's contents into cPanel's *Import Key* screen and
authorize it. Keep the **private** key for step 3 — never commit either file.

## 3. Find the plugin path

The plugin must land at the WordPress install's
`wp-content/plugins/kic-importer/`. Over SSH:

```sh
find / -maxdepth 6 -type d -name kic-importer 2>/dev/null
# or, if the plugin isn't installed yet:
find / -maxdepth 6 -type d -name plugins 2>/dev/null
```

A typical cPanel path looks like:

```
/home/<cpanel-user>/public_html/wp-content/plugins/kic-importer/
```

(or `public_html/staging/wp-content/plugins/kic-importer/` if staging lives in
a subfolder/subdomain).

## 4. Add GitHub repository secrets

On GitHub: **Settings → Secrets and variables → Actions → New repository
secret**. Add each of these — paste values directly into GitHub's form, not
into this chat or any file:

| Secret | Value |
|---|---|
| `STAGING_HOST` | Server hostname or IP |
| `STAGING_USER` | cPanel/SSH username |
| `STAGING_PORT` | SSH port from step 1 |
| `STAGING_SSH_KEY` | The **private** key's full contents (`-----BEGIN OPENSSH PRIVATE KEY-----` … `-----END OPENSSH PRIVATE KEY-----`) |
| `STAGING_PLUGIN_PATH` | Absolute path from step 3, trailing slash included |
| `STAGING_WP_PATH` *(optional)* | WordPress root (not the plugin folder) — only if WP-CLI is installed on the host and you want auto-activate + cache flush after each deploy |

## 5. First deploy

```sh
git push origin develop
```

Watch the run under the repo's **Actions** tab. If it fails on `Host key
verification failed`, the `ssh-keyscan` step couldn't reach
`STAGING_HOST:STAGING_PORT` — double-check those two secrets first.

If WP-CLI isn't installed on the host (common on shared cPanel), the last
step is skipped safely — just refresh **Plugins** in wp-admin to see the
update, or deactivate/reactivate once if WordPress doesn't pick it up
immediately.

## Rotating or revoking access

To cut off this deploy key later: cPanel → **SSH Access → Manage Keys** →
remove/deauthorize the key, and delete the `STAGING_SSH_KEY` secret on GitHub.
