# Collabora Online (CODE) Deployment with Docker & Caddy Reverse Proxy

Το **Collabora Online Development Edition (CODE)** είναι μια ανοικτού κώδικα διαδικτυακή σουίτα γραφείου βασισμένη στο LibreOffice, ιδανική για συνεργατική επεξεργασία εγγράφων (WOPI) μέσα από το **Nextcloud Office**. Ο παρών οδηγός καλύπτει την αυτόνομη ανάπτυξή του με **Docker Compose** και **Caddy** ως Reverse Proxy με προστασία `anti-php2shell`.

> **Σημείωση:** Για ταυτόχρονη ανάπτυξη μαζί με ONLYOFFICE στο ίδιο VM, δείτε τον φάκελο [office_suites](../office_suites/).

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
* Εγκατεστημένος **Caddy Server** συνδεδεμένος στο εξωτερικό Docker δίκτυο `web`.
* Έγκυρο DNS A/AAAA record για `collabora.iosifidis.gr`.

---

## 📁 Δομή Αρχείων

```bash
/collabora
├── Caddyfile              # Hardened Caddy Reverse Proxy (anti-php2shell & WebSockets)
├── docker-compose.yml     # Υπηρεσία Collabora Online (CODE)
├── .env.example           # Πρότυπο μεταβλητών aliasgroup για Nextcloud
├── Readme.md              # Οδηγίες εγκατάστασης & χρήσης
└── collabora/             # Persistent data (/opt/cool/data)
```

---

## ⚙️ Οδηγίες Εγκατάστασης

### 1. Δημιουργία Φακέλου & Μεταβλητών Περιβάλλοντος (`.env`)

```bash
mkdir -p collabora
cp .env.example .env
```

Προσαρμόστε τα επιτρεπόμενα Nextcloud instances στο `.env`:

```env
NEXTCLOUD_DOMAIN_1=https://nextcloud1.iosifidis.gr:443
NEXTCLOUD_DOMAIN_2=https://nextcloud2.iosifidis.gr:443
```

### 2. Εκκίνηση Container

Το αρχείο [docker-compose.yml](./docker-compose.yml) εκκινεί το `collabora/code:latest` με `SSL termination` στον Caddy (`--o:ssl.enable=false --o:ssl.termination=true`):

```bash
docker compose up -d
```

### 3. Ρύθμιση Caddyfile (με Anti-php2shell Hardening)

Προσθέστε το περιεχόμενο του [Caddyfile](./Caddyfile) στον Caddy server. Περιλαμβάνει αποκλεισμό εκτέλεσης `.php` και script paths (`403 Forbidden`) έναντι probes τύπου `php2shell`, security headers και σωστή προώθηση WebSocket/TLS headers.

---

## 🔗 Ρύθμιση στο Nextcloud

1. Ενεργοποιήστε την εφαρμογή **Nextcloud Office** (`richdocuments`).
2. Μεταβείτε στις **Ρυθμίσεις Διαχείρισης → Nextcloud Office**.
3. Επιλέξτε **Χρήση δικού σας διακομιστή (Use your own server)** και εισάγετε: `https://collabora.iosifidis.gr`.
4. Πατήστε **Αποθήκευση (Save)**.

---

## ✅ Επαλήθευση Λειτουργίας

```bash
# Έλεγχος discovery endpoint (αναμενόμενο: HTTP/2 200 & XML)
curl -s -i https://collabora.iosifidis.gr/hosting/discovery | head -n 1

# Έλεγχος προστασίας anti-php2shell (αναμενόμενο: HTTP/2 403)
curl -s -i https://collabora.iosifidis.gr/shell.php | head -n 1
```

---

## 🔧 Αντιμετώπιση Προβλημάτων (Troubleshooting)

* **`Unauthorized WOPI Host` / Αδυναμία ανοίγματος εγγράφων:** Βεβαιωθείτε ότι στο `aliasgroup1` / `aliasgroup2` του `.env` έχει δηλωθεί το **Nextcloud** URL (`https://...iosifidis.gr:443`) χωρίς κενά. Εάν χρησιμοποιείτε regex, κάντε escape τις τελείες (π.χ. `https://nextcloud(1|2)\.iosifidis\.gr:443`).
