# WireGuard Home VPN Server (Banana Pi & Docker)

Το **WireGuard** είναι ένα σύγχρονο, εξαιρετικά γρήγορο και ελαφρύ πρωτόκολλο VPN. Η παρούσα υλοποίηση παρέχει **ασφαλή απομακρυσμένη πρόσβαση** στο οικιακό δίκτυο (Banana Pi & τοπικές υπηρεσίες) καθώς και **κρυπτογραφημένη περιήγηση στο Internet** μέσω της οικιακής σύνδεσης όταν βρίσκεστε εκτός σπιτιού.

---

## 📋 Περιεχόμενα
- [Σκοπός & Δυνατότητες](#-σκοπός--δυνατότητες)
- [Προαπαιτούμενα](#-προαπαιτούμενα)
- [Δομή Αρχείων](#-δομή-αρχείων)
- [Οδηγίες Εγκατάστασης](#-οδηγίες-εγκατάστασης)
  - [1. Ενεργοποίηση IP Forwarding στον Host](#1-ενεργοποίηση-ip-forwarding-στον-host)
  - [2. Δημιουργία Φακέλων & Δικαιωμάτων](#2-δημιουργία-φακέλων--δικαιωμάτων)
  - [3. Ρύθμιση docker-compose.yml](#3-ρύθμιση-docker-composeyml)
  - [4. Ρύθμιση Router (Port Forwarding)](#4-ρύθμιση-router-port-forwarding)
  - [5. Εκκίνηση Υπηρεσίας](#5-εκκίνηση-υπηρεσίας)
- [Προβολή QR Codes & Διαχείριση Συσκευών (Peers)](#-προβολή-qr-codes--διαχείριση-συσκευών-peers)
- [Ενσωμάτωση με AdGuard Home (Ad-blocking)](#-ενσωμάτωση-με-adguard-home-ad-blocking)
- [Full-Tunnel vs Split-Tunnel VPN](#-full-tunnel-vs-split-tunnel-vpn)
- [Αντιμετώπιση Προβλημάτων (Troubleshooting)](#-αντιμετώπιση-προβλημάτων-troubleshooting)
- [Οδηγός Σύνδεσης Συσκευών](#-οδηγός-σύνδεσης-συσκευών)

---

## 🚀 Σκοπός & Δυνατότητες

Με την εγκατάσταση του WireGuard στο Banana Pi πετυχαίνετε:
1. **Ασφαλή Απομακρυσμένη Πρόσβαση:** Πρόσβαση στο Banana Pi και σε όλες τις τοπικές υπηρεσίες του σπιτιού σας (Nextcloud, AdGuard, Vaultwarden, DokuWiki, Gatus, κ.ά.) σαν να είστε συνδεδεμένοι στο οικιακό Wi-Fi.
2. **Ασφαλή Περιήγηση (Full-Tunnel VPN):** Όλη η κίνηση Internet από το κινητό ή το laptop σας (όταν είστε σε δημόσια Wi-Fi, 4G/5G, κ.λπ.) δρομολογείται κρυπτογραφημένα μέσω της οικιακής σας σύνδεσης.
3. **Δυναμικό Domain (DDNS):** Χρήση δωρεάν υπηρεσίας DDNS (π.χ. `vpndomain.duckdns.org`), ώστε η σύνδεση να διατηρείται ακόμα κι αν αλλάξει η δημόσια IP του σπιτιού σας.

---

## 🛠 Προαπαιτούμενα

* **Banana Pi** (ή άλλο Linux SBC/server) με **Docker** και **Docker Compose**.
* Εγκατεστημένο **WireGuard module** στον Linux kernel (περιλαμβάνεται στους σύγχρονους πυρήνες Linux ≥ 5.6).
* Ένα **DDNS Domain** (π.χ. μέσω [DuckDNS](https://www.duckdns.org/)) που ενημερώνει τη δημόσια IP του σπιτιού σας.
* Δυνατότητα πρόσβασης στη σελίδα διαχείρισης του **Router** για άνοιγμα θύρας (Port Forwarding UDP `51820`).
* Πρόσβαση `sudo` / `root` στο Banana Pi.

---

## 📁 Δομή Αρχείων

```bash
/wireguard
├── docker-compose.yml        # Ορισμός υπηρεσίας WireGuard
├── Readme.md                 # Οδηγός εγκατάστασης & παραμετροποίησης
├── Client-Instructions.md    # Αναλυτικός οδηγός σύνδεσης για χρήστες/συσκευές
└── config/                   # Αυτόματα παραγόμενα κλειδιά & client configurations
    ├── peer_phone/           # Αρχείο .conf & png QR code για το κινητό
    ├── peer_laptop/          # Αρχείο .conf & png QR code για το laptop
    └── peer_tablet/          # Αρχείο .conf & png QR code για το tablet
```

---

## ⚙️ Οδηγίες Εγκατάστασης

### 1. Ενεργοποίηση IP Forwarding στον Host

Για να μπορεί το Banana Pi να δρομολογεί πακέτα δικτύου μεταξύ των VPN clients και του οικιακού δικτύου/Internet, πρέπει να ενεργοποιηθεί το IPv4 forwarding στο σύστημα:

```bash
echo "net.ipv4.ip_forward=1" | sudo tee -a /etc/sysctl.d/99-wireguard.conf
sudo sysctl --system
```

### 2. Δημιουργία Φακέλων & Δικαιωμάτων

```bash
mkdir -p config
sudo chmod 700 config
```

### 3. Ρύθμιση docker-compose.yml

Δημιουργήστε το αρχείο `docker-compose.yml`:

```yaml
services:
  wireguard:
    image: lscr.io/linuxserver/wireguard:latest
    container_name: wireguard
    cap_add:
      - NET_ADMIN
      - SYS_MODULE
    environment:
      - PUID=1000
      - PGID=1000
      - TZ=Europe/Athens
      - SERVERURL=vpndomain.duckdns.org # Το δικό σας DuckDNS domain
      - SERVERPORT=51820
      - PEERS=phone,laptop,tablet # Ονόματα συσκευών που θα δημιουργηθούν
      - PEERDNS=auto # ή η IP του AdGuard Home (π.χ. 192.168.1.XX)
      - INTERNAL_SUBNET=10.13.13.0/24
      - ALLOWEDIPS=0.0.0.0/0, ::/0 # Full tunnel (Internet + LAN)
      - PERSISTENTKEEPALIVE_PEERS=25
      - LOG_CONFS=true
    volumes:
      - ./config:/config
      - /lib/modules:/lib/modules
    ports:
      - "51820:51820/udp"
    sysctls:
      - net.ipv4.conf.all.src_valid_mark=1
      - net.ipv4.ip_forward=1
    restart: unless-stopped
```

> **💡 Σημείωση:** Αντικαταστήστε το `SERVERURL=vpndomain.duckdns.org` με το δικό σας πραγματικό DuckDNS domain!

### 4. Ρύθμιση Router (Port Forwarding)

Στο περιβάλλον διαχείρισης του router σας:
1. Εντοπίστε την καρτέλα **Port Forwarding** / **Virtual Server**.
2. Δημιουργήστε έναν νέο κανόνα:
   - **Protocol:** UDP (όχι TCP!)
   - **External Port:** 51820
   - **Internal IP:** Η σταθερή τοπική IP του Banana Pi (π.χ. `192.168.1.55`)
   - **Internal Port:** 51820

### 5. Εκκίνηση Υπηρεσίας

```bash
docker compose up -d
```

---

## 📱 Προβολή QR Codes & Διαχείριση Συσκευών (Peers)

Το container παράγει αυτόματα τα κλειδιά και τις ρυθμίσεις για κάθε συσκευή που δηλώσατε στο `PEERS`.

### Εμφάνιση QR Code στο Terminal (για σκανάρισμα από κινητό):
```bash
docker exec -it wireguard qrencode -t ansiutf8 < /config/peer_phone/peer_phone.conf
```

### Προβολή ενεργών συνδέσεων & Handshakes:
```bash
docker exec -it wireguard wg show
```

### Ανάκτηση των αρχείων `.conf` (για laptops/desktops):
Τα αρχεία `.conf` βρίσκονται στον φάκελο `./config/peer_laptop/peer_laptop.conf` κ.ο.κ.

---

## 🛡️ Ενσωμάτωση με AdGuard Home (Ad-blocking)

Αν εκτελείτε ήδη **AdGuard Home** στο Banana Pi (π.χ. στην τοπική IP `192.168.1.55`), μπορείτε να το ορίσετε ως DNS server για τους VPN clients:

Στο `docker-compose.yml`:
```yaml
      - PEERDNS=192.168.1.55 # Η τοπική IP του AdGuard Home
```

Με αυτόν τον τρόπο, όταν συνδέεστε στο WireGuard VPN από το κινητό ή το laptop σας εκτός σπιτιού, **όλες οι διαφημίσεις και τα trackers μπλοκάρονται αυτόματα** από το οικιακό σας AdGuard Home!

---

## 🔄 Full-Tunnel vs Split-Tunnel VPN

* **Full-Tunnel (`ALLOWEDIPS=0.0.0.0/0, ::/0`):**
  Όλη η διαδικτυακή κίνηση της συσκευής περνάει μέσα από το σπίτι σας. Προσφέρει πλήρη ασφάλεια σε δημόσια Wi-Fi και προστασία απορρήτου.
* **Split-Tunnel (`ALLOWEDIPS=10.13.13.0/24, 192.168.1.0/24`):**
  Μόνο οι αιτήσεις προς το οικιακό δίκτυο και το Banana Pi περνούν από το VPN. Η υπόλοιπη κίνηση internet (π.χ. YouTube, Netflix) περνάει απευθείας από τη σύνδεση της συσκευής σας.

---

## 🔍 Αντιμετώπιση Προβλημάτων (Troubleshooting)

### 1. Δεν υπάρχει σύνδεση εκτός σπιτιού (Handshake Never)
* **Αιτία:** Η θύρα UDP 51820 δεν έχει προωθηθεί σωστά στο Router ή το ISP χρησιμοποιεί CGNAT.
* **Λύση:**
  1. Βεβαιωθείτε ότι ο κανόνας στο router αφορά πρωτόκολλο **UDP** (και όχι TCP).
  2. Ελέγξτε αν η WAN IP του router ταυτίζεται με την IP που επιστρέφει το `curl ifconfig.me`. Αν διαφέρουν, βρίσκεστε πίσω από CGNAT (επικοινωνήστε με τον πάροχο για αποδέσμευση/δημόσια IP).

### 2. Σύνδεση επιτυγχάνεται αλλά δεν υπάρχει πρόσβαση στο Internet
* **Αιτία:** Το IP forwarding δεν είναι ενεργοποιημένο στον host ή η τιμή `PEERDNS` δεν είναι προσβάσιμη.
* **Λύση:** Εκτελέστε `sudo sysctl net.ipv4.ip_forward` (πρέπει να επιστρέψει `1`). Δοκιμάστε προσωρινά `PEERDNS=1.1.1.1`.

---

## 📖 Οδηγός Σύνδεσης Συσκευών

Για αναλυτικές οδηγίες βήμα-βήμα σχετικά με το πώς συνδέετε κινητά (Android/iOS) και υπολογιστές (Windows/macOS/Linux), ανατρέξτε στο αρχείο:

👉 **[`Client-Instructions.md`](./Client-Instructions.md)**
