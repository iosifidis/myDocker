# Pretix Deployment with Docker & Caddy Reverse Proxy

Το **Pretix** είναι μια ανοικτού κώδικα πλατφόρμα διαχείρισης εκδηλώσεων, εγγραφών και έκδοσης εισιτηρίων (ticketing). Ο παρών οδηγός περιγράφει την ανάπτυξη του Pretix σε περιβάλλον Docker Compose με **PostgreSQL**, **Redis** και **Caddy** ως Reverse Proxy, χρησιμοποιώντας αποκλειστικά bind mounts.

---

## 📋 Περιεχόμενα
- [Τι είναι το Pretix](#-τι-είναι-το-pretix)
- [Προαπαιτούμενα](#-προαπαιτούμενα)
- [Δομή Αρχείων](#-δομή-αρχείων)
- [Οδηγίες Εγκατάστασης](#-οδηγίες-εγκατάστασης)
  - [1. Δημιουργία Φακέλων & Δικαιωμάτων](#1-δημιουργία-φακέλων--δικαιωμάτων)
  - [2. Παραμετροποίηση Εφαρμογής (pretix.cfg & .env)](#2-παραμετροποίηση-εφαρμογής-pretixcfg--env)
  - [3. Docker Compose Configuration](#3-docker-compose-configuration)
  - [4. Αρχικοποίηση Βάσης & Πρώτη Εκκίνηση](#4-αρχικοποίηση-βάσης--πρώτη-εκκίνηση)
  - [5. Ρύθμιση Caddyfile](#5-ρύθμιση-caddyfile)
- [Διαχείριση, Συντήρηση & Troubleshooting](#-διαχείριση-συντήρηση--troubleshooting)
- [Ρύθμιση Διοργανωτών, Ομάδων & Συσκευών](#-ρύθμιση-διοργανωτών-ομάδων--συσκευών)

---

## 🎟️ Τι είναι το Pretix

Το Pretix προσφέρει:
* **Πλήρη διαχείριση εκδηλώσεων & εισιτηρίων:** Ποσοστώσεις (quotas), προσαρμοσμένες ερωτήσεις, κουπόνια (vouchers) και αυτόματη παραγωγή PDF/Passbook εισιτηρίων.
* **Ασύγχρονη επεξεργασία:** Διαχωρισμός σε web frontend (`pretix-web`) και background task worker (`pretix-worker`) μέσω Redis για αποστολή emails και δημιουργία αρχείων.
* **Check-in & Σάρωση (pretixSCAN):** Υποστήριξη εφαρμογών Android/iOS για έλεγχο εισόδου (ακόμα και offline).

---

## 🛠 Προαπαιτούμενα

* **Docker** και **Docker Compose** εγκατεστημένα στον διακομιστή (Linux).
* Εγκατεστημένος **Caddy Server** συνδεδεμένος στο εξωτερικό Docker δίκτυο `web`:
  ```bash
  docker network inspect web >/dev/null 2>&1 || docker network create web
  ```
* Ένα **Domain Name / FQDN** (π.χ. `ticket.iosifidis.gr`) συνδεδεμένο με τη δημόσια IP του διακομιστή.
* Δικαιώματα `sudo` / `root` στον host διακομιστή.

---

## 📁 Δομή Αρχείων

```bash
/Pretix
├── Caddyfile              # Ρυθμίσεις Caddy Reverse Proxy
├── Configuration.md       # Οδηγός ρύθμισης διοργανωτών, ομάδων, δικαιωμάτων & pretixSCAN
├── docker-compose.yml     # Υπηρεσίες pretix-web, pretix-worker, PostgreSQL & Redis
├── .env.example           # Πρότυπο μεταβλητών περιβάλλοντος
├── Readme.md              # Οδηγίες εγκατάστασης, συντήρησης & troubleshooting
├── data/                  # Bind mount δεδομένων Pretix (δικαιώματα 15371:15371)
├── etc/                   # Bind mount ρυθμίσεων (pretix.cfg, δικαιώματα 15371:15371)
├── db/                    # Bind mount βάσης PostgreSQL (δικαιώματα 999:999)
└── redis/                 # Bind mount Redis (δικαιώματα 999:999)
```

---

## ⚙️ Οδηγίες Εγκατάστασης

### 1. Δημιουργία Φακέλων & Δικαιωμάτων

Το container του Pretix εκτελείται εσωτερικά με **UID/GID `15371:15371`**, ενώ η PostgreSQL και το Redis εκτελούνται με **`999:999`**. Εκτελέστε στο host:

```bash
# Δημιουργία φακέλων
mkdir -p data db redis etc

# Ορισμός σωστών ιδιοκτησιών ανά υπηρεσία
sudo chown -R 15371:15371 data etc
sudo chown -R 999:999 db redis

# Περιορισμός δικαιωμάτων
sudo chmod 700 db redis
sudo chmod 750 data etc
```

### 2. Παραμετροποίηση Εφαρμογής (`pretix.cfg` & `.env`)

#### Αρχείο `etc/pretix.cfg`
Το Pretix διαβάζει τις βασικές του ρυθμίσεις από το `/etc/pretix/pretix.cfg`. Δημιουργήστε το αρχείο `etc/pretix.cfg`:

```ini
[pretix]
instance_name = ΕΕΛΛΑΚ Εισιτήριο
url = https://ticket.iosifidis.gr
currency = EUR
datadir = /data
trust_x_forwarded_for = on
trust_x_forwarded_proto = on

[database]
backend = postgresql
name = pretix
user = pretix
password = SUPER_SECRET_POSTGRES_PASSWORD
host = pretix-db
port = 5432

[redis]
location = redis://pretix-redis:6379/1
sessions = true

[mail]
from = tickets@iosifidis.gr
host = mail.iosifidis.gr
port = 587
user = tickets
password = SUPER_SECRET_MAIL_PASSWORD
tls = on
ssl = off
```

Ορίστε αυστηρά δικαιώματα στο αρχείο ρυθμίσεων:

```bash
sudo chown 15371:15371 etc/pretix.cfg
sudo chmod 600 etc/pretix.cfg
```

#### Αρχείο Περιβάλλοντος (`.env`)
Αντιγράψτε το `.env.example` σε `.env` και συμπληρώστε τον ίδιο κωδικό βάσης με το `pretix.cfg`:

```bash
cp .env.example .env
```

```dotenv
# General
COMPOSE_PROJECT_NAME=pretix

# Database
POSTGRES_DB=pretix
POSTGRES_USER=pretix
POSTGRES_PASSWORD=SUPER_SECRET_POSTGRES_PASSWORD
```

### 3. Docker Compose Configuration

Το αρχείο [docker-compose.yml](./docker-compose.yml) ορίζει τα services `pretix-db`, `pretix-redis`, `pretix-web` και `pretix-worker`:

```yaml
services:
  pretix-db:
    image: postgres:16-alpine
    container_name: pretix-db
    restart: unless-stopped
    environment:
      POSTGRES_DB: ${POSTGRES_DB}
      POSTGRES_USER: ${POSTGRES_USER}
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD}
    volumes:
      - ./db:/var/lib/postgresql/data
    networks:
      - internal

  pretix-redis:
    image: redis:7-alpine
    container_name: pretix-redis
    restart: unless-stopped
    volumes:
      - ./redis:/data
    networks:
      - internal

  pretix-web:
    image: pretix/standalone:stable
    container_name: pretix-web
    restart: unless-stopped
    depends_on:
      - pretix-db
      - pretix-redis
    volumes:
      - ./data:/data
      - ./etc:/etc/pretix
    networks:
      - internal
      - web

  pretix-worker:
    image: pretix/standalone:stable
    container_name: pretix-worker
    restart: unless-stopped
    command: taskworker
    depends_on:
      - pretix-db
      - pretix-redis
    volumes:
      - ./data:/data
      - ./etc:/etc/pretix
    networks:
      - internal

networks:
  web:
    external: true
  internal:
    driver: bridge
```

### 4. Αρχικοποίηση Βάσης & Πρώτη Εκκίνηση

Εκτελέστε τα βήματα με τη συγκεκριμένη σειρά:

```bash
# 1. Εκκίνηση μόνο της βάσης και του redis
docker compose up -d pretix-db pretix-redis

# 2. Αναμονή μερικών δευτερολέπτων για initialization της Postgres
sleep 5

# 3. Εκτέλεση database migrations
# Σημείωση: Το entrypoint καλεί ήδη το management script, οπότε δίνουμε απευθείας την εντολή migrate
docker compose run --rm pretix-web migrate

# 4. Δημιουργία Administrator
docker compose run --rm pretix-web createsuperuser

# 5. Εκκίνηση ολόκληρου του stack (pretix-web και pretix-worker)
docker compose up -d
```

### 5. Ρύθμιση Caddyfile

Προσθέστε το περιεχόμενο του [Caddyfile](./Caddyfile) στον Caddy server:

```caddyfile
ticket.iosifidis.gr {
    encode zstd gzip

    # Περιορισμός μεγέθους uploads (π.χ. 16MB)
    request_body {
        max_size 16MB
    }

    reverse_proxy pretix-web:80 {
        header_up Host {host}
        header_up X-Real-IP {remote_host}
        header_up X-Forwarded-For {remote_host}
        header_up X-Forwarded-Proto https
    }
}
```

Επαναφόρτωση του Caddy χωρίς διακοπή:

```bash
docker exec -w /etc/caddy caddy caddy reload
```

---

## 🔧 Διαχείριση, Συντήρηση & Troubleshooting

### Εντολές Ελέγχου

```bash
# Έλεγχος κατάστασης containers
docker compose ps

# Ζωντανή παρακολούθηση logs web container
docker compose logs -f --tail=100 pretix-web

# Logs worker (εκτέλεση emails, PDF generation, async tasks)
docker compose logs -f --tail=100 pretix-worker

# Επανεκκίνηση εφαρμογής μετά από αλλαγή στο pretix.cfg
docker compose restart pretix-web pretix-worker
```

### Περιοδική Συντήρηση (`runperiodic`)

Στο Pretix η περιοδική συντήρηση εκτελείται μέσω της εντολής `runperiodic`.

#### Χειροκίνητη εκτέλεση
```bash
docker compose run --rm pretix-web runperiodic
```
Μόλις ολοκληρωθεί, κάντε refresh στο Dashboard και τυχόν προειδοποίηση συντήρησης θα εξαφανιστεί.

#### Αυτοματοποίηση μέσω Cron στο Host
```bash
sudo crontab -e
```
Προσθέστε την παρακάτω γραμμή (εκτέλεση κάθε 15 λεπτά):

```cron
*/15 * * * * cd /docker/pretix && /usr/bin/docker compose run --rm -T pretix-web runperiodic > /dev/null 2>&1
```
*(Η παράμετρος `-T` είναι απαραίτητη στο cron ώστε να μην προσπαθήσει το docker να δεσμεύσει pseudo-TTY).*

### Συνηθισμένα Σφάλματα

* **`Unknown host` / `Expected host: localhost`:**
  * **Αιτία:** Το Pretix δεν διαβάζει το σωστό URL από το `/etc/pretix/pretix.cfg` ή δεν έγινε σωστά το bind mount.
  * **Έλεγχος:** Βεβαιωθείτε ότι υπάρχει η γραμμή `url = https://ticket.iosifidis.gr` στο `etc/pretix.cfg` και ότι το αρχείο ανήκει στο UID `15371`.
* **`Permission denied` στο `/data`:**
  * **Αιτία:** Λανθασμένο UID στο mount point.
  * **Επίλυση:** `sudo chown -R 15371:15371 data`
* **`Unknown command: pretix` κατά το setup:**
  * **Αιτία:** Το image έχει ως entrypoint το Django CLI.
  * **Επίλυση:** Χρήση `docker compose run --rm pretix-web migrate` (και **όχι** `docker compose run --rm pretix-web pretix migrate`).

---

## 👥 Ρύθμιση Διοργανωτών, Ομάδων & Συσκευών

Μετά την εγκατάσταση, ανατρέξτε στον οδηγό [Configuration.md](./Configuration.md) για τη διαμόρφωση **Organizers**, **Teams & Permissions**, την ετήσια ροή δημιουργίας εκδηλώσεων και τη σύνδεση συσκευών σάρωσης **pretixSCAN**.
