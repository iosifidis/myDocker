# Τεκμηρίωση Εγκατάστασης Forgejo με Caddy και Docker

## Πίνακας Περιεχομένων
1. [Εισαγωγή](#εισαγωγή)
2. [Προαπαιτούμενα](#προαπαιτούμενα)
3. [Δομή Αρχείων](#δομή-αρχείων)
4. [Βήμα 1: Προετοιμασία Συστήματος](#βήμα-1-προετοιμασία-συστήματος)
5. [Βήμα 2: Δημιουργία Φακέλων και Δικαιωμάτων](#βήμα-2-δημιουργία-φακέλων-και-δικαιωμάτων)
6. [Βήμα 3: Docker Compose Configuration](#βήμα-3-docker-compose-configuration)
7. [Βήμα 4: Caddy Configuration](#βήμα-4-caddy-configuration)
8. [Βήμα 5: Εκκίνηση Υπηρεσιών](#βήμα-5-εκκίνηση-υπηρεσιών)
9. [Βήμα 6: Αρχική Ρύθμιση Forgejo](#βήμα-6-αρχική-ρύθμιση-forgejo)
10. [Βήμα 7: Επαλήθευση Λειτουργίας](#βήμα-7-επαλήθευση-λειτουργίας)
11. [Διαχείριση και Συντήρηση](#διαχείριση-και-συντήρηση)
12. [Backup και Επαναφορά](#backup-και-επαναφορά)
13. [Αντιμετώπιση Προβλημάτων](#αντιμετώπιση-προβλημάτων)
14. [Ασφάλεια](#ασφάλεια)

## Εισαγωγή

Το [Forgejo](https://forgejo.org/) είναι ένα ελαφρύ, αυτο-φιλοξενούμενο σύστημα διαχείρισης Git repositories, fork του Gitea. Αυτή η τεκμηρίωση περιγράφει την εγκατάσταση του Forgejo σε Docker με Caddy ως reverse proxy, χρησιμοποιώντας bind volumes για εύκολη μεταφορά και backup.

**Χαρακτηριστικά:**
- Πλήρως containerized εγκατάσταση
- Caddy με αυτόματο HTTPS (Let's Encrypt)
- SQLite3 για απλότητα (εύκολο backup)
- SSH server για git operations
- Bind mounts για φορητότητα

## Προαπαιτούμενα

- Docker Engine (έκδοση 20.10+)
- Docker Compose (έκδοση 2.0+)
- Caddy server (σε Docker ή host)
- Domain name: `forgejo.iosifidis.gr`
- Ports: 80, 443 (Caddy), 2222 (Forgejo SSH)
- Δίκτυο Docker: `web`

## Δομή Αρχείων

```
/docker/forgejo/
├── docker-compose.yml
├── data/           # Δεδομένα βάσης και repositories
├── config/         # Configuration files
└── ssh/            # SSH keys
```

## Βήμα 1: Προετοιμασία Συστήματος

### 1.1 Δημιουργία Docker Network

```bash
# Έλεγχος αν υπάρχει το network
docker network ls | grep web

# Δημιουργία αν δεν υπάρχει
docker network create web
```

### 1.2 Έλεγχος Διαθέσιμων Ports

```bash
# Έλεγχος ότι οι πόρτες είναι ελεύθερες
sudo ss -tlnp | grep -E ":(2222|3000)"
```

## Βήμα 2: Δημιουργία Φακέλων και Δικαιωμάτων

```bash
# Δημιουργία της βασικής δομής
sudo mkdir -p /docker/forgejo/{data,config,ssh}

# Το Forgejo τρέχει με user ID 1000 (git)
sudo chown -R 1000:1000 /docker/forgejo

# Δικαιώματα
sudo chmod -R 755 /docker/forgejo
sudo chmod 700 /docker/forgejo/ssh  # Πιο αυστηρά για SSH keys

# Επαλήθευση
ls -la /docker/forgejo/
```

## Βήμα 3: Docker Compose Configuration

Δημιουργήστε `/docker/forgejo/docker-compose.yml`:

```yaml
version: '3.8'

services:
  forgejo:
    image: codeberg.org/forgejo/forgejo:1.21
    container_name: forgejo
    restart: unless-stopped
    networks:
      - web
    volumes:
      - /docker/forgejo/data:/data
      - /docker/forgejo/config:/etc/forgejo
      - /docker/forgejo/ssh:/data/ssh
      - /etc/timezone:/etc/timezone:ro
      - /etc/localtime:/etc/localtime:ro
    ports:
      - "2222:2222"  # SSH: host:2222 → container:2222
    environment:
      # User/Group IDs
      - USER_UID=1000
      - USER_GID=1000
      
      # Domain και URLs
      - FORGEJO__server__DOMAIN=forgejo.iosifidis.gr
      - FORGEJO__server__ROOT_URL=https://forgejo.iosifidis.gr
      - FORGEJO__server__HTTP_PORT=3000
      - FORGEJO__server__PROTOCOL=http
      
      # SSH Configuration
      - FORGEJO__server__SSH_DOMAIN=forgejo.iosifidis.gr
      - FORGEJO__server__SSH_PORT=2222
      - FORGEJO__server__START_SSH_SERVER=true
      - FORGEJO__server__SSH_LISTEN_PORT=2222  # Σημαντικό: αποφυγή conflict με host SSH
      
      # Database
      - FORGEJO__database__DB_TYPE=sqlite3
      - FORGEJO__database__PATH=/data/forgejo.db
      
      # Logging
      - FORGEJO__log__MODE=console
      - FORGEJO__log__LEVEL=Info

networks:
  web:
    external: true
```

## Βήμα 4: Caddy Configuration

### 4.1 Αν το Caddy τρέχει σε Docker

Προσθέστε στο Caddyfile σας:

```
forgejo.iosifidis.gr {
    reverse_proxy forgejo:3000
    
    # Security headers
    header {
        Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
        X-Content-Type-Options nosniff
        X-Frame-Options DENY
        X-XSS-Protection "1; mode=block"
        Referrer-Policy strict-origin-when-cross-origin
        Permissions-Policy "geolocation=(), microphone=(), camera=()"
    }
    
    # Compression
    encode gzip zstd
    
    # Logging
    log {
        output file /var/log/caddy/forgejo.log {
            roll_size 10mb
            roll_keep 5
        }
    }
    
    # Metrics (προαιρετικό)
    metrics /metrics
}
```

### 4.2 Αν το Caddy τρέχει στο host

```bash
# /etc/caddy/Caddyfile
forgejo.iosifidis.gr {
    reverse_proxy 127.0.0.1:3000
    
    header {
        Strict-Transport-Security "max-age=31536000;"
        X-Content-Type-Options nosniff
        X-Frame-Options DENY
        X-XSS-Protection "1; mode=block"
    }
    
    encode gzip zstd
}
```

## Βήμα 5: Εκκίνηση Υπηρεσιών

```bash
# Μεταβείτε στον φάκελο
cd /docker/forgejo

# Κατεβάστε την τελευταία image
docker compose pull

# Εκκίνηση
docker compose up -d

# Έλεγχος logs
docker compose logs -f

# Έλεγχος κατάστασης
docker compose ps
```

## Βήμα 6: Αρχική Ρύθμιση Forgejo

1. Ανοίξτε browser: `https://forgejo.iosifidis.gr`

2. Συμπληρώστε τη φόρμα εγκατάστασης:

**Database Settings:**
- **Database Type**: SQLite3
- **Path**: `/data/forgejo.db` (αυτόματα)

**General Settings:**
- **Site Title**: Forgejo - iosifidis.gr
- **Domain**: forgejo.iosifidis.gr
- **SSH Port**: 2222
- **HTTP Port**: 3000
- **Root URL**: https://forgejo.iosifidis.gr

**Admin Account Settings:**
- Δημιουργήστε τον πρώτο διαχειριστή

3. Πατήστε "Install Forgejo"

## Βήμα 7: Επαλήθευση Λειτουργίας

```bash
# Έλεγχος container
docker ps | grep forgejo

# Έλεγχος ports
ss -tlnp | grep 2222
ss -tlnp | grep 3000

# Έλεγχος configuration
docker exec forgejo cat /data/gitea/conf/app.ini

# Έλεγχος SSH
ssh -p 2222 git@forgejo.iosifidis.gr

# Test clone (μετά από δημιουργία repo)
git clone ssh://git@forgejo.iosifidis.gr:2222/username/repo.git
```

## Διαχείριση και Συντήρηση

### Βασικές Εντολές

```bash
# Status
docker compose ps

# Logs
docker compose logs -f
docker compose logs -f --tail=100

# Restart
docker compose restart

# Stop
docker compose stop

# Start
docker compose start

# Πλήρης διακοπή
docker compose down

# Αναβάθμιση (αλλάξτε version)
docker compose pull
docker compose up -d
```

### Configuration Updates

```bash
# Αλλάξτε config και κάνετε restart
docker compose restart

# Ή πιο ήπια
docker compose exec forgejo forgejo admin reload
```

## Backup και Επαναφορά

### Backup

```bash
#!/bin/bash
# backup-forgejo.sh

BACKUP_DIR="/backup/forgejo"
DATE=$(date +%Y%m%d_%H%M%S)

# Δημιουργία backup directory
mkdir -p $BACKUP_DIR

# Stop container για consistency
cd /docker/forgejo
docker compose stop

# Backup data
tar -czf $BACKUP_DIR/forgejo_data_$DATE.tar.gz /docker/forgejo/data

# Backup config
tar -czf $BACKUP_DIR/forgejo_config_$DATE.tar.gz /docker/forgejo/config

# Backup SSH keys
tar -czf $BACKUP_DIR/forgejo_ssh_$DATE.tar.gz /docker/forgejo/ssh

# Start container
docker compose start

# Κράτηση μόνο των 7 τελευταίων backups
ls -t $BACKUP_DIR/forgejo_data_*.tar.gz | tail -n +8 | xargs -r rm
ls -t $BACKUP_DIR/forgejo_config_*.tar.gz | tail -n +8 | xargs -r rm
ls -t $BACKUP_DIR/forgejo_ssh_*.tar.gz | tail -n +8 | xargs -r rm

echo "Backup completed: $DATE"
```

### Επαναφορά

```bash
# Σταματήστε το container
cd /docker/forgejo
docker compose stop

# Επαναφορά data
rm -rf /docker/forgejo/data/*
tar -xzf /backup/forgejo/forgejo_data_20250213_143000.tar.gz -C /

# Επαναφορά config
rm -rf /docker/forgejo/config/*
tar -xzf /backup/forgejo/forgejo_config_20250213_143000.tar.gz -C /

# Διορθώστε δικαιώματα
chown -R 1000:1000 /docker/forgejo

# Ξεκινήστε
docker compose start
```

## Αντιμετώπιση Προβλημάτων

### 1. SSH Port Conflict

**Σύμπτωμα:** `bind: address already in use` για port 22

**Λύση:** Χρησιμοποιήστε διαφορετική internal port:
```yaml
environment:
  - FORGEJO__server__SSH_LISTEN_PORT=2222
ports:
  - "2222:2222"
```

### 2. Database Permission Issues

**Σύμπτωμα:** `unable to open database file`

**Λύση:**
```bash
# Διορθώστε δικαιώματα
sudo chown -R 1000:1000 /docker/forgejo/data
sudo chmod 755 /docker/forgejo/data
sudo touch /docker/forgejo/data/forgejo.db
sudo chown 1000:1000 /docker/forgejo/data/forgejo.db
```

### 3. Caddy SSL Certificates

**Σύμπτωμα:** HTTPS δεν λειτουργεί

**Λύση:**
```bash
# Έλεγχος logs Caddy
docker logs caddy

# Manual renewal
docker exec caddy caddy renew
```

### 4. Forgejo Doesn't Start

**Diagnostics:**
```bash
# Full logs
docker compose logs --tail=100

# Check config
docker exec forgejo cat /data/gitea/conf/app.ini

# Check processes
docker exec forgejo ps aux

# Check ports inside container
docker exec forgejo netstat -tlnp
```

## Ασφάλεια

### 1. Firewall Rules

```bash
# UFW example
sudo ufw allow 2222/tcp  # Forgejo SSH
sudo ufw allow 80/tcp     # Caddy HTTP
sudo ufw allow 443/tcp    # Caddy HTTPS
sudo ufw status
```

### 2. Automatic Security Updates

```bash
# Περιοδικό restart για updates
crontab -e
# 0 3 * * 0 cd /docker/forgejo && docker compose pull && docker compose up -d
```

### 3. SSH Hardening

Στο configuration του Forgejo (app.ini):
```ini
[server]
SSH_SERVER_CIPHERS = chacha20-poly1305@openssh.com, aes256-gcm@openssh.com
SSH_SERVER_MACS = hmac-sha2-256-etm@openssh.com, hmac-sha2-512-etm@openssh.com
SSH_SERVER_KEY_EXCHANGES = curve25519-sha256@libssh.org, ecdh-sha2-nistp521
```

### 4. Security Headers

Στο Caddyfile:
```
header {
    Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
    X-Content-Type-Options nosniff
    X-Frame-Options DENY
    X-XSS-Protection "1; mode=block"
    Content-Security-Policy "default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self'"
    Referrer-Policy strict-origin-when-cross-origin
}
```

### 5. Monitoring

```bash
# Health check
curl -k https://forgejo.iosifidis.gr/api/health

# Simple monitoring script
#!/bin/bash
if ! curl -f -k https://forgejo.sandbox.ellak.gr > /dev/null 2>&1; then
    echo "Forgejo is down!" | mail -s "Alert: Forgejo" admin@example.com
    cd /docker/forgejo && docker-compose restart
fi
```

## Χρήσιμοι Σύνδεσμοι

- [Forgejo Documentation](https://forgejo.org/docs/latest/)
- [Caddy Documentation](https://caddyserver.com/docs/)
- [Docker Documentation](https://docs.docker.com/)

## Συμπεράσματα

Η εγκατάσταση αυτή προσφέρει:

- **Φορητότητα**: Όλα τα δεδομένα σε bind mounts
- **Ασφάλεια**: Αυτόματο HTTPS με Caddy
- **Ευκολία διαχείρισης**: Πλήρως containerized
- **Backup friendly**: SQLite για απλά backups
- **Scalability**: Εύκολη αναβάθμιση

Για οποιοδήποτε πρόβλημα, ελέγξτε πρώτα τα logs και επαληθεύστε τα δικαιώματα των φακέλων.
