# AdGuard Home with Docker & Caddy Reverse Proxy

This repository contains a Docker Compose configuration for running [**AdGuard Home**](https://adguard.com/en/adguard-home/overview.html) on a Raspberry Pi. This setup also includes integration with **Caddy** to provide a secure, HTTPS-encrypted dashboard via a custom domain.

## 🛡️ Why AdGuard Home?

I chose AdGuard Home as my network-wide ad and tracker blocker for several reasons:

1.  **Modern Web UI:** It offers a much more modern and intuitive interface compared to Pi-hole.
2.  **Built-in Parental Controls:** Easily block adult content or specific services (like YouTube, TikTok, or Gaming) per device with a single click.
3.  **Native Encrypted DNS:** It supports DNS-over-HTTPS (DoH), DNS-over-TLS (DoT), and DNS-over-QUIC out of the box without needing extra plugins.
4.  **Client Management:** You can see exactly which device is making which request and apply different filtering rules for your PC, your phone, or your smart home devices.

## 🛠 Prerequisites

*   A Raspberry Pi with Docker installed.
*   **Port 53 available:** Most Linux distros (like Ubuntu/Raspbian) use `systemd-resolved` which takes up port 53. See the "Fixing Port 53 Conflict" section below.
*   The `web` docker network created: `docker network create web`.

## 📁 Directory Structure

```bash
.
├── docker-compose.yml
├── adguard/
│   ├── conf/   # Persistent configuration
│   └── work/   # Persistent data
└── Caddyfile   # (In your Caddy folder)
```

## ⚙️ Setup Instructions

### 1. Fix the Port 53 Conflict (Crucial for DNS)
If you get an error that port 53 is already in use, run these commands on your Raspberry Pi host:

```bash
# Disable the DNS stub listener
sudo sed -i 's/#DNSStubListener=yes/DNSStubListener=no/' /etc/systemd/resolved.conf
# Link the resolv.conf to a static one
sudo ln -sf /run/systemd/resolve/resolv.conf /etc/resolv.conf
# Restart the service
sudo systemctl restart systemd-resolved
```

### 2. Deploy AdGuard Home
Run the following command to start the service:

```bash
docker-compose up -d
```

### 3. Initial Configuration
1.  Open your browser and go to `http://<your-pi-ip>:3000`.
2.  Follow the setup wizard.
3.  **Important:** In the "Web Interface" settings, keep the port as **80** (internal) but set the "DNS Server" to listen on port **53**.

### 4. Configure Caddy Reverse Proxy
Add the following to your existing `Caddyfile` to access your dashboard securely from anywhere:

```caddyfile
nextguard.duckdns.org {
    reverse_proxy adguard:80
}
```
*Note: Ensure both Caddy and AdGuard are in the same `web` network.*

## 🖥️ Useful Commands

| Action | Command |
| :--- | :--- |
| **Start AdGuard** | `docker-compose up -d` |
| **View Logs** | `docker logs -f adguard` |
| **Restart Service** | `docker-compose restart adguard` |
| **Check Port 53** | `sudo lsof -i :53` |

## 🌐 Post-Installation: Use AdGuard as your DNS
To protect your entire house, log in to your **Router's settings** and change the **Primary DNS Server** to the IP address of your Raspberry Pi.
