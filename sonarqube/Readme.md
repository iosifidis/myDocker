# Οδηγός Εγκατάστασης και Διαχείρισης SonarQube (Docker)

Το παρόν έγγραφο περιγράφει τη διαδικασία εγκατάστασης, παραμετροποίησης και συντήρησης του SonarQube (LTS Community Edition) με χρήση Docker Compose, καθώς και την ενσωμάτωσή του με Caddy Server και την εγκατάσταση πρόσθετων (Plugins).

---

## 1. Προαπαιτούμενα Συστήματος

### Ρύθμιση Elasticsearch (Virtual Memory)
Το SonarQube χρησιμοποιεί Elasticsearch, το οποίο απαιτεί αυξημένο όριο `vm.max_map_count` στον host υπολογιστή. Χωρίς αυτή τη ρύθμιση, το container θα τερματίζεται απροσδόκητα.

*   **Προσωρινή εφαρμογή:**
    ```bash
    sudo sysctl -w vm.max_map_count=262144
    ```
*   **Μόνιμη εφαρμογή:**
    Προσθέστε τη γραμμή `vm.max_map_count=262144` στο αρχείο `/etc/sysctl.conf`.

---

## 2. Δομή Φακέλων και Δικαιώματα

Πριν την εκτέλεση του Docker Compose, είναι απαραίτητη η δημιουργία της δομής των φακέλων και η απόδοση των σωστών δικαιωμάτων χρήστη (Permissions), καθώς το SonarQube και η PostgreSQL τρέχουν με διαφορετικά UIDs.

1.  **Δημιουργία φακέλων:**
    ```bash
    mkdir -p sonar/conf sonar/data sonar/extensions sonar/logs sonar/temp sonar/db_data
    ```

2.  **Ρύθμιση Δικαιωμάτων (Ownership):**
    *   **PostgreSQL:** Τρέχει με UID `999`.
    *   **SonarQube:** Τρέχει με UID `1000`.

    Εκτελέστε τις παρακάτω εντολές:
    ```bash
    # Δικαιώματα για τη Βάση Δεδομένων
    sudo chown -R 999:999 ./sonar/db_data

    # Δικαιώματα για το SonarQube
    sudo chown -R 1000:1000 ./sonar/data ./sonar/extensions ./sonar/logs ./sonar/conf ./sonar/temp
    ```

---

## 3. Ρύθμιση Docker Compose

Ακολουθεί το αρχείο `docker-compose.yml`. 

**Σημαντικές Σημειώσεις:**
*   **Bind Mounts:** Χρησιμοποιούνται τοπικοί φάκελοι (`./sonar/...`) αντί για named volumes για ευκολότερη διαχείριση και backup.
*   **Database Path:** Για την PostgreSQL χρησιμοποιείται αποκλειστικά το `/var/lib/postgresql/data`.

```yaml
#version: "3"

services:
  sonarqube:
    image: sonarqube:lts-community
    depends_on:
      - sonar_db
    environment:
      SONAR_JDBC_URL: jdbc:postgresql://sonar_db:5432/sonar
      SONAR_JDBC_USERNAME: sonar
      SONAR_JDBC_PASSWORD: sonar
    ports:
      - "9001:9000" # Mapping για απευθείας πρόσβαση (debug)
    volumes:
      - ./sonar/conf:/opt/sonarqube/conf
      - ./sonar/data:/opt/sonarqube/data
      - ./sonar/extensions:/opt/sonarqube/extensions
      - ./sonar/logs:/opt/sonarqube/logs
      - ./sonar/temp:/opt/sonarqube/temp

  sonar_db:
    image: postgres:13
    environment:
      POSTGRES_USER: sonar
      POSTGRES_PASSWORD: sonar
      POSTGRES_DB: sonar
    volumes:
      - ./sonar/db_data:/var/lib/postgresql/data
```

### Εκκίνηση Υπηρεσίας
```bash
docker compose up -d
```

*Σημείωση: Την πρώτη φορά η εκκίνηση ενδέχεται να διαρκέσει 1-2 λεπτά για την αρχικοποίηση της βάσης και του Elasticsearch.*

---

## 4. Ενσωμάτωση με Caddy (Reverse Proxy)

Το αρχείο `docker-compose.yml` μετατρέπεται σε:

```yaml
version: "3"

services:
  sonarqube:
    image: sonarqube:lts-community
    depends_on:
      - sonar_db
    environment:
      SONAR_JDBC_URL: jdbc:postgresql://sonar_db:5432/sonar
      SONAR_JDBC_USERNAME: sonar
      SONAR_JDBC_PASSWORD: sonar
      # Προαιρετικό: Base URL για σωστά links στα emails/reports
      SONAR_SERVER_BASEURL: https://sonar.iosifidis.gr
    ports:
      - "9001:9000" # Mapping για απευθείας πρόσβαση (debug) ή μέσω Caddy
    volumes:
      - ./sonar/conf:/opt/sonarqube/conf
      - ./sonar/data:/opt/sonarqube/data
      - ./sonar/extensions:/opt/sonarqube/extensions
      - ./sonar/logs:/opt/sonarqube/logs
      - ./sonar/temp:/opt/sonarqube/temp
    networks:
      - web            # Επικοινωνία με Reverse Proxy (Caddy)
      - sonar-internal # Επικοινωνία με Database

  sonar_db:
    image: postgres:13
    environment:
      POSTGRES_USER: sonar
      POSTGRES_PASSWORD: sonar
      POSTGRES_DB: sonar
    volumes:
      - ./sonar/db_data:/var/lib/postgresql/data
    networks:
      - sonar-internal

networks:
  web:
    external: true
  sonar-internal:
    driver: bridge
```

Για την εξυπηρέτηση του SonarQube μέσω domain με SSL, προσθέστε τα παρακάτω στο `Caddyfile`:

```caddyfile
sonar.iosifidis.gr {
    reverse_proxy sonarqube:9000
}
```
*Το Caddy επικοινωνεί απευθείας με το container `sonarqube` μέσω του Docker network `web` στην εσωτερική θύρα 9000.*

---

## 5. Εγκατάσταση Plugin: CNES Report (PDF Export)

Για την εξαγωγή αναφορών σε PDF στην έκδοση **SonarQube LTS (9.9)**, απαιτείται συγκεκριμένη έκδοση του plugin.

1.  **Λήψη Plugin (Έκδοση 4.1.3):**
    ```bash
    wget https://github.com/cnescatlab/sonar-cnes-report/releases/download/4.1.3/sonar-cnes-report-4.1.3.jar
    ```
    *Προσοχή: Νεότερες εκδόσεις (π.χ. 4.2.x) ενδέχεται να μην είναι συμβατές με την LTS 9.9.*

2.  **Εγκατάσταση:**
    Μετακινήστε το αρχείο `.jar` στον φάκελο:
    `./sonar/extensions/plugins/`

3.  **Επανεκκίνηση:**
    Απαιτείται επανεκκίνηση των containers με συγκεκριμένη σειρά:
    ```bash
    # Restart DB
    docker restart sonar-sonar_db-1
    # Αναμονή 10 δευτερολέπτων...
    
    # Restart SonarQube
    docker restart sonar-sonarqube-1
    ```

4.  **Χρήση:**
    Στο περιβάλλον του SonarQube: `Project` > `More` > `CNES Report` > `Generate`.

### Λύση 2: Εκτύπωση σε PDF μέσω Browser (Η πιο γρήγορη λύση)
Αν θέλεις απλά μια οπτική απεικόνιση του Dashboard για να τη στείλεις κάπου γρήγορα:

1.  Άνοιξε το **Overview** του project στο SonarQube.
2.  Πάτησε `Ctrl + P` (ή Command + P σε Mac).
3.  Επίλεξε ως εκτυπωτή το **"Save as PDF"** (Αποθήκευση ως PDF).
4.  Ρύθμισε το layout (συνήθως το Landscape/Οριζόντιο βολεύει καλύτερα) και αποθήκευσέ το.

*Σημείωση:* Δεν είναι επαγγελματικό report, αλλά περιέχει τα γραφήματα και τα νούμερα της πρώτης σελίδας.

### Λύση 3: Χρήση του Web API (Για προχωρημένους)
Αν έχεις γνώσεις προγραμματισμού (Python, Bash, κλπ), μπορείς να τραβήξεις τα δεδομένα και να φτιάξεις το δικό σου report.
*   Το SonarQube έχει πλήρες API.
*   Μπορείς να καλέσεις το endpoint: `api/measures/component?component=MY_PROJECT_KEY&metricKeys=bugs,vulnerabilities,code_smells,coverage`
*   Με τα δεδομένα JSON που θα λάβεις, μπορείς να δημιουργήσεις ένα custom PDF.

---

## 6. Διαχείριση & Troubleshooting

*   **Πρόσβαση:** `http://localhost:9001` (ή μέσω του domain που ορίσατε στο Caddy).
*   **Default Credentials:** User: `admin`, Password: `admin` (Απαιτείται αλλαγή στην πρώτη είσοδο).
*   **Logs:** Σε περίπτωση προβλήματος εκκίνησης, ελέγξτε τα logs:
    ```bash
    docker compose logs -f sonarqube
    ```
    Αν δεις σφάλμα σχετικά με το **Elasticsearch** ή **max virtual memory areas**, τότε πρέπει οπωσδήποτε να τρέξεις στον host server την εντολή:
    `sudo sysctl -w vm.max_map_count=262144`
*   **Backup & Μεταφορά σε νέο Server:**
    1.  `docker compose down`
    2.  Συμπίεση όλου του φακέλου (περιλαμβάνει το `docker-compose.yml` και τον υποφάκελο `sonar`):
        ```bash
        tar -czvf sonarqube_backup.tar.gz .
        ```
    3.  Μεταφορά στον νέο server, αποσυμπίεση και εκτέλεση `docker-compose up -d`.
    
    **Προσοχή:** Αν έχεις ήδη δεδομένα στα παλιά Named Volumes, η αλλαγή αυτή **δεν** θα τα μεταφέρει αυτόματα. Θα ξεκινήσει "καθαρό" setup. Αν θέλεις να μεταφέρεις τα υπάρχοντα δεδομένα σου στους νέους φακέλους, θα χρειαστεί μια διαδικασία `docker cp` πριν την αλλαγή.
