; <?php exit; ?> DO NOT REMOVE THIS LINE
; Αυτό είναι ένα βασικό config.

[main]
; Τίτλος του site
name = "My PrivateBin"
basepath = "https://privatebin.duckdns.org/"

; Επιτρέπονται συζητήσεις; (true/false)
discussion = true

; Ανοιχτό για όλους; (true/false)
opendiscussion = false

; Ενεργοποίηση password;
password = true

; Χρόνος ζωής (περιόδοι διατήρησης)
[expire]
default = "1week"

[expire_options]
5min = 300
10min = 600
1hour = 3600
1day = 86400
1week = 604800
1month = 2592000
1year = 31536000
never = 0

[traffic]
; Όριο στα 10 δευτερόλεπτα μεταξύ posts
limit = 10
