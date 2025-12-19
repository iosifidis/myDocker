# PrivateBin Deployment with Docker & Caddy Reverse Proxy

This repository provides a production-ready configuration for deploying [**PrivateBin**](https://privatebin.info/) using Docker Compose and Caddy Server as a reverse proxy. This setup focuses on security, zero-knowledge storage, and high portability using bind mounts.

## 🔒 Why PrivateBin?

PrivateBin is a minimalist, open-source online pastebin where the server has **zero knowledge** of pasted data. Data is encrypted/decrypted in the browser using 256-bit AES.

I chose this specific deployment method for:
1.  **Enhanced Security:** The container runs as a non-privileged user (`nobody`), and the filesystem is set to `read_only`.
2.  **Zero-Knowledge:** Host privacy is guaranteed as data is encrypted before it ever reaches the server.
3.  **Full Portability:** By using bind mounts instead of named volumes, you can backup or move your entire instance by simply copying the project folder.
4.  **Automatic HTTPS:** Integrated with Caddy to handle SSL certificates and mandatory security headers.

## 🛠 Prerequisites

*   Raspberry Pi or any Linux server with **Docker** and **Docker Compose** installed.
*   An existing **Caddy** installation running on a Docker network named `web`.
*   A domain name (e.g., via DuckDNS) pointed to your server's IP.

## 📁 Project Structure

```bash
/privatebin
├── docker-compose.yml  # Container definition
├── conf.php           # PrivateBin application settings
└── data/              # Persistent storage for pastes (auto-created)
```

## ⚙️ Setup & Installation

### 1. Prepare Permissions
PrivateBin runs as user `nobody` (UID: 65534) for security. You must set the correct ownership for the data folder on your host:

```bash
mkdir data
sudo chown -R 65534:65534 ./data
sudo chmod 644 conf.php
```

### 2. Configure `conf.php`
Create a `conf.php` file. **Crucial:** Ensure the `basepath` matches your domain and ends with a forward slash:

```php
; <?php exit; ?> DO NOT REMOVE THIS LINE
[main]
name = "My PrivateBin"
basepath = "https://privatebin.duckdns.org/"
; ... other settings ...
```

### 3. Caddy Reverse Proxy Configuration
Add the following block to your `Caddyfile`. The specific headers are **mandatory** for the WebCrypto API (encryption) to function correctly:

```caddyfile
privatebin.duckdns.org {
    reverse_proxy privatebin:8080 {
        header_up Host {host}
        header_up X-Real-IP {remote}
        # Required for browser-side encryption to work
        header_up X-Forwarded-Proto https
        header_up X-Forwarded-Ssl on
    }
}
```

## 🚀 Deployment

Start the PrivateBin container:
```bash
docker-compose up -d
```

Reload your Caddy container to apply the new configuration:
```bash
docker restart caddy
```

## 💾 Backup & Restore (Portability)

Since this setup uses bind mounts, moving your instance to a new server is easy.

**To Backup:**
```bash
docker-compose down
tar -czvf privatebin-backup.tar.gz .
```

**To Restore:**
1. Transfer the `.tar.gz` to the new server.
2. Extract: `tar -xzvf privatebin-backup.tar.gz`
3. Verify permissions: `ls -l data` (should show owner `65534`).
4. Start: `docker-compose up -d`

## 🖥️ Useful Commands

| Action | Command |
| :--- | :--- |
| **Start Service** | `docker-compose up -d` |
| **Stop Service** | `docker-compose down` |
| **View Logs** | `docker logs -f privatebin` |
| **Check Permissions** | `ls -l data/` |

---
*Based on the [deployment guide by Efstathios Iosifidis](https://iosifidis.github.io/privatebin-docker-caddy/).*
