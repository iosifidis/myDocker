# Raspberry Pi Reverse Proxy with Caddy & Docker

This repository contains a production-ready `docker-compose` setup for running **Caddy Server** on a Raspberry Pi. Caddy is a powerful, enterprise-ready open-source web server with automatic HTTPS.

## 🚀 Why Caddy?

I chose Caddy for this setup over Nginx or Apache for several key reasons:

1.  **Automatic HTTPS:** Caddy provisions and renews SSL certificates from Let's Encrypt or ZeroSSL automatically. No more manual certbot scripts.
2.  **Simple Configuration:** The `Caddyfile` is extremely readable and much easier to configure compared to verbose Nginx configs.
3.  **Performance:** Written in Go, it is lightweight and highly efficient for the ARM architecture of a Raspberry Pi.
4.  **Security by Default:** It uses modern security protocols (TLS 1.3, HSTS) out of the box.

## 🛠 Prerequisites

*   A Raspberry Pi (Running Raspberry Pi OS or any Linux distro).
*   [Docker and Docker Compose](https://docs.docker.com/engine/install/) installed.
*   A domain name pointing to your Pi's public IP (for SSL).
*   Ports **80** and **443** forwarded on your router.

## 📁 Directory Structure

```bash
.
├── docker-compose.yml
├── Caddyfile          # Caddy configuration
├── .env               # Environment variables
├── site/              # Static website files
├── data/              # Persistent data (Certs & Keys)
└── config/            # Caddy internal config
```

## ⚙️ Setup Instructions

### 1. Create the External Network
This setup uses an external network called `web` so that other Docker containers (like Nextcloud, Home Assistant, etc.) can communicate with Caddy without being in the same compose file.

```bash
docker network create web
```

### 2. Configure Environment Variables
Create a `.env` file in the root directory:
```bash
touch .env
```
Example `.env` content:
```env
DOMAIN=yourdomain.com
EMAIL=your-email@example.com
```

### 3. Edit the Caddyfile
Open `Caddyfile` and define your services. Example:
```caddyfile
{$DOMAIN} {
    # Serve static files from the /site directory
    root * /srv
    file_server

    # Example: Reverse Proxy to another container
    # reverse_proxy other_container_name:port
}
```

### 4. Deploy
Run the following command to start the container in detached mode:

```bash
docker-compose up -d
```

## 🖥️ Useful Commands

| Action | Command |
| :--- | :--- |
| **Start Services** | `docker-compose up -d` |
| **Stop Services** | `docker-compose down` |
| **View Logs** | `docker-compose logs -f caddy` |
| **Reload Caddyfile** (without restart) | `docker exec -w /etc/caddy caddy caddy reload` |
| **Check Config Syntax** | `docker exec -w /etc/caddy caddy caddy validate` |
| **Restart Container** | `docker-compose restart caddy` |

## 🔒 Persistence
The configuration includes bind mounts for the `/data` and `/config` folders. This ensures that your SSL certificates are not lost when the container is deleted or updated, preventing you from hitting Let's Encrypt rate limits.

---
*Created for self-hosting on Raspberry Pi.*
