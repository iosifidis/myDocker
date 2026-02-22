# Εγκατάσταση και Ρύθμιση Caddy ως Reverse Proxy

Ο Caddy είναι ένας ισχυρός και εύχρηστος web server που διακρίνεται για την αυτόματη διαχείριση πιστοποιητικών SSL/TLS (μέσω Let's Encrypt) και την απλότητα της διαμόρφωσής του. Σε αυτή την εγκατάσταση, ο Caddy θα λειτουργήσει ως reverse proxy για το Nextcloud και το Nextcloud Talk High Performance Backend (HPB), καθώς και για οποιεσδήποτε άλλες υπηρεσίες επιθυμείτε να εκθέσετε.

**Domain Name:** `cloud.iosifidis.gr` (για Nextcloud), `kopia.cloud.iosifidis.gr` (για Kopia - προαιρετικό)

## 1. Δομή Καταλόγων και Δικτύωση

Θα δημιουργήσουμε έναν ειδικό φάκελο για τις ρυθμίσεις του Caddy και θα χρησιμοποιήσουμε ένα κοινό Docker network.

*   **Κατάλογος Caddy:** `/data/caddy/`
    *   `Caddyfile`: Η κύρια διαμόρφωση του Caddy.
    *   `docker-compose.yml`: Οι ρυθμίσεις του Docker container του Caddy.
    *   `data/`: Περιέχει τα certificates, cache και άλλες πληροφορίες που δημιουργεί ο Caddy.
    *   `config/`: Περιέχει την εσωτερική διαμόρφωση του Caddy.
*   **External Docker Network:** `web`
    Αυτό το δίκτυο θα επιτρέψει στα containers (Caddy, Nextcloud `app`, `nc-talk`, `kopia`) να επικοινωνούν μεταξύ τους με τα ονόματά τους, χωρίς να εκθέτουν τις εσωτερικές τους πόρτες στο host.

### 1.1 Δημιουργία External Network
Πριν ξεκινήσετε τον Caddy (ή οποιοδήποτε άλλο container που θα χρησιμοποιήσει το δίκτυο `web`), βεβαιωθείτε ότι αυτό το δίκτυο έχει δημιουργηθεί:

```bash
docker network create web
```

## 2. Caddy Docker Compose Configuration
Δημιουργήστε τον φάκελο `/data/caddy` στον host σας και εντός του το αρχείο `docker-compose.yml`.

**Αρχείο:** `/data/caddy/docker-compose.yml`

```yaml
version: '3.8'

services:
  caddy:
    image: caddy:latest
    container_name: caddy
    restart: unless-stopped
    ports:
      # Εκθέτουμε τις standard HTTP και HTTPS πόρτες του host στον Caddy
      - "80:80"
      - "443:443"
    volumes:
      # Mountάρουμε το Caddyfile μας στο container
      - ./Caddyfile:/etc/caddy/Caddyfile
      # Mountάρουμε τους φακέλους για μόνιμη αποθήκευση δεδομένων του Caddy
      - ./data:/data 
      - ./config:/config
      # Mountάρουμε τους φακέλους του Nextcloud σε read-only για πρόσβαση σε στατικά αρχεία
      - /data/nextcloud/app:/var/www/html:ro  # Κώδικας Nextcloud
      - /data/nextcloud/nc_data:/var/www/data:ro  # Δεδομένα χρηστών Nextcloud
    networks:
      - web # Συνδέουμε τον Caddy στο external 'web' network

networks:
  web:
    external: true # Δηλώνουμε ότι το 'web' network είναι external
```

## 3. Caddyfile Configuration
Δημιουργήστε το αρχείο `Caddyfile` στον φάκελο `/data/caddy`. Αυτή η διαμόρφωση περιλαμβάνει ρυθμίσεις για το Nextcloud (με PHP-FPM και Talk HPB) και, προαιρετικά, για το Kopia UI.

**Αρχείο:** `/data/caddy/Caddyfile`

```caddy
# Ρυθμίσεις για το Nextcloud
cloud.iosifidis.gr {
    # Ορίζουμε το root για τα στατικά αρχεία του Nextcloud
    root * /var/www/html
    file_server # Ενεργοποιεί το σερβίρισμα στατικών αρχείων
    encode zstd gzip # Ενεργοποιεί την συμπίεση περιεχομένου

    # Προσθήκη για σωστό handling των PHP αρχείων μέσω FastCGI
    php_fastcgi nextcloud_app:9000 { # Επικοινωνία με το Nextcloud FPM container
        env FRONT_END_HTTPS on
        index index.php # Αυτό βοηθάει το Caddy να βρει το index.php αν λείπει από το URL
    }

    # Redirects για mobile apps (CalDAV/CardDAV discovery)
    redir /.well-known/carddav /remote.php/dav 301
    redir /.well-known/caldav /remote.php/dav 301

    # Security Headers για βελτιωμένη ασφάλεια
    header {
        Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
        X-Content-Type-Options "nosniff"
        X-Frame-Options "SAMEORIGIN"
        Referrer-Policy "no-referrer"
        X-XSS-Protection "1; mode=block" # Πρόσθετο header για προστασία από XSS
    }

    # Προστασία ευαίσθητων φακέλων από άμεση πρόσβαση
    @forbidden {
        path /data/* /config/* /db_structure/* /.xml /etc/*
    }
    respond @forbidden 403

    # Ορίζουμε το μέγιστο μέγεθος request body (για uploads)
    request_body {
        max_size 10GB
    }

    # Ρυθμίσεις για το Nextcloud Talk High Performance Backend
    route /standalone-signaling/* {
        uri strip_prefix /standalone-signaling
        reverse_proxy http://talk_hpb:8081 { # Επικοινωνία με το Nextcloud Talk HPB container
           header_up X-Real-IP {remote_host}
        }
    }
}

# Προαιρετικές ρυθμίσεις για το Kopia Web UI
kopia.cloud.iosifidis.gr {
    reverse_proxy kopia:51515 # Επικοινωνία με το Kopia container
}
```

## 4. Εκκίνηση και Διαχείριση του Caddy

1.  **Μεταβείτε στον κατάλογο του Caddy:**
    ```bash
    cd /data/caddy
    ```
2.  **Εκκίνηση του Caddy container:**
    ```bash
    docker compose up -d
    ```
    Ο Caddy θα ξεκινήσει, θα προσπαθήσει να εκδώσει SSL certificates για τα domains που ορίσατε (`cloud.iosifidis.gr`, `kopia.cloud.iosifidis.gr`) και θα αρχίσει να εξυπηρετεί την κίνηση.
    *Σημείωση: Βεβαιωθείτε ότι τα domains σας δείχνουν στην IP του server σας και ότι οι πόρτες 80 και 443 είναι ανοιχτές στο firewall.*

3.  **Επανεκκίνηση ή Reload της διαμόρφωσης (αν αλλάξετε το Caddyfile):**
    Αν κάνετε αλλαγές στο `Caddyfile`, μπορείτε να τις εφαρμόσετε χωρίς να διακόψετε πλήρως την υπηρεσία:
    ```bash
    docker exec caddy caddy reload --config /etc/caddy/Caddyfile
    ```
    Για πλήρη επανεκκίνηση του container:
    ```bash
    docker compose restart caddy
    ```

## 5. Έλεγχος Λειτουργίας
*   Επισκεφθείτε τα domains σας στον browser (π.χ., `https://cloud.iosifidis.gr`, `https://kopia.cloud.iosifidis.gr`). Θα πρέπει να δείτε την αντίστοιχη υπηρεσία και ένα έγκυρο SSL certificate.
*   Ελέγξτε τα logs του Caddy για τυχόν σφάλματα:
    ```bash
    docker compose logs caddy
    ```
