# Shlink URL Shortener with Docker & Caddy Reverse Proxy

This repository contains the configuration for a self-hosted **Shlink** instance. This setup is optimized for resource-constrained environments like a Raspberry Pi by using **SQLite** and **Caddy Server**.

## 🔗 Why Shlink?

Shlink is a powerful, self-hosted URL shortener that provides:
1.  **Full Ownership:** You own your short links and the data behind them.
2.  **Detailed Analytics:** Track visits, locations, and referrers for every link.
3.  **API-First Design:** Easily integrate link shortening into other applications or use the sleek official web client.
4.  **Lightweight Performance:** By using the SQLite driver, this setup minimizes RAM and CPU usage while maintaining high portability.

## 🛠 Prerequisites

*   Raspberry Pi or any Linux server with **Docker** and **Docker Compose**.
*   An existing **Caddy** installation on the `web` Docker network.
*   A domain (e.g., `shlink.duckdns.org`) pointing to your server.

## 📁 Project Structure

```bash
/shlink
├── docker-compose.yml
└── database/          # Persistent SQLite database storage
```

## ⚙️ Setup & Installation

### 1. Fix Permissions (Crucial)
Shlink runs internally as **UID 1001**. To avoid "Permission Denied" errors with the SQLite database, you must set the correct ownership on the host:

```bash
mkdir database
sudo chown -R 1001:1001 ./database
```

### 2. Configure Docker Compose
Create the `docker-compose.yml` file. Ensure `DEFAULT_DOMAIN` matches your Caddy domain.

```yaml
services:
  shlink:
    image: shlinkio/shlink:stable
    container_name: shlink
    restart: unless-stopped
    environment:
      - DEFAULT_DOMAIN=shlink.duckdns.org
      - IS_HTTPS_ENABLED=true
      - DB_DRIVER=sqlite
      - VALIDATE_URL=true
      - DEFAULT_BASE_URL_REDIRECT=https://your-main-site.com
    volumes:
      - ./database:/etc/shlink/data
    networks:
      - web

networks:
  web:
    external: true
```

### 3. Caddy Reverse Proxy
Add the following to your `Caddyfile`. Shlink listens on port **8080** internally:

```caddyfile
shlink.duckdns.org {
    reverse_proxy shlink:8080

    # Security Headers
    header {
        Strict-Transport-Security "max-age=31536000;"
        X-XSS-Protection "1; mode=block"
        X-Content-Type-Options "nosniff"
        X-Frame-Options "DENY"
        Referrer-Policy "no-referrer-when-downgrade"
    }
}
```

### 4. Deploy
```bash
docker-compose up -d
```

## 🔑 Initial Setup (API Key)

Shlink does not have a built-in GUI for security. You must generate an API key to manage your links via a web client or CLI:

```bash
docker exec -it shlink shlink api-key:generate
```
**Save the generated key!** You will need it to connect to the dashboard.

## 📊 Managing your Links

You can use the official web client at **[app.shlink.io](https://app.shlink.io)**.
*   **Privacy Note:** This web client runs entirely in your browser. Your data and API keys are never sent to Shlink's servers; the browser communicates directly with your Raspberry Pi.
*   Click **"Add a server"**, enter your URL (`https://mytiny.duckdns.org`) and the API key you generated.

## 💾 Backup & Migration

Since we use **SQLite** and **Bind Mounts**, migration is as simple as copying the folder:

1.  **Backup:**
    ```bash
    tar -czvf shlink-backup.tar.gz ./shlink
    ```
2.  **Restore:**
    Extract on the new machine and **verify permissions**:
    ```bash
    sudo chown -R 1001:1001 ./database
    docker-compose up -d
    ```

---

*Based on the [deployment guide by Efstathios Iosifidis](https://iosifidis.github.io/shlink-docker-caddy/).*
