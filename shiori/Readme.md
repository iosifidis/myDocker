# Τεκμηρίωση Εγκατάστασης Shiori με Caddy και Docker

## Πίνακας Περιεχομένων
1. [Εισαγωγή](#εισαγωγή)
2. [Προαπαιτούμενα](#προαπαιτούμενα)
3. [Δομή Αρχείων](#δομή-αρχείων)
4. [Βήμα 1: Προετοιμασία Συστήματος](#βήμα-1-προετοιμασία-συστήματος)
5. [Βήμα 2: Δημιουργία Φακέλων και Δικαιωμάτων](#βήμα-2-δημιουργία-φακέλων-και-δικαιωμάτων)
6. [Βήμα 3: Docker Compose Configuration](#βήμα-3-docker-compose-configuration)
7. [Βήμα 4: Caddy Configuration](#βήμα-4-caddy-configuration)
8. [Βήμα 5: Εκκίνηση Υπηρεσιών](#βήμα-5-εκκίνηση-υπηρεσιών)
9. [Βήμα 6: Αρχική Ρύθμιση Shiori](#βήμα-6-αρχική-ρύθμιση-shiori)
10. [Βήμα 7: Επαλήθευση Λειτουργίας](#βήμα-7-επαλήθευση-λειτουργίας)
11. [Διαχείριση και Συντήρηση](#διαχείριση-και-συντήρηση)
12. [Backup και Επαναφορά](#backup-και-επαναφορά)
13. [Αντιμετώπιση Προβλημάτων](#αντιμετώπιση-προβλημάτων)
14. [Ασφάλεια](#ασφάλεια)

## Εισαγωγή

Το [Shiori](https://github.com/go-shiori/shiori) είναι ένα απλό και αποτελεσματικό προσωπικό εργαλείο διαχείρισης bookmarks, φτιαγμένο με Go. Αυτή η τεκμηρίωση περιγράφει την εγκατάσταση του Shiori σε Docker με Caddy ως reverse proxy, χρησιμοποιώντας bind volumes για εύκολη μεταφορά και backup.

**Χαρακτηριστικά:**
- Πλήρως containerized εγκατάσταση
- Caddy με αυτόματο HTTPS (Let's Encrypt)
- SQLite3 για απλότητα (εύκολο backup)
- Bind mounts για φορητότητα

## Προαπαιτούμενα

- Docker Engine (έκδοση 20.10+)
- Docker Compose (έκδοση 2.0+)
- Caddy server (σε Docker ή host)
- Domain name: `shiori.iosifidis.gr`
- Ports: 80, 443 (Caddy), 8080 (Shiori)
- Δίκτυο Docker: `web`

## Δομή Αρχείων

```
/docker/shiori/
├── docker-compose.yml
└── shiori-data/    # Δεδομένα βάσης και configuration του Shiori
```

![Shiori](shiori.png)

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
sudo ss -tlnp | grep -E ":(8080)"
```

## Βήμα 2: Δημιουργία Φακέλων και Δικαιωμάτων

```bash
# Δημιουργία της βασικής δομής
sudo mkdir -p /docker/shiori/shiori-data

# Το Shiori τρέχει ως non-root user μέσα στο container, αλλά τα δικαιώματα του host volume
# θα πρέπει να επιτρέπουν εγγραφή. Συνήθως, ο Docker daemon διαχειρίζεται αυτό,
# αλλά αν υπάρχουν προβλήματα, μπορεί να χρειαστεί ρύθμιση.
# Για απλότητα, προς το παρόν αφήνουμε τα default, εκτός αν παρουσιαστεί πρόβλημα.

# Επαλήθευση
ls -la /docker/shiori/
```

## Βήμα 3: Docker Compose Configuration

Δημιουργήστε `/docker/shiori/docker-compose.yml`:

```yaml
version: '3.8'

services:
  shiori:
    image: ghcr.io/go-shiori/shiori:latest
    container_name: shiori
    restart: unless-stopped
    networks:
      - web
    volumes:
      - /docker/shiori/shiori-data:/srv/shiori
    environment:
      - SHIORI_DIR=/srv/shiori/
    ports:
      - "8080:8080"  # HTTP: host:8080 → container:8080 (θα χρησιμοποιηθεί από τον Caddy)

networks:
  web:
    external: true
```

## Βήμα 4: Caddy Configuration

### 4.1 Αν το Caddy τρέχει σε Docker

Προσθέστε στο Caddyfile σας:

```
shiori.iosifidis.gr {
    reverse_proxy shiori:8080
    
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
        output file /var/log/caddy/shiori.log {
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
shiori.iosifidis.gr {
    reverse_proxy 127.0.0.1:8080
    
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
cd /docker/shiori

# Κατεβάστε την τελευταία image
docker compose pull

# Εκκίνηση
docker compose up -d

# Έλεγχος logs
docker compose logs -f

# Έλεγχος κατάστασης
docker compose ps
```

## Βήμα 6: Αρχική Ρύθμιση Shiori

1. Ανοίξτε browser: `https://shiori.iosifidis.gr`

2. Θα οδηγηθείτε στη σελίδα εγγραφής. Δημιουργήστε έναν νέο λογαριασμό διαχειριστή.

Ο προεπιλεγμένος χρήστης ο `shiori` και συνθηματικό είναι το `gopher`. Μόλις συνδεθείτε, θα μπορείτε να χρησιμοποιήσετε τη διεπαφή ιστού. Για να προσθέσετε έναν νέο λογαριασμό, ανοίξτε τη σελίδα ρυθμίσεων και κάντε κλικ στο «Προσθήκη νέου λογαριασμού». Με αυτό, ο προεπιλεγμένος χρήστης θα απενεργοποιηθεί αυτόματα.

## Βήμα 7: Επαλήθευση Λειτουργίας

```bash
# Έλεγχος container
docker ps | grep shiori

# Έλεγχος ports
ss -tlnp | grep 8080

# Έλεγχος ότι έχει δημιουργηθεί το αρχείο βάσης (shiori.db)
ls -la /docker/shiori/shiori-data
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

# Αναβάθμιση (αλλάξτε version σε docker-compose.yml αν χρειάζεται)
docker compose pull
docker compose up -d
```

### Configuration Updates

Οι ρυθμίσεις του Shiori αποθηκεύονται στο `shiori.db` και επηρεάζονται από τις μεταβλητές περιβάλλοντος. Αν αλλάξετε κάτι στο `docker-compose.yml`, απλά κάντε `docker compose restart`.

## Backup και Επαναφορά

### Backup

Επειδή το Shiori χρησιμοποιεί ένα μόνο αρχείο SQLite (`shiori.db`) και πιθανώς κάποια embedded data, το backup είναι απλό.

```bash
#!/bin/bash
# backup-shiori.sh

BACKUP_DIR="/backup/shiori"
DATE=$(date +%Y%m%d_%H%M%S)
SHIORI_DATA_PATH="/docker/shiori/shiori-data"

# Δημιουργία backup directory
mkdir -p $BACKUP_DIR

# Backup data
tar -czf $BACKUP_DIR/shiori_data_$DATE.tar.gz $SHIORI_DATA_PATH

# Κράτηση μόνο των 7 τελευταίων backups
ls -t $BACKUP_DIR/shiori_data_*.tar.gz | tail -n +8 | xargs -r rm

echo "Backup completed: $DATE"
```

### Επαναφορά

```bash
# Σταματήστε το container
cd /docker/shiori
docker compose stop

# Διαγράψτε τα υπάρχοντα δεδομένα
rm -rf /docker/shiori/shiori-data/*

# Επαναφορά data από το backup
tar -xzf /backup/shiori/shiori_data_20250213_143000.tar.gz -C /docker/shiori/shiori-data --strip-components=3

# Διορθώστε δικαιώματα αν χρειαστεί
# sudo chown -R <user>:<group> /docker/shiori/shiori-data
# (Συνήθως δεν χρειάζεται για το Shiori)

# Ξεκινήστε
docker compose start
```

## Αντιμετώπιση Προβλημάτων

### 1. Shiori Doesn't Start

**Diagnostics:**
```bash
# Full logs
docker compose logs --tail=100

# Check processes inside container
docker exec shiori ps aux

# Check ports inside container
docker exec shiori netstat -tlnp
```

### 2. Caddy SSL Certificates

**Σύμπτωμα:** HTTPS δεν λειτουργεί

**Λύση:**
```bash
# Έλεγχος logs Caddy
docker logs caddy

# Manual renewal
docker exec caddy caddy renew
```

### 3. Permission Issues

**Σύμπτωμα:** Το Shiori δεν μπορεί να γράψει στο `/srv/shiori`

**Λύση:**
Βεβαιωθείτε ότι ο χρήστης κάτω από τον οποίο τρέχει ο Docker daemon (ή ο χρήστης του container αν έχει καθοριστεί) έχει δικαιώματα εγγραφής στον φάκελο `/docker/shiori/shiori-data`.
```bash
sudo chmod -R 777 /docker/shiori/shiori-data # (Προσοχή: χαμηλότερη ασφάλεια, για debugging)
# Ή πιο σωστά, βρείτε το UID/GID του container user αν είναι non-root και ρυθμίστε τα
```

## Ασφάλεια

### 1. Firewall Rules

```bash
# UFW example
sudo ufw allow 80/tcp     # Caddy HTTP
sudo ufw allow 443/tcp    # Caddy HTTPS
sudo ufw status
```

### 2. Automatic Security Updates

```bash
# Περιοδικό restart για updates
crontab -e
# 0 3 * * 0 cd /docker/shiori && docker compose pull && docker compose up -d
```

### 3. Security Headers

Στο Caddyfile:
```
header {
    Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
    X-Content-Type-Options nosniff
    X-Frame-Options DENY
    X-XSS-Protection "1; mode=block"
    Content-Security-Policy "default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-eval'" # Προσαρμόστε ανάλογα με scripts/styles του Shiori
    Referrer-Policy strict-origin-when-cross-origin
}
```

### 4. Monitoring

```bash
# Health check (δεν υπάρχει επίσημο health endpoint στο Shiori API, ελέγχουμε την αρχική σελίδα)
curl -k https://shiori.iosifidis.gr

# Simple monitoring script
#!/bin/bash
if ! curl -f -k https://shiori.iosifidis.gr > /dev/null 2>&1; then
    echo "Shiori is down!" | mail -s "Alert: Shiori" admin@example.com
    cd /docker/shiori && docker-compose restart
fi
```

## Χρήσιμοι Σύνδεσμοι

- [Shiori GitHub Repository](https://github.com/go-shiori/shiori)
- [Caddy Documentation](https://caddyserver.com/docs/)
- [Docker Documentation](https://docs.docker.com/)

## Συμπεράσματα

Η εγκατάσταση αυτή προσφέρει:

- **Φορητότητα**: Όλα τα δεδομένα σε bind mounts
- **Ασφάλεια**: Αυτόματο HTTPS με Caddy
- **Ευκολία διαχείρισης**: Πλήρως containerized
- **Backup friendly**: SQLite για απλά backups

Για οποιοδήποτε πρόβλημα, ελέγξτε πρώτα τα logs και επαληθεύστε τα δικαιώματα των φακέλων.
