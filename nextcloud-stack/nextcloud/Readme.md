# Εγκατάσταση Nextcloud με Docker Compose (Υψηλή Απόδοση)

Αυτός ο οδηγός περιγράφει μια πλήρη εγκατάσταση του Nextcloud χρησιμοποιώντας Docker Compose, εστιάζοντας στην απόδοση και την ευκολία συντήρησης. Χρησιμοποιείται ο Caddy ως reverse proxy, το Nextcloud FPM για την εφαρμογή, PostgreSQL για τη βάση δεδομένων και Redis για caching.

**Domain Name:** `cloud.iosifidis.gr`

## 1. Αρχιτεκτονική Συστήματος
Η εγκατάσταση βασίζεται σε Docker containers για μέγιστη απομόνωση, ασφάλεια και ευκολία συντήρησης.

*   **Web Server / Reverse Proxy:** [Caddy](https://caddyserver.com/) (διαχειρίζεται SSL/TLS, στατικά αρχεία και FastCGI).
*   **Application Server:** [Nextcloud FPM](https://hub.docker.com/_/nextcloud) (Alpine-based) για υψηλή απόδοση.
*   **Βάση Δεδομένων:** [PostgreSQL 16](https://hub.docker.com/_/postgres) (Alpine).
*   **Caching / Locking:** [Redis 7](https://hub.docker.com/_/redis) (Alpine).

## 2. Δομή Καταλόγων & Αρχείων
Όλα τα δεδομένα αποθηκεύονται σε ένα κεντρικό mount point `/data` (προτείνεται δίσκος τουλάχιστον 200GB).

```text
/data/
├── nextcloud/
│   ├── app/                # Κώδικας Nextcloud (bind mount από το container)
│   ├── nc_data/            # Αρχεία χρηστών (User Data)
│   ├── db/                 # Δεδομένα PostgreSQL
│   └── docker-compose.yml  # Ρυθμίσεις Containers Nextcloud
├── caddy/
│   ├── Caddyfile           # Ρυθμίσεις Web Server Caddy
│   └── docker-compose.yml  # Ρυθμίσεις Container Caddy
└── backups/
    └── databases/          # SQL Dumps για το Kopia
```

## 3. Ρυθμίσεις Docker Compose (Nextcloud Stack)
Δημιουργήστε τον φάκελο `/data/nextcloud` και το αρχείο `docker-compose.yml` εντός του.

**Αρχείο:** `/data/nextcloud/docker-compose.yml`

```yaml
version: '3.8'

services:
  db:
    image: postgres:16-alpine
    container_name: nextcloud_db
    restart: unless-stopped
    environment:
      - POSTGRES_PASSWORD=your_db_password # Αλλάξτε το σε ένα ισχυρό κωδικό
      - POSTGRES_DB=nextcloud
      - POSTGRES_USER=nextcloud
    volumes:
      - ./db:/var/lib/postgresql/data
    networks:
      - backend

  redis:
    image: redis:7-alpine
    container_name: nextcloud_redis
    restart: unless-stopped
    networks:
      - backend

  app:
    image: nextcloud:fpm-alpine # Χρησιμοποιούμε την FPM έκδοση για συνεργασία με Caddy
    container_name: nextcloud_app
    restart: unless-stopped
    depends_on:
      - db
      - redis
    environment:
      - POSTGRES_HOST=db
      - POSTGRES_DB=nextcloud
      - POSTGRES_USER=nextcloud
      - POSTGRES_PASSWORD=your_db_password # Πρέπει να ταιριάζει με τον κωδικό της βάσης
      - REDIS_HOST=redis
      - NEXTCLOUD_TRUSTED_DOMAINS=cloud.iosifidis.gr # Το domain σας
      - TRUSTED_PROXIES=caddy # Το όνομα του Caddy container
      # Εναλλακτικά για TRUSTED_PROXIES (αν υπάρχουν προβλήματα με το όνομα container)
      # - TRUSTED_PROXIES=172.16.0.0/12 # Εδώ λέμε στο Nextcloud να εμπιστεύεται το δίκτυο του Caddy
      - OVERWRITEPROTOCOL=https
      - PHP_MEMORY_LIMIT=1G
      - PHP_UPLOAD_LIMIT=10G
    volumes:
      - ./app:/var/www/html # Εδώ θα μπει ο κώδικας του Nextcloud
      - ./nc_data:/var/www/data # Εδώ θα αποθηκευτούν τα αρχεία των χρηστών
    networks:
      - backend
      - web

networks:
  backend:
    driver: bridge
  web: # Αυτό το δίκτυο πρέπει να είναι external και να δημιουργηθεί εκ των προτέρων
    external: true
```

## 4. Ρυθμίσεις Caddy (Reverse Proxy)

### 4.1 Δημιουργία External Network
Πριν ξεκινήσετε τον Caddy, βεβαιωθείτε ότι το `web` network υπάρχει.
```bash
docker network create web
```

### 4.2 Caddy Docker Compose
Δημιουργήστε τον φάκελο `/data/caddy` και το αρχείο `docker-compose.yml` εντός του.

**Αρχείο:** `/data/caddy/docker-compose.yml`
```yaml
version: '3.8'

services:
  caddy:
    image: caddy:latest
    container_name: caddy
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./Caddyfile:/etc/caddy/Caddyfile
      - ./site:/srv # Προαιρετικό, για στατικά αρχεία αν χρειαστεί
      - ./data:/data # Για certificates και cache του Caddy
      - ./config:/config # Για config του Caddy
      # Συνδέουμε τους φακέλους του Nextcloud με το Caddy σε read-only
      - /data/nextcloud/app:/var/www/html:ro
      - /data/nextcloud/nc_data:/var/www/data:ro
    networks:
      - web

networks:
  web:
    external: true
```

### 4.3 Caddyfile Configuration
Δημιουργήστε το αρχείο `Caddyfile` στον φάκελο `/data/caddy`.

**Αρχείο:** `/data/caddy/Caddyfile`

```caddy
cloud.iosifidis.gr { # Το domain σας
    # Ορίζουμε τον ριζικό φάκελο του Nextcloud για το Caddy
    root * /var/www/html
    file_server
    encode zstd gzip

    # PHP-FPM Configuration για το Nextcloud
    php_fastcgi nextcloud_app:9000 { # Επικοινωνία με το Nextcloud FPM container
        env FRONT_END_HTTPS on
        index index.php
    }

    # CalDAV/CardDAV Redirects (για mobile clients)
    redir /.well-known/carddav /remote.php/dav 301
    redir /.well-known/caldav /remote.php/dav 301

    # Security Headers για βελτιωμένη ασφάλεια
    header {
        Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
        X-Content-Type-Options "nosniff"
        X-Frame-Options "SAMEORIGIN"
        Referrer-Policy "no-referrer"
        X-XSS-Protection "1; mode=block"
    }

    # Απαγόρευση πρόσβασης σε ευαίσθητα αρχεία/καταλόγους
    @forbidden {
        path /data/* /config/* /db_structure/* /.xml /etc/*
    }
    respond @forbidden 403

    # Ορίζουμε το μέγιστο μέγεθος request body (για uploads)
    request_body {
        max_size 10GB
    }
}
```

## 5. Εκκίνηση Containers και Διαχείριση Δικαιωμάτων

1.  **Ξεκινήστε τα Nextcloud Containers:**
    Πηγαίνετε στον φάκελο `/data/nextcloud` και εκτελέστε:
    ```bash
    docker compose up -d
    ```

2.  **Ξεκινήστε το Caddy Container:**
    Πηγαίνετε στον φάκελο `/data/caddy` και εκτελέστε:
    ```bash
    docker compose up -d
    ```

3.  **Διαχείριση Δικαιωμάτων (Permissions)**
    Η σωστή απόδοση ιδιοκτησίας είναι κρίσιμη για τη λειτουργία των Alpine containers και την αποφυγή προβλημάτων.

    | Κατάλογος | Χρήστης (UID) | Εντολή |
    | :--- | :--- | :--- |
    | `/data/nextcloud/app` | 82 (www-data) | `sudo chown -R 82:82 app` |
    | `/data/nextcloud/nc_data` | 82 (www-data) | `sudo chown -R 82:82 nc_data` |
    | `/data/nextcloud/db` | 70 (postgres) | `sudo chown -R 70:70 db` |

    **Εντολές επιβολής:**
    ```bash
    sudo chown -R 82:82 /data/nextcloud/app /data/nextcloud/nc_data
    sudo chown -R 70:70 /data/nextcloud/db
    sudo chmod 700 /data/nextcloud/db # Περιορίζουμε τα δικαιώματα του DB φακέλου
    ```

4.  **Ολοκλήρωση Εγκατάστασης μέσω Browser:**
    Επισκεφθείτε το `https://cloud.iosifidis.gr` στον browser σας.
    *   Όταν σας ζητηθεί ο "Φάκελος δεδομένων" (Data folder), σβήστε το `/var/www/html/data` και γράψτε: `/var/www/data`.
    *   Συμπληρώστε τα στοιχεία της βάσης δεδομένων όπως τα ορίσατε στο `docker-compose.yml`.

## 6. Βασικές Εντολές Διαχείρισης (occ)
Οι εντολές `occ` εκτελούνται μέσω του Nextcloud Docker container ως χρήστης `www-data`.

*   **Προσθήκη ευρετηρίων που λείπουν:**
    ```bash
    docker exec --user www-data nextcloud_app php occ db:add-missing-indices
    ```
*   **Ρύθμιση Περιοχής Τηλεφώνου:**
    ```bash
    docker exec --user www-data nextcloud_app php occ config:system:set default_phone_region --value="GR"
    ```
*   **Μεταναστεύσεις τύπων MIME (προαιρετικό):**
    ```bash
    docker exec --user www-data nextcloud_app php occ maintenance:repair --include-expensive
    ```
*   **Ώρα έναρξης παραθύρου συντήρησης (προαιρετικό, για updates):**
    ```bash
    docker exec --user www-data nextcloud_app php occ config:system:set maintenance_window_start --type=integer --value=1
    ```
*   **Απενεργοποίηση του AppAPI:** Απενεργοποιεί το σύστημα εγκατάστασης "Εξωτερικών Εφαρμογών" (Ex-Apps), οι οποίες τρέχουν σε ξεχωριστά Docker containers (π.χ., Nextcloud Assistant, Local AI).
    ```bash
    docker exec --user www-data nextcloud_app php occ app:disable app_api
    ```
*   **Ενεργοποίηση Maintenance Mode (για αναβαθμίσεις):**
    ```bash
    docker exec --user www-data nextcloud_app php occ maintenance:mode --on
    ```
*   **Απενεργοποίηση Maintenance Mode:**
    ```bash
    docker exec --user www-data nextcloud_app php occ maintenance:mode --off
    ```

### Πιθανές επιπλέον διορθώσεις βάσης δεδομένων
Συχνά, μετά την προσθήκη ευρετηρίων, το Nextcloud μπορεί να ζητήσει και άλλες μικροδιορθώσεις στη βάση. Αν δείτε παρόμοια σφάλματα, τρέξτε και τις παρακάτω εντολές:

1.  **Για στήλες που λείπουν:**
    ```bash
    docker exec --user www-data nextcloud_app php occ db:add-missing-columns
    ```

2.  **Για πρωτεύοντα κλειδιά (primary keys) που λείπουν:**
    ```bash
    docker exec --user www-data nextcloud_app php occ db:add-missing-primary-keys
    ```

## 7. Εργασίες Παρασκηνίου (Cron)
Για βέλτιστη απόδοση, συνιστάται η χρήση του System Cron του Host αντί για AJAX ή Webcron.

**Προσθήκη στο `crontab -e` του Debian host:**
```cron
# Nextcloud Background Jobs (κάθε 5 λεπτά)
*/5 * * * * docker exec --user www-data nextcloud_app php -f /var/www/html/cron.php

# Database Backup Dump (καθημερινά στις 02:50 π.μ.)
50 2 * * * docker exec nextcloud_db pg_dumpall -U nextcloud > /data/backups/databases/nextcloud_db.sql
```

### 7.1 Ρύθμιση Retention (Περιορισμός παλιών εκδόσεων)
Για να διαχειριστείτε τον χώρο που καταλαμβάνουν οι παλιές εκδόσεις αρχείων και ο κάδος ανακύκλωσης, μπορείτε να ορίσετε πολιτικές διατήρησης. Προσθέστε αυτές τις γραμμές στο αρχείο `app/config/config.php` (ή μέσω `occ`):

```php
'versions_retention_obligation' => 'auto, 30', // Διαγραφή παλιών εκδόσεων μετά από 30 μέρες ή αν γεμίσει ο χώρος
'trashbin_retention_obligation' => 'auto, 30', // Διαγραφή αντικειμένων στον κάδο μετά από 30 μέρες
```
Εναλλακτικά, μέσω τερματικού:
```bash
docker exec --user www-data nextcloud_app php occ config:system:set versions_retention_obligation --value="auto, 30"
docker exec --user www-data nextcloud_app php occ config:system:set trashbin_retention_obligation --value="auto, 30"
```

## 8. Πολιτική Αναβαθμίσεων
Ακολουθήστε τα παρακάτω βήματα για την αναβάθμιση του Nextcloud.

1.  **Λήψη Backup:** Δημιουργήστε πλήρες backup (βάση δεδομένων και αρχεία χρηστών) χρησιμοποιώντας το Kopia ή άλλη μέθοδο. (Θα το καλύψουμε σε άλλο οδηγό).
2.  **Αλλαγή του tag της εικόνας:** Επεξεργαστείτε το `/data/nextcloud/docker-compose.yml` και αλλάξτε το tag της εικόνας του Nextcloud (π.χ., από `29-fpm-alpine` σε `30-fpm-alpine`).
    *   **Σημαντική Σημείωση:** Ποτέ μην πηδάτε major εκδόσεις (π.χ., από 28 σε 30). Οι αναβαθμίσεις πρέπει να γίνονται διαδοχικά (28 -> 29 -> 30).
3.  **Εκτέλεση αναβάθμισης:** Από τον φάκελο `/data/nextcloud`, εκτελέστε:
    ```bash
    docker compose pull && docker compose up -d
    ```
4.  **Έλεγχος και ολοκλήρωση αναβάθμισης:** Ελέγξτε για αναγκαίες αλλαγές στη βάση δεδομένων και τον κώδικα:
    ```bash
    docker exec --user www-data nextcloud_app php occ upgrade
    ```
