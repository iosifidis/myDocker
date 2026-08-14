# Beszel Monitoring (Hub & Agent) Deployment with Docker & Caddy

Το **Beszel** είναι ένα εξαιρετικά ελαφρύ, σύγχρονο και γρήγορο εργαλείο παρακολούθησης διακομιστών (Server Monitoring Hub & Agent) με υποστήριξη στατιστικών για CPU, RAM, Disk, Network, GPU καθώς και Docker containers.

Αποτελείται από δύο διακριτά τμήματα:
1. **Beszel Hub (Server):** Το κεντρικό Web Dashboard που συγκεντρώνει τα δεδομένα και προβάλλει τα γραφήματα (διαθέσιμο μέσω Caddy στο `https://status.iosifidis.gr`).
2. **Beszel Agent:** Ο ελαφρύς πράκτορας (agent) που εκτελείται σε κάθε διακομιστή προς παρακολούθηση (π.χ. στο Banana Pi) και συλλέγει τα στατιστικά συστήματος.

---

## 📋 Περιεχόμενα
- [Τι είναι το Beszel](#-τι-είναι-το-beszel)
- [Προαπαιτούμενα](#-προαπαιτούμενα)
- [Δομή Αρχείων](#-δομή-αρχείων)
- [Οδηγίες Εγκατάστασης](#-οδηγίες-εγκατάστασης)
  - [Βήμα 1: Εκκίνηση του Beszel Hub (Server)](#βήμα-1-εκκίνηση-του-beszel-hub-server)
  - [Βήμα 2: Ρύθμιση Reverse Proxy (Caddy)](#βήμα-2-ρύθμιση-reverse-proxy-caddy)
  - [Βήμα 3: Πρώτη Σύνδεση & Δημιουργία Λογαριασμού](#βήμα-3-πρώτη-σύνδεση--δημιουργία-λογαριασμού)
  - [Βήμα 4: Έκδοση Public Key / Token για τον Agent](#βήμα-4-έκδοση-public-key--token-για-τον-agent)
  - [Βήμα 5: Εκκίνηση του Beszel Agent](#βήμα-5-εκκίνηση-του-beszel-agent)
- [Παρακολούθηση Docker Containers](#-παρακολούθηση-docker-containers)
- [Επαλήθευση Λειτουργίας](#-επαλήθευση-λειτουργίας)
- [Αντιμετώπιση Προβλημάτων (Troubleshooting)](#-αντιμετώπιση-προβλημάτων-troubleshooting)

---

## 💻 Τι είναι το Beszel

Το Beszel προσφέρει:
* **Χαμηλή κατανάλωση πόρων:** Ελάχιστη χρήση επεξεργαστή και μνήμης RAM (< 20MB).
* **Πραγματικού χρόνου γραφήματα:** Χρήση CPU, RAM, Swap, Disk I/O, Network Bandwidth & θερμοκρασίες.
* **Docker Stats:** Αυτόματη καταγραφή χρήσης πόρων ανά Docker container.
* **Ειδοποιήσεις (Alerts):** Ειδοποιήσεις όταν η χρήση CPU/RAM/Disk υπερβεί καθορισμένα όρια.
* **Ασφάλεια:** Η επικοινωνία Hub ↔ Agent προστατεύεται με κρυπτογραφημένα κλειδιά SSH (ed25519).

---

## 🛠 Προαπαιτούμενα

* **Docker** και **Docker Compose** εγκατεστημένα στον διακομιστή (Linux ARM64 / x86_64).
* Εγκατεστημένος **Caddy Server** συνδεδεμένος στο εξωτερικό δίκτυο `web`.
* Ένα **Domain Name** (π.χ. `status.iosifidis.gr`) συνδεδεμένο με την IP του διακομιστή.

---

## 📁 Δομή Αρχείων

```bash
/beszel
├── Caddyfile              # Ρυθμίσεις Caddy Reverse Proxy για το status.iosifidis.gr
├── docker-compose.yml     # Ορισμός του Beszel Hub (Server)
├── .env.example           # Πρότυπο μεταβλητών περιβάλλοντος
├── Readme.md              # Οδηγίες χρήσης & εγκατάστασης
└── agent/
    └── docker-compose.yml # Ορισμός του Beszel Agent (Host mode & Docker socket)
```

---

## ⚙️ Οδηγίες Εγκατάστασης

### Βήμα 1: Εκκίνηση του Beszel Hub (Server)

Στον κατάλογο `/beszel`:

1. Δημιουργήστε το αρχείο `docker-compose.yml`:
   ```yaml
   services:
     beszel:
       image: henrygd/beszel:latest
       container_name: beszel
       restart: unless-stopped
       ports:
         - "8090:8090"
       environment:
         - APP_URL=https://status.iosifidis.gr
       volumes:
         - ./beszel_data:/beszel_data
       networks:
         - web

   networks:
     web:
       external: true
   ```

2. Εκκινήστε το Hub:
   ```bash
   docker compose up -d
   ```

---

### Βήμα 2: Ρύθμιση Reverse Proxy (Caddy)

Προσθέστε το παρακάτω block στο `Caddyfile` του Caddy server:

```caddyfile
status.iosifidis.gr {
    reverse_proxy beszel:8090
}
```

Ανανεώστε τις ρυθμίσεις του Caddy:

```bash
docker exec caddy caddy reload
```

---

### Βήμα 3: Πρώτη Σύνδεση & Δημιουργία Λογαριασμού

1. Ανοίξτε τον browser και επισκεφθείτε τη διεύθυνση **`https://status.iosifidis.gr`**.
2. Κατά την πρώτη είσοδο, συμπληρώστε το email και τον κωδικό πρόσβασης για να δημιουργήσετε τον λογαριασμό διαχειριστή (Admin Account).

---

### Βήμα 4: Έκδοση Public Key / Token για τον Agent

Για να συνδέσετε το Banana Pi (ή οποιονδήποτε άλλο διακομιστή) στο Hub:

1. Στο Web UI του Beszel (`https://status.iosifidis.gr`), κάντε κλικ στο κουμπί **`+ Add System`** (Προσθήκη Συστήματος).
2. Συμπληρώστε τα στοιχεία:
   - **Name:** Όνομα διακομιστή (π.χ. `Banana Pi`).
   - **Host / IP:** Η IP του διακομιστή (π.χ. `192.168.1.55` ή `localhost` αν είναι στον ίδιο host).
   - **Port:** `45876` (προεπιλεγμένη θύρα του Agent).
3. Το Beszel Hub θα παράξει αυτόματα ένα μοναδικό **Public Key / Token** (της μορφής `ssh-ed25519 AAAAC3N...`).
4. **Αντιγράψτε** αυτό το κλειδί.

---

### Βήμα 5: Εκκίνηση του Beszel Agent

Μεταβείτε στον υποφάκελο `/beszel/agent`:

1. Δημιουργήστε το αρχείο `docker-compose.yml`:
   ```yaml
   services:
     beszel-agent:
       image: henrygd/beszel-agent:latest
       container_name: beszel-agent
       restart: unless-stopped
       network_mode: host
       environment:
         - PORT=45876
         - KEY="ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAI..." # Επικολλήστε εδώ το κλειδί από το Βήμα 4
       volumes:
         - /var/run/docker.sock:/var/run/docker.sock:ro
   ```

2. Αντικαταστήστε την τιμή `KEY` με το Public Key που αντιγράψατε από το UI.
3. Εκκινήστε τον Agent:
   ```bash
   docker compose up -d
   ```

4. Επιστρέψτε στο Web UI στο `https://status.iosifidis.gr`. Σε λίγα δευτερόλεπτα η κατάσταση του συστήματος θα γίνει **Green / Connected** και θα αρχίσουν να εμφανίζονται τα γραφήματα!

---

## 🐳 Παρακολούθηση Docker Containers

Ο Beszel Agent είναι ρυθμισμένος με:
```yaml
volumes:
  - /var/run/docker.sock:/var/run/docker.sock:ro
```
Χάρη σε αυτό το volume, ο Agent διαβάζει αυτόματα όλα τα τρέχοντα Docker containers του διακομιστή και εμφανίζει τη χρήση CPU/RAM ανά container στην καρτέλα **Containers** του Dashboard.

---

## ✅ Επαλήθευση Λειτουργίας

1. **Έλεγχος Hub Container:**
   ```bash
   docker compose logs -f beszel
   ```
2. **Έλεγχος Agent Container:**
   ```bash
   cd agent
   docker compose logs -f beszel-agent
   ```
3. **Έλεγχος Listening Port 45876:**
   ```bash
   netstat -tulpn | grep 45876
   ```

---

## 🔍 Αντιμετώπιση Προβλημάτων (Troubleshooting)

### 1. Σφάλμα `Connection Refused` ή `Agent Unreachable` στο Hub UI
* **Αιτία:** Η θύρα `45876` εμποδίζεται από το Firewall του host (π.χ. ufw/nftables) ή ο Agent δεν εκτελείται με `network_mode: host`.
* **Λύση:**
  - Βεβαιωθείτε ότι ο Agent έχει `network_mode: host`.
  - Επιτρέψτε την εισερχόμενη κίνηση στη θύρα `45876` (TCP).

### 2. Δεν εμφανίζονται στατιστικά για τα Docker containers
* **Αιτία:** Το `/var/run/docker.sock` δεν έχει γίνει mount ή ο χρήστης του container δεν έχει δικαιώματα ανάγνωσης.
* **Λύση:** Επιβεβαιώστε την ύπαρξη του volume `- /var/run/docker.sock:/var/run/docker.sock:ro`.
