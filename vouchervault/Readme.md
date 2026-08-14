# VoucherVault Deployment with Docker Compose & Caddy Reverse Proxy

Το **VoucherVault** είναι μια ανοικτού κώδικα εφαρμογή διαχείρισης κουπονιών, δωροκαρτών και ημερομηνιών λήξης (vouchers & gift cards). Υποστηρίζει αυτόματες ειδοποιήσεις λήξης μέσω της βιβλιοθήκης **Apprise** (e-mail/SMTP, Telegram, Discord, κ.ά.) και χρησιμοποιεί **Redis** για τη διαχείριση εργασιών Celery.

Αυτός ο οδηγός παρέχει αναλυτικές οδηγίες εγκατάστασης και παραμετροποίησης της εφαρμογής σε περιβάλλον Docker με **Caddy** ως reverse proxy.

---

## 📋 Περιεχόμενα
- [Τι είναι το VoucherVault](#-τι-είναι-το-vouchervault)
- [Προαπαιτούμενα](#-προαπαιτούμενα)
- [Δομή Αρχείων](#-δομή-αρχείων)
- [Οδηγίες Εγκατάστασης](#-οδηγίες-εγκατάστασης)
  - [1. Δημιουργία Φακέλων & Δικαιωμάτων](#1-δημιουργία-φακέλων--δικαιωμάτων)
  - [2. Ρύθμιση Μεταβλητών Περιβάλλοντος (.env)](#2-ρύθμιση-μεταβλητών-περιβάλλοντος-env)
  - [3. Docker Compose Configuration](#3-docker-compose-configuration)
  - [4. Εκκίνηση Stack & Ανάκτηση Κωδικού Admin](#4-εκκίνηση-stack--ανάκτηση-κωδικού-admin)
  - [5. Ρύθμιση Reverse Proxy (Caddy)](#5-ρύθμιση-reverse-proxy-caddy)
- [Ρύθμιση Ειδοποιήσεων (Apprise / SMTP)](#-ρύθμιση-ειδοποιήσεων-apprise--smtp)
- [Επαλήθευση Λειτουργίας](#-επαλήθευση-λειτουργίας)
- [Αντιμετώπιση Προβλημάτων (Troubleshooting)](#-αντιμετώπιση-προβλημάτων-troubleshooting)
- [Αντίγραφα Ασφαλείας & Βέλτιστες Πρακτικές](#-αντίγραφα-ασφαλείας--βέλτιστες-πρακτικές)

---

## 🎟️ Τι είναι το VoucherVault

Το VoucherVault σάς επιτρέπει να:
* Αποθηκεύετε και να οργανώνετε κουπόνια, δωροκάρτες, εκπτωτικούς κωδικούς και vouchers.
* Παρακολουθείτε τις ημερομηνίες λήξης τους.
* Λαμβάνετε αυτόματες ειδοποιήσεις πριν λήξουν τα κουπόνια σας μέσω **Apprise** (e-mail, push notifications, κ.λπ.).
* Διαχειρίζεστε πρόσβαση πολλαπλών χρηστών με ασφάλεια.

---

## 🛠 Προαπαιτούμενα

* **Docker** και **Docker Compose** εγκατεστημένα στον διακομιστή (π.χ. Linux ARM64 / x86_64).
* Εγκατεστημένος **Caddy Server** συνδεδεμένος σε εξωτερικό Docker δίκτυο με όνομα `web`.
* Ένα **Domain Name** (π.χ. `iosifidis.myddns.me`) συνδεδεμένο με τη δημόσια IP του διακομιστή σας.
* Δικαιώματα `sudo` / `root` στον host διακομιστή.

---

## 📁 Δομή Αρχείων

```bash
/vouchervault
├── Caddyfile              # Ρυθμίσεις Reverse Proxy για το Caddy
├── docker-compose.yml     # Ορισμός υπηρεσιών app & redis
├── .env.example           # Πρότυπο μεταβλητών περιβάλλοντος
├── Readme.md              # Οδηγίες χρήσης και εγκατάστασης
└── volume-data/           # Persistent storage
    └── database/          # SQLite βάση δεδομένων (δικαιώματα 33:33)
```

---

## ⚙️ Οδηγίες Εγκατάστασης

### 1. Δημιουργία Φακέλων & Δικαιωμάτων

Το container της εφαρμογής εκτελείται με τον χρήστη `www-data` (UID/GID `33`). Δημιουργήστε τον κατάλληλο φάκελο για τη βάση δεδομένων και ορίστε τα δικαιώματα:

```bash
mkdir -p volume-data/database
sudo chown -R 33:33 volume-data
```

### 2. Ρύθμιση Μεταβλητών Περιβάλλοντος (.env)

Αντιγράψτε το αρχείο `.env.example` σε `.env`:

```bash
cp .env.example .env
```

Δημιουργήστε ένα τυχαίο `SECRET_KEY` εκτελώντας:

```bash
openssl rand -base64 48
```

Επεξεργαστείτε το αρχείο `.env` και συμπληρώστε τα στοιχεία σας:

```env
# --- Django Configuration ---
DOMAIN=iosifidis.myddns.me
SECURE_COOKIES=True
TZ=Europe/Athens
SECRET_KEY=το_παραγόμενο_secret_key

# --- Expiry Notifications ---
EXPIRY_THRESHOLD_DAYS=30
EXPIRY_LAST_NOTIFICATION_DAYS=7
```

> **⚠️ Προσοχή:** Η τιμή `DOMAIN` πρέπει να ταυτίζεται **ακριβώς** με το domain που θα χρησιμοποιηθεί στο Caddyfile.

### 3. Docker Compose Configuration

Το αρχείο `docker-compose.yml` ορίζει το VoucherVault (`app`) και το `redis`:

```yaml
services:

  app:
    image: l4rm4nd/vouchervault:latest
    container_name: vouchervault
    restart: unless-stopped
    env_file:
      - .env
    environment:
      - REDIS_URL=redis://redis:6379/0
    volumes:
      - ./volume-data/database:/opt/app/database
    networks:
      - web
      - internal
    depends_on:
      - redis

  redis:
    image: redis:7-alpine
    container_name: vouchervault-redis
    restart: unless-stopped
    networks:
      - internal

networks:
  web:
    external: true
  internal:
    driver: bridge
```

### 4. Εκκίνηση Stack & Ανάκτηση Κωδικού Admin

Εκκινήστε τα containers:

```bash
docker compose up -d
```

Κατά την πρώτη εκκίνηση, ο αρχικός κωδικός πρόσβασης του διαχειριστή (`admin`) παράγεται αυτόματα. Δείτε τον στα logs της εφαρμογής:

```bash
docker compose logs -f app
```

### 5. Ρύθμιση Reverse Proxy (Caddy)

Προσθέστε το παρακάτω block στο `Caddyfile` του διακομιστή Caddy:

```caddyfile
iosifidis.myddns.me {
    reverse_proxy vouchervault:8000
}
```

Επανεκκινήστε ή ανανεώστε τις ρυθμίσεις του Caddy:

```bash
docker exec caddy caddy reload
```

---

## 🔔 Ρύθμιση Ειδοποιήσεων (Apprise / SMTP)

Οι ειδοποιήσεις ρυθμίζονται **αποκλειστικά μέσω του UI** του VoucherVault στο προφίλ χρήστη (**Notification Settings**).

### Παράδειγμα URL SMTP (Apprise format):

```text
mailtos://eiosifidis%40ellak.gr:<SMTP_PASSWORD>@mail1.ellak.gr:587/?to=eiosifidis%40gmail.com&from=eiosifidis%40ellak.gr
```

> **💡 Σημαντικές σημειώσεις URL Encoding:**
> - Ο χαρακτήρας `@` στα usernames και emails πρέπει να αντικατασταθεί με `%40`.
> - Ειδικοί χαρακτήρες στον κωδικό πρόσβασης (π.χ. `#`, `&`, `%`) πρέπει να υποστούν URL encoding (`%23`, `%26`, `%25`).
> - Η παράμετρος `from=` πρέπει να ορίζεται ρητά στο τέλος του URL.

#### Δοκιμή αποστολής μέσω CLI:
```bash
docker exec -it vouchervault apprise -t "Test Title" -b "Test Body" "mailtos://eiosifidis%40ellak.gr:<PASSWORD>@mail1.ellak.gr:587/?to=eiosifidis%40gmail.com&from=eiosifidis%40ellak.gr"
```

---

## ✅ Επαλήθευση Λειτουργίας

1. **Έλεγχος HTTP Response:**
   ```bash
   curl -v http://vouchervault:8000 -H "Host: iosifidis.myddns.me"
   ```
   *Αναμενόμενο αποτέλεσμα: HTTP 200 OK ή 302 Redirect.*

2. **Έλεγχος Logs:**
   ```bash
   docker compose logs -f app
   ```

---

## 🔍 Αντιμετώπιση Προβλημάτων (Troubleshooting)

### 1. Σφάλμα `400 Bad Request`
* **Αιτία:** Ασυμφωνία της μεταβλητής `DOMAIN` στο `.env` με το Domain στο Caddyfile ή τα `ALLOWED_HOSTS` του Django.
* **Λύση:** Βεβαιωθείτε ότι το `DOMAIN=iosifidis.myddns.me` στο `.env` γράφεται ακριβώς όπως στο Caddyfile (χωρίς ορθογραφικά λάθη) και κάντε επανεκκίνηση:
  ```bash
  docker compose down && docker compose up -d
  ```

### 2. Σφάλμα `Invalid ~From~ email specified` στο Apprise
* **Αιτία:** Μη κωδικοποιημένος χαρακτήρας `@` ή ειδικός χαρακτήρας στο username/password του SMTP URL.
* **Λύση:** Αντικαταστήστε το `@` με `%40` και προσθέστε παράμετρο `&from=your_email%40domain.com`.

---

## 💾 Αντίγραφα Ασφαλείας & Βέλτιστες Πρακτικές

1. **SQLite Database Backup:**
   Δημιουργήστε περιοδικά αντίγραφα ασφαλείας του φακέλου `./volume-data/database`:
   ```bash
   tar -czvf vouchervault-db-backup-$(date +%F).tar.gz ./volume-data/database
   ```
2. **Δικαιώματα:**
   Διατηρείτε πάντα τα δικαιώματα `33:33` στον φάκελο `volume-data` για να αποφύγετε σφάλματα εγγραφής στη βάση SQLite.
3. **Ενημερώσεις:**
   ```bash
   docker compose pull
   docker compose up -d
   docker image prune -f
   ```
