# OMA — fizjoterapia i terapia twarzy, Kraków

Strona wizytówka gabinetu OMA (Natalia Podgórska): fizjoterapia, ZOGA Face, ERT Marii Margo, masaż.
Angular 22, standalone components, SCSS, bez zewnętrznych bibliotek UI.

## Uruchomienie

Wymagany Node 20.19+ / 22.12+ / 24+ (lokalnie użyto `/opt/homebrew/bin/node`, v23.7).

```bash
npm install
npm start          # http://localhost:4200
npm run build      # dist/oma
npm test
```

## Struktura

```
src/app/
  core/          reveal.directive.ts (animacje scroll), site.data.ts (treści i cennik)
  layout/        navbar, footer
  sections/      hero, about, services, manifesto, gallery, pricing, testimonials, contact
  pages/home/    kompozycja sekcji (lazy-loaded route)
public/assets/img/  zdjęcia gabinetu (zoptymalizowane JPG)
```

Cała treść tekstowa, cennik i dane kontaktowe są w jednym pliku: `src/app/core/site.data.ts`.

## Design system

Zmienne w `src/styles.scss`:

- `--wine-900/800/700/600` — bordo z logotypu OMA (navbar, akcenty, CTA)
- `--cream`, `--sand-100/200/300` — ciepłe beże ze zdjęć gabinetu
- `--font-display` Cormorant Garamond (nagłówki), `--font-body` Jost (treść)
- motyw łuku (`--radius-arch`) nawiązuje do lustra i wnęk z gabinetu

## Do uzupełnienia przed publikacją

Dane w `site.data.ts` są przykładowe i wymagają potwierdzenia:

- adres, telefon, e-mail, godziny otwarcia (obecnie placeholdery `00/0`, `+48 000 000 000`)
- ceny i czasy trwania zabiegów
- opinie klientek (obecnie treści przykładowe)
- opisy ZOGA Face i ERT Marii Margo — warto zweryfikować z autorką metody

Formularz kontaktowy nie ma backendu — składa wiadomość i otwiera klienta pocztowego (`mailto:`).
Docelowo warto podpiąć system rezerwacji (np. Booksy) lub endpoint wysyłający maila.
