# WordPress (PHP-FPM) Deployment with Docker & Caddy Reverse Proxy

Η παρούσα υλοποίηση παρέχει οδηγίες και αρχεία ρυθμίσεων για τη φιλοξενία του **WordPress (PHP-FPM Alpine)** σε περιβάλλον Docker με **MariaDB** ως βάση δεδομένων και **Caddy** ως Reverse Proxy & Web Server.

---

## 📋 Περιεχόμενα
- [Γιατί WordPress με PHP-FPM & Caddy;](#-γιατί-wordpress-με-php-fpm--caddy)
- [Προαπαιτούμενα](#-προαπαιτούμενα)
- [Δομή Αρχείων](#-δομή-αρχείων)
- [Οδηγίες Εγκατάστασης](#-οδηγίες-εγκατάστασης)
  - [1. Δημιουργία Φακέλων & Δικαιωμάτων](#1-δημιουργία-φακέλων--δικαιωμάτων)
  - [2. Μεταβλητές Περιβάλλοντος (.env)](#2-μεταβλητές-περιβάλλοντος-env)
  - [3. Docker Compose Configuration](#3-docker-compose-configuration)
  - [4. Ρύθμιση Caddyfile](#4-ρύθμιση-caddyfile)
  - [5. Εκκίνηση Stack](#5-εκκίνηση-stack)
- [Επαλήθευση Λειτουργίας](#-επαλήθευση-λειτουργίας)
- [Αντιμετώπιση Προβλημάτων (Troubleshooting)](#-αντιμετώπιση-προβλημάτων-troubleshooting)
- [Μαθήματα & Βέλτιστες Πρακτικές](#-μαθήματα--βέλτιστες-πρακτικές)

---

## 🚀 Γιατί WordPress με PHP-FPM & Caddy;

Η αρχιτεκτονική διαχωρισμού **Caddy (Web Server) + WordPress (PHP-FPM)** πλεονεκτεί έναντι του κλασικού Apache image:
1. **Εξαιρετικά Χαμηλή Κατανάλωση Πόρων:** Το Alpine-based PHP-FPM image ζυγίζει μόλις ~80MB (αντί για ~400MB του Apache) και εκτελεί αποκλειστικά την PHP.
2. **Απευθείας Σερβίρισμα Στατικών Αρχείων:** Ο Caddy αναλαμβάνει να σερβίρει απευθείας τις εικόνες, τα CSS και τα JavaScript αρχεία από το κοινόχρηστο volume `/var/www/html`, χωρίς να επιβαρύνει την PHP!
3. **Αυτόματο HTTPS & HTTP/3:** Ο Caddy διαχειρίζεται αυτόματα τα πιστοποιητικά SSL (Let's Encrypt / ZeroSSL) και παρέχει υποστήριξη HTTP/3.

---

## 🛠 Προαπαιτούμενα

* **Docker** και **Docker Compose** εγκατεστημένα στον διακομιστή (Linux ARM64 / x86_64).
* Εγκατεστημένος **Caddy Server** συνδεδεμένος σε εξωτερικό Docker δίκτυο `web`.
* Ένα **Domain Name** (π.χ. `opensourceai4d.ellak.gr`).
* Δικαιώματα `sudo` / `root`.

---

## 📁 Δομή Αρχείων

```bash
/wordpress
├── Caddyfile              # Ρυθμίσεις Caddy Reverse Proxy & FastCGI
├── docker-compose.yml     # Υπηρεσίες WordPress PHP-FPM & MariaDB
├── .env.example           # Πρότυπο μεταβλητών περιβάλλοντος βάσης
├── Readme.md              # Οδηγός χρήσης & εγκατάστασης
├── wordpress.md           # Αναλυτική τεκμηρίωση αρχιτεκτονικής FPM
├── php-conf/
│   └── uploads.ini        # Προσαρμοσμένα όρια PHP (max_upload_size, memory_limit)
├── wp-data/               # Bind mount αρχείων WordPress (δικαιώματα 33:33)
└── db-data/               # Bind mount βάσης MariaDB
```

---

## ⚙️ Οδηγίες Εγκατάστασης

### 1. Δημιουργία Φακέλων & Δικαιωμάτων

Δημιουργήστε τους φακέλους και δώστε δικαιώματα στον χρήστη `www-data` (UID `33`):

```bash
mkdir -p wp-data db-data php-conf
sudo chown -R 33:33 wp-data
sudo chmod -R 755 wp-data
```

### 2. Μεταβλητές Περιβάλλοντος (.env)

Αντιγράψτε το `.env.example` σε `.env` και συμπληρώστε ισχυρούς κωδικούς:

```bash
cp .env.example .env
```

```env
DB_ROOT_PASSWORD=ασφαλής_κωδικός_root
DB_NAME=wordpress
DB_USER=wp_user
DB_PASSWORD=ασφαλής_κωδικός_χρήστη
```

### 3. Docker Compose Configuration

Το αρχείο `docker-compose.yml`:

```yaml
services:

  db:
    image: mariadb:10.11
    container_name: opensource_db
    restart: unless-stopped
    env_file:
      - .env
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
      MYSQL_DATABASE: ${DB_NAME}
      MYSQL_USER: ${DB_USER}
      MYSQL_PASSWORD: ${DB_PASSWORD}
    volumes:
      - ./db-data:/var/lib/mysql
    networks:
      - internal
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      timeout: 20s
      retries: 10

  wordpress:
    image: wordpress:fpm-alpine
    container_name: opensource_wp_fpm
    restart: unless-stopped
    depends_on:
      db:
        condition: service_healthy
    env_file:
      - .env
    environment:
      WORDPRESS_DB_HOST: db:3306
      WORDPRESS_DB_USER: ${DB_USER}
      WORDPRESS_DB_PASSWORD: ${DB_PASSWORD}
      WORDPRESS_DB_NAME: ${DB_NAME}
      WORDPRESS_CONFIG_EXTRA: |
        $$_SERVER['HTTPS'] = 'on';
    volumes:
      - ./wp-data:/var/www/html
      - ./php-conf/uploads.ini:/usr/local/etc/php/conf.d/uploads.ini
    networks:
      - internal
      - web

networks:
  internal:
    driver: bridge
  web:
    external: true
```

### 4. Ρύθμιση Caddyfile

Προσθέστε στο `Caddyfile` του Caddy server:

```caddyfile
opensourceai4d.ellak.gr {
    root * /var/www/html
    
    encode zstd gzip
    redir /wp-admin /wp-admin/
    
    # FastCGI proxy στο WordPress FPM container στη θύρα 9000
    php_fastcgi opensource_wp_fpm:9000
    
    file_server
}
```

> **⚠️ Κρίσιμο:** Το Caddy container πρέπει να έχει mount τον φάκελο `wp-data` στο `/var/www/html` ώστε να σερβίρει τα στατικά αρχεία.

### 5. Εκκίνηση Stack

```bash
docker compose up -d
docker exec caddy caddy reload
```

---

## ✅ Επαλήθευση Λειτουργίας

1. **Έλεγχος Logs:**
   ```bash
   docker compose logs -f wordpress
   ```
   *Αναμενόμενο μήνυμα:* `ready to handle connections`.

2. **Έλεγχος Browser:**
   Επισκεφθείτε τη διεύθυνση `https://opensourceai4d.ellak.gr/` για να ολοκληρώσετε τον οδηγό αρχικής εγκατάστασης του WordPress.

---

## 🔍 Αντιμετώπιση Προβλημάτων (Troubleshooting)

### 1. Σφάλμα `404 Not Found` στο `/wp-admin`
* **Αιτία:** Απουσία trailing slash στο URL.
* **Λύση:** Βεβαιωθείτε ότι υπάρχει ο κανόνας `redir /wp-admin /wp-admin/` στο `Caddyfile`.

### 2. Infinite Redirect Loops (HTTP -> HTTPS -> HTTP)
* **Αιτία:** Το WordPress πίσω από reverse proxy δεν αναγνωρίζει ότι η σύνδεση είναι HTTPS.
* **Λύση:** Η παράμετρος `$$_SERVER['HTTPS'] = 'on';` στο `WORDPRESS_CONFIG_EXTRA` επιλύει το θέμα.

---

## 💡 Μαθήματα & Βέλτιστες Πρακτικές

* **Διπλό `$`: `$$_SERVER['HTTPS']`:** Στο `docker-compose.yml`, η χρήση διπλού `$$` αποτρέπει το Docker Compose από το να ερμηνεύσει τη μεταβλητή ως περιβάλλοντος του host shell.
* **Δικαιώματα 33:33:** Ο χρήστης `www-data` εντός του FPM container χρειάζεται πρόσβαση εγγραφής στο `./wp-data` για να πραγματοποιούνται αυτόματες ενημερώσεις και uploads.
