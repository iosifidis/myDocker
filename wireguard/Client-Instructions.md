# Οδηγός Σύνδεσης Συσκευών στο WireGuard VPN

Αυτός ο οδηγός περιγράφει βήμα προς βήμα τη διαδικασία σύνδεσης των συσκευών σας (κινητά, tablets, υπολογιστές) στο οικιακό WireGuard VPN που εκτελείται στο Banana Pi.

---

## 📱 1. Σύνδεση από Κινητό (Android / iOS)

Η σύνδεση από smartphone είναι η ευκολότερη διαδικασία μέσω σκαναρίσματος **QR Code**.

### Βήματα:
1. **Εγκατάσταση Εφαρμογής:**
   - Κατεβάστε την επίσημη εφαρμογή **WireGuard** από το [Google Play Store](https://play.google.com/store/apps/details?id=com.wireguard.android) (Android) ή το [Apple App Store](https://apps.apple.com/app/wireguard/id1441195209) (iOS).
2. **Εμφάνιση QR Code στο Banana Pi:**
   - Στον διακομιστή (Banana Pi), εκτελέστε την ακόλουθη εντολή στο terminal για το κινητό σας (π.χ. `peer_phone`):
     ```bash
     docker exec -it wireguard qrencode -t ansiutf8 < /config/peer_phone/peer_phone.conf
     ```
3. **Σκανάρισμα QR Code:**
   - Ανοίξτε την εφαρμογή WireGuard στο κινητό.
   - Πατήστε το κουμπί **`+`** (Προσθήκη Tunnel) και επιλέξτε **Create from QR code** / **Σάρωση από κωδικό QR**.
   - Στρέψτε την κάμερα στην οθόνη του terminal και σκανάρετε τον κωδικό.
   - Δώστε ένα όνομα στη σύνδεση (π.χ. `Home-VPN`).
4. **Ενεργοποίηση:**
   - Ενεργοποιήστε τον διακόπτη (toggle) δίπλα από τη σύνδεση.
   - Στην οθόνη του κινητού θα εμφανιστεί το εικονίδιο του VPN 🗝️.

---

## 💻 2. Σύνδεση από Υπολογιστή (Windows / macOS / Linux GUI)

Για υπολογιστές χρησιμοποιούμε το αρχείο ρυθμίσεων `.conf`.

### Βήματα:
1. **Ανάκτηση του αρχείου `.conf`:**
   - Μεταφέρετε με ασφαλή τρόπο το αρχείο `./config/peer_laptop/peer_laptop.conf` από το Banana Pi στον υπολογιστή σας (π.χ. μέσω SCP, SFTP ή τοπικού δικτύου).
2. **Εγκατάσταση Εφαρμογής:**
   - Κατεβάστε την εφαρμογή WireGuard από την επίσημη ιστοσελίδα [wireguard.com/install](https://www.wireguard.com/install/).
3. **Εισαγωγή Ρυθμίσεων (Import):**
   - Ανοίξτε την εφαρμογή WireGuard στον υπολογιστή.
   - Πατήστε **Add Tunnel** (Προσθήκη Tunnel) και επιλέξτε το αρχείο `peer_laptop.conf`.
4. **Σύνδεση:**
   - Πατήστε **Activate** (Ενεργοποίηση).

---

## 🐧 3. Σύνδεση από Linux Terminal (`wg-quick` & NetworkManager)

### Επιλογή Α: Χρήση `wg-quick`
1. Αντιγράψτε το αρχείο `peer_laptop.conf` στο `/etc/wireguard/home-vpn.conf`:
   ```bash
   sudo cp peer_laptop.conf /etc/wireguard/home-vpn.conf
   sudo chmod 600 /etc/wireguard/home-vpn.conf
   ```
2. Εκκίνηση του VPN:
   ```bash
   sudo wg-quick up home-vpn
   ```
3. Διακοπή του VPN:
   ```bash
   sudo wg-quick down home-vpn
   ```

### Επιλογή Β: Χρήση NetworkManager (GNOME / KDE GUI)
```bash
nmcli connection import type wireguard file peer_laptop.conf
nmcli connection up peer_laptop
```

---

## ✅ 4. Επαλήθευση Σύνδεσης

Αφού ενεργοποιήσετε το VPN στη συσκευή σας εκτός σπιτιού (π.χ. κλείστε το Wi-Fi του κινητού και ανάψτε τα δεδομένα 4G/5G):

1. **Έλεγχος Πρόσβασης στο Banana Pi & Τοπικές Υπηρεσίες:**
   - Ανοίξτε έναν browser και επισκεφθείτε την τοπική IP του Banana Pi ή τις υπηρεσίες σας:
     - `http://192.168.1.55:8080` (Gatus)
     - `http://192.168.1.55:80` (AdGuard Home)
     - `http://10.13.13.1` (Εσωτερική IP του WireGuard Server)

2. **Έλεγχος Δημόσιας IP (Full-Tunnel):**
   - Επισκεφθείτε την ιστοσελίδα [ifconfig.me](https://ifconfig.me) ή [whatismyip.com](https://www.whatismyip.com).
   - Η διεύθυνση IP που εμφανίζεται πρέπει να είναι **η δημόσια IP του σπιτιού σας** (και όχι της κινητής τηλεφωνίας).

3. **Έλεγχος Handshake στον Server:**
   - Εκτελέστε στο Banana Pi:
     ```bash
     docker exec -it wireguard wg show
     ```
   - Θα πρέπει να βλέπετε `latest handshake: X seconds ago` και μη μηδενική μεταφορά δεδομένων (`transfer`).

---

## 🔀 5. Αλλαγή μεταξύ Full-Tunnel & Split-Tunnel (Στο Client `.conf`)

Αν θέλετε να αλλάξετε τη συμπεριφορά του VPN στη συσκευή σας, επεξεργαστείτε το πεδίο `AllowedIPs` στο client profile:

* **Full-Tunnel (Όλα μέσω VPN - Προεπιλογή):**
  ```ini
  AllowedIPs = 0.0.0.0/0, ::/0
  ```
  *(Όλη η κίνηση Internet + η πρόσβαση στο σπίτι περνούν από το VPN)*

* **Split-Tunnel (Μόνο το οικιακό δίκτυο μέσω VPN):**
  ```ini
  AllowedIPs = 10.13.13.0/24, 192.168.1.0/24
  ```
  *(Μόνο οι αιτήσεις για το Banana Pi και το οικιακό LAN περνούν από το VPN. Το υπόλοιπο internet περνάει απευθείας από τη συσκευή)*
