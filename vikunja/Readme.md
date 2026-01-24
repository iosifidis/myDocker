# Τεκμηρίωση Εγκατάστασης Vikunja (Lightweight) σε Banana Pi M4

**Σύστημα:** Banana Pi M4 (2GB RAM) / Armbian
**Stack:** Docker, Docker Compose, Caddy (Reverse Proxy)
**Βάση Δεδομένων:** SQLite (Ενσωματωμένη για ελαχιστοποίηση πόρων)
**Domain:** `example.me`

Το [Vikunja](https://vikunja.io/) είναι μια δωρεάν, ανοιχτού κώδικα (open-source) και αυτοφιλοξενούμενη (self-hosted) εφαρμογή διαχείρισης εργασιών (to-do list). Σχεδιασμένο για να οργανώνει τη ζωή και τα έργα σας, προσφέρει πολλαπλές προβολές όπως λίστες, Kanban πίνακες (kanban board), Gantt διαγράμματα και πίνακες δεδομένων. Υποστηρίζει ιεραρχική οργάνωση με υποέργα και είναι διαθέσιμο σε όλες τις πλατφόρμες. 

## 1. Δομή Φακέλων & Δικαιώματα
Το Vikunja απαιτεί συγκεκριμένα δικαιώματα εγγραφής (UID 1000) στους φακέλους για να λειτουργήσει η βάση SQLite και η αποθήκευση αρχείων.

**Εντολές προετοιμασίας:**

```bash
# 1. Δημιουργία κεντρικού φακέλου και υποφακέλων
mkdir -p ./vikunja/{db,files}

# 2. Ρύθμιση ιδιοκτησίας (Ownership)
# Το Vikunja τρέχει με User ID 1000. Οι φάκελοι πρέπει να ανήκουν σε αυτόν.
sudo chown -R 1000:1000 ./vikunja

# 3. Ρύθμιση δικαιωμάτων (Permissions)
sudo chmod -R 755 ./vikunja
```

## 2. Αρχείο `docker-compose.yml`
Διαδρομή: `/docker/vikunja/docker-compose.yml`

Χρησιμοποιούμε την unified εικόνα (`vikunja/vikunja`) με SQLite. Δεν απαιτείται ξεχωριστό container για βάση δεδομένων, εξοικονομώντας ~150-200MB RAM.

```yaml
version: '3'

services:
  vikunja:
    image: vikunja/vikunja
    restart: unless-stopped
    container_name: vikunja
    environment:
      # --- General Settings ---
      VIKUNJA_SERVICE_PUBLICURL: "https://example.me/"
      VIKUNJA_SERVICE_TIMEZONE: "Europe/Athens"
      
      # --- Database Settings (SQLite) ---
      VIKUNJA_DATABASE_TYPE: "sqlite"
      VIKUNJA_DATABASE_PATH: "/db/vikunja.db"
      
      # --- Security & Registration ---
      # Ενεργοποίηση εγγραφών για την αρχική δημιουργία Admin.
      # Προτείνεται να γίνει "false" μετά την εγγραφή του πρώτου χρήστη.
      VIKUNJA_SERVICE_ENABLEREGISTRATION: "true" 
      
    volumes:
      # Bind Mounts για εύκολο backup/migration
      - ./files:/app/vikunja/files  # Αποθήκευση uploaded files
      - ./db:/db                    # Αποθήκευση SQLite DB
    networks:
      - web
    ports:
      - "3456:3456"

networks:
  web:
    external: true
```

## 3. Ρύθμιση Caddy (Reverse Proxy)
Το Caddy αναλαμβάνει το SSL termination και τη δρομολόγηση.
Αρχείο: `/etc/caddy/Caddyfile` (ή όπου έχετε το mapping του Caddy config).

Προσθήκη του παρακάτω block:

```caddy
example.me {
    # Συμπίεση για βελτίωση ταχύτητας (σημαντικό για mobile/pi)
    encode zstd gzip

    # Reverse Proxy στην θύρα 3456 του Vikunja
    reverse_proxy vikunja:3456
}
```

**Εφαρμογή αλλαγών Caddy:**
```bash
# Αν το Caddy τρέχει σε docker (αντικαταστήστε το caddy_container_name)
docker exec -it caddy caddy reload
```

## 4. Διαδικασία Εκκίνησης (Deployment)

```bash
cd ~/vikunja

# Εκκίνηση στο background
docker compose up -d

# Έλεγχος logs (για επιβεβαίωση "Migration successful")
docker compose logs -f
```

## 5. Διαχείριση & Συντήρηση

### Backup / Migration
Επειδή χρησιμοποιούμε **Bind Mounts** και **SQLite**, η διαδικασία backup είναι απλή αντιγραφή φακέλου.

**Διαδικασία Backup:**
1.  `cd ~/vikunja`
2.  `docker compose down` (Για να σταματήσει η εγγραφή στη βάση)
3.  Αντιγραφή όλου του φακέλου `~/vikunja` σε άλλο δίσκο/server (συμπίεση `tar -czvf vikinja.tar.gz vikinja/` και αποσυμπίεση `tar -xzvf vikinja.tar.gz`).
4.  `docker compose up -d`

### Αναβάθμιση (Update)
Για να αναβαθμιστείτε σε νεότερη έκδοση του Vikunja:

```bash
cd ~/vikunja
docker compose pull
docker compose up -d
```

### Troubleshooting
Αν εμφανιστεί σφάλμα `permission denied` στα logs (όπως `open /db/vikunja.db: permission denied`):
1.  Σταματήστε το container.
2.  Τρέξτε ξανά: `sudo chown -R 1000:1000 ~/vikunja`
3.  Ξεκινήστε το container.
