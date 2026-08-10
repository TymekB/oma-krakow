# Logotypy metod płatności

Pliki nazywamy kodem metody płatności z Syliusa, np. `stripe.svg`, `bank_transfer.svg`.
Szablon stopki sam podmienia nazwę metody na obrazek, gdy plik tu istnieje.

Osobny przypadek to marki obsługiwane *wewnątrz* bramki — nie mają własnej metody w Syliusie,
a i tak należą się im logotypy w stopce. Lista jest w szablonie stopki (`gateway_brands`):
przy włączonym PayU dokładamy `blik.svg`.

Co już jest:

- `payu.svg` — `PAYU_dark_green.svg` z poland.payu.com, bez zmian
- `blik.svg` — `blik-logo` z blik.com; usunięty tylko rastrowy `<image>` (base64) z miękkim
  cieniem pod wordmarkiem, przez który plik ważył 53 kB zamiast 2,5 kB. Sam znak (badge,
  wordmark, kropka) jest nietknięty i w całości wektorowy

Assety pobieraj wyłącznie z oficjalnych brand kitów operatora — nie odtwarzaj logotypów ręcznie:

- Przelewy24 — panel partnera / kontakt z dzialem marketingu (materialy dla akceptantow)
- PayU — https://poland.payu.com (sekcja dla partnerow)
- paynow — panel mBanku dla akceptantow
- Apple Pay / Google Pay — oficjalne wytyczne marki; marki wolno uzyc TYLKO wtedy,
  gdy sklep faktycznie obsluguje dana metode
- Stripe — dla metod wlaczonych na koncie udostepnia gotowe assety w dokumentacji
