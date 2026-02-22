Υπέροχα! Αυτός ο οδηγός για το Kopia είναι εξαιρετικά αναλυτικός και καλύπτει όλες τις πτυχές ενός robust συστήματος backup για το Nextcloud. Η δομή και οι λεπτομέρειες είναι πολύ καλές.

Έχω επεξεργαστεί το κείμενο για να το φέρω σε μορφή τελικής τεκμηρίωσης, προσθέτοντας κάποιες μικρές βελτιώσεις στη διατύπωση, διευκρινίσεις και μορφοποίηση για καλύτερη αναγνωσιμότητα και συνέπεια με τους προηγούμενους οδηγούς. Επίσης, έχω αφαιρέσει την αρχική παράγραφο που επαναλάμβανε κάτι από τον προηγούμενο οδηγό.

Ορίστε η τελική έκδοση του τρίτου κειμένου:

---

# Εγκατάσταση και Ρύθμιση Kopia για Backups του Nextcloud

Αυτός ο οδηγός περιγράφει την εγκατάσταση και ρύθμιση του Kopia για τη δημιουργία αυτοματοποιημένων και αξιόπιστων backups του Nextcloud σας. Η λύση περιλαμβάνει τοπικά snapshots και απομακρυσμένο συγχρονισμό (π.χ., σε SFTP server όπως το Pensieve) για πλήρη προστασία δεδομένων.

## 1. Δομή Καταλόγων στο Host
Βεβαιωθείτε ότι υπάρχουν οι απαραίτητοι κατάλογοι στον host σας (π.χ., στο mount point `/data`) πριν προχωρήσετε:

*   `/data/nextcloud/app` (Κώδικας εφαρμογής Nextcloud - για backup ρυθμίσεων)
*   `/data/nextcloud/nc_data` (Αρχεία χρηστών του Nextcloud - **το πιο κρίσιμο backup**)
*   `/data/backups/databases` (Εδώ θα αποθηκεύεται το SQL dump της PostgreSQL)
*   `/data/kopia/config`, `/data/kopia/cache`, `/data/kopia/logs` (Φάκελοι λειτουργίας του Kopia)
*   `/data/kopia_repository` (Εδώ θα αποθηκεύεται το **τοπικό** κρυπτογραφημένο backup του Kopia)
*   `/data/kopia/restore` (Φάκελος για προσωρινή επαναφορά αρχείων)

### 1.1 Διαχείριση Δικαιωμάτων για το Kopia
Ο χρήστης που εκτελεί το Kopia container (`user: "1000:1000"`) χρειάζεται δικαιώματα ανάγνωσης στους φακέλους που θα γίνουν backup. Επειδή οι φάκελοι του Nextcloud έχουν συγκεκριμένα UIDs, πρέπει να διασφαλίσουμε την πρόσβαση.

Εκτελέστε τις παρακάτω εντολές στον **host** για να δώσετε δικαιώματα ανάγνωσης σε όλους (ή στην ομάδα του χρήστη `1000`) στους απαραίτητους φακέλους:

```bash
# Δίνει δικαιώματα ανάγνωσης στους φακέλους του Nextcloud
sudo chmod -R o+rx /data/nextcloud/nc_data
sudo chmod -R o+rx /data/nextcloud/app/config
sudo chmod -R o+rx /data/backups/databases

# Επίσης, βεβαιωθείτε ότι το αρχείο του dump της βάσης έχει δικαιώματα ανάγνωσης
# (Αυτό συνήθως συμβαίνει αυτόματα με το cron job, αλλά είναι καλό να το ελέγξετε)
sudo chmod o+r /data/backups/databases/nextcloud_db.sql
```
*Σημείωση: Το `o+rx` δίνει δικαιώματα ανάγνωσης και εκτέλεσης σε "άλλους" (δηλαδή σε χρήστες εκτός του ιδιοκτήτη και της ομάδας). Η εκτέλεση είναι απαραίτητη για την περιήγηση στους καταλόγους. Το `o+r` για τα αρχεία αρκεί.*

## 2. Cron Job για Database Dump
Πριν το Kopia δημιουργήσει snapshots, χρειαζόμαστε ένα πρόσφατο dump της βάσης δεδομένων. Προσθέστε την παρακάτω εντολή στο `crontab -e` του host σας, ώστε να εκτελείται καθημερινά στις 02:50 π.μ.

```cron
# Database Backup Dump (καθημερινά στις 02:50)
50 2 * * * docker exec nextcloud_db pg_dumpall -U nextcloud > /data/backups/databases/nextcloud_db.sql
```
*Σημείωση: Το αρχείο `nextcloud_db.sql` θα ανανεώνεται κάθε βράδυ και το Kopia θα δημιουργεί το snapshot του μετά από αυτή την ώρα.*

## 3. Docker Compose του Kopia
Δημιουργήστε τον φάκελο `/data/kopia` και το αρχείο `docker-compose.yml` εντός του. Αυτό το αρχείο θα ρυθμίσει το Kopia container και θα του παρέχει πρόσβαση στις πηγές δεδομένων που θέλετε να κάνετε backup.

**Αρχείο:** `/data/kopia/docker-compose.yml`

```yaml
version: '3.8'

services:
  kopia:
    image: kopia/kopia:latest
    container_name: kopia
    restart: unless-stopped
    user: "1000:1000" # Συνήθως ο default user του host, προσαρμόστε αν χρειάζεται
    networks:
      - web # Συνδέστε το στο ίδιο external network με το Caddy (για πρόσβαση στο UI)
    environment:
      - KOPIA_PASSWORD=your_master_repository_password # Το κλειδί για το repository (ΜΗΝ ΤΟ ΧΑΣΕΙΣ - ΑΠΑΡΑΙΤΗΤΟ ΓΙΑ ΕΠΑΝΑΦΟΡΑ)
    command:
      - server
      - start
      - --address=0.0.0.0:51515 # Ανοίγει το UI σε όλες τις διεπαφές
      - --insecure # Προσοχή: Χρησιμοποιείται μόνο πίσω από reverse proxy όπως ο Caddy
      - --server-username=admin
      - --server-password=your_ui_password # Κωδικός για την πρόσβαση στο Web UI του Kopia
    volumes:
      # Φάκελοι λειτουργίας Kopia
      - ./config:/app/config
      - ./cache:/app/cache
      - ./logs:/app/logs
      
      # SSH Keys για σύνδεση σε SFTP repository (π.χ., Pensieve)
      # Προσαρμόστε το path στο .ssh του χρήστη σας στον host
      - /home/debian/.ssh:/app/.ssh:ro

      # ΤΟΠΙΚΟ REPOSITORY (Εδώ αποθηκεύεται το κρυπτογραφημένο backup)
      - /data/kopia_repository:/repository

      # ΠΗΓΕΣ BACKUP (Τι θέλουμε να σώσουμε) - Read-only για ασφάλεια
      - /data/nextcloud/nc_data:/data/nextcloud_user_files:ro
      - /data/nextcloud/app/config:/data/nextcloud_config:ro
      - /data/backups/databases:/data/nextcloud_database_dumps:ro
      
      # Φάκελος Επαναφοράς (για να στέλνονται τα αρχεία που ανακτώνται)
      - ./restore:/restore:rw

networks:
  web:
    external: true # Χρησιμοποιεί το υπάρχον external network "web"
```
Εκκινήστε το Kopia container από τον φάκελο `/data/kopia`:
```bash
docker compose up -d
```

## 4. Ρυθμίσεις του Caddy για το Kopia UI (Προαιρετικό αλλά Συνιστώμενο)
Για ασφαλή πρόσβαση στο Web UI του Kopia μέσω HTTPS, προσθέστε το παρακάτω block στο `Caddyfile` σας (π.χ., `/data/caddy/Caddyfile`).

```caddy
# ... (Άλλες ρυθμίσεις) ...

kopia.cloud.iosifidis.gr { # Χρησιμοποιήστε το δικό σας subdomain για το Kopia
    reverse_proxy kopia:51515 # Το όνομα του Kopia container και η πόρτα του UI
}
```
Αφού αποθηκεύσετε, κάντε reload την διαμόρφωση του Caddy:
```bash
docker exec caddy caddy reload --config /etc/caddy/Caddyfile
```

## 5. Ρυθμίσεις στο Γραφικό Περιβάλλον του Kopia (Kopia UI)

Επισκεφθείτε το UI του Kopia στον browser σας (π.χ., `https://kopia.cloud.iosifidis.gr` αν χρησιμοποιείτε Caddy, αλλιώς `http://<IP_του_HOST>:51515`). Χρησιμοποιήστε το `server-username` και `server-password` που ορίσατε στο `docker-compose.yml`.

### Α. Αρχική Σύνδεση & Setup Repository
1.  Στην αρχική σελίδα, επιλέξτε **Filesystem** (τοπικό repository).
2.  Στο πεδίο **Path** εισάγετε: `/repository`.
3.  Δώστε το **Master Password** (KOPIA_PASSWORD) που ορίσατε στο `docker-compose.yml`.
4.  Κάντε κλικ στο **Connect**.
*Τώρα το Kopia είναι συνδεδεμένο με το τοπικό του repository και έτοιμο να αποθηκεύει δεδομένα στον δίσκο σας.*

### Β. Δημιουργία Snapshots (Πηγές Backup)
Πηγαίνετε στο μενού **Snapshots** -> **Add Snapshot Source**. Προσθέστε τις παρακάτω τρεις πηγές, οι οποίες αντιστοιχούν στους φακέλους που mountάρατε στο container:

1.  **Source Path:** `/data/nextcloud_user_files` (για τα αρχεία των χρηστών)
2.  **Source Path:** `/data/nextcloud_config` (για τις ρυθμίσεις του Nextcloud)
3.  **Source Path:** `/data/nextcloud_database_dumps` (για τα SQL dumps της βάσης)

Για κάθε πηγή, πατήστε **Add Snapshot Source**.

### Γ. Πολιτική Διακράτησης (Retention Policy)
Για κάθε μία από τις τρεις πηγές που προσθέσατε:
1.  Πηγαίνετε στο μενού **Snapshots**.
2.  Δίπλα σε κάθε πηγή, πατήστε το **Edit Policy**.
3.  Στο τμήμα **Retention**, ρυθμίστε την πολιτική GFS (Grandfather-Father-Son) ως εξής:
    *   **Keep Latest:** 10 (διατηρεί τα 10 πιο πρόσφατα snapshots)
    *   **Keep Daily:** 7 (διατηρεί 7 καθημερινά snapshots)
    *   **Keep Weekly:** 4 (διατηρεί 4 εβδομαδιαία snapshots)
    *   **Keep Monthly:** 12 (διατηρεί 12 μηνιαία snapshots)
4.  Στο τμήμα **Scheduling**, ρυθμίστε την εκτέλεση του snapshot:
    *   **Interval:** Daily
    *   **Start Time:** 03:00 (Ώστε να έχει ολοκληρωθεί το database dump που ξεκινάει 02:50).
5.  Πατήστε **Save**.

### Δ. Συγχρονισμός με Απομακρυσμένο Repository με χρήση UI (π.χ., SFTP)
Εδώ θα ρυθμίσετε το απομακρυσμένο backup για off-site αποθήκευση.
1.  Πηγαίνετε στο μενού **Repositories** -> **Repository Sync**.
2.  Πατήστε **Add Sync Target**.
3.  Επιλέξτε **SFTP**.
4.  Συμπληρώστε τα στοιχεία:
    *   **Host:** Η διεύθυνση IP ή το hostname του απομακρυσμένου SFTP server (π.χ., Pensieve).
    *   **Port:** 22 (συνήθως).
    *   **Path:** Ο απομακρυσμένος κατάλογος όπου θα αποθηκευτούν τα backups (π.χ., `/srv/backups/cloud`).
    *   **Username:** Το username για τη σύνδεση στον SFTP server.
    *   **Key File Path:** `/app/.ssh/id_ed25519` (οδηγεί στο ιδιωτικό κλειδί SSH που mountάρατε).
    *   **Known Hosts Path:** `/app/.ssh/known_hosts` (οδηγεί στο αρχείο known_hosts που mountάρατε).
    *   **Sync Direction:** Normal (local-to-remote).
    *   **Scheduling:** Καθημερινά στις 04:00 (αφού έχουν ολοκληρωθεί τα τοπικά snapshots).
5.  Πατήστε **Save**.

### Ε. Δημιουργία και Σύνδεση με Απομακρυσμένο Repository με τερματικό (SFTP)

Για να διασφαλίσετε ότι έχετε ένα off-site backup, θα δημιουργήσετε ένα δεύτερο repository στον απομακρυσμένο SFTP server.

1.  **Προετοιμασία SSH Keys:**
    Βεβαιωθείτε ότι το αρχείο ιδιωτικού κλειδιού SSH (`id_ed25519`) και το αρχείο `known_hosts` του SFTP server βρίσκονται στον κατάλογο `/home/debian/.ssh` στον host σας, όπως έχετε κάνει mount στο Kopia container (`/app/.ssh`).
    *   Αν δεν έχετε ακόμα `known_hosts` για τον remote SFTP server, μπορείτε να το δημιουργήσετε στον host:
        ```bash
        ssh-keyscan <remote_sftp_host_ip_or_domain> >> /home/debian/.ssh/known_hosts
        ```
    *   Βεβαιωθείτε ότι τα δικαιώματα των κλειδιών είναι σωστά:
        ```bash
        chmod 600 /home/debian/.ssh/id_ed25519
        chmod 644 /home/debian/.ssh/known_hosts
        ```

2.  **Σύνδεση με το Απομακρυσμένο Repository (μέσω τερματικού του Kopia container):**
    Δυστυχώς, η σύνδεση σε νέο SFTP repository μέσω του Kopia UI μπορεί να είναι περίπλοκη ή να μην λειτουργεί όπως αναμένεται με τα SSH keys. Η πιο αξιόπιστη μέθοδος είναι μέσω του τερματικού του Kopia container.
    Ανοίξτε ένα τερματικό στο Kopia container:
    ```bash
    docker exec -it kopia /bin/sh
    ```
    Μέσα στο container, εκτελέστε την παρακάτω εντολή για να συνδεθείτε στο SFTP repository. Αντικαταστήστε τα `sfthost.example.com`, `sftpuser` και `your_master_repository_password_for_remote` με τα δικά σας στοιχεία.
    ```bash
    kopia repository connect sftp --host=sfthost.example.com --port=22 --username=sftpuser --path=/srv/backups/cloud --key-file=/app/.ssh/id_ed25519 --known-hosts-file=/app/.ssh/known_hosts --password=your_master_repository_password_for_remote --override-host-options
    ```
    *Σημείωση:*
    *   Χρησιμοποιήστε ένα **διαφορετικό** Master Password για το απομακρυσμένο repository από αυτό του τοπικού, για επιπλέον ασφάλεια.
    *   Το flag `--override-host-options` είναι σημαντικό για να χρησιμοποιηθούν τα SSH keys που έχετε ορίσει.
    *   Βεβαιωθείτε ότι η διαδρομή `/srv/backups/cloud` υπάρχει και είναι writable από τον `sftpuser` στον απομακρυσμένο SFTP server.

3.  **Δημιουργία Snapshots στο Απομακρυσμένο Repository (μέσω Kopia UI):**
    Τώρα που το απομακρυσμένο repository είναι συνδεδεμένο, μπορείτε να δημιουργήσετε Snapshot Sources και Retention Policies για αυτό, ακριβώς όπως κάνατε για το τοπικό repository.
    *   Επιστρέψτε στο Kopia UI.
    *   Πηγαίνετε στο μενού **Repositories**. Θα πρέπει να δείτε και τα δύο repositories (τοπικό και SFTP). Επιλέξτε το SFTP repository.
    *   Πηγαίνετε στο μενού **Snapshots** -> **Add Snapshot Source**.
    *   Προσθέστε ξανά τις ίδιες τρεις πηγές:
        1.  `/data/nextcloud_user_files`
        2.  `/data/nextcloud_config`
        3.  `/data/nextcloud_database_dumps`
    *   Για κάθε πηγή στο **απομακρυσμένο repository**, ρυθμίστε την **Πολιτική Διακράτησης (Retention Policy)**. Μπορείτε να χρησιμοποιήσετε την ίδια GFS πολιτική (Keep Latest: 10, Keep Daily: 7, Keep Weekly: 4, Keep Monthly: 12).
    *   Στο **Scheduling**, ρυθμίστε την εκτέλεση του snapshot για το απομακρυσμένο repository σε μια ώρα που είναι **μετά** την ολοκλήρωση των τοπικών snapshots (π.χ., καθημερινά στις 04:00 π.μ.). Αυτό διασφαλίζει ότι τα αρχεία είναι ήδη στο τοπικό filesystem όταν το Kopia επιχειρεί να τα στείλει στο remote.

## 6. Τι Κερδίζετε με αυτό το Production Backup Setup:

*   **Ασφάλεια Δεδομένων:** Τα αρχεία χρηστών (`nc_data`), οι ρυθμίσεις και η βάση δεδομένων καλύπτονται πλήρως.
*   **Ταχύτητα Επαναφοράς:** Σε περίπτωση διαγραφής αρχείων, μπορείτε να τα επαναφέρετε σε δευτερόλεπτα από το τοπικό repository.
*   **Πλήρες Disaster Recovery:** Αν ο κύριος server καταστραφεί ολοσχερώς, έχετε στο απομακρυσμένο repository:
    *   Όλα τα αρχεία των χρηστών.
    *   Τις ρυθμίσεις του Nextcloud (`config.php`).
    *   Το SQL dump για να ξαναστήσετε τη βάση ακριβώς όπως ήταν.
*   **Αυτοματοποίηση:** Το Cron job φροντίζει για το database dump, ενώ το Kopia αναλαμβάνει τη δημιουργία snapshots και τον συγχρονισμό, όλα αυτόματα.
*   **Κρυπτογράφηση:** Όλα τα δεδομένα αποθηκεύονται κρυπτογραφημένα, εξασφαλίζοντας την ιδιωτικότητα.

**Τελευταία Συμβουλή:** Μετά την αρχική ρύθμιση, εκτελέστε χειροκίνητα ένα snapshot για κάθε πηγή και έναν συγχρονισμό (Repository Sync -> Run Now) για να βεβαιωθείτε ότι όλα λειτουργούν σωστά και δεν υπάρχουν προβλήματα δικαιωμάτων ή σύνδεσης με το απομακρυσμένο repository.
