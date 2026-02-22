# Ολοκληρωμένη Εγκατάσταση Nextcloud με Docker Compose

Αυτό το αποθετήριο περιέχει την πλήρη τεκμηρίωση για την εγκατάσταση ενός Nextcloud instance υψηλής απόδοσης, χρησιμοποιώντας Docker Compose. Περιλαμβάνει ρυθμίσεις για τον reverse proxy (Caddy), το High Performance Backend του Nextcloud Talk, καθώς και μια ολοκληρωμένη λύση backup με το Kopia.

Κάθε κύρια υπηρεσία βρίσκεται στον δικό της φάκελο, με ξεχωριστό `Readme.md` που περιγράφει αναλυτικά την εγκατάσταση και τη διαμόρφωσή της.

## Δομή του Αποθετηρίου

```
.
├── caddy/
│   ├── Caddyfile                   # Ρυθμίσεις Web Server του Caddy
│   ├── docker-compose.yml          # Ρυθμίσεις Docker Compose για τον Caddy
│   └── Readme.md                   # Οδηγός εγκατάστασης και ρύθμισης του Caddy
├── kopia/
│   ├── docker-compose.yml          # Ρυθμίσεις Docker Compose για το Kopia
│   └── Readme.md                   # Οδηγός εγκατάστασης και ρύθμισης του Kopia για Backups
├── nextcloud/
│   ├── allagi-sinthimatikou.md     # (Πιθανό) Οδηγός για αλλαγή συνθηματικού
│   ├── docker-compose.yml          # Ρυθμίσεις Docker Compose για τον βασικό Nextcloud stack
│   └── Readme.md                   # Οδηγός εγκατάστασης και ρύθμισης του Nextcloud
├── nextcloud-talk/
│   ├── docker-compose.yml          # Ρυθμίσεις Docker Compose για το Nextcloud Talk HPB
│   └── Readme.md                   # Οδηγός εγκατάστασης και ρύθμισης του Nextcloud Talk HPB
└── Readme.md                       # Αυτό το κεντρικό Readme
```

## Υπηρεσίες & Components

Η αρχιτεκτονική βασίζεται σε Docker containers και περιλαμβάνει τα εξής κύρια στοιχεία:

*   **Nextcloud FPM:** Η βασική εφαρμογή του Nextcloud για συγχρονισμό αρχείων και συνεργασία.
*   **PostgreSQL:** Η βάση δεδομένων για το Nextcloud.
*   **Redis:** Χρησιμοποιείται για caching και locking του Nextcloud.
*   **Caddy:** Λειτουργεί ως Reverse Proxy και Web Server, παρέχοντας αυτόματα SSL/TLS certificates και δρομολογώντας την κίνηση στις υπηρεσίες.
*   **Nextcloud Talk High Performance Backend (HPB):** Παρέχει τον Signaling, STUN και TURN server για βελτιωμένες επιδόσεις στις κλήσεις και συναντήσεις του Nextcloud Talk.
*   **Kopia:** Μια ισχυρή λύση backup για τη δημιουργία κρυπτογραφημένων snapshots των δεδομένων του Nextcloud, με δυνατότητα τοπικής αποθήκευσης και απομακρυσμένου συγχρονισμού.

## Οδηγοί Εγκατάστασης

Για την πλήρη εγκατάσταση του συστήματος, ακολουθήστε τους παρακάτω οδηγούς με τη σειρά που προτείνονται:

1.  **[Εγκατάσταση Nextcloud](nextcloud/Readme.md)**
    *   Αναλυτικές οδηγίες για την εγκατάσταση του βασικού Nextcloud stack (Nextcloud FPM, PostgreSQL, Redis) χρησιμοποιώντας Docker Compose.

2.  **[Εγκατάσταση Caddy](caddy/Readme.md)**
    *   Οδηγός για τη ρύθμιση του Caddy ως reverse proxy, διαχείριση SSL/TLS και δρομολόγηση κίνησης προς το Nextcloud και άλλες υπηρεσίες.

3.  **[Εγκατάσταση Nextcloud Talk High Performance Backend (HPB)](nextcloud-talk/Readme.md)**
    *   Βήματα για την ενσωμάτωση του High Performance Backend για το Nextcloud Talk, βελτιώνοντας την απόδοση των real-time επικοινωνιών.

4.  **[Εγκατάσταση Kopia για Backups](kopia/Readme.md)**
    *   Πλήρης οδηγός για τη διαμόρφωση του Kopia για την αυτοματοποιημένη δημιουργία και διαχείριση backups των δεδομένων του Nextcloud, με τοπική και απομακρυσμένη αποθήκευση.
