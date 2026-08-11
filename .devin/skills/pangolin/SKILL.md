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

You manage the Pangolin ZTNA tunnel that connects this workstation to the KORA internal network. The tunnel is required to reach Gitea and other internal resources.

## Infrastructure Context

- **Pangolin host**: `auth.mezaccess.fr`
- **Organization**: `actudata-poc`
- **Account**: `eden@mezabo.fr`
- **Gitea URL**: `https://gitea.mezagency.fr/`
- **Gitea org**: `BJ`
- **Gitea repo**: `BJ/kora-platform`

## Resources accessible through the tunnel

- `gitea.mezagency.fr` — Gitea web UI and API
- `ssh-gitea.mezagency.fr` — Gitea SSH access

## Commands

The user passes an argument to specify the action. If no argument is given, default to `status`.

### `up` — Start the tunnel

Pangolin requires sudo to create the WireGuard network interface. The agent cannot type the sudo password interactively, so instruct the user to run the command in their own terminal:

```
sudo pangolin up client
```

After the user confirms the tunnel is up, verify connectivity:
1. Run `pangolin status client` to confirm the client is connected.
2. Run `pangolin list aliases` to list accessible resources.
3. Run `curl -sS -o /dev/null -w "HTTP %{http_code}" https://gitea.mezagency.fr/` to confirm Gitea is reachable.
4. Report the status to the user.

### `down` — Stop the tunnel

Run:
```
pangolin down client
```

Confirm the tunnel is down with `pangolin status client`.

### `status` — Check tunnel and Gitea connectivity

1. Run `pangolin status client` to check if the client is running.
2. If connected, run `pangolin list aliases` to show accessible resources.
3. Run `curl -sS -o /dev/null -w "HTTP %{http_code} | %{time_total}s" https://gitea.mezagency.fr/` to test Gitea.
4. If Gitea is reachable, run `curl -sS https://gitea.mezagency.fr/api/v1/version` to confirm the API.
5. Report a summary table:

| Component | Status |
|-----------|--------|
| Pangolin client | connected / disconnected |
| Gitea web | HTTP 200 / unreachable |
| Gitea API | version X.Y.Z / unreachable |

### `test` — Full connectivity test

Run the same checks as `status`, but also:
1. Test Gitea authentication with the stored credentials.
2. List repos in the `BJ` organization.
3. Verify the `BJ/kora-platform` repo exists and is accessible.
4. Run `git remote -v` in the project root to confirm the remote is configured.

## Troubleshooting

### "No client is currently running"

The tunnel is down. Instruct the user to run `sudo pangolin up client` in their terminal.

### "Could not resolve host: gitea.mezagency.fr"

The tunnel is up but DNS is not resolving. Check:
1. `pangolin status client` — is it connected?
2. `pangolin list aliases` — is `gitea.mezagency.fr` listed?
3. If aliases are empty, the CTO needs to add the resource in the Pangolin admin panel.
4. If the exit node is unreachable (hole punch fails), check the logs at `~/.config/pangolin/logs/client.log` for `Failed to get DNS response` errors.

### "Connection reset by peer" or "Not allowed at this time"

The VPS fail2ban may have banned the IP. This is separate from Pangolin. Check the VPS section below.

### Hole punch failures

If logs show `Sent UDP hole punch to <IP>:<PORT>` but no response:
1. The exit node may be down — ask the CTO to verify.
2. NAT may be incompatible — try `sudo pangolin up client --holepunch=false` (forces relay mode).
3. Check `~/.config/pangolin/logs/client.log` for details.

## VPS Access (separate from Pangolin)

The KORA VPS is accessible directly over the public internet (no Pangolin needed):

- **Host**: `51.255.196.129`
- **Port**: `2222`
- **Users**: `debian` (admin), `deploy` (CI/CD)
- **SSH config aliases**: `kora-vps` (debian), `kora-deploy` (deploy)
- **Key**: `~/.ssh/kora_vps_ed25519`
- **Auth**: key-only (password disabled)

Test with:
```
ssh kora-vps "hostname && uptime"
```

If SSH fails with "Connection reset" or "Not allowed at this time", the IP may be banned by fail2ban on the VPS. Ask the CTO to run:
```
sudo fail2ban-client unban <IP>
```

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
