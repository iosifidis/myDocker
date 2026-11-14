# ONLYOFFICE Document Server Deployment with Docker & Caddy Reverse Proxy

Το **ONLYOFFICE Docs (Document Server)** είναι μια ανοικτού κώδικα σουίτα επεξεργασίας κειμένων, υπολογιστικών φύλλων και παρουσιάσεων σε πραγματικό χρόνο, συμβατή με πολλαπλές εγκαταστάσεις **Nextcloud** (multi-tenancy) μέσω **JWT authentication**. Ο παρών οδηγός καλύπτει την αυτόνομη ανάπτυξή του με **PostgreSQL**, **RabbitMQ** και **Caddy** ως Reverse Proxy.

> **Σημείωση:** Για ταυτόχρονη ανάπτυξη μαζί με Collabora Online στο ίδιο VM, δείτε τον φάκελο [office_suites](../office_suites/).

---

## 📋 Περιεχόμενα
- [Προαπαιτούμενα](#-προαπαιτούμενα)
- [Δομή Αρχείων](#-δομή-αρχείων)
- [Οδηγίες Εγκατάστασης](#-οδηγίες-εγκατάστασης)
- [Ρύθμιση στο Nextcloud](#-ρύθμιση-στο-nextcloud)
- [Επαλήθευση Λειτουργίας](#-επαλήθευση-λειτουργίας)
- [Αντιμετώπιση Προβλημάτων (Troubleshooting)](#-αντιμετώπιση-προβλημάτων-troubleshooting)

---

## 🛠 Προαπαιτούμενα

* **Docker** και **Docker Compose V2** εγκατεστημένα στον διακομιστή (Linux).
* **Swapfile (4 GB προτείνεται):** Απαραίτητο για την αποφυγή memory alarms στο RabbitMQ.
* Εγκατεστημένος **Caddy Server** συνδεδεμένος στο εξωτερικό Docker δίκτυο `web`.
* Έγκυρο DNS A/AAAA record για `onlyoffice.iosifidis.gr`.

---

## 📁 Δομή Αρχείων

```bash
/onlyoffice
├── Caddyfile              # Caddy Reverse Proxy για onlyoffice.iosifidis.gr
├── docker-compose.yml     # Υπηρεσίες onlyoffice, onlyoffice-db (PostgreSQL) & RabbitMQ
├── .env.example           # Πρότυπο μεταβλητών περιβάλλοντος (DB & JWT)
├── Readme.md              # Οδηγίες εγκατάστασης & χρήσης
├── onlyoffice_data/       # Persistent data (/var/www/onlyoffice/Data)
├── onlyoffice_log/        # Logs (/var/log/onlyoffice)
└── onlyoffice_postgresql/ # PostgreSQL data
```

---

## ⚙️ Οδηγίες Εγκατάστασης

### 1. Δημιουργία Φακέλων & Δικαιωμάτων

Το ONLYOFFICE εκτελείται εντός του container με μη-root χρήστη (`ds`, συνήθως UID/GID `107:107`):

```bash
mkdir -p onlyoffice_data onlyoffice_log onlyoffice_postgresql
chmod -R 777 onlyoffice_data onlyoffice_log onlyoffice_postgresql
```

*(Μετά το αρχικό bootstrap, μπορείτε να περιορίσετε την ιδιοκτησία σε `sudo chown -R 107:107 onlyoffice_data onlyoffice_log`).*

### 2. Μεταβλητές Περιβάλλοντος (`.env`)

```bash
cp .env.example .env
```

```env
POSTGRES_DB_PASSWORD=StrongPostgresPasswordHere
ONLYOFFICE_JWT_SECRET=StrongRandomJwtSecretHere
```

### 3. Εκκίνηση Stack & Ρύθμιση Caddyfile

```bash
docker compose up -d
```

Προσθέστε το περιεχόμενο του [Caddyfile](./Caddyfile) στον Caddy server για το `onlyoffice.iosifidis.gr`.

---

## 🔗 Ρύθμιση στο Nextcloud (Multi-Tenancy)

Το ONLYOFFICE υποστηρίζει απεριόριστα Nextcloud instances ταυτόχρονα μέσω του `JWT_SECRET`:

1. Στο Nextcloud, ενεργοποιήστε την εφαρμογή **ONLYOFFICE**.
2. Μεταβείτε στις **Ρυθμίσεις Διαχείρισης → ONLYOFFICE** και συμπληρώστε:
   * **Document Editing Service address:** `https://onlyoffice.iosifidis.gr`
   * **Secret key:** `<ONLYOFFICE_JWT_SECRET>`
   * **Authorization header:** `Authorization`
3. Πατήστε **Αποθήκευση (Save)**.

---

## ✅ Επαλήθευση Λειτουργίας

```bash
# Έλεγχος RabbitMQ alarms (αναμενόμενο: χωρίς alarms)
docker compose exec onlyoffice-rabbitmq rabbitmq-diagnostics -q alarms

# Έλεγχος εσωτερικών διεργασιών ONLYOFFICE
docker compose exec onlyoffice supervisorctl status

# Εξωτερικό healthcheck (αναμενόμενο: HTTP/2 200 και body: true)
curl -s -i https://onlyoffice.iosifidis.gr/healthcheck | head -n 1
```

---

## 🔧 Αντιμετώπιση Προβλημάτων (Troubleshooting)

* **`HTTP 502 Bad Gateway` στο `/healthcheck`:** Ελέγξτε αν η PostgreSQL και το RabbitMQ είναι ενεργά (`docker compose ps`) και αν τα `onlyoffice_data`/`onlyoffice_log` έχουν δικαιώματα εγγραφής.
* **RabbitMQ `system_memory_high_watermark`:** Ενεργοποιήστε swapfile 4 GB στον host (`swapon /swapfile`) και επανεκκινήστε το RabbitMQ (`docker compose restart onlyoffice-rabbitmq`).
