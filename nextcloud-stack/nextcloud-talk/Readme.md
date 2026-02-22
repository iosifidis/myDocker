# Εγκατάσταση Nextcloud Talk High Performance Backend (HPB)

Για να προσθέσετε το High Performance Backend (HPB) στην υπάρχουσα εγκατάσταση του Nextcloud σας, ο πιο αποτελεσματικός τρόπος είναι να χρησιμοποιήσετε το επίσημο Docker image (`aio-talk`) που παρέχεται από το Nextcloud All-in-One (AIO). Αυτό το image περιέχει ήδη ενσωματωμένες όλες τις απαραίτητες υπηρεσίες: τον **Signaling server**, τον **STUN server** και τον **TURN server**.

Ακολουθούν τα βήματα για την εγκατάσταση και ρύθμιση του HPB χρησιμοποιώντας Docker, Caddy (ως reverse proxy) και το περιβάλλον (UI) του Nextcloud.

**Domain Name:** `cloud.iosifidis.gr`

## 1. Δημιουργία Ισχυρών Κωδικών (Secrets)
Το HPB απαιτεί την παραγωγή τριών ισχυρών κωδικών (τουλάχιστον 24-32 χαρακτήρων) για την ασφάλεια των υπηρεσιών του.

Εκτελέστε την παρακάτω εντολή στο τερματικό σας τρεις φορές για να παράγετε τους κωδικούς που θα χρειαστείτε στο επόμενο βήμα:
```bash
openssl rand -hex 32
```
Σημειώστε τα αποτελέσματα. Θα τα χρησιμοποιήσετε ως:
1.  `TURN_SECRET`
2.  `SIGNALING_SECRET`
3.  `INTERNAL_SECRET`

**Παράδειγμα:**
`openssl rand -hex 32`  θα μπορούσε να επιστρέψει `c5f2b1d7e8a9c0b3f4e5d6c7b8a9f0e1d2c3b4a5f6e7d8c9b0a1f2e3d4c5b6a7`

## 2. Προσθήκη του HPB στο Docker Compose
Προσθέστε την παρακάτω υπηρεσία στο `docker-compose.yml` αρχείο του Nextcloud stack (π.χ., `/data/nextcloud/docker-compose.yml`) ή σε ένα ξεχωριστό `docker-compose.yml` αν το προτιμάτε.

**Σημαντικό:** Προσαρμόστε τα μυστικά με αυτά που δημιουργήσατε στο Βήμα 1 και το `NC_DOMAIN` / `TALK_HOST` με το domain σας (`cloud.iosifidis.gr`).

```yaml
# ... (Οι υπάρχουσες υπηρεσίες db, redis, app) ...

  nc-talk:
    container_name: talk_hpb
    image: ghcr.io/nextcloud-releases/aio-talk:latest
    init: true
    ports:
      - "3478:3478/tcp"  # Πόρτες για STUN/TURN
      - "3478:3478/udp"
      - "8081:8081/tcp"  # Πόρτα για το Signaling (εσωτερικά)
    environment:
      - NC_DOMAIN=cloud.iosifidis.gr # Το domain του Nextcloud σας
      - TALK_HOST=cloud.iosifidis.gr # Το domain του Nextcloud σας
      - TURN_SECRET=<Ο_ΚΩΔΙΚΟΣ_TURN_ΠΟΥ_ΔΗΜΙΟΥΡΓΗΣΑΤΕ>
      - SIGNALING_SECRET=<Ο_ΚΩΔΙΚΟΣ_SIGNALING_ΠΟΥ_ΔΗΜΙΟΥΡΓΗΣΑΤΕ>
      - INTERNAL_SECRET=<Ο_ΚΩΔΙΚΟΣ_INTERNAL_ΠΟΥ_ΔΗΜΙΟΥΡΓΗΣΑΤΕ>
      - TZ=Europe/Athens
      - TALK_PORT=3478 # Η πόρτα για STUN/TURN, πρέπει να είναι ανοιχτή στο firewall
    restart: unless-stopped
    networks:
      - web # Συνδέστε το στο ίδιο external network με το Caddy και το Nextcloud app
```

Εκκινήστε ή ενημερώστε τα Docker containers από τον φάκελο του `docker-compose.yml`:
```bash
docker compose up -d
```

## 3. Ρύθμιση του Caddy (v2) Reverse Proxy
Το Caddy πρέπει να δρομολογεί την κίνηση των WebSockets και του API του Signaling Server. Προσθέστε το παρακάτω block στο `Caddyfile` σας (π.χ., `/data/caddy/Caddyfile`), μέσα στο υπάρχον block του `cloud.iosifidis.gr`:

```caddy
cloud.iosifidis.gr {
    # ... (Άλλες ρυθμίσεις του Nextcloud-fpm) ...

    # Ρυθμίσεις για το Nextcloud Talk High Performance Backend
    route /standalone-signaling/* {
        uri strip_prefix /standalone-signaling
        reverse_proxy http://nc-talk:8081 {
           header_up X-Real-IP {remote_host}
        }
    }
}
```
**Σημείωση:** Αν το Caddy και το `nc-talk` container δεν βρίσκονται στο ίδιο Docker network ή για λόγους troubleshooting, μπορείτε να χρησιμοποιήσετε την IP του host αντί για το `talk_hpb` (π.χ., `http://127.0.0.1:8081` αν τρέχουν στον ίδιο host). Η χρήση του ονόματος container είναι η προτιμώμενη μέθοδος σε περιβάλλον Docker Compose.

Αφού αποθηκεύσετε το `Caddyfile`, κάντε reload την διαμόρφωση του Caddy:
```bash
docker exec caddy caddy reload --config /etc/caddy/Caddyfile
```
Μπορείτε να επιβεβαιώσετε ότι το Signaling Server λειτουργεί κάνοντας μια κλήση στο:
`curl -i https://cloud.iosifidis.gr/standalone-signaling/api/v1/welcome`. Η απάντηση θα πρέπει να επιστρέφει ένα JSON καλωσορίσματος, παρόμοιο με αυτό:
```json
{"data":{"version":"1.0.0"},"message":"Welcome to the Nextcloud Talk Backend!","statusCode":200}
```

## 4. Ρύθμιση του Firewall
Βεβαιωθείτε ότι **η πόρτα 3478 (τόσο TCP όσο και UDP)** είναι ανοιχτή στο router ή στο firewall του διακομιστή σας, καθώς είναι απαραίτητη για τη λειτουργία των STUN και TURN από τους εξωτερικούς πελάτες.

**Μέθοδος 1: Χρήση του UFW (Συνιστάται)**

Το UFW είναι ένα πιο εύκολο στη χρήση frontend για το iptables.

1.  **Εγκατάσταση UFW (αν δεν υπάρχει):**
    ```bash
    sudo apt update
    sudo apt install ufw
    ```

2.  **Ενεργοποίηση UFW (αν δεν είναι ενεργό):**
    Πριν το ενεργοποιήσετε, βεβαιωθείτε ότι έχετε επιτρέψει την πρόσβαση SSH (θύρα 22) αλλιώς μπορεί να κλειδωθείτε εκτός του διακομιστή σας.
    ```bash
    sudo ufw allow ssh # ή sudo ufw allow 22/tcp
    sudo ufw enable
    sudo ufw status verbose # για να ελέγξετε την κατάσταση
    ```

3.  **Άνοιγμα της θύρας 3478 για TCP και UDP:**
    ```bash
    sudo ufw allow 3478/tcp
    sudo ufw allow 3478/udp
    ```

4.  **Επαλήθευση:**
    ```bash
    sudo ufw status verbose
    ```
    Θα πρέπει να δείτε τις καταχωρήσεις για 3478 (TCP) και 3478 (UDP).

**Μέθοδος 2: Χρήση του IPTables**

Το Iptables είναι πιο περίπλοκο, αλλά είναι η βάση του firewall στο Linux. Οι αλλαγές στο iptables δεν είναι μόνιμες από προεπιλογή και θα χαθούν μετά την επανεκκίνηση.

1.  **Άνοιγμα της θύρας 3478 για TCP:**
    ```bash
    sudo iptables -A INPUT -p tcp --dport 3478 -j ACCEPT
    ```

2.  **Άνοιγμα της θύρας 3478 για UDP:**
    ```bash
    sudo iptables -A INPUT -p udp --dport 3478 -j ACCEPT
    ```

3.  **Αποθήκευση των κανόνων (για να επιβιώσουν μετά την επανεκκίνηση):**
    Για να κάνετε τους κανόνες μόνιμους, θα πρέπει να εγκαταστήσετε ένα πακέτο που τους αποθηκεύει και τους επαναφέρει κατά την εκκίνηση.

    ```bash
    sudo apt install iptables-persistent
    ```
    Κατά την εγκατάσταση, θα σας ρωτήσει αν θέλετε να αποθηκεύσετε τους τρέχοντες κανόνες IPv4 και IPv6. Επιλέξτε "Yes" και για τα δύο.

    Αν χρειαστεί να αποθηκεύσετε κανόνες χειροκίνητα αργότερα:
    ```bash
    sudo netfilter-persistent save
    ```

4.  **Επαλήθευση:**
    ```bash
    sudo iptables -L -n -v
    ```
    Αναζητήστε τις καταχωρήσεις για τη θύρα 3478.

## 5. Ρύθμιση μέσα από το Nextcloud UI
Συνδεθείτε στο Nextcloud ως διαχειριστής, πηγαίνετε στις **Ρυθμίσεις (Settings)** -> **Talk (Συζήτηση)** και εισάγετε τις παρακάτω ρυθμίσεις:

1.  **STUN server:**
    *   **Διακομιστής (Server):** `cloud.iosifidis.gr:3478`
    *(Προσοχή: χωρίς `https://` ή `stun:`, μόνο το domain και η πόρτα)*.

2.  **TURN server:**
    *   **Διακομιστής (Server):** `cloud.iosifidis.gr:3478`
    *   **Μυστικό (TURN secret):** Εισάγετε τον κωδικό που βάλατε στο `TURN_SECRET` στο `docker-compose.yml`.
    *   **Πρωτόκολλο:** Επιλέξτε "UDP and TCP".

3.  **High-performance backend (Signaling server):**
    *   **URL (External signaling server):** `https://cloud.iosifidis.gr/standalone-signaling`
    *(Χρησιμοποιήστε `https://`, όχι `wss://`)*.
    *   **Shared secret:** Εισάγετε τον κωδικό που βάλατε στο `SIGNALING_SECRET` στο `docker-compose.yml`.

Μόλις αποθηκεύσετε τις ρυθμίσεις, μπορείτε να πατήσετε το εικονίδιο ελέγχου (συνήθως ένα πράσινο tick) δίπλα σε κάθε ρύθμιση για να επιβεβαιώσετε ότι το Nextcloud επικοινωνεί επιτυχώς με τον Signaling και τον TURN server σας.
