# RustDesk Self-Hosted Server with Docker & Caddy

This repository contains the configuration to run your own [**RustDesk**](https://rustdesk.com/) remote desktop server. This allows you to bypass public relays, ensuring faster connections, better privacy, and full control over your remote support infrastructure.

## 🖥️ Why RustDesk Server?

RustDesk is the leading open-source alternative to TeamViewer and AnyDesk. By hosting your own server:
1.  **Privacy & Security:** All connection data stays on your hardware. You own the private keys used for encryption.
2.  **Superior Performance:** Hosting locally or on a close VPS significantly reduces latency compared to using busy public relay servers.
3.  **No Limits:** No "commercial use" nag screens or session time limits.
4.  **Web Client Ready:** This configuration is pre-configured to work with the RustDesk Web Client via Caddy.

## 🛠 Prerequisites

*   Raspberry Pi or Linux Server with **Docker** and **Docker Compose**.
*   **Port Forwarding:** You MUST open the following ports on your router/firewall to the host IP:
    *   `21115` (TCP) - Connection heartbeat
    *   `21116` (TCP/UDP) - Connection/ID service
    *   `21117` (TCP) - Relay service
*   A domain name and Caddy for the Web Client (optional, but configured here).

## 📁 Project Structure

```bash
/rustdesk-server
├── docker-compose.yml
└── data/              # Stores Public/Private keys and database
```

## ⚙️ Configuration

### 1. Docker Compose
The setup uses two services:
*   **HBBS:** The ID server (handles initial handshakes).
*   **HBBR:** The Relay server (handles the data stream when a direct P2P connection isn't possible).

### 2. Caddyfile (Web Client Support)
Add this to your `Caddyfile`. This allows you to use the RustDesk web client by proxying WebSockets from the RustDesk official site to your local containers.

```caddyfile
rustdesk.duckdns.org {
    header {
        Access-Control-Allow-Origin "https://rustdesk.com"
        Access-Control-Allow-Methods "GET, POST, OPTIONS"
        Access-Control-Allow-Headers "*"
    }

    # Reverse proxy for Web Client WebSockets
    reverse_proxy /ws/id/* hbbs:21118
    reverse_proxy /ws/relay/* hbbr:21119
}
```

## 🚀 Deployment

1.  **Start the server:**
    ```bash
    docker-compose up -d
    ```

2.  **Retrieve your Public Key:**
    RustDesk uses an encrypted "Key" system for security. Find your key inside the `data` folder:
    ```bash
    cat ./data/id_ed25519.pub
    ```
    **Copy this string; you will need it for your clients.**

## 🔧 Client Setup (Desktop/Mobile)

To use your new server:
1.  Open the RustDesk app on your computer/phone.
2.  Go to **Settings > Network > ID/Relay Server**.
3.  Enter the following:
    *   **ID Server:** `rustdesk.duckdns.org` (or your IP)
    *   **Relay Server:** `rustdesk.duckdns.org`
    *   **Key:** (Paste the string you found in step 2 above)
4.  Click **Apply**. You should see "Ready" at the bottom of the app.

## 🖥️ Useful Commands

| Action | Command |
| :--- | :--- |
| **Start Server** | `docker-compose up -d` |
| **View Logs** | `docker logs -f hbbs` |
| **Find Public Key** | `cat ./data/id_ed25519.pub` |
| **Restart Services** | `docker-compose restart` |

## 💾 Backup & Security
*   **Backup the `data/` folder:** This contains your unique identity keys. If you lose them, you will have to reconfigure all your clients with a new Key.
*   **Encrypted Connections:** All traffic is encrypted end-to-end using NaCl.
