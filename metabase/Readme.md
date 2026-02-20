**Υπηρεσία:** Business Intelligence (Metabase)   
**URL:** https://www.metabase.com/   
**Υποδομή:** Docker / Docker Compose   
**Reverse Proxy:** Caddy   
**Δίκτυο:** web (external overlay/bridge)

## 1. Περιγραφή αρχιτεκτονικής
Η υπηρεσία Metabase εγκαθίσταται μέσω containerization. Για τη διασφάλιση της ακεραιότητας των δεδομένων και των ρυθμίσεων (dashboards, χρήστες, credentials), δεν χρησιμοποιείται η ενσωματωμένη H2 database, αλλά μια αποκλειστική instance της **PostgreSQL** (version 15-alpine).

Τα δεδομένα της βάσης αποθηκεύονται στο host σύστημα μέσω **bind volume**, επιτρέποντας την εύκολη λήψη αντιγράφων ασφαλείας (backup) και τη μεταφορά της υπηρεσίας.

## 2. Προετοιμασία συστήματος αρχείων

Για την αποφυγή σφαλμάτων δικαιωμάτων (permission denied) κατά την εγγραφή της PostgreSQL στο host filesystem, απαιτείται η δημιουργία καταλόγου με συγκεκριμένη ιδιοκτησία (UID 999).

**Εντολές προετοιμασίας:**

```bash
# Δημιουργία καταλόγου για τα δεδομένα της βάσης
mkdir -p metabase-db-data

# Αλλαγή ιδιοκτησίας για να αποφύγουμε permission denied errors.
# Ρύθμιση ιδιοκτησίας για τον χρήστη postgres (UID 999 εντός του container)
sudo chown -R 999:999 metabase-db-data

# Εναλλακτικά, αν δεν σε πειράζει η ασφάλεια σε τοπικό επίπεδο φακέλου:
# sudo chmod 777 metabase-db-data
```

## 3. Ρυθμίσεις Docker Compose

Αρχείο: `docker-compose.yml`

```yaml
#version: '3.8'

services:
  # ----------------------------------------------------------------
  # Application Service: Metabase
  # ----------------------------------------------------------------
  metabase:
    image: metabase/metabase:latest
    container_name: metabase
    restart: unless-stopped
    networks:
      - web
    environment:
      # Database Connection Configuration
      MB_DB_TYPE: postgres
      MB_DB_DBNAME: metabase
      MB_DB_PORT: 5432
      MB_DB_USER: metabase
      MB_DB_PASS: ${MB_DB_PASS}  # Ορίζεται στο .env ή αντικαθίσταται με τον κωδικό
      MB_DB_HOST: metabase-db
      # Service Configuration
      MB_SITE_URL: https://metabase.iosifidis.gr
      JAVA_OPTS: "-Xmx1024m"     # Όριο μνήμης Java Heap
    depends_on:
      - metabase-db

  # ----------------------------------------------------------------
  # Backend Service: PostgreSQL (Metadata Storage)
  # ----------------------------------------------------------------
  metabase-db:
    image: postgres:15-alpine
    container_name: metabase-db
    restart: unless-stopped
    networks:
      - web
    environment:
      POSTGRES_USER: metabase
      POSTGRES_PASSWORD: ${MB_DB_PASS} # Πρέπει να ταυτίζεται με το MB_DB_PASS
      POSTGRES_DB: metabase
    volumes:
      # Persistent Storage (Bind Mount)
      - ./metabase-db-data:/var/lib/postgresql/data

networks:
  web:
    external: true
```

*Σημείωση: Συνιστάται η χρήση αρχείου `.env` για την αποθήκευση των κωδικών με το περιεχόμενο:*

```
MB_DB_PASS=αλλάξε_με_έναν_ισχυρό_κωδικό_σου
```

## 4. Ρυθμίσεις Reverse Proxy (Caddy)

Η υπηρεσία εξυπηρετείται μέσω του Caddy, το οποίο διαχειρίζεται αυτόματα τα πιστοποιητικά SSL/TLS. Η επικοινωνία μεταξύ Caddy και Metabase γίνεται εσωτερικά μέσω του δικτύου `web`.

Αρχείο: `Caddyfile`

```caddy
metabase.iosifidis.gr {
    # Forwarding traffic to the metabase container on port 3000
    reverse_proxy metabase:3000
    
    # Logging configuration
    log {
        output file /var/log/caddy/metabase_access.log
    }
}
```

## 5. Διαδικασία Διαχείρισης & Συντήρησης

### 5.1 Εκκίνηση Υπηρεσίας
```bash
docker compose up -d
```

### 5.2 Εφαρμογή αλλαγών στο Caddy
Εφόσον τροποποιηθεί το Caddyfile:
```bash
docker exec -w /etc/caddy caddy caddy reload
```

### 5.3 Διαδικασία Backup
Λόγω της χρήσης bind volume, η διαδικασία backup περιλαμβάνει την απλή αντιγραφή του φακέλου δεδομένων.

1.  Τερματισμός υπηρεσίας για διασφάλιση data consistency:
    ```bash
    docker compose stop
    ```
2.  Αρχειοθέτηση του φακέλου δεδομένων:
    ```bash
    tar -czvf metabase_backup_$(date +%F).tar.gz ./metabase-db-data
    ```
3.  Επανεκκίνηση υπηρεσίας:
    ```bash
    docker compose up -d
    ```

### 5.4 Αναβάθμιση (Update)
Για την αναβάθμιση στην τελευταία έκδοση:
```bash
docker compose pull
docker compose up -d
```
