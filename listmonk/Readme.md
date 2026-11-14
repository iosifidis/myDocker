# Listmonk Deployment with Docker & Caddy Reverse Proxy

Το **Listmonk** είναι ένα αυτο-φιλοξενούμενο (self-hosted), υψηλής απόδοσης σύστημα διαχείρισης ενημερωτικών δελτίων (newsletters) και λιστών αλληλογραφίας (mailing lists), γραμμένο σε Go. Ο παρών οδηγός παρέχει οδηγίες και αρχεία ρυθμίσεων για την εγκατάσταση του **Listmonk** σε περιβάλλον Docker με **PostgreSQL** ως βάση δεδομένων και **Caddy** ως Reverse Proxy.

---

## 📋 Περιεχόμενα
- [Τι είναι το Listmonk](#-τι-είναι-το-listmonk)
- [Προαπαιτούμενα](#-προαπαιτούμενα)
- [Δομή Αρχείων](#-δομή-αρχείων)
- [Οδηγίες Εγκατάστασης](#-οδηγίες-εγκατάστασης)
  - [1. Δημιουργία Φακέλων & Δικαιωμάτων](#1-δημιουργία-φακέλων--δικαιωμάτων)
  - [2. Μεταβλητές Περιβάλλοντος (.env)](#2-μεταβλητές-περιβάλλοντος-env)
  - [3. Docker Compose Configuration](#3-docker-compose-configuration)
  - [4. Ρύθμιση Caddyfile](#4-ρύθμιση-caddyfile)
  - [5. Εκκίνηση και Έλεγχος](#5-εκκίνηση-και-έλεγχος)
- [Ρύθμιση SMTP & Deliverability](#-ρύθμιση-smtp--deliverability)

---

## 📧 Τι είναι το Listmonk

Το Listmonk προσφέρει:
* **Υψηλή απόδοση:** Διαχειρίζεται εκατομμύρια συνδρομητές και καμπάνιες με ελάχιστη κατανάλωση πόρων.
* **Διαχείριση λιστών & προτύπων:** Υποστήριξη οπτικού επεξεργαστή (WYSIWYG), HTML templates, τμηματοποίησης (segmentation) και παρακολούθησης ανοιγμάτων/κλικ.
* **Υποχρεωτική χρήση PostgreSQL:** Το Listmonk απαιτεί **υποχρεωτικά PostgreSQL** (έκδοση `>= 13`) λόγω εξειδικευμένων χαρακτηριστικών (όπως JSONB, full-text search και specific indexing). Δεν υποστηρίζει SQLite.

> **Σημείωση για SQLite (Εγκυκλοπαιδικά):**
> Το backend του Listmonk είναι σχεδιασμένο σε Go αποκλειστικά γύρω από drivers και queries της PostgreSQL. Αν υποστήριζε SQLite, δεν θα χρειαζόταν το service `listmonk-db` και θα είχαμε απλώς ένα bind mount τύπου `./data:/listmonk/data`. Στην περίπτωση του Listmonk, όμως, η PostgreSQL είναι μονόδρομος.

---

## 🛠 Προαπαιτούμενα

* **Docker** και **Docker Compose** εγκατεστημένα στον διακομιστή (Linux ARM64 / x86_64).
* Εγκατεστημένος **Caddy Server** συνδεδεμένος σε εξωτερικό Docker δίκτυο με όνομα `web`.
* Ένα **Domain Name** (π.χ. `listmonk.sandbox.iosifidis.gr`).
* Δικαιώματα `sudo` / `root` στον host διακομιστή.

---

## 📁 Δομή Αρχείων

```bash
/listmonk
├── Caddyfile              # Ρυθμίσεις Caddy Reverse Proxy
├── Configuration.md       # Αναλυτικός οδηγός ρύθμισης Postfix SMTP & Deliverability
├── docker-compose.yml     # Υπηρεσίες listmonk-app & listmonk-db (PostgreSQL)
├── .env.example           # Πρότυπο μεταβλητών περιβάλλοντος
├── Readme.md              # Οδηγίες εγκατάστασης & χρήσης
├── db/                    # Bind mount βάσης PostgreSQL (δικαιώματα 999:999)
└── uploads/               # Bind mount αρχείων πολυμέσων (δικαιώματα 1000:1000)
```

---

## ⚙️ Οδηγίες Εγκατάστασης

### 1. Δημιουργία Φακέλων & Δικαιωμάτων

Εκτελέστε τις παρακάτω εντολές στο host σύστημα για τη δημιουργία της δομής καταλόγων και τη σωστή απόδοση δικαιωμάτων.

Η επίσημη εικόνα της **PostgreSQL** εκτελείται με UID/GID `999:999`, ενώ το **Listmonk** εκτελείται ως μη προνομιούχος χρήστης (`1000:1000`) για τη διαχείριση των ανεβασμένων αρχείων (media/uploads):

```bash
# Δημιουργία δομής φακέλων
mkdir -p db uploads

# Ορισμός δικαιωμάτων
# 999:999 για τη βάση PostgreSQL
sudo chown -R 999:999 db
sudo chmod 700 db

# 1000:1000 για τα uploads του Listmonk
sudo chown -R 1000:1000 uploads
sudo chmod 755 uploads
```

### 2. Μεταβλητές Περιβάλλοντος (.env)

Αντιγράψτε το αρχείο `.env.example` σε `.env` και αντικαταστήστε τους κωδικούς πρόσβασης με ασφαλή αλφαριθμητικά:

```bash
cp .env.example .env
```

```env
# Domain & URLs
ROOT_URL=https://listmonk.sandbox.iosifidis.gr

# PostgreSQL Credentials
LISTMONK_DB_USER=listmonk
LISTMONK_DB_PASSWORD=StrongDatabasePasswordHere123!
LISTMONK_DB_NAME=listmonk

# Super Admin Account (αρχική δημιουργία κατά το install)
LISTMONK_ADMIN_USER=admin
LISTMONK_ADMIN_PASSWORD=StrongAdminPasswordHere123!
```

### 3. Docker Compose Configuration

Το αρχείο [docker-compose.yml](./docker-compose.yml) ορίζει δύο υπηρεσίες (`listmonk-db` και `listmonk-app`) σε απομονωμένο εσωτερικό δίκτυο `internal`, ενώ εκθέτει την εφαρμογή στο εξωτερικό δίκτυο `web` για τον Caddy:

```yaml
services:
  listmonk-db:
    image: postgres:16-alpine
    container_name: listmonk-db
    restart: unless-stopped
    environment:
      POSTGRES_USER: ${LISTMONK_DB_USER}
      POSTGRES_PASSWORD: ${LISTMONK_DB_PASSWORD}
      POSTGRES_DB: ${LISTMONK_DB_NAME}
    volumes:
      - ./db:/var/lib/postgresql/data
    networks:
      - internal
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${LISTMONK_DB_USER} -d ${LISTMONK_DB_NAME}"]
      interval: 10s
      timeout: 5s
      retries: 5

  listmonk-app:
    image: listmonk/listmonk:latest
    container_name: listmonk-app
    restart: unless-stopped
    # Στο πρώτο boot εκτελεί schema install / upgrade και ξεκινά το app
    command: [sh, -c, "yes | ./listmonk --install --idempotent --config '' && ./listmonk --upgrade --yes --config '' && ./listmonk --config ''"]
    depends_on:
      listmonk-db:
        condition: service_healthy
    environment:
      - LISTMONK_app__address=0.0.0.0:9000
      - LISTMONK_app__root_url=${ROOT_URL}
      - LISTMONK_db__host=listmonk-db
      - LISTMONK_db__port=5432
      - LISTMONK_db__user=${LISTMONK_DB_USER}
      - LISTMONK_db__password=${LISTMONK_DB_PASSWORD}
      - LISTMONK_db__database=${LISTMONK_DB_NAME}
      - LISTMONK_db__ssl_mode=disable
      - LISTMONK_db__max_open=25
      - LISTMONK_db__max_idle=25
      - LISTMONK_ADMIN_USER=${LISTMONK_ADMIN_USER}
      - LISTMONK_ADMIN_PASSWORD=${LISTMONK_ADMIN_PASSWORD}
    volumes:
      - ./uploads:/listmonk/uploads
    networks:
      - internal
      - web

networks:
  internal:
    internal: true
  web:
    external: true
```

### 4. Ρύθμιση Caddyfile

#### Εκδοχή Α: Subdomain (`listmonk.sandbox.iosifidis.gr`) — Προτεινόμενη

Περιλαμβάνεται έτοιμη στο [Caddyfile](./Caddyfile):

```caddyfile
listmonk.sandbox.iosifidis.gr {
    encode zstd gzip

    reverse_proxy listmonk-app:9000
}
```

#### Εκδοχή Β: Subpath / Subfolder (`sandbox.iosifidis.gr/listmonk`)

* **Απαραίτητη αλλαγή στο `.env`:** Πρέπει να ορίσετε το `ROOT_URL=https://sandbox.iosifidis.gr/listmonk`, ώστε τα links επιβεβαίωσης, εγγραφής και τα static assets να σερβίρονται με το σωστό πρόθεμα.
* **Ιδιαιτερότητα Caddy:** Το Listmonk χειρίζεται εσωτερικά το base path του root URL. Συνεπώς, χρησιμοποιούμε απλό matcher `handle /listmonk*` **χωρίς** `handle_path` (ώστε να μην αφαιρεθεί το πρόθεμα `/listmonk` από το URI που λαμβάνει το container):

```caddyfile
sandbox.iosifidis.gr {
    encode zstd gzip

    handle /listmonk* {
        reverse_proxy listmonk-app:9000
    }

    # Χειρισμός υπόλοιπου domain (αν υπάρχει)
    handle {
        respond "Default landing page" 200
    }
}
```

### 5. Εκκίνηση και Έλεγχος

Εκτελέστε στον φάκελο του Listmonk:

```bash
docker compose up -d
docker compose logs -f listmonk-app
```

Μόλις δείτε το μήνυμα εκκίνησης του HTTP server στο port `9000`, επαληθεύστε την πρόσβαση στο domain σας (π.χ. `https://listmonk.sandbox.iosifidis.gr`) και συνδεθείτε με τα στοιχεία του Super Admin από το αρχείο `.env`.

---

## 📬 Ρύθμιση SMTP & Deliverability

Μετά την ολοκλήρωση της εγκατάστασης, ανατρέξτε στον αναλυτικό οδηγό [Configuration.md](./Configuration.md) για τη ρύθμιση του **Postfix SMTP Submission**, των **General & Campaign Settings**, των απαιτήσεων **SPF/DKIM/DMARC** και του **Troubleshooting Checklist** αποστολής emails.
