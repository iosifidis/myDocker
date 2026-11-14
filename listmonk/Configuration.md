# Τεκμηρίωση Ρύθμισης Listmonk με Postfix SMTP Submission

Ο παρών οδηγός περιγράφει τις απαιτούμενες ρυθμίσεις στο περιβάλλον του **Listmonk** και στον **Postfix SMTP Server** (`mail.iosifidis.gr`), καθώς και τα κρίσιμα σημεία ελέγχου για την αποφυγή απορρίψεων από μεγάλους παρόχους (Gmail, Outlook κ.ά.).

---

## 1. Ρυθμίσεις Listmonk UI

### Α. Settings -> SMTP
Αυτές οι ρυθμίσεις ορίζουν τον τρόπο με τον οποίο το Listmonk συνδέεται ως client στην υπηρεσία submission του mail server.

| Πεδίο | Τιμή | Σημειώσεις / Pitfalls |
| :--- | :--- | :--- |
| **Enabled** | `Toggle ON` | Ενεργοποιεί τη χρήση του συγκεκριμένου SMTP server. |
| **Host** | `mail.iosifidis.gr` | Το πλήρες hostname (FQDN) του Postfix server. |
| **Port** | `587` | **Προσοχή:** Μην αφήνετε το πεδίο κενό. Για STARTTLS χρησιμοποιείται η πόρτα 587 (submission). |
| **Auth protocol** | `PLAIN` | Συμβατό με το SASL PLAIN μηχανισμό του Postfix (`sasl_method=PLAIN`). |
| **Username** | `eiosifidis@iosifidis.gr` | Ο πλήρης λογαριασμός SASL authentication. |
| **Password** | `********` | Ο κωδικός πρόσβασης του χρήστη. |
| **HELO hostname** | `mail.iosifidis.gr` | **Κρίσιμο:** Μην το αφήνετε κενό. Αν μείνει κενό, στέλνει `localhost`, προκαλώντας spam scoring ή rejection από το Postfix (`reject_non_fqdn_helo_hostname`). |
| **TLS** | `STARTTLS` | Αναβαθμίζει την plain σύνδεση σε TLS μετά το EHLO. |
| **Skip TLS verification**| `Off` | Αφήστε το ανενεργό εφόσον ο server διαθέτει έγκυρο πιστοποιητικό Let's Encrypt / CA. |
| **Max connections** | `10` | Μέγιστος αριθμός ταυτόχρονων συνδέσεων προς τον SMTP. |
| **Idle timeout** | `15s` | Χρόνος αναμονής αδράνειας πριν κλείσει το socket. |
| **Wait timeout** | `5s` | Χρόνος αναμονής για απόκτηση σύνδεσης από το pool. |
| **Retries** | `2` | Αριθμός επαναπροσπαθειών σε περίπτωση αποτυχίας. |
| **Retry delay** | `10ms` | Χρόνος καθυστέρησης πριν την επανάληψη. |

> **Σημαντικό:** Μετά από κάθε αλλαγή στις ρυθμίσεις, βεβαιωθείτε ότι πατάτε το κουμπί **Save** πάνω δεξιά και ότι εμφανίζεται μήνυμα επιτυχούς αποθήκευσης.

---

### Β. Settings -> General
Καθορίζει τις γενικές παραμέτρους της πλατφόρμας και τη default διεύθυνση αποστολέα.

* **Site name:** `Librehub`
* **Root URL:** `http://librehub.duckdns.org` (ή το HTTPS domain παραγωγής)
* **Default 'from' email:** `Librehub <noreply@iosifidis.gr>` ή `Librehub <eiosifidis@iosifidis.gr>`
  * **Κανόνας:** Πρέπει να είναι διεύθυνση του domain του mail server (`@iosifidis.gr`) και **ποτέ** εξωτερικό domain (π.χ. `@gmail.com`).

---

### Γ. Campaign Settings (Ρυθμίσεις Καμπάνιας)

Κατά τη δημιουργία ή επεξεργασία καμπάνιας:

1. **From address:**
   * **Σωστή μορφή:** `Librehub <eiosifidis@iosifidis.gr>`
   * **Λάθος μορφή 1:** `Efstathios` (Χωρίς email και domain -> Απορρίπτεται άμεσα ως μη RFC 5322 compliant).
   * **Λάθος μορφή 2:** `Librehub <eiosifidis@gmail.com>` (Απορρίπτεται από το DMARC policy της Google `p=reject`, καθώς ο `mail.iosifidis.gr` δεν επιτρέπεται να στέλνει εκ μέρους του Gmail).
2. **Send Test Message:**
   * Για να σταλεί test email σε μια διεύθυνση, η διεύθυνση αυτή **πρέπει να υπάρχει ήδη ως συνδρομητής** στη βάση του Listmonk (Menu *Subscribers* -> Status: *Enabled* και *Confirmed*).

---

## 2. Απαιτήσεις Postfix & DNS (Deliverability)

Για να γίνονται δεκτά τα emails από το Gmail και άλλους μεγάλους παρόχους:

### Α. RFC 5322 Headers
Τα headers του email πρέπει να περιλαμβάνουν πλήρη στοιχεία αποστολέα:
```text
From: Display Name <user@yourdomain.gr>
```
Αν λείπει το domain ή η μορφή δεν είναι έγκυρη, ο Postfix και οι εξωτερικοί MX επιστρέφουν:
`550-5.7.1 message is not RFC 5322 compliant: 'From' header has non compliant domain name.`

### Β. Ευθυγράμμιση SPF, DKIM, DMARC
1. **SPF (TXT Record στο iosifidis.gr):**
   Πρέπει να περιλαμβάνει την IPv4 (`83.212.169.58`) και IPv6 του `mail.iosifidis.gr`:
   ```text
   v=spf1 ip4:83.212.169.58 ip6:2001:648:2ffc:1015:a800:ff:fee8:74da -all
   ```
2. **DKIM:**
   Το OpenDKIM υπογράφει το email βάσει του domain στο header `From:`. Αν το header είναι ανορθόγραφο, το OpenDKIM αποτυγχάνει (`can't parse From: header value`) και το email φεύγει ανυπόγραφο.
3. **DMARC:**
   Απαιτείται συνέπεια μεταξύ του `From:` header και του SPF/DKIM domain.

---

## 3. Χρήσιμες Εντολές Ελέγχου & Παρακολούθησης

### Live παρακολούθηση logs στον Mail Server (`mail.iosifidis.gr`)
```bash
# Παρακολούθηση συναλλαγών submission σε πραγματικό χρόνο
tail -f /var/log/mail.log | grep --line-buffered -E "submission|postfix/smtp"

# Έλεγχος της κατάστασης της ουράς (Queue)
mailq

# Έλεγχος συγκεκριμένου Message ID
grep "QUEUE_ID" /var/log/mail.log
```

### Έλεγχος από το Container του Listmonk
```bash
# Έλεγχος logs εφαρμογής
docker logs -f --tail 50 listmonk-app

# Έλεγχος συνδεσιμότητας προς τον mail server στην πόρτα 587
docker exec listmonk-app nc -zv -w 5 mail.iosifidis.gr 587
```

---

## 4. Troubleshooting Checklist

- [x] Το πεδίο **Port** στο SMTP είναι ρητά ορισμένο σε `587`.
- [x] Το **HELO hostname** έχει FQDN τιμή (`mail.iosifidis.gr`).
- [x] Το **Auth Protocol** είναι `PLAIN` και τα credentials σωστά.
- [x] Το **From address** ανήκει στο domain εξουσιοδότησης (`@iosifidis.gr`).
- [x] Ο παραλήπτης του test email υπάρχει ως **Confirmed Subscriber** στο Listmonk.
- [x] Οι αλλαγές έχουν αποθηκευτεί (κουμπί **Save**).
