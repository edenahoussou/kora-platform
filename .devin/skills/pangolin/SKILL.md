---
name: pangolin
description: Manage the Pangolin ZTNA tunnel to access Gitea and internal KORA resources
argument-hint: "[up|down|status|test|vps-restart|vps-status|install-autostart]"
allowed-tools:
  - exec
  - read
  - grep
triggers:
  - user
  - model
---

# Pangolin ZTNA Management

You manage the Pangolin ZTNA tunnels that connect the workstation AND the VPS to the KORA internal network. The tunnel is required to reach Gitea and other internal resources. Both nodes need an active tunnel.

## Infrastructure Context

- **Pangolin host**: `auth.mezaccess.fr`
- **Organization**: `actudata-poc`
- **Account**: `eden@mezabo.fr`
- **Gitea URL**: `https://gitea.mezagency.fr/`
- **Gitea org**: `BJ`
- **Gitea repo**: `BJ/kora-platform`
- **Pangolin version**: 0.15.1
- **Pangolin binary (VPS)**: `/usr/local/bin/pangolin`
- **Pangolin config (VPS)**: `~/.config/pangolin/accounts.json` (copied from workstation)

## Resources accessible through the tunnel

- `gitea.mezagency.fr` — Gitea web UI and API
- `ssh-gitea.mezagency.fr` — Gitea SSH access

## Two tunnel nodes

| Node | Host | SSH alias | Purpose |
|------|------|-----------|---------|
| Workstation | localhost | — | Dev access to Gitea |
| VPS | 51.255.196.129:2222 | `kora-vps` | Gitea Actions runner + deployment target |

Both nodes run `pangolin up client` independently. The VPS tunnel is critical for the CI/CD runner to reach Gitea.

## Commands

The user passes an argument to specify the action. If no argument is given, default to `status`.

## Agent execution policy

On the workstation, Pangolin is configured in `/etc/sudoers.d/pangolin` for passwordless execution by the `eden` user. The agent must execute the allowed commands directly with `sudo -n` and the absolute binary path; `-n` prevents an interactive password prompt:

```
sudo -n /usr/local/bin/pangolin up client
sudo -n /usr/local/bin/pangolin down client
sudo -n /usr/local/bin/pangolin status client
sudo -n /usr/local/bin/pangolin list aliases
```

If `sudo -n` fails, do not ask for or handle a password. Report that the local sudoers rule is missing or invalid and ask the user to repair it. VPS commands remain separate and require their own sudo permissions on `kora-vps`.

### `up` — Start the workstation tunnel

Start the workstation tunnel directly as the agent:

```
sudo -n /usr/local/bin/pangolin up client
```

After the command completes, verify connectivity:
1. Run `pangolin status client` to confirm the client is connected.
2. Run `pangolin list aliases` to list accessible resources.
3. Run `curl -sS -o /dev/null -w "HTTP %{http_code}" https://gitea.mezagency.fr/` to confirm Gitea is reachable.
4. Report the status to the user.

### `down` — Stop the workstation tunnel

Run:
```
sudo -n /usr/local/bin/pangolin down client
```

Confirm the tunnel is down with `sudo -n /usr/local/bin/pangolin status client`.

### `status` — Check tunnel and Gitea connectivity (both nodes)

1. Run `pangolin status client` to check the workstation client.
2. If connected, run `pangolin list aliases` to show accessible resources.
3. Run `curl -sS -o /dev/null -w "HTTP %{http_code} | %{time_total}s" https://gitea.mezagency.fr/` to test Gitea from the workstation.
4. Run `ssh kora-vps "/usr/local/bin/pangolin status client"` to check the VPS client.
5. Run `ssh kora-vps "curl -sS -o /dev/null -w 'HTTP %{http_code}' --connect-timeout 5 https://gitea.mezagency.fr/"` to test Gitea from the VPS.
6. Report a summary table:

| Node | Pangolin client | Gitea |
|------|-----------------|-------|
| Workstation | connected / disconnected | HTTP 200 / unreachable |
| VPS | connected / disconnected | HTTP 200 / unreachable |

### `test` — Full connectivity test

Run the same checks as `status`, but also:
1. Test Gitea authentication with the stored credentials.
2. List repos in the `BJ` organization.
3. Verify the `BJ/kora-platform` repo exists and is accessible.
4. Run `git remote -v` in the project root to confirm the remote is configured.

### `vps-status` — Check VPS tunnel only

1. Run `ssh kora-vps "/usr/local/bin/pangolin status client"`.
2. Run `ssh kora-vps "curl -sS -o /dev/null -w 'HTTP %{http_code}' --connect-timeout 5 https://gitea.mezagency.fr/"`.
3. Report the VPS tunnel status.

### `vps-restart` — Restart the VPS tunnel

The VPS tunnel requires sudo on the VPS. Instruct the user to run:

```
ssh -t kora-vps "sudo pangolin down client && sudo pangolin up client"
```

After the user confirms, verify:
1. `ssh kora-vps "/usr/local/bin/pangolin status client"` — site should be "Connected".
2. `ssh kora-vps "curl -sS -o /dev/null -w 'HTTP %{http_code}' --connect-timeout 5 https://gitea.mezagency.fr/"` — should be HTTP 200.
3. If the Gitea Actions runner container was running, restart it: `ssh kora-vps "sudo docker restart gitea-runner"`.
4. Report the status.

### `install-autostart` — Install systemd service for VPS Pangolin auto-restart

Creates a systemd service that keeps the Pangolin tunnel up on the VPS across reboots and disconnects. This requires sudo on the VPS — instruct the user to run the commands.

1. Create the service file on the VPS:
```
ssh -t kora-vps 'sudo tee /etc/systemd/system/pangolin-client.service' << 'EOF'
[Unit]
Description=Pangolin ZTNA Client
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
ExecStart=/usr/local/bin/pangolin up client --daemon
ExecStop=/usr/local/bin/pangolin down client
Restart=always
RestartSec=10
User=root

[Install]
WantedBy=multi-user.target
EOF
```

2. Enable and start the service:
```
ssh -t kora-vps "sudo systemctl daemon-reload && sudo systemctl enable --now pangolin-client"
```

3. Verify: `ssh kora-vps "sudo systemctl status pangolin-client"`.

Note: if `pangolin up client --daemon` is not supported by the installed version, use a wrapper script that runs `pangolin up client` in a loop with sleep.

## Troubleshooting

### "No client is currently running"

The tunnel is down. Run `sudo -n /usr/local/bin/pangolin up client` directly on the workstation.

### Site shows "Disconnected" but client shows "Connected"

The WireGuard tunnel to the exit node has dropped, but the Pangolin client process is still running. This is the most common issue. Fix:

1. On the workstation: `sudo -n /usr/local/bin/pangolin down client && sudo -n /usr/local/bin/pangolin up client`
2. On the VPS: `ssh -t kora-vps "sudo pangolin down client && sudo pangolin up client"`
3. If the issue recurs frequently, install the autostart service (see `install-autostart` command).
4. If the exit node is consistently unreachable, try relay mode: `sudo -n /usr/local/bin/pangolin up client --holepunch=false`.

### "Could not resolve host: gitea.mezagency.fr"

The tunnel is up but DNS is not resolving. Check:
1. `pangolin status client` — is it connected? Is the site "Connected"?
2. `pangolin list aliases` — is `gitea.mezagency.fr` listed?
3. If aliases are empty, the CTO needs to add the resource in the Pangolin admin panel.
4. If the exit node is unreachable (hole punch fails), check the logs at `~/.config/pangolin/logs/client.log` for `Failed to get DNS response` errors.

### Docker containers on VPS cannot resolve gitea.mezagency.fr

Docker containers use their own DNS resolver and don't see the Pangolin DNS overrides. The Gitea Actions runner container must use `--network host` to share the host's DNS configuration (which includes Pangolin's DNS via systemd-resolved at `127.0.0.53`).

If a container still cannot resolve, check:
1. `ssh kora-vps "sudo docker exec <container> cat /etc/resolv.conf"` — should show `nameserver 127.0.0.53`.
2. If not, recreate the container with `--network host`.
3. Verify: `ssh kora-vps "sudo docker exec <container> nslookup gitea.mezagency.fr"` — should resolve to `100.96.128.12`.

### "Connection reset by peer" or "Not allowed at this time"

The VPS fail2ban may have banned the IP. This is separate from Pangolin. Check the VPS section below.

### Hole punch failures

If logs show `Sent UDP hole punch to <IP>:<PORT>` but no response:
1. The exit node may be down — ask the CTO to verify.
2. NAT may be incompatible — try `sudo -n /usr/local/bin/pangolin up client --holepunch=false` (forces relay mode).
3. Check `~/.config/pangolin/logs/client.log` for details.

### Tunnel drops frequently

The relay connection can be unstable. Mitigations:
1. Install the systemd autostart service (see `install-autostart` command) for automatic recovery.
2. Use `--holepunch=false` to force relay mode if hole punching is unreliable.
3. Check `pangolin logs` for recurring disconnect patterns.
4. If the problem persists, ask the CTO to check the Pangolin server status.

## VPS Access (separate from Pangolin)

The KORA VPS is accessible directly over the public internet (no Pangolin needed for SSH):

- **Host**: `51.255.196.129`
- **Port**: `2222`
- **Users**: `debian` (admin), `deploy` (CI/CD)
- **SSH config aliases**: `kora-vps` (debian), `kora-deploy` (deploy)
- **Key**: `~/.ssh/kora_vps_ed25519`
- **Auth**: key-only (password disabled)
- **OS**: Debian 13 (trixie), x86_64
- **Docker**: Docker CE 29.x + Compose v2 (installed)
- **Pangolin**: `/usr/local/bin/pangolin` (installed, config copied from workstation)

Test with:
```
ssh kora-vps "hostname && uptime"
```

If SSH fails with "Connection reset" or "Not allowed at this time", the IP may be banned by fail2ban on the VPS. Ask the CTO to run:
```
sudo fail2ban-client unban <IP>
```

## Gitea Actions Runner (VPS)

The Gitea Actions runner runs in a Docker container on the VPS:

- **Container name**: `gitea-runner`
- **Data volume**: `/opt/gitea-runner`
- **Network**: `host` (required for Pangolin DNS resolution)
- **Image**: `gitea/act_runner:latest`
- **Labels**: `ubuntu-latest:docker://catthehacker/ubuntu:act-22.04`

The runner depends on the VPS Pangolin tunnel being up. If the tunnel drops, the runner cannot communicate with Gitea. After restoring the tunnel, restart the runner:

```
ssh kora-vps "sudo docker restart gitea-runner"
```

Check runner status:
```
ssh kora-vps "sudo docker logs gitea-runner --tail 20"
```

The runner should also be registered in Gitea under BJ/kora-platform > Settings > Actions > Runners.

## Git Remote Configuration

The project remote is configured with an embedded access token. To verify:
```
git -C /var/www/html/kora-platform remote -v
```

If the remote is missing, reconstruct it:
```
git -C /var/www/html/kora-platform remote add origin https://eden:<token>@gitea.mezagency.fr/BJ/kora-platform.git
```

The token is stored in Gitea under `eden` > Settings > Applications > Access Tokens (name: `kora-push`).
