Αυτός ο οδηγός περιγράφει την επίσημη και προτεινόμενη μέθοδο για την εγκατάσταση του [Uptime Kuma](https://github.com/louislam/uptime-kuma), όπου η υπηρεσία εκτελείται στο δικό της subdomain (π.χ., `uptime.ellak.gr`) και η πρόσβαση γίνεται μέσω του Caddy reverse proxy.

#### **Προαπαιτούμενα**

1.  **Docker & Docker Compose:** Εγκατεστημένα και λειτουργικά στον server σας.
2.  **Caddy Reverse Proxy:** Ένα Caddy container να εκτελείται ήδη και να είναι συνδεδεμένο σε ένα κοινό Docker δίκτυο (στο παράδειγμά μας, το δίκτυο ονομάζεται `web`).
3.  **DNS Εγγραφή:** Μια εγγραφή τύπου `A` στον DNS provider σας που να δείχνει το subdomain που επιλέξατε (π.χ., `uptime.ellak.gr`) στη δημόσια IP διεύθυνση του server σας.

---

### **Βήμα 1: Δημιουργία του Docker Compose για το Uptime Kuma**

Δημιουργούμε ένα αρχείο `docker-compose.yml` αποκλειστικά για την υπηρεσία Uptime Kuma. Αυτό το αρχείο είναι απλό, καθώς δεν χρειάζεται να ορίσουμε πόρτες ή πολύπλοκες μεταβλητές, αφού ο Caddy θα διαχειριστεί την πρόσβαση.

**Αρχείο:** `docker-compose.uptime-kuma.yml`

```yaml
# version: '3.8'

services:
  uptime-kuma:
    image: louislam/uptime-kuma:2
    container_name: uptime-kuma
    volumes:
      # Ο φάκελος ./uptime-kuma-data θα δημιουργηθεί και θα αποθηκεύει τα δεδομένα της εφαρμογής.
      - ./uptime-kuma-data:/app/data
    restart: unless-stopped
    networks:
      # Συνδέουμε το container στο ίδιο δίκτυο 'web' που χρησιμοποιεί και ο Caddy.
      - web

networks:
  web:
    # Δηλώνουμε ότι το δίκτυο αυτό είναι εξωτερικό και έχει ήδη δημιουργηθεί.
    external: true
```

---

### **Βήμα 2: Ενημέρωση του Caddyfile**

Προσθέτουμε ένα νέο, απλό μπλοκ στο κεντρικό `Caddyfile` για να διαχειριστεί το νέο subdomain. Το Caddy θα αναλάβει αυτόματα την έκδοση και ανανέωση του SSL πιστοποιητικού (HTTPS).

**Αρχείο:** `Caddyfile` (Πλήρες περιεχόμενο με την προσθήκη)

```caddy
# ===================================================
# Υπηρεσία Uptime Kuma (ΝΕΑ ΠΡΟΣΘΗΚΗ)
# ===================================================
uptime.iosifidis.gr {
    reverse_proxy uptime-kuma:3001
}
```

Το docker-compose.yml του caddy είναι το:

```yaml
services:
  caddy:
    image: caddy:latest
    container_name: caddy
    restart: unless-stopped
    env_file: .env # Φορτώνει τις μεταβλητές από το .env
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./Caddyfile:/etc/caddy/Caddyfile
      - ./data:/data # Bind mount για μόνιμη αποθήκευση
      - ./config:/config
    networks:
      - web

networks:
  web:
    external: true
```

---

### **Βήμα 3: Εκτέλεση και Ενεργοποίηση**

Αφού τα παραπάνω αρχεία είναι έτοιμα, εκτελούμε τις παρακάτω εντολές με τη σωστή σειρά.

1.  **Εκκίνηση του Uptime Kuma Container:**
    Μεταβείτε στον φάκελο όπου αποθηκεύσατε το `docker-compose.yml` (**cd /docker/uptime-kuma**) και εκτελέστε την εντολή για να κατεβάσετε το image και να ξεκινήσετε το container στο background.

    ```bash
    # Το -d (detached) το τρέχει στο background
    docker compose up -d
    ```

2.  **Επανεκκίνηση του Caddy:**
    Για να εφαρμόσει τις αλλαγές που κάνατε στο `Caddyfile`, ο Caddy πρέπει να κάνει reload τη διαμόρφωσή του. Ο πιο απλός τρόπος είναι να τον επανεκκινήσετε.

    ```bash
    # Σημείωση: Αντικαταστήστε το 'caddy' με το πραγματικό όνομα της υπηρεσίας του Caddy 
    docker restart caddy
    ```

---

### **Βήμα 4: Επαλήθευση**

Ανοίξτε έναν browser και πλοηγηθείτε στο subdomain που ορίσατε:

`https://uptime.iosifidis.gr`

Θα πρέπει να σας υποδεχτεί η οθόνη αρχικής ρύθμισης του Uptime Kuma, όπου θα δημιουργήσετε τον λογαριασμό διαχειριστή. Η σελίδα θα πρέπει να φορτώνει πλήρως, με σωστή εμφάνιση (CSS) και λειτουργικότητα (JavaScript), και η σύνδεση να είναι ασφαλής (HTTPS).
