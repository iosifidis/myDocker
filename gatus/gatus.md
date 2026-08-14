# Εγκατάσταση & Ρύθμιση Gatus (Service Monitor) στο Banana Pi

## Σκοπός / Περιγραφή (Overview)

Η παρούσα τεκμηρίωση περιγράφει την εγκατάσταση και παραμετροποίηση του **Gatus**, ενός εξαιρετικά ελαφρού εργαλείου παρακολούθησης υπηρεσιών (service monitor) γραμμένου σε Go. Το σύστημα επιλέχθηκε ώστε να λειτουργεί αποδοτικά σε περιβάλλον περιορισμένων πόρων (Banana Pi / αρχιτεκτονική ARM) μέσω Docker.

Εκτελεί διαρκείς ελέγχους υγείας (health checks) σε εσωτερικές και εξωτερικές υπηρεσίες (π.χ. Vaultwarden, Adguard, Nextwiki) και είναι ρυθμισμένο να αποστέλλει αυτοματοποιημένες ειδοποιήσεις μέσω **Email (SMTP)** και **Matrix** σε περίπτωση πτώσης ή επαναφοράς. Η εξωτερική πρόσβαση στο γραφικό περιβάλλον διαχειρίζεται μέσω του **Caddy Web Server** σε αποκλειστική θύρα.

## Προαπαιτούμενα (Prerequisites)

* Λειτουργικό σύστημα Linux (Banana Pi / ARM architecture).
* Εγκατεστημένο **Docker** και το plugin **Docker Compose**.
* Υφιστάμενη εγκατάσταση **Caddy** σε περιβάλλον Docker, με ρυθμισμένο εξωτερικό δίκτυο (π.χ. `web`).
* Δικαιώματα `root` ή χρήστης που ανήκει στο group `docker`.
* (Προαιρετικό) Ανοιχτή θύρα `8888` (TCP) στο Firewall/Router (Port Forwarding προς την εσωτερική IP του server), εάν επιθυμείται η πρόσβαση εκτός τοπικού δικτύου μέσω domain.
* Διαπιστευτήρια SMTP (για αποστολή email) και Matrix Bot Access Token.

## Βήματα Υλοποίησης / Εγκατάστασης (Step-by-Step Guide)

**1. Δημιουργία δομής φακέλων και αρχείων**
Απαιτείται η δημιουργία του βασικού φακέλου εγκατάστασης και του υποφακέλου ρυθμίσεων. Επίσης, δημιουργείται ένα κενό αρχείο `config.yaml` για να αποφευχθεί η κατάρρευση του container κατά την πρώτη εκκίνηση.

```bash
sudo mkdir -p /docker/gatus/config
sudo touch /docker/gatus/config/config.yaml

```

**2. Απόδοση Δικαιωμάτων**
Ρύθμιση της ιδιοκτησίας στον τρέχοντα χρήστη και απόδοση των απαραίτητων δικαιωμάτων ανάγνωσης/εγγραφής.

```bash
sudo chown -R $USER:$USER /docker/gatus
chmod 755 /docker/gatus
chmod 755 /docker/gatus/config
chmod 644 /docker/gatus/config/config.yaml

```

**3. Δημιουργία αρχείων παραμετροποίησης**
Σε αυτό το βήμα πρέπει να συμπληρωθούν τα αρχεία `docker-compose.yml` και `config.yaml` με τα περιεχόμενα που παρατίθενται στην ενότητα "Αρχεία Ρυθμίσεων" παρακάτω. Επίσης, πρέπει να ενημερωθούν τα αρχεία του Caddy.

**4. Εκκίνηση και Εφαρμογή Αλλαγών**
Εφόσον τα αρχεία έχουν αποθηκευτεί, εκκινείται πρώτα το Caddy (για να δεσμεύσει τη νέα πόρτα) και στη συνέχεια το Gatus.

```bash
# Επανεκκίνηση του Caddy για να ανοίξει η πόρτα 8888
docker compose -f /path/to/caddy/docker-compose.yml up -d

# Εκκίνηση του Gatus
cd /docker/gatus
docker compose up -d

```

## Αρχεία Ρυθμίσεων (Configuration Files)

### 1. Αρχείο docker-compose.yml (Gatus)

Διαδρομή: `/docker/gatus/docker-compose.yml`

```yaml
services:
  gatus:
    image: twinproduction/gatus:latest
    container_name: gatus
    restart: unless-stopped
    ports:
      - "8888:8080" # Επιτρέπει την απευθείας τοπική πρόσβαση μέσω http://<LOCAL_IP>:8888
    volumes:
      - ./config:/config
    networks:
      - web

networks:
  web:
    external: true

```

### 2. Ρυθμίσεις Caddy

Για την εξωτερική πρόσβαση στο Gatus μέσω συγκεκριμένου domain και πόρτας (π.χ. `iosifidis.servebeer.com:8888`), απαιτούνται δύο προσθήκες στις ρυθμίσεις του Caddy.

*Σημείωση: Αν η πρόσβαση γίνεται αποκλειστικά εντός τοπικού δικτύου μέσω της IP (π.χ. `[http://192.168.1.55:8080](http://192.168.1.55:8080)`), αυτό το βήμα παραλείπεται εντελώς.*

**Α. Προσθήκη θύρας στο docker-compose.yml του Caddy:**

```yaml
    ports:
      - "80:80"
      - "443:443"
      - "8888:8888" # Απαραίτητο για τη δρομολόγηση της κίνησης από το Caddy

```

**Β. Προσθήκη block στο Caddyfile:**
Διαδρομή: `/path/to/caddy/Caddyfile`

```text
iosifidis.servebeer.com:8888 {
    reverse_proxy gatus:8080
}

```

### 3. Αρχείο Παραμετροποίησης Gatus (config.yaml)

Διαδρομή: `/docker/gatus/config/config.yaml`

Το παρακάτω αρχείο περιέχει την πλήρη παραμετροποίηση, τα κανάλια ειδοποιήσεων (Matrix & Email) και τα endpoints προς παρακολούθηση.

```yaml
web:
  port: 8080

alerting:
  matrix:
    access-token: "<YOUR_MATRIX_BOT_ACCESS_TOKEN>"
    internal-room-id: "<!your_room_id:matrix.org>"
    # server-url: "https://matrix.yourdomain.com" # Αφαιρέστε το σχόλιο αν χρησιμοποιείται self-hosted homeserver
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
  - name: "Banana Pi"
    group: "Central"
    url: "http://localhost:8080" # Το Gatus ελέγχει τον εαυτό του
    interval: 1m
    conditions:
      - "[STATUS] == 200"

  - name: "Vault"
    group: "Security"
    # Σε τοπικές IP μέσω HTTPS, απαιτείται το client.insecure = true
    url: "https://<LOCAL_IP_HERE>:8080" 
    interval: 1m
    client:
      insecure: true
    conditions:
      - "[STATUS] == 200"
    alerts:
      - type: email
      - type: matrix

  - name: "Adguard"
    group: "Security"
    url: "http://<LOCAL_IP_HERE>:<PORT>"
    interval: 1m
    conditions:
      - "[STATUS] == 200"

  - name: "Nextwiki"
    group: "Documentation"
    url: "http://nextwiki:8080" # Επικοινωνία μέσω Docker internal DNS
    interval: 1m
    conditions:
      - "[STATUS] == 200"

```

## Επαλήθευση Λειτουργίας (Verification)

1. **Έλεγχος εκτέλεσης του container:**
```bash
docker ps | grep gatus

```


2. **Παρακολούθηση καταγραφών (logs) για την επιβεβαίωση των health checks:**
```bash
docker compose logs -f gatus

```


*Αναμενόμενη έξοδος:* `Monitored group=... success=true; errors=0;`
3. **Έλεγχος γραφικού περιβάλλοντος (Web UI):**
Πρόσβαση μέσω προγράμματος περιήγησης στη διεύθυνση [https://iosifidis.servebeer.com:8888](https://iosifidis.servebeer.com:8888) ή `http://<LOCAL_IP>:8080`.

## Αντιμετώπιση Προβλημάτων (Troubleshooting & Known Issues)

* **Σφάλμα `DNS_PROBE_FINISHED_NXDOMAIN` κατά την πρόσβαση στο Web UI:**
* **Αιτία:** Τυπογραφικό λάθος κατά την εισαγωγή του domain name (π.χ. `servbeer.com` αντί για `servebeer.com`).
* **Επίλυση:** Επιβεβαίωση και χρήση του ακριβούς FQDN που έχει δηλωθεί στον DNS provider.


* **Σφάλμα `ERR_CONNECTION_REFUSED` κατά τη σύνδεση στο port 8888:**
* **Αιτία:** Η αίτηση από το εξωτερικό δίκτυο απορρίπτεται επειδή η θύρα `8888` είναι κλειστή στο router.
* **Επίλυση:** Δημιουργία κανόνα Port Forward (TCP/8888) στο router με κατεύθυνση την εσωτερική IP του Banana Pi. Για δοκιμές, η τοπική πρόσβαση (`http://<LOCAL_IP>:8888`) λειτουργεί κανονικά.


* **Το Endpoint (π.χ. Vault) καταγράφεται ως `success=false` ενώ λειτουργεί:**
* **Αιτία:** Το health check εκτελείται μέσω `https://` σε τοπική διεύθυνση IP. Αυτό προκαλεί αποτυχία στην επαλήθευση του πιστοποιητικού SSL, καθώς τα πιστοποιητικά (π.χ. Let's Encrypt) αφορούν αποκλειστικά domains.
* **Επίλυση:** Προσθήκη της παραμέτρου `client: insecure: true` στο συγκεκριμένο endpoint εντός του `config.yaml`, ώστε να παρακάμπτονται τα σφάλματα πιστοποιητικών. Εναλλακτικά, χρήση της `http://` διεύθυνσης εάν η υπηρεσία εκτίθεται και χωρίς SSL εσωτερικά.



## Μαθήματα που πήραμε / Best Practices (Lessons Learned)

* **Στρατηγική Reverse Proxy:** Η δρομολόγηση κίνησης σε custom θύρες (π.χ. `:8888`) ή ξεχωριστά subdomains αποτελεί βέλτιστη πρακτική έναντι της χρήσης subpaths (`/gatus`). Τα subpaths προκαλούν συχνά προβλήματα φόρτωσης στατικών αρχείων (CSS/JS) και απαιτούν πολύπλοκα URL rewrites.
* **Δικτύωση Docker:** Η αξιοποίηση του εσωτερικού DNS του Docker (π.χ. `http://nextwiki:8080`) για υπηρεσίες που βρίσκονται στο ίδιο bridge network (`web`) εξαλείφει προβλήματα δρομολόγησης, είναι ταχύτερη και αποφεύγει ζητήματα με άκυρα πιστοποιητικά SSL.
* **Κατανάλωση Πόρων:** Το Gatus, λόγω της φύσης του (compiled Go binary), παρουσιάζει εξαιρετικά χαμηλό αποτύπωμα μνήμης (< 50MB RAM). Αποτελεί την ιδανική επιλογή για αρχιτεκτονικές ARM (όπως το Banana Pi), σε αντίθεση με βαριές λύσεις βασισμένες σε Node.js.
