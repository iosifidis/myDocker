# DokuWiki with Docker & Caddy Reverse Proxy

This repository contains a Docker Compose configuration for running [**DokuWiki**](https://www.dokuwiki.org/dokuwiki) on a Raspberry Pi, behind a **Caddy** reverse proxy with automatic HTTPS.

## 📝 Why DokuWiki?

I chose DokuWiki as my documentation engine for several reasons:

1.  **No Database Required:** DokuWiki stores all data in plain text files. This makes backups, migrations, and manual edits extremely simple.
2.  **Lightweight:** It has very low system requirements, making it perfect for a Raspberry Pi.
3.  **Highly Extensible:** There are thousands of plugins available for everything from Markdown support to professional themes.
4.  **Revision Control:** It has built-in versioning for every page, allowing you to see changes and revert to previous versions easily.

## 📁 Directory Structure

```bash
.
├── docker-compose.yml
└── /docker/dokuwiki/  # Persistent data and config
    ├── data/
    └── config/
```

## ⚙️ Configuration Adjustments

### 1. Docker Compose
**Note:** The `linuxserver/dokuwiki` image usually handles the URL through its internal settings, but it's good practice to keep your environment variables updated.

```yaml
# Updated portion of docker-compose.yml
environment:
  - PUID=1000
  - PGID=1000
  - TZ=Europe/Athens
  - DOKUWIKI_BASEURL=https://dokuwiki.duckdns.org
```

### 2. Update Caddyfile
Ensure your `Caddyfile` points to the `dokuwiki` service container on port 80:

```caddyfile
dokuwiki.duckdns.org {
    reverse_proxy dokuwiki:80
    log {
        output stdout
        format console
        level INFO
    }
}
```

## 🚀 Deployment

1.  **Start the container:**
    ```bash
    docker-compose up -d
    ```

2.  **Initial Setup:**
    The first time you run it, visit `https://dokuwiki.duckdns.org/install.php` to configure your admin user and wiki settings.

3.  **Permissions:**
    If you encounter permission issues, ensure the data folders are owned by the user defined in `PUID` (usually 1000):
    ```bash
    sudo chown -R 1000:1000 /docker/dokuwiki
    ```

## 🖥️ Useful Commands

| Action | Command |
| :--- | :--- |
| **Start Wiki** | `docker-compose up -d` |
| **Stop Wiki** | `docker-compose down` |
| **View Logs** | `docker logs -f dokuwiki` |
| **Update Image** | `docker-compose pull && docker-compose up -d` |

## 🛠 Troubleshooting
If your links or CSS are not loading correctly after setting up the proxy:
1. Log in to DokuWiki as **Admin**.
2. Go to **Admin > Configuration Settings**.
3. Look for the `basedir` and `baseurl` settings.
4. Set `userewrite` to `1` (or `.htaccess`) to get "Nice URLs".
