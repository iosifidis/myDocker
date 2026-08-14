# Gatus Deployment with Docker Compose & Caddy Reverse Proxy

Το **Gatus** είναι ένα εξαιρετικά ελαφρύ και αποδοτικό εργαλείο παρακολούθησης διαθεσιμότητας υπηρεσιών (Service Health Dashboard / Status Page) γραμμένο σε Go. Είναι ιδανικό για διακομιστές με περιορισμένους πόρους (π.χ. Raspberry Pi / Banana Pi ARM) και προσφέρει αυτοματοποιημένες ειδοποιήσεις μέσω **Email (SMTP)** και **Matrix**.

---

## 📋 Περιεχόμενα
- [Τι είναι το Gatus](#-τι-είναι-το-gatus)
- [Προαπαιτούμενα](#-προαπαιτούμενα)
- [Δομή Αρχείων](#-δομή-αρχείων)
- [Οδηγίες Εγκατάστασης](#-οδηγίες-εγκατάστασης)
  - [1. Δημιουργία Φακέλων & Αρχείων](#1-δημιουργία-φακέλων--αρχείων)
  - [2. Δικαιώματα Αρχείων](#2-δικαιώματα-αρχείων)
  - [3. Docker Compose Configuration](#3-docker-compose-configuration)
  - [4. Ρύθμιση Reverse Proxy (Caddy)](#4-ρύθμιση-reverse-proxy-caddy)
  - [5. Εκκίνηση Stack](#5-εκκίνηση-stack)
- [Διαμόρφωση Παρακολούθησης & Ειδοποιήσεων (config.yaml)](#-διαμόρφωση-παρακολούθησης--ειδοποιήσεων-configyaml)
- [Επαλήθευση Λειτουργίας](#-επαλήθευση-λειτουργίας)
- [Αντιμετώπιση Προβλημάτων (Troubleshooting)](#-αντιμετώπιση-προβλημάτων-troubleshooting)
- [Μαθήματα & Βέλτιστες Πρακτικές](#-μαθήματα--βέλτιστες-πρακτικές)

---

## 📊 Τι είναι το Gatus

Το Gatus σάς επιτρέπει να:
* Εκτελείτε περιοδικούς ελέγχους υγείας (HTTP, ICMP, TCP, DNS) σε εσωτερικές και εξωτερικές υπηρεσίες.
* Παρακολουθείτε χρόνους απόκρισης (latency), status codes (π.χ. HTTP 200 OK) και πιστοποιητικά SSL.
* Λαμβάνετε άμεσες ειδοποιήσεις σε περίπτωση πτώσης ή επαναφοράς υπηρεσιών μέσω **Matrix**, **Email (SMTP)**, Discord, Slack, Telegram κ.ά.
* Προβάλλετε ένα σύγχρονο, ελαφρύ Dashboard με ελάχιστη κατανάλωση πόρων (< 50MB RAM).

---

## 🛠 Προαπαιτούμενα

* **Docker** και **Docker Compose** εγκατεστημένα στον διακομιστή (Linux ARM64 / x86_64).
* Εγκατεστημένος **Caddy Server** συνδεδεμένος σε εξωτερικό Docker δίκτυο `web`.
* (Προαιρετικό) Domain Name (π.χ. `iosifidis.servebeer.com`) και άνοιγμα θύρας `8888` (TCP) στο Router/Firewall αν επιθυμείται εξωτερική πρόσβαση.
* Διαπιστευτήρια SMTP (για ειδοποιήσεις Email) και Matrix Bot Access Token.

---

## 📁 Δομή Αρχείων

```bash
/gatus
├── Caddyfile              # Ρυθμίσεις Caddy Reverse Proxy
├── docker-compose.yml     # Ορισμός υπηρεσίας Gatus
├── Readme.md              # Οδηγίες χρήσης και παραμετροποίησης
└── config/
    └── config.yaml        # Κύριο αρχείο ρυθμίσεων Gatus & endpoints
```

---

## ⚙️ Οδηγίες Εγκατάστασης

### 1. Δημιουργία Φακέλων & Αρχείων

Δημιουργήστε τη δομή φακέλων και το αρχικό αρχείο ρυθμίσεων:

```bash
mkdir -p config
touch config/config.yaml
```

### 2. Δικαιώματα Αρχείων

Ορίστε τα κατάλληλα δικαιώματα ανάγνωσης/εγγραφής:

```bash
chmod 755 .
chmod 755 config
chmod 644 config/config.yaml
```

### 3. Docker Compose Configuration

Δημιουργήστε το αρχείο `docker-compose.yml`:

```yaml
services:
  gatus:
    image: twinproduction/gatus:latest
    container_name: gatus
    restart: unless-stopped
    ports:
      - "8888:8080" # Local access: http://<LOCAL_IP>:8888
    volumes:
      - ./config:/config
    networks:
      - web

networks:
  web:
    external: true
```

### 4. Ρύθμιση Reverse Proxy (Caddy)

Για πρόσβαση μέσω Caddy στη θύρα `8888` (π.χ. `iosifidis.servebeer.com:8888`):

1. Βεβαιωθείτε ότι η θύρα `8888` είναι εκτεθειμένη στο `docker-compose.yml` του Caddy:
   ```yaml
   ports:
     - "80:80"
     - "443:443"
     - "8888:8888"
   ```

2. Προσθέστε το παρακάτω block στο `Caddyfile` του Caddy:
   ```caddyfile
   iosifidis.servebeer.com:8888 {
       reverse_proxy gatus:8080
   }
   ```

3. Ανανεώστε τις ρυθμίσεις του Caddy:
   ```bash
   docker exec caddy caddy reload
   ```

### 5. Εκκίνηση Stack

Εκκινήστε το container του Gatus:

```bash
docker compose up -d
```

---

## 🔔 Διαμόρφωση Παρακολούθησης & Ειδοποιήσεων (config.yaml)

Επεξεργαστείτε το αρχείο `config/config.yaml` για να προσθέσετε υπηρεσίες (endpoints) και κανάλια ειδοποιήσεων:

```yaml
web:
  port: 8080

alerting:
  matrix:
    access-token: "<YOUR_MATRIX_BOT_ACCESS_TOKEN>"
    internal-room-id: "<!your_room_id:matrix.org>"
    default-alert:
      failure-threshold: 3
      success-threshold: 2
      send-on-resolved: true

  email:
    from: "<alerts@yourdomain.tld>"
    username: "<alerts@yourdomain.tld>"
    password: "<YOUR_SMTP_PASSWORD>"
    host: "<smtp.yourprovider.tld>"
    port: 587
    to: "<admin@yourdomain.tld>"
    default-alert:
      failure-threshold: 3
      success-threshold: 2
      send-on-resolved: true

endpoints:
  - name: "Local Server"
    group: "Central"
    url: "http://localhost:8080"
    interval: 1m
    conditions:
      - "[STATUS] == 200"

  - name: "Vaultwarden"
    group: "Security"
    url: "https://<LOCAL_IP>:8080"
    interval: 1m
    client:
      insecure: true # Παράκαμψη SSL verification για τοπικές IP
    conditions:
      - "[STATUS] == 200"
    alerts:
      - type: email
      - type: matrix

  - name: "AdGuard Home"
    group: "Security"
    url: "http://<LOCAL_IP>:<PORT>"
    interval: 1m
    conditions:
      - "[STATUS] == 200"

  - name: "Nextcloud / Wiki"
    group: "Documentation"
    url: "http://nextwiki:8080" # Επικοινωνία μέσω Docker internal DNS
    interval: 1m
    conditions:
      - "[STATUS] == 200"
```

---

## ✅ Επαλήθευση Λειτουργίας

1. **Έλεγχος κατάστασης Container:**
   ```bash
   docker ps | grep gatus
   ```

2. **Έλεγχος Logs:**
   ```bash
   docker compose logs -f gatus
   ```
   *Αναμενόμενο μήνυμα:* `Monitored group=... success=true; errors=0;`

3. **Πρόσβαση στο Web UI:**
   Ανοίξτε στον browser τη διεύθυνση `https://iosifidis.servebeer.com:8888` ή `http://<LOCAL_IP>:8888`.

---

## 🔍 Αντιμετώπιση Προβλημάτων (Troubleshooting)

### 1. Σφάλμα `ERR_CONNECTION_REFUSED` στη θύρα 8888
* **Αιτία:** Η θύρα `8888` δεν έχει ανοιχτεί στο Router (Port Forwarding) ή δεν έχει δηλωθεί στα `ports` του Caddy container.
* **Λύση:** Προσθέστε τη θύρα `"8888:8888"` στο `docker-compose.yml` του Caddy και ρυθμίστε κανόνα Port Forwarding στο router για εξωτερική πρόσβαση.

### 2. Το Endpoint εμφανίζεται ως `success=false` ενώ η υπηρεσία λειτουργεί
* **Αιτία:** Έλεγχος HTTPS σε τοπική IP διεύθυνση προκαλεί αποτυχία λόγω αυτοϋπογεγραμμένου ή ακατάλληλου SSL πιστοποιητικού.
* **Λύση:** Προσθέστε την παράμετρο `client: insecure: true` στο συγκεκριμένο endpoint στο `config.yaml`.

### 3. Σφάλμα `DNS_PROBE_FINISHED_NXDOMAIN`
* **Αιτία:** Τυπογραφικό σφάλμα στο domain name.
* **Λύση:** Επιβεβαιώστε την ορθότητα του FQDN στον DNS provider σας.

---

## 💡 Μαθήματα & Βέλτιστες Πρακτικές

* **Docker Internal DNS:** Χρησιμοποιείτε τα ονόματα των containers (π.χ. `http://nextwiki:8080`) για υπηρεσίες στο ίδιο Docker δίκτυο `web`. Είναι ταχύτερο, ασφαλέστερο και αποφεύγει προβλήματα με πιστοποιητικά SSL.
* **Custom Ports vs Subpaths:** Η χρήση ξεχωριστών θυρών (π.χ. `:8888`) ή subdomains είναι πιο αξιόπιστη από τη χρήση subpaths (`/gatus`), καθώς αποφεύγονται προβλήματα φόρτωσης static assets (JS/CSS).
* **Χαμηλή Κατάναλωση Πόρων:** Το Gatus καταναλώνει λιγότερο από 50MB RAM, καθιστώντας το την πλέον ενδεδειγμένη λύση για ARM Single Board Computers (Raspberry Pi / Banana Pi).
