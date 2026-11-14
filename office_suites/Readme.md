# ONLYOFFICE Docs & Collabora Online (CODE) with Caddy Reverse Proxy

Ο παρών οδηγός περιγράφει τη διαδικασία εγκατάστασης και παραμετροποίησης ενός αυτόνομου Virtual Machine αποκλειστικά για την ταυτόχρονη εξυπηρέτηση σουιτών επεξεργασίας εγγράφων γραφείου — **ONLYOFFICE Document Server** και **Collabora Online Development Edition (CODE)** — μέσω Docker Compose, με αυτόματη διαχείριση πιστοποιητικών TLS και θωράκιση (`anti-php2shell`) από το **Caddy reverse proxy**.

> **Σημείωση:** Εάν επιθυμείτε να αναπτύξετε μόνο μία από τις δύο σουίτες μεμονωμένα (π.χ. σε υπάρχοντα Caddy server), ανατρέξτε στους αυτόνομους φακέλους [onlyoffice](../onlyoffice/) ή [collabora](../collabora/).

---

## 📋 Περιεχόμενα
- [Προαπαιτούμενα](#-προαπαιτούμενα)
- [Δομή Αρχείων](#-δομή-αρχείων)
- [Οδηγίες Εγκατάστασης](#-οδηγίες-εγκατάστασης)
  - [1. Προετοιμασία Host & Διαμόρφωση Swap](#1-προετοιμασία-host--διαμόρφωση-swap)
  - [2. Δημιουργία Φακέλων & Δικαιωμάτων](#2-δημιουργία-φακέλων--δικαιωμάτων)
  - [3. Μεταβλητές Περιβάλλοντος (.env)](#3-μεταβλητές-περιβάλλοντος-env)
  - [4. Docker Compose & Caddyfile (με Anti-php2shell Hardening)](#4-docker-compose--caddyfile-με-anti-php2shell-hardening)
  - [5. Εκκίνηση Υπηρεσιών](#5-εκκίνηση-υπηρεσιών)
- [Οδηγίες Ρύθμισης στο Nextcloud](#-οδηγίες-ρύθμισης-στο-nextcloud)
- [Επαλήθευση Λειτουργίας](#-επαλήθευση-λειτουργίας)
- [Αντιμετώπιση Προβλημάτων (Troubleshooting)](#-αντιμετώπιση-προβλημάτων-troubleshooting)
- [Μαθήματα & Βέλτιστες Πρακτικές](#-μαθήματα--βέλτιστες-πρακτικές)

---

## 🛠 Προαπαιτούμενα

* **Λειτουργικό Σύστημα:** Linux (Debian 12 / Ubuntu 22.04+ προτείνεται).
* **Πόροι Συστήματος:**
  * Ελάχιστο: 4 vCPU, 8 GB RAM.
  * **Υποχρεωτικό Swap:** Τουλάχιστον 4 GB swapfile ενεργό στο σύστημα.
  * Αποθηκευτικός χώρος: 20 GB ελεύθερα (για containers, βάση δεδομένων και caches).
* **Δικτύωση / DNS:**
  * Δημόσια διεύθυνση IPv4/IPv6 με ανοιχτές πόρτες `80/TCP`, `443/TCP`, `443/UDP` (HTTP/3).
  * Έγκυρα DNS A/AAAA records: `onlyoffice.iosifidis.gr` και `collabora.iosifidis.gr`.
* **Λογισμικό:** Docker Engine (v24.0+) και Docker Compose V2 (`docker compose`).

---

## 📁 Δομή Αρχείων

```bash
/office_suites
├── Caddyfile              # Reverse proxy για ONLYOFFICE & Collabora (με anti-php2shell)
├── docker-compose.yml     # Caddy, ONLYOFFICE (App, PostgreSQL, RabbitMQ) & Collabora
├── .env.example           # Πρότυπο μεταβλητών περιβάλλοντος
├── Readme.md              # Οδηγός εγκατάστασης & διασύνδεσης με Nextcloud
├── data/                  # Caddy persistent data
├── config/                # Caddy config
├── onlyoffice_data/       # ONLYOFFICE persistent data
├── onlyoffice_log/        # ONLYOFFICE logs
├── onlyoffice_postgresql/ # ONLYOFFICE PostgreSQL data
└── collabora/             # Collabora persistent data
```

---

## ⚙️ Οδηγίες Εγκατάστασης

### 1. Προετοιμασία Host & Διαμόρφωση Swap

Η ταυτόχρονη εκτέλεση PostgreSQL, RabbitMQ, ONLYOFFICE (Node.js/Java) και Collabora απαιτεί επαρκή εικονική μνήμη για την αποφυγή trigger του RabbitMQ memory alarm:

```bash
# 1. Δημιουργία και ενεργοποίηση Swap 4GB (αν δεν υπάρχει ήδη)
sudo fallocate -l 4G /swapfile || sudo dd if=/dev/zero of=/swapfile bs=1M count=4096
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile

# 2. Μόνιμη κατοχύρωση στο /etc/fstab
grep -q '/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab

# 3. Επαλήθευση
free -h
```

### 2. Δημιουργία Φακέλων & Δικαιωμάτων

Το ONLYOFFICE εκτελείται εντός του container με μη-root χρήστη (`ds`, συνήθως UID/GID `107:107`):

```bash
mkdir -p data config onlyoffice_data onlyoffice_log onlyoffice_postgresql collabora
chmod -R 777 onlyoffice_data onlyoffice_log onlyoffice_postgresql
```

> **Προσοχή Ασφαλείας:** Η χρήση `chmod 777` προτείνεται κατά το αρχικό bootstrap. Μετά τη δημιουργία των αρχείων από τα containers, περιορίστε την ιδιοκτησία στον χρήστη `107:107` (`sudo chown -R 107:107 onlyoffice_data onlyoffice_log`).

### 3. Μεταβλητές Περιβάλλοντος (.env)

Αντιγράψτε το [.env.example](./.env.example) σε `.env` και συμπληρώστε ισχυρά κλειδιά:

```bash
cp .env.example .env
```

```env
# ONLYOFFICE PostgreSQL & JWT
POSTGRES_DB_PASSWORD=StrongPostgresPasswordHere
ONLYOFFICE_JWT_SECRET=StrongRandomJwtSecretHere

# Collabora Allowed Nextcloud Instances (WOPI aliasgroups)
NEXTCLOUD_DOMAIN_1=https://nextcloud1.iosifidis.gr:443
NEXTCLOUD_DOMAIN_2=https://nextcloud2.iosifidis.gr:443
```

### 4. Docker Compose & Caddyfile (με Anti-php2shell Hardening)

* **[docker-compose.yml](./docker-compose.yml):** Εκκινεί τα `caddy`, `onlyoffice-db`, `onlyoffice-rabbitmq`, `onlyoffice` και `collabora` στο κοινό δίκτυο `web`.
* **[Caddyfile](./Caddyfile):** Διαχειρίζεται αυτόματα το TLS termination, τα WebSocket headers και εφαρμόζει κανόνες θωράκισης στο `collabora.iosifidis.gr` (αποκλεισμός εκτέλεσης `.php`/scripts με `403` έναντι επιθέσεων `php2shell`, καθώς και security headers `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `-Server`).

### 5. Εκκίνηση Υπηρεσιών

```bash
docker compose up -d
```

---

## 🔗 Οδηγίες Ρύθμισης στο Nextcloud

### 1. ONLYOFFICE Connector (Multi-Tenancy)
Το ONLYOFFICE επιτρέπει τη χρήση του από απεριόριστα Nextcloud instances ταυτόχρονα, ασφαλισμένο μέσω του **JWT Token**:
1. Στο Nextcloud, μεταβείτε στις **Εφαρμογές (Apps)** και ενεργοποιήστε το **ONLYOFFICE**.
2. Μεταβείτε στις **Ρυθμίσεις Διαχείρισης → ONLYOFFICE** και συμπληρώστε:
   * **Document Editing Service address:** `https://onlyoffice.iosifidis.gr`
   * **Secret key:** `<ONLYOFFICE_JWT_SECRET>` (από το `.env`)
   * **Authorization header:** `Authorization`
3. Πατήστε **Αποθήκευση (Save)**.

### 2. Nextcloud Office (Collabora Online)
1. Στο Nextcloud, ενεργοποιήστε την εφαρμογή **Nextcloud Office** (`richdocuments`).
2. Μεταβείτε στις **Ρυθμίσεις Διαχείρισης → Nextcloud Office**.
3. Επιλέξτε **Χρήση δικού σας διακομιστή (Use your own server)** και εισάγετε: `https://collabora.iosifidis.gr`.
4. Πατήστε **Αποθήκευση (Save)**. Εφόσον το Nextcloud domain έχει δηλωθεί στο `aliasgroup` του Collabora, θα εμφανιστεί πράσινο checkmark.

---

## ✅ Επαλήθευση Λειτουργίας

```bash
# 1. Έλεγχος κατάστασης containers (όλα Running, Restarts=0)
docker inspect -f '{{.Name}}: Restarts={{.RestartCount}}, Running={{.State.Running}}' onlyoffice onlyoffice-rabbitmq onlyoffice-db collabora caddy

# 2. Έλεγχος RabbitMQ Alarms (αναμενόμενο: χωρίς alarms)
docker compose exec onlyoffice-rabbitmq rabbitmq-diagnostics -q alarms

# 3. Έλεγχος κατάστασης ONLYOFFICE εσωτερικών διεργασιών
docker compose exec onlyoffice supervisorctl status

# 4. Healthcheck ONLYOFFICE (αναμενόμενο: HTTP/2 200)
curl -s -i https://onlyoffice.iosifidis.gr/healthcheck | head -n 1

# 5. Healthcheck Collabora Discovery (αναμενόμενο: HTTP/2 200)
curl -s -i https://collabora.iosifidis.gr/hosting/discovery | head -n 1

# 6. Επαλήθευση προστασίας anti-php2shell στο Collabora (αναμενόμενο: HTTP/2 403)
curl -s -i https://collabora.iosifidis.gr/shell.php | head -n 1
```

---

## 🔧 Αντιμετώπιση Προβλημάτων (Troubleshooting)

* **`HTTP 502 Bad Gateway` στο ONLYOFFICE (`/healthcheck`):** Ελέγξτε αν η PostgreSQL και το RabbitMQ είναι ενεργά (`docker compose ps`), αν τα δικαιώματα στα `onlyoffice_data`/`onlyoffice_log` είναι σωστά, και αν το RabbitMQ έχει ενεργοποιήσει memory alarm.
* **RabbitMQ `system_memory_high_watermark`:** Ενεργοποιήστε το Swapfile 4 GB (`swapon /swapfile`) και επανεκκινήστε το container (`docker compose restart onlyoffice-rabbitmq`).
* **Collabora `Unauthorized WOPI Host`:** Βεβαιωθείτε ότι στο `aliasgroup` έχει δηλωθεί το **Nextcloud** URL (`https://...iosifidis.gr:443`) χωρίς κενά. Σε χρήση regex, κάντε escape τις τελείες (π.χ. `https://nextcloud(1|2)\.iosifidis\.gr:443`).

---

## 💡 Μαθήματα & Βέλτιστες Πρακτικές

1. **Αποσύνδεση Υπηρεσιών:** Οι πρόσφατες εκδόσεις του `onlyoffice/documentserver` απαιτούν αυτόνομη PostgreSQL και RabbitMQ.
2. **Σημασία του Swap:** Ακόμη και με 8 GB RAM, το swapfile αποτρέπει memory alarms στο RabbitMQ και παρεμβάσεις του OOM Killer κατά την εκκίνηση.
3. **Persistent JWT Secret:** Ο ρητός ορισμός `JWT_SECRET` στο `.env` αποτρέπει την αλλαγή κλειδιού σε κάθε επανεκκίνηση/αναβάθμιση.
4. **Header Handling & Hardening:** Τα WebSockets απαιτούν `X-Forwarded-Proto https` και `Host`, ενώ ο αποκλεισμός `.php`/script paths στον Caddy προστατεύει επιπλέον από probes τύπου `php2shell`.
