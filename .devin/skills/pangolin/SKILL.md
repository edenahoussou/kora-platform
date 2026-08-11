---
name: pangolin
description: Manage the Pangolin ZTNA tunnel to access Gitea and internal KORA resources
argument-hint: "[up|down|status|test]"
allowed-tools:
  - exec
  - read
  - grep
triggers:
  - user
  - model
---

# Pangolin ZTNA Management

You manage the Pangolin ZTNA tunnel on the workstation. The tunnel is required to reach the internal Gitea instance and other KORA internal resources. The VPS no longer needs Pangolin — CI/CD runs on GitHub Actions and the VPS is deployed via SSH over the public internet.

## Infrastructure Context

- **Pangolin host**: `auth.mezaccess.fr`
- **Organization**: `actudata-poc`
- **Account**: `eden@mezabo.fr`
- **Gitea URL**: `https://gitea.mezagency.fr/` (legacy, for reference)
- **GitHub repo**: `https://github.com/edenahoussou/kora-platform` (active)
- **Pangolin version**: 0.15.1

## Resources accessible through the tunnel

- `gitea.mezagency.fr` — Gitea web UI and API (legacy, if needed)
- `ssh-gitea.mezagency.fr` — Gitea SSH access (legacy, if needed)

## Commands

The user passes an argument to specify the action. If no argument is given, default to `status`.

## Agent execution policy

Pangolin is configured in `/etc/sudoers.d/pangolin` for passwordless execution by the `eden` user. The agent must execute the allowed commands directly with `sudo -n` and the absolute binary path; `-n` prevents an interactive password prompt:

```
sudo -n /usr/local/bin/pangolin up client
sudo -n /usr/local/bin/pangolin down client
sudo -n /usr/local/bin/pangolin status client
sudo -n /usr/local/bin/pangolin list aliases
```

If `sudo -n` fails, do not ask for or handle a password. Report that the local sudoers rule is missing or invalid and ask the user to repair it.

### `up` — Start the tunnel

```
sudo -n /usr/local/bin/pangolin up client
```

After the command completes, verify connectivity:
1. Run `sudo -n /usr/local/bin/pangolin status client` to confirm the client is connected.
2. Run `sudo -n /usr/local/bin/pangolin list aliases` to list accessible resources.
3. Run `curl -sS -o /dev/null -w "HTTP %{http_code}" https://gitea.mezagency.fr/` to confirm Gitea is reachable (if needed).
4. Report the status to the user.

### `down` — Stop the tunnel

```
sudo -n /usr/local/bin/pangolin down client
```

Confirm the tunnel is down with `sudo -n /usr/local/bin/pangolin status client`.

### `status` — Check tunnel connectivity

1. Run `sudo -n /usr/local/bin/pangolin status client` to check if the client is running.
2. If connected, run `sudo -n /usr/local/bin/pangolin list aliases` to show accessible resources.
3. Report a summary table:

| Component | Status |
|-----------|--------|
| Pangolin client | connected / disconnected |
| Site | connected / disconnected |

### `test` — Full connectivity test

Run the same checks as `status`, but also:
1. Run `curl -sS -o /dev/null -w "HTTP %{http_code} | %{time_total}s" https://gitea.mezagency.fr/` to test Gitea.
2. If Gitea is reachable, run `curl -sS https://gitea.mezagency.fr/api/v1/version` to confirm the API.
3. Run `git remote -v` in the project root to confirm the GitHub remote is configured.

## Troubleshooting

### "No client is currently running"

The tunnel is down. Run `sudo -n /usr/local/bin/pangolin up client` directly.

### Site shows "Disconnected" but client shows "Connected"

The WireGuard tunnel to the exit node has dropped, but the Pangolin client process is still running. This is the most common issue. Fix:

1. `sudo -n /usr/local/bin/pangolin down client && sudo -n /usr/local/bin/pangolin up client`
2. If the exit node is consistently unreachable, try relay mode: `sudo -n /usr/local/bin/pangolin up client --holepunch=false`.

### "Could not resolve host: gitea.mezagency.fr"

The tunnel is up but DNS is not resolving. Check:
1. `pangolin status client` — is it connected? Is the site "Connected"?
2. `pangolin list aliases` — is `gitea.mezagency.fr` listed?
3. If aliases are empty, the CTO needs to add the resource in the Pangolin admin panel.
4. Check the logs at `~/.config/pangolin/logs/client.log` for `Failed to get DNS response` errors.

### Hole punch failures

If logs show `Sent UDP hole punch to <IP>:<PORT>` but no response:
1. The exit node may be down — ask the CTO to verify.
2. NAT may be incompatible — try `sudo -n /usr/local/bin/pangolin up client --holepunch=false` (forces relay mode).
3. Check `~/.config/pangolin/logs/client.log` for details.

## VPS Access (no Pangolin needed)

The KORA VPS is accessible directly over the public internet:

- **Host**: `51.255.196.129`
- **Port**: `2222`
- **Users**: `debian` (admin), `deploy` (CI/CD)
- **SSH config aliases**: `kora-vps` (debian), `kora-deploy` (deploy)
- **Key**: `~/.ssh/kora_vps_ed25519`
- **Deploy key**: `~/.ssh/kora_deploy_ed25519` (for GitHub Actions SSH deploys)
- **Auth**: key-only (password disabled)
- **OS**: Debian 13 (trixie), x86_64
- **Docker**: Docker CE 29.x + Compose v2 (installed)

Test with:
```
ssh kora-vps "hostname && uptime"
```

If SSH fails with "Connection reset" or "Not allowed at this time", the IP may be banned by fail2ban on the VPS. Ask the CTO to run:
```
sudo fail2ban-client unban <IP>
```

## Git Remote Configuration

The project remote is now on GitHub:
```
git -C /var/www/html/kora-platform remote -v
```

Should show `https://github.com/edenahoussou/kora-platform.git`. If the old Gitea remote is present, replace it:
```
git -C /var/www/html/kora-platform remote set-url origin https://github.com/edenahoussou/kora-platform.git
```
