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
- [**Vikunja**](./vikunja) - Vikunja is a free, open-source, self-hosted to-do list application.
- [**Linkding**](./linkding/) - A lightweight, self-hosted bookmark manager for efficiently organizing your web links with tags and full-text search.

### 📈 Monitoring

- [**Uptime Kuma**](./uptime-kuma/) - A self-hosted monitoring tool that provides a beautiful and user-friendly dashboard to monitor the uptime of your services, websites, and APIs. It sends notifications via various channels (e.g., Telegram, Discord, Email) when a service goes down or recovers.

### 📤 File Sharing

- [**Psitransfer**](./psitransfer/) – A simple, self-hosted file transfer tool that allows secure and temporary file sharing via the browser. Ideal for quick and anonymous file transfers without the need for registration or complex configuration.

### 📊 Data & Analytics
- [**Metabase**](./metabase/) – An open-source business intelligence tool that lets you ask questions about your data and display answers in formats that make sense, from simple charts to detailed dashboards. Easy to set up and use, with no SQL knowledge required for basic queries.
- [**Forgejo**](./forgejo) is a lightweight, self-hosted Git repositories management system, a fork of Gitea.
