# Semaphore UI (Ansible UI) Deployment with Docker & Caddy Reverse Proxy

Η παρούσα τεκμηρίωση περιγράφει τη διαδικασία εγκατάστασης και παραμετροποίησης της εφαρμογής **Semaphore UI** (Web UI για το Ansible) σε περιβάλλον Docker. Η υλοποίηση χρησιμοποιεί τη βάση δεδομένων **SQLite** για απλοποίηση της αρχιτεκτονικής και ευκολία στη μεταφερσιμότητα/backup των δεδομένων, και **Caddy** ως Reverse Proxy για αυτόματο SSL (Let's Encrypt). Παράλληλα, τεκμηριώνεται η σωστή παραμετροποίηση του τείχους προστασίας (**Ferm**) ώστε να μην προκαλούνται συγκρούσεις με τους κανόνες δρομολόγησης (NAT/Forwarding) του Docker.

---

## 📋 Περιεχόμενα
- [Προαπαιτούμενα](#-προαπαιτούμενα)
- [Δομή Αρχείων](#-δομή-αρχείων)
- [Οδηγίες Εγκατάστασης](#-οδηγίες-εγκατάστασης)
  - [1. Δημιουργία Φακέλων & Δικαιωμάτων](#1-δημιουργία-φακέλων--δικαιωμάτων)
  - [2. Μεταβλητές Περιβάλλοντος (.env)](#2-μεταβλητές-περιβάλλοντος-env)
  - [3. Docker Compose & Caddyfile](#3-docker-compose--caddyfile)
  - [4. Εκκίνηση & Δημιουργία Διαχειριστή](#4-εκκίνηση--δημιουργία-διαχειριστή)
- [Συμβατότητα Ferm Firewall με Docker](#-συμβατότητα-ferm-firewall-με-docker)
- [Επαλήθευση Λειτουργίας](#-επαλήθευση-λειτουργίας)
- [Αντιμετώπιση Προβλημάτων (Troubleshooting)](#-αντιμετώπιση-προβλημάτων-troubleshooting)
- [Μαθήματα & Βέλτιστες Πρακτικές](#-μαθήματα--βέλτιστες-πρακτικές)

---

## 🛠 Προαπαιτούμενα

* Λειτουργικό σύστημα **Linux** (π.χ. Debian/Ubuntu) με εγκατεστημένα τα `docker` και `docker compose`.
* Εγκατεστημένος **Caddy** reverse proxy συνδεδεμένος στο εξωτερικό δίκτυο Docker `web`.
* DNS A/AAAA Record που να δείχνει στην IP του server (π.χ. `infra.iosifidis.gr`).
* Εγκατεστημένο το `ferm` (εφόσον χρησιμοποιείται για διαχείριση firewall).
* Δικαιώματα υπερχρήστη (`root` ή `sudo`).

---

## 📁 Δομή Αρχείων

```bash
/semaphore
├── Caddyfile              # Ρυθμίσεις Caddy Reverse Proxy για infra.iosifidis.gr
├── docker-compose.yml     # Υπηρεσία Semaphore UI
├── .env.example           # Πρότυπο μεταβλητών περιβάλλοντος & κλειδιών
├── Readme.md              # Οδηγός εγκατάστασης & troubleshooting
├── config/                # Bind mount ρυθμίσεων (/etc/semaphore, UID 1001)
├── data/                  # Bind mount βάσης SQLite (/var/lib/semaphore, UID 1001)
└── tmp/                   # Προσωρινός κατάλογος εργασιών (/tmp/semaphore, UID 1001)
```

---

## ⚙️ Οδηγίες Εγκατάστασης

### 1. Δημιουργία Φακέλων & Δικαιωμάτων

Το container του Semaphore εκτελείται ως non-root χρήστης (**UID `1001`**). Η προετοιμασία των bind mounts είναι κρίσιμη για την αποφυγή σφαλμάτων εγγραφής:

```bash
mkdir -p config data tmp
sudo chown -R 1001:0 config data tmp
sudo chmod -R 2770 config data tmp
```

### 2. Μεταβλητές Περιβάλλοντος (.env)

Αντιγράψτε το [.env.example](./.env.example) σε `.env`:

```bash
cp .env.example .env
```

Παράγετε τρία τυχαία κλειδιά κρυπτογράφησης με την εντολή:

```bash
openssl rand -base64 32
```

Συμπληρώστε τα κλειδιά, το domain (`https://infra.iosifidis.gr`) και τα στοιχεία SMTP στο `.env`:

```env
SEMAPHORE_DB_DIALECT=sqlite

SEMAPHORE_ADMIN=admin
SEMAPHORE_ADMIN_NAME=Administrator
SEMAPHORE_ADMIN_EMAIL=admin@iosifidis.gr
SEMAPHORE_ADMIN_PASSWORD=StrongAdminPasswordHere

# Μην αλλάξετε αυτά τα κλειδιά μετά το αρχικό setup
SEMAPHORE_COOKIE_HASH=<GENERATED_KEY_1>
SEMAPHORE_COOKIE_ENCRYPTION=<GENERATED_KEY_2>
SEMAPHORE_ACCESS_KEY_ENCRYPTION=<GENERATED_KEY_3>

SEMAPHORE_WEB_ROOT=https://infra.iosifidis.gr

SEMAPHORE_EMAIL_SENDER=semaphore@iosifidis.gr
SEMAPHORE_EMAIL_HOST=mail.iosifidis.gr
SEMAPHORE_EMAIL_PORT=587
SEMAPHORE_EMAIL_USERNAME=server@iosifidis.gr
SEMAPHORE_EMAIL_PASSWORD=<SMTP_PASSWORD>
SEMAPHORE_EMAIL_SECURE=False
```

### 3. Docker Compose & Caddyfile

* **[docker-compose.yml](./docker-compose.yml):** Εκκινεί το `semaphoreui/semaphore:latest` με timezone `Europe/Athens` και σύνδεση στο δίκτυο `web`.
* **[Caddyfile](./Caddyfile):** Προωθεί την κίνηση του `infra.iosifidis.gr` στο `semaphore:3000` με συμπίεση `zstd gzip` και JSON logging.

### 4. Εκκίνηση & Δημιουργία Διαχειριστή

Εκκινήστε το container:

```bash
docker compose up -d
```

Λόγω περιορισμών στο script αρχικοποίησης (non-interactive περιβάλλον) στις νεότερες εκδόσεις του image, ο λογαριασμός διαχειριστή πρέπει να δημιουργηθεί χειροκίνητα εντός του container:

```bash
docker exec -it semaphore semaphore user add \
  --admin \
  --login admin \
  --name Administrator \
  --email admin@iosifidis.gr \
  --password '<ADMIN_PASSWORD>'
```

---

## 🔥 Συμβατότητα Ferm Firewall με Docker

Για να διασφαλιστεί η σωστή δρομολόγηση του Docker και η επικοινωνία με εξωτερικούς servers (π.χ. Let's Encrypt), το `/etc/ferm/ferm.conf` πρέπει να διατηρεί (`@preserve`) τα chains του Docker:

```text
@def $SSH_PORT = 2022;

domain (ip ip6) {
    table filter {
        chain (DOCKER DOCKER-USER DOCKER-ISOLATION-STAGE-1 DOCKER-ISOLATION-STAGE-2 DOCKER-FORWARD FORWARD) @preserve;

        chain INPUT {
            policy DROP;
            interface lo ACCEPT;
            mod state state (ESTABLISHED RELATED) ACCEPT;
            proto icmp ACCEPT;
            proto tcp dport ($SSH_PORT 80 443) ACCEPT;
        }

        chain OUTPUT {
            policy ACCEPT;
        }
    }

    table nat {
        chain (PREROUTING POSTROUTING OUTPUT DOCKER DOCKER-INGRESS) @preserve;
    }
}
```

Επανεκκίνηση με τη σωστή σειρά:

```bash
sudo systemctl restart ferm
sudo systemctl restart docker
```

---

## ✅ Επαλήθευση Λειτουργίας

```bash
# 1. Έλεγχος κατάστασης container
docker ps | grep semaphore

# 2. Έλεγχος εξωτερικής πρόσβασης & DNS μέσω Caddy
docker exec -it caddy wget -qO- --timeout=5 https://acme-v02.api.letsencrypt.org/directory
```

Στη συνέχεια, ανοίξτε στον browser το `https://infra.iosifidis.gr` και συνδεθείτε με τα στοιχεία διαχειριστή.

---

## 🔧 Αντιμετώπιση Προβλημάτων (Troubleshooting)

* **Σφάλμα `"no rows in result set"` / Αδυναμία σύνδεσης στο Web UI:**
  * *Αιτία:* Το `semaphoreui/semaphore:latest` αγνοεί τα admin environment variables χωρίς TTY.
  * *Λύση:* Εκτελέστε την εντολή `docker exec -it semaphore semaphore user add ...`. Αν εμφανιστεί `UNIQUE constraint failed: user.email`, διαγράψτε τα περιεχόμενα των `data/*` και `config/*` και επανεκκινήστε (`docker compose down && docker compose up -d`).
* **Caddy Let's Encrypt Timeout / Μηδενική πρόσβαση containers στο ίντερνετ:**
  * *Αιτία:* Το Ferm διέγραφε τα δυναμικά iptables rules του Docker στα chains `FORWARD` και `POSTROUTING`.
  * *Λύση:* Προσθέστε τα `@preserve` blocks στο `/etc/ferm/ferm.conf` και εκτελέστε `systemctl restart ferm && systemctl restart docker`.
* **Το Firewall μπλοκάρει κίνηση παρά τις σωστές ρυθμίσεις στο Ferm:**
  * *Αιτία:* Split-brain μεταξύ `iptables-legacy` και `nftables`.
  * *Λύση:* Εκκαθάριση των `iptables-legacy` κανόνων (`iptables-legacy -F`, `-X`, `-t nat -F`, `-t nat -X`) και επανεκκίνηση `ferm` και `docker`.

---

## 💡 Μαθήματα & Βέλτιστες Πρακτικές

* **Διαχείριση Firewalls με Docker:** Διατηρείτε (`@preserve`) πάντα τα Docker chains στο Ferm/UFW και επανεκκινείτε με σειρά `ferm` ➔ `docker`.
* **SQLite αντί BoltDB:** Η υποστήριξη BoltDB έχει εγκαταλειφθεί. Η SQLite απλοποιεί τη μεταφορά και τα backups (π.χ. Borg / Kopia) χωρίς εξωτερικό RDBMS container.
* **Debugging Docker Networking:** Ο έλεγχος εξερχόμενης κίνησης μέσα από το container (`docker exec -it caddy wget ...`) απομονώνει άμεσα προβλήματα δικτύου από προβλήματα DNS/SSL.
