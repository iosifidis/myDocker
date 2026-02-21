Η παρούσα τεκμηρίωση αφορά την εγκατάσταση του OpenEMR v7.0.4 σε περιβάλλον Docker, με χρήση Caddy ως Reverse Proxy και bind mounts για την ευκολότερη διαχείριση backups και μεταφοράς (migration).

## 1. Δομή Καταλόγων και Αρχείων
Όλα τα αρχεία της εφαρμογής βρίσκονται στον κατάλογο `/docker/openemr`.

### Δημιουργία Φακέλων
```bash
mkdir -p /docker/openemr/mysql_data
mkdir -p /docker/openemr/sites
mkdir -p /docker/openemr/logs
```

### Προετοιμασία Φακέλου `sites`
Επειδή το bind mount "κρύβει" τα αρχεία του image, απαιτείται η αρχική εξαγωγή της δομής του OpenEMR στον host:
```bash
docker run --rm -v /docker/openemr/sites:/target openemr/openemr:7.0.4 sh -c "cp -rp /var/www/localhost/htdocs/openemr/sites/* /target/"
```

## 2. Διαχείριση Δικαιωμάτων (Permissions)
Για την απρόσκοπτη λειτουργία των containers, οι φάκελοι πρέπει να ανήκουν στους σωστούς εσωτερικούς χρήστες (UIDs):

*   **OpenEMR App (Apache/PHP):** UID 1000
*   **MariaDB:** UID 999 (ή 1000 ανάλογα το image)

Εφαρμογή δικαιωμάτων:
```bash
sudo chown -R 1000:1000 /docker/openemr/sites
sudo chown -R 1000:1000 /docker/openemr/logs
sudo chown -R 999:999 /docker/openemr/mysql_data
sudo chmod -R 775 /docker/openemr/sites
```

## 3. Docker Compose Configuration
Το αρχείο `docker-compose.yml` ρυθμίστηκε ώστε να παρακάμπτει το προβληματικό αυτόματο script εγκατάστασης (`MANUAL_SETUP: "yes"`) και να επιτρέπει την παραμετροποίηση μέσω του Wizard.

```yaml
version: '3.8'

services:
  mysql:
    image: mariadb:10.11
    container_name: openemr_db
    restart: always
    environment:
      MYSQL_ROOT_PASSWORD: root_password_here
    volumes:
      - ./mysql_data:/var/lib/mysql
    networks:
      - internal

  openemr:
    image: openemr/openemr:7.0.4
    container_name: openemr_app
    restart: always
    environment:
      MANUAL_SETUP: "yes" # Επιτρέπει τη χειροκίνητη εγκατάσταση
      MYSQL_HOST: mysql
    volumes:
      - ./sites:/var/www/localhost/htdocs/openemr/sites
      - ./logs:/var/www/logs # Διόρθωση για τα SSL Apache logs
    depends_on:
      - mysql
    networks:
      - internal
      - web

networks:
  internal:
  web:
    external: true
```

## 4. Αρχικές Ρυθμίσεις Wizard (Step 2 - Database Setup)
Κατά την πρώτη εκτέλεση μέσω της διεύθυνσης `https://emr.iosifidis.gr/setup.php`, εισάγουμε τις εξής παραμέτρους:

| Πεδίο | Τιμή | Περιγραφή |
| :--- | :--- | :--- |
| **Server Host** | `mysql` | Το όνομα της υπηρεσίας βάσης στο Docker |
| **Server Port** | `3306` | Η εσωτερική θύρα της MariaDB |
| **Database Name** | `openemr` | Το όνομα της βάσης που θα δημιουργηθεί |
| **Login Name** | `openemr` | Ο χρήστης της βάσης δεδομένων |
| **Name for Root Account** | `root` | Ο διαχειριστής της MariaDB |
| **Root Password** | `root_password_here` | Ο κωδικός που ορίστηκε στο Compose |
| **User Hostname** | `%` | Επιτρέπει σύνδεση από το app container |
| **Initial User Login** | `admin` | Ο πρώτος διαχειριστής του OpenEMR |

## 5. Reverse Proxy (Caddyfile)
Η σύνδεση του Caddy με το OpenEMR γίνεται μέσω του εξωτερικού δικτύου `web`:

```caddyfile
emr.iosifidis.gr {
    reverse_proxy openemr_app:80
}
```

## 6. Backup Στρατηγική
Λόγω της χρήσης bind mounts, το πλήρες backup της εγκατάστασης επιτυγχάνεται με τη λήψη αντιγράφου ολόκληρου του καταλόγου `/docker/openemr`. 
**Σημείωση:** Για συνέπεια δεδομένων της βάσης, προτείνεται η χρήση της εντολής `mariadb-dump` πριν τη λήψη αντιγράφου του φακέλου.

---

### Ασφάλεια: Read-only το `sqlconf.php`
Αυτό το αρχείο περιέχει τους κωδικούς της βάσης δεδομένων. Τώρα που τελείωσε ο Wizard, πρέπει να το κλειδώσουμε.

Τρέξε στο τερματικό του VM:
```bash
sudo chmod 444 /docker/openemr/sites/default/sqlconf.php
```
*Σημείωση: Αν ποτέ χρειαστεί να αλλάξεις κάτι στις ρυθμίσεις της βάσης, θα πρέπει να το κάνεις προσωρινά `644`.*

---

### Αυτοματοποιημένο Database Dump (Cron)
Το Kopia παίρνει snapshots αρχείων. Αν πάρει snapshot τον φάκελο `mysql_data` την ώρα που η βάση λειτουργεί, το backup μπορεί να είναι κατεστραμμένο (corrupted). Γι' αυτό θα εξάγουμε τη βάση σε ένα αρχείο `.sql` κάθε βράδυ.

#### Α. Δημιουργία φακέλου για τα dumps
```bash
mkdir -p /docker/openemr/backups
sudo chown -R 1000:1000 /docker/openemr/backups
```

#### Β. Δημιουργία του Script
Δημιούργησε ένα αρχείο: `nano /docker/openemr/db_backup.sh`
Πρόσθεσε τα εξής:
```bash
#!/bin/bash
# Εξαγωγή της βάσης δεδομένων σε αρχείο
docker exec openemr_db mariadb-dump -u root -p'root_password_here' openemr > /docker/openemr/backups/openemr_db.sql

# Διόρθωση δικαιωμάτων για να μπορεί το Kopia να το διαβάσει
chown 1000:1000 /docker/openemr/backups/openemr_db.sql
```
*Αντικατάστησε το `root_password_here` με τον πραγματικό κωδικό.*

Δώσε δικαιώματα εκτέλεσης:
```bash
chmod +x /docker/openemr/db_backup.sh
```

#### Γ. Προσθήκη στο Cron (02:00 π.μ.)
Τρέξε `crontab -e` και πρόσθεσε τη γραμμή στο τέλος:
```cron
00 02 * * * /docker/openemr/db_backup.sh
```

---

### Σύνδεση με το Kopia (Docker UI)
Για να μπορεί το Kopia (που τρέχει σε docker) να "δει" τους φακέλους του OpenEMR, πρέπει να τους προσθέσεις στο `docker-compose.yml` του **Kopia**.

#### Α. Ενημέρωση του Kopia Container
Στο `docker-compose.yml` του Kopia, πρόσθεσε στα `volumes`:
```yaml
volumes:
  - /docker/openemr:/data/openemr:ro # Το :ro σημαίνει read-only για ασφάλεια
```
Κάνε ένα `docker compose up -d` στο Kopia για να δει τη νέα διαδρομή.

#### Β. Ρύθμιση μέσω του Kopia UI
1.  Μπες στο UI του Kopia.
2.  Πάτα **New Snapshot**.
3.  Στο **Path**, βάλε την εσωτερική διαδρομή που ορίσαμε στο βήμα Α: `/data/openemr`.
4.  Στο **Scheduling**, όρισε τις ώρες που θέλεις (π.χ. καθημερινά στις 03:00, ώστε να έχει τελειώσει το SQL dump των 02:00).
5.  Στο **Retention**, βάλε τις ρυθμίσεις που ανέφερες (Daily, Weekly, Monthly).

---

### 4. Τι θα περιλαμβάνει το Backup σου;
Με αυτή τη δομή, το Kopia θα αποθηκεύει στο repository του:
1.  `/data/openemr/sites/`: Όλα τα έγγραφα των ασθενών, τις φωτογραφίες και τα αρχεία ρυθμίσεων.
2.  `/data/openemr/backups/openemr_db.sql`: Ολόκληρη τη βάση δεδομένων σε μορφή κειμένου.

### Γιατί αυτό είναι το ιδανικό σενάριο;
*   **Ασφάλεια:** Η βάση δεδομένων είναι σε μορφή `.sql`, οπότε αν χρειαστεί να την επαναφέρεις σε άλλο VM, απλώς κάνεις ένα `import`.
*   **Versioning:** Το Kopia θα κρατάει εκδόσεις του `.sql` αρχείου. Αν ένας γιατρός διαγράψει κάτι κατά λάθος την Τρίτη, μπορείς να γυρίσεις στο snapshot της Δευτέρας.
*   **Συνέπεια:** Το SQL dump στις 02:00 διασφαλίζει ότι τα δεδομένα της βάσης είναι "παγωμένα" σωστά εκείνη τη στιγμή.
