# Vaultwarden Deployment with Docker & Caddy Reverse Proxy

This repository provides a step-by-step guide and configuration files to deploy [**Vaultwarden**](https://www.vaultwarden.net/) (formerly bitwarden_rs) on a Raspberry Pi using Docker Compose and Caddy.

## 🛡️ Why Vaultwarden?

Vaultwarden is an open-source, lightweight implementation of the Bitwarden API written in Rust. It is the perfect choice for a Raspberry Pi because:
1.  **Low Resource Usage:** It uses significantly less RAM and CPU than the official Bitwarden instance.
2.  **Full Compatibility:** It works perfectly with all official Bitwarden apps (Desktop, Mobile) and browser extensions.
3.  **Self-Hosted Privacy:** You maintain full control over your encrypted password database.
4.  **Portability:** Using bind mounts makes backing up and migrating your entire vault as simple as copying a folder.

## 🛠 Prerequisites

*   Raspberry Pi with **Docker** and **Docker Compose** installed.
*   An existing **Caddy** installation on the same Docker network (named `web`).
*   A domain name (e.g., DuckDNS) pointing to your server's IP.

## 📁 Project Structure

```bash
/vaultwarden
├── docker-compose.yml
└── vw-data/           # Persistent storage for the database and keys
```

## ⚙️ Setup Instructions

### 1. Configure Docker Compose
Create a `docker-compose.yml` file. Note that we enable WebSockets for instant device syncing.

```yaml
services:
  vaultwarden:
    image: vaultwarden/server:latest
    container_name: vaultwarden
    restart: unless-stopped
    volumes:
      - ./vw-data:/data
    environment:
      - WEBSOCKET_ENABLED=true  # For instant sync
      - SIGNUPS_ALLOWED=true    # Switch to false after first user registration
      - DOMAIN=https://myvault.duckdns.org
    networks:
      - web

networks:
  web:
    external: true
```

### 2. Configure Caddyfile
Add the following block to your `Caddyfile`. This includes HSTS security headers and WebSocket support.

```caddyfile
myvault.duckdns.org {
    # Security Headers
    header {
        Strict-Transport-Security "max-age=31536000;"
        X-XSS-Protection "1; mode=block"
        X-Frame-Options "DENY"
    }

    # Reverse Proxy to Vaultwarden
    reverse_proxy vaultwarden:80 {
        header_up X-Real-IP {remote_host}
    }
}
```
*Reload Caddy after editing: `docker exec caddy caddy reload`*

### 3. Initial Registration & Hardening
1.  Start the container: `docker-compose up -d`
2.  Navigate to your domain and **Create an Account**.
3.  **CRITICAL SECURITY STEP:** Once your account is created, go back to `docker-compose.yml` and set:
    `SIGNUPS_ALLOWED=false`
4.  Apply changes: `docker-compose up -d --force-recreate`

## 💾 Backup & Migration

To move your vault to a new machine or VM:
1.  **Stop the container** to prevent database corruption: `docker stop vaultwarden`
2.  **Compress the data:** `tar -czvf vault-backup.tar.gz ./vw-data`
3.  **Transfer:** Use `scp` to move the file to the destination.
4.  **Restore:** Extract the tar file next to your `docker-compose.yml` on the new machine and run `docker-compose up -d`.

## 🔒 Security Recommendations

1.  **Enable 2FA:** After logging in, go to **Settings -> Security -> Two-step Login** and enable an Authenticator App (Aegis, Authy, or Google Authenticator).
2.  **Automated Backups:** Set up a `cronjob` to copy the `vw-data` folder to an external drive or cloud storage weekly.
3.  **Regular Updates:** Keep Vaultwarden updated with these commands:
    ```bash
    docker-compose pull
    docker-compose up -d
    docker image prune -f
    ```

---
*Based on the [deployment guide by Efstathios Iosifidis](https://iosifidis.github.io/vaultwarden-docker-caddy/).*
