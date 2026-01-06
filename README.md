# myDocker

Here you can see my Docker files.

- [**myUoM**](./myUoM/) - myUoM app is a project of the Open Software Team of Applied Informatics, University of Macedonia. It was designed to facilitate students' daily interactions with the university.
- [**SonarQube**](./sonarqube/) - SonarQube is the leading open-source platform for continuous code quality inspection and static analysis. It empowers development teams to write cleaner, safer, and more maintainable code by automatically detecting bugs, security vulnerabilities, and "code smells" across more than 30 programming languages (including Java, Python, JavaScript, C#, and C++).

# 🗂️ Raspberry Pi Project Overview & Services

This repository manages a suite of self-hosted services running on a Raspberry Pi via Docker Compose and Caddy.

### 🌐 Core Infrastructure

- [**Caddy Server**](./caddy/) – The heart of the setup. An enterprise-ready reverse proxy that handles automatic HTTPS (SSL) for all services.

### 🛡️ Privacy & Security

- [**AdGuard Home**](./adguard/) – Network-wide ads and trackers blocking via a customized DNS server with a modern web interface.
- [**Vaultwarden**](./vaultwarden/) – A lightweight, self-hosted implementation of the Bitwarden API for secure password management.
- [**PrivateBin**](./privatebin/) – A minimalist, zero-knowledge encrypted pastebin where the server has no knowledge of the stored data.

### 📡 Remote Access & Communication

- [**RustDesk Server**](./rustdesk/) – A self-hosted remote desktop infrastructure, providing a private and fast alternative to TeamViewer/AnyDesk.

### 🛠️ Utilities & Productivity

- [**Shlink**](./shlink/) – A self-hosted, feature-rich URL shortener with detailed visit analytics and an API-first design.
- [**DokuWiki**](./dokuwiki/) – A highly versatile, database-less Open Source wiki engine used for personal documentation and knowledge bases.
