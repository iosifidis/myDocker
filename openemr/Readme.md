# OpenEMR v7.0.4 Deployment, Hardening & Backup with Docker & Caddy

Η παρούσα τεκμηρίωση περιγράφει τη διαδικασία εγκατάστασης, αρχικοποίησης βάσης δεδομένων, θωράκισης ασφαλείας (hardening έναντι webshells / `php2shell` και εκτεθειμένων setup endpoints) και αυτοματοποιημένης λήψης αντιγράφων ασφαλείας (backups με `mariadb-dump` & **Kopia**) για το **OpenEMR v7.0.4** σε περιβάλλον Docker με reverse proxy τον **Caddy** και bind mounts.

---

## 📋 Περιεχόμενα
- [Προαπαιτούμενα](#-προαπαιτούμενα)
- [Δομή Αρχείων](#-δομή-αρχείων)
- [Οδηγίες Εγκατάστασης](#-οδηγίες-εγκατάστασης)
  - [1. Δημιουργία Φακέλων & Προετοιμασία `sites/`](#1-δημιουργία-φακέλων--προετοιμασία-sites)
  - [2. Μεταβλητές Περιβάλλοντος (.env)](#2-μεταβλητές-περιβάλλοντος-env)
  - [3. Docker Compose Configuration](#3-docker-compose-configuration)
  - [4. Προσωρινό Caddyfile & Web Setup Wizard](#4-προσωρινό-caddyfile--web-setup-wizard)
- [Θωράκιση Ασφαλείας (Hardening)](#-θωράκιση-ασφαλείας-hardening)
- [Αυτοματοποιημένο Backup & Kopia](#-αυτοματοποιημένο-backup--kopia)
- [Επαλήθευση Λειτουργίας](#-επαλήθευση-λειτουργίας)
- [Αντιμετώπιση Προβλημάτων (Troubleshooting)](#-αντιμετώπιση-προβλημάτων-troubleshooting)
- [Μαθήματα & Βέλτιστες Πρακτικές](#-μαθήματα--βέλτιστες-πρακτικές)
- [Παραμετροποίηση & Οδηγίες Χρήσης](#-παραμετροποίηση--οδηγίες-χρήσης)

---

## 🛠 Προαπαιτούμενα

* **Λειτουργικό Σύστημα:** Linux Host (Debian/Ubuntu) με εγκατεστημένα Docker Engine και Docker Compose v2.
* **Reverse Proxy:** Ενεργό container `caddy` συνδεδεμένο σε εξωτερικό Docker network με όνομα `web`.
* **DNS:** Εγγραφή τύπου `A`/`AAAA` για το domain της εφαρμογής (π.χ. `emr.iosifidis.gr`) που να δείχνει στην IP του server.
* **Backup Agent (Προαιρετικά):** Ενεργό container `kopia` με read-only πρόσβαση στον κατάλογο του OpenEMR.

---

## 📁 Δομή Αρχείων

```bash
/openemr
├── Caddyfile              # Hardened ρυθμίσεις Caddy Reverse Proxy
├── Configuration.md       # Επιχειρησιακή παραμετροποίηση (Facility, Locale, ACL, SOAP)
├── docker-compose.yml     # Υπηρεσίες OpenEMR & MariaDB
├── .env.example           # Πρότυπο μεταβλητών περιβάλλοντος
├── Readme.md              # Οδηγός εγκατάστασης, θωράκισης & backup
├── kefalaio-1-...md       # Οδηγίες χρήσης (Κεφάλαια 1–7)
├── mysql_data/            # Bind mount βάσης MariaDB (UID 999)
├── sites/                 # Bind mount αρχείων OpenEMR (UID 1000)
├── logs/                  # Bind mount Apache/SSL logs (UID 1000)
└── backups/               # Κατάλογος ημερήσιων SQL dumps (UID 1000)
```

---

## ⚙️ Οδηγίες Εγκατάστασης

### 1. Δημιουργία Φακέλων & Προετοιμασία `sites/`

Δημιουργήστε τους απαραίτητους καταλόγους στον host:

```bash
mkdir -p mysql_data sites logs backups
```

Επειδή το bind mount `./sites` αποκρύπτει τα ενσωματωμένα αρχεία του Docker image κατά την εκκίνηση, απαιτείται η εξαγωγή της αρχικής δομής του OpenEMR στον host **πριν** την πρώτη εκκίνηση:

```bash
docker run --rm -v "$(pwd)/sites:/target" openemr/openemr:7.0.4 sh -c "cp -rp /var/www/localhost/htdocs/openemr/sites/* /target/"
```

Εφαρμογή των σωστών UIDs/GIDs (**OpenEMR App:** UID `1000`, **MariaDB:** UID `999`):

```bash
sudo chown -R 1000:1000 sites logs backups
sudo chown -R 999:999 mysql_data
sudo chmod -R 775 sites
```

### 2. Μεταβλητές Περιβάλλοντος (.env)

Αντιγράψτε το [.env.example](./.env.example) σε `.env` και ορίστε έναν ισχυρό κωδικό root για τη MariaDB:

```bash
cp .env.example .env
```

```env
# MariaDB Root Password
MYSQL_ROOT_PASSWORD=StrongRootPasswordHere
```

### 3. Docker Compose Configuration

Το αρχείο [docker-compose.yml](./docker-compose.yml) παρακάμπτει το αυτόματο script εγκατάστασης (`MANUAL_SETUP: "yes"`) ώστε η παραμετροποίηση να γίνεται ελεγχόμενα μέσω του Web Wizard:

```yaml
services:
  mysql:
    image: mariadb:10.11
    container_name: openemr_db
    restart: unless-stopped
    environment:
      MYSQL_ROOT_PASSWORD: ${MYSQL_ROOT_PASSWORD}
    volumes:
      - ./mysql_data:/var/lib/mysql
    networks:
      - internal

  openemr:
    image: openemr/openemr:7.0.4
    container_name: openemr_app
    restart: unless-stopped
    environment:
      MANUAL_SETUP: "yes" # Allows manual setup via web wizard
      MYSQL_HOST: mysql
    volumes:
      - ./sites:/var/www/localhost/htdocs/openemr/sites
      - ./logs:/var/www/logs # SSL Apache logs
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

Εκκινήστε τα containers:

```bash
docker compose up -d
```

### 4. Προσωρινό Caddyfile & Web Setup Wizard

1. Προσθέστε **προσωρινά** το βασικό block στον Caddy (χωρίς τους περιορισμούς του `/setup.php`) ώστε να μπορέσετε να τρέξετε τον Wizard:
   ```caddyfile
   emr.iosifidis.gr {
       reverse_proxy openemr_app:80
   }
   ```
2. Επικυρώστε και επαναφορτώστε τον Caddy:
   ```bash
   docker exec caddy caddy validate --config /etc/caddy/Caddyfile
   docker exec caddy caddy reload --config /etc/caddy/Caddyfile
   ```
3. Ανοίξτε στον browser τη διεύθυνση `https://emr.iosifidis.gr/setup.php` και συμπληρώστε στο **Step 2 (Database Setup)**:

| Πεδίο Wizard | Τιμή | Σημείωση |
| :--- | :--- | :--- |
| **Server Host** | `mysql` | Όνομα υπηρεσίας MariaDB στο εσωτερικό δίκτυο Docker |
| **Server Port** | `3306` | Εσωτερική θύρα MariaDB |
| **Database Name** | `openemr` | Όνομα βάσης δεδομένων |
| **Login Name** | `openemr` | Χρήστης βάσης εφαρμογής |
| **Password** | `<OPENEMR_DB_PASSWORD>` | Αποφυγή ειδικών χαρακτήρων (`'`, `"`, `\`, `$`) |
| **Name for Root Account** | `root` | Διαχειριστής MariaDB |
| **Root Password** | `<MYSQL_ROOT_PASSWORD>` | Ο κωδικός από το `.env` |
| **User Hostname** | `%` | **Υποχρεωτική αλλαγή** από το default `localhost` σε `%` |
| **Initial User Login** | `admin` | Αρχικός λογαριασμός διαχειριστή OpenEMR |
| **Initial User Password** | `<ADMIN_PASSWORD>` | Ισχυρός κωδικός εισόδου εφαρμογής |

---

## 🛡️ Θωράκιση Ασφαλείας (Hardening)

Μετά την επιτυχή σύνδεση ως `admin`, εφαρμόστε τα παρακάτω μέτρα άμυνας έναντι webshells (`php2shell`) και εκτεθειμένων setup endpoints:

1. **Κλείδωμα του `sqlconf.php` (read-only) και αφαίρεση δικαιωμάτων εκτέλεσης από τα setup/upgrade scripts:**
   ```bash
   sudo chmod 444 sites/default/sqlconf.php
   docker exec openemr_app chmod 000 \
     /var/www/localhost/htdocs/openemr/setup.php \
     /var/www/localhost/htdocs/openemr/sql_upgrade.php \
     /var/www/localhost/htdocs/openemr/acl_upgrade.php \
     /var/www/localhost/htdocs/openemr/ippf_upgrade.php
   ```
   *(Αν ποτέ χρειαστεί αναβάθμιση ή αλλαγή ρυθμίσεων βάσης, επαναφέρετε προσωρινά το `sqlconf.php` σε `644`).*

2. **Ενημέρωση του Caddy με το πλήρες hardened [Caddyfile](./Caddyfile):**
   ```caddyfile
   emr.iosifidis.gr {
       # Block sensitive installation/upgrade scripts
       @blocked_scripts {
           path /setup.php /sql_upgrade.php /acl_upgrade.php /ippf_upgrade.php /gacl/setup.php
       }
       respond @blocked_scripts 403

       # Prevent PHP/script execution in data/uploads directories (anti-php2shell)
       @blocked_uploads_php {
           path_regexp ^/sites/.*\.(php|php[3-8]|phtml|phar|sh|cgi|pl|py)$
       }
       respond @blocked_uploads_php 403

       # Security Headers
       header {
           X-Content-Type-Options "nosniff"
           X-Frame-Options "SAMEORIGIN"
           Referrer-Policy "strict-origin-when-cross-origin"
           -Server
       }

       reverse_proxy openemr_app:80
   }
   ```

3. **Επικύρωση και επαναφόρτωση του Caddy:**
   ```bash
   docker exec caddy caddy validate --config /etc/caddy/Caddyfile
   docker exec caddy caddy reload --config /etc/caddy/Caddyfile
   ```

---

## 💾 Αυτοματοποιημένο Backup & Kopia

Λόγω της χρήσης bind mounts, το backup καλύπτει τόσο τα αρχεία (`sites/`) όσο και συνεπή SQL dumps της MariaDB (αποφεύγοντας corrupted snapshots εν ώρα λειτουργίας).

### 1. Script Εξαγωγής Βάσης (`db_backup.sh`)

Δημιουργήστε το `/docker/openemr/db_backup.sh`:

```bash
#!/bin/bash
docker exec openemr_db mariadb-dump -u root -p'<MYSQL_ROOT_PASSWORD>' openemr > /docker/openemr/backups/openemr_db.sql
chown 1000:1000 /docker/openemr/backups/openemr_db.sql
```

Δώστε δικαιώματα εκτέλεσης και προσθέστε το στο `crontab -e` (καθημερινά στις 02:00 π.μ.):

```bash
chmod +x /docker/openemr/db_backup.sh
```

```cron
00 02 * * * /docker/openemr/db_backup.sh
```

### 2. Σύνδεση με το Kopia

Στο `docker-compose.yml` του **Kopia**, προσθέστε read-only mount:

```yaml
volumes:
  - /docker/openemr:/data/openemr:ro
```

Στο UI του Kopia δημιουργήστε snapshot για το path `/data/openemr` με χρονοπρογραμματισμό στις `03:00` π.μ. (μετά την ολοκλήρωση του SQL dump).

---

## ✅ Επαλήθευση Λειτουργίας

```bash
# 1. Έλεγχος κατάστασης containers (αναμενόμενο: Up)
docker ps --filter "name=openemr" --format "table {{.Names}}\t{{.Status}}"

# 2. Έλεγχος δικαιωμάτων sqlconf.php (αναμενόμενο: 444 1000:1000)
stat -c "%a %u:%g %n" sites/default/sqlconf.php

# 3. Επαλήθευση αποκλεισμού setup.php και εκτέλεσης PHP στα uploads (αναμενόμενο: HTTP/2 403)
curl -I https://emr.iosifidis.gr/setup.php
curl -I https://emr.iosifidis.gr/sites/default/documents/shell.php

# 4. Δοκιμαστική εκτέλεση SQL backup
/docker/openemr/db_backup.sh && ls -lh /docker/openemr/backups/openemr_db.sql
```

---

## 🔧 Αντιμετώπιση Προβλημάτων (Troubleshooting)

### Ατέρμονη επανεκκίνηση `openemr_app` (`Restarting (255)`)
* **Σύμπτωμα:** `PHP Fatal error: Uncaught Error: Failed opening required '/var/www/localhost/htdocs/openemr/sites/default/sqlconf.php'`
* **Αιτία:** Το bind mount `./sites` στον host είναι κενό, αποκρύπτοντας τη δομή `sites/default/` του container image.
* **Επίλυση:** Διακοπή (`docker compose down`), εξαγωγή αρχείων με προσωρινό container (`cp -rp /var/www/localhost/htdocs/openemr/sites/* /target/`), διόρθωση δικαιωμάτων (`sudo chown -R 1000:1000 sites`) και εκ νέου εκκίνηση.

### Αποτυχία σύνδεσης χρήστη `openemr` κατά τον Web Wizard
* **Σύμπτωμα:** `Reconnecting as new user... ERROR. unable to connect to database as user: 'openemr'`
* **Αιτία:** Στο πεδίο **User Hostname** του wizard παρέμεινε το `localhost` αντί για `%`.
* **Επίλυση:** Διαγραφή της ημιτελούς βάσης/χρήστη και επανεκτέλεση του wizard με **User Hostname** = `%`:
  ```bash
  docker exec openemr_db mariadb -u root -p'<MYSQL_ROOT_PASSWORD>' -e "DROP DATABASE IF EXISTS openemr; DROP USER IF EXISTS 'openemr'@'localhost'; FLUSH PRIVILEGES;"
  ```

---

## 💡 Μαθήματα & Βέλτιστες Πρακτικές

* **Αρχικοποίηση Bind Mounts:** Σε PHP εφαρμογές όπως το OpenEMR, τα bind mounts σε φακέλους εφαρμογής (`sites/`) απαιτούν προ-εξαγωγή των αρχείων στον host πριν την πρώτη εκκίνηση.
* **Απομόνωση Δικτύου & MySQL Hostnames:** Σε multi-container architectures, οι χρήστες βάσης πρέπει να ορίζονται με host `%` και όχι `localhost`.
* **Σειρά Εφαρμογής Hardening:** Οι κανόνες αποκλεισμού του `/setup.php` στον Caddy πρέπει να εφαρμόζονται **αφού** ολοκληρωθεί ο web wizard.
* **Άμυνα σε Βάθος:** Ο συνδυασμός read-only `sqlconf.php` (`444`), αφαίρεσης δικαιωμάτων από τα upgrade scripts και regex αποκλεισμού `.php` στο `/sites/*` εξαλείφει τον κίνδυνο εκτέλεσης ανεβασμένων webshells.

---

## 📚 Παραμετροποίηση & Οδηγίες Χρήσης

* **[Οδηγός Επιχειρησιακής Παραμετροποίησης (Configuration.md)](./Configuration.md):** Ρύθμιση κύριας μονάδας (Facility), ελληνικής τοπικοποίησης, χρηστών & ρόλων ACL, φόρμας SOAP και κατηγοριών ημερολογίου.

### Εγχειρίδιο Χρήσης (Ανά Κεφάλαιο)
1. [Κεφάλαιο 1: Σύνδεση στο Σύστημα & Δημιουργία Νέου Ασθενούς](./kefalaio-1-syndesi-sto-systima-dimioyrghia-neoy-asthenous.md)
2. [Κεφάλαιο 2: Αναζήτηση Καρτέλας Ασθενούς & Ενημέρωση Δημογραφικών Στοιχείων](./kefalaio-2-anazitisi-kartelas-asthenous-enimerosi-dimoghrafikwn-stoikhion.md)
3. [Κεφάλαιο 3: Διαχείριση Ημερολογίου & Εισαγωγή Ραντεβού](./kefalaio-3-diakhirisi-hmerologhioy-eisaghoghi-rantevou-calendar-appointments.md)
4. [Κεφάλαιο 4: Καταγραφή Επίσκεψης & Κλινικής Σημείωσης (Encounter & SOAP)](./kefalaio-4-kataghrafi-episkepsis-klinikis-simiosis-encounter-forma-soap.md)
5. [Κεφάλαιο 5: Ενημέρωση Κλινικής Καρτέλας (Ιατρικά Προβλήματα, Φαρμακευτική Αγωγή, Αλλεργίες)](./kefalaio-5-enimerosi-klinikis-kartelas-iatrika-provlimata-farmakeftiki-aghoghi-allerghies.md)
6. [Κεφάλαιο 6: Ανέβασμα & Προβολή Εξετάσεων / Εγγράφων Ασθενούς](./kefalaio-6-anevasma-provoli-eksetaseon-eghghrafon-asthenous-documents.md)
7. [Κεφάλαιο 7: Εμφάνιση & Εκτύπωση Αναφορών (Ιατρικός Φάκελος & Ερευνητικές Αναφορές)](./kefalaio-7-emfanisi-ektyposi-anaforwn-iatrikos-fakelos-erefnitikes-anafores.md)
