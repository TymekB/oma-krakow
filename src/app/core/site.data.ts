export interface Treatment {
  readonly slug: string;
  readonly index: string;
  readonly name: string;
  readonly summary: string;
  readonly description: string;
  readonly highlights: readonly string[];
  readonly duration: string;
  readonly price: string;
  readonly image: string;
}

export interface PriceGroup {
  readonly name: string;
  readonly items: readonly PriceItem[];
}

export interface PriceItem {
  readonly name: string;
  readonly duration: string;
  readonly price: string;
}

export interface Value {
  readonly title: string;
  readonly text: string;
}

export interface Testimonial {
  readonly quote: string;
  readonly author: string;
  readonly context: string;
}

export interface GalleryPhoto {
  readonly src: string;
  readonly alt: string;
  readonly span: 'tall' | 'wide' | 'square';
}

export const SHOP_URL = '/sklep';

export const CONTACT = {
  studio: 'OMA — fizjoterapia i terapia twarzy',
  owner: 'Natalia Podgórska',
  role: 'Fizjoterapeutka, twórczyni OMA',
  street: 'ul. Karmelicka 00/0',
  city: '31-000 Kraków',
  phone: '+48 000 000 000',
  phoneHref: 'tel:+48000000000',
  email: 'kontakt@oma-fizjo.pl',
  instagram: 'https://www.instagram.com/oma.fizjo.krakow/',
  instagramHandle: '@oma.fizjo.krakow',
  hours: [
    { day: 'Poniedziałek — Czwartek', value: '9:00 — 20:00' },
    { day: 'Piątek', value: '9:00 — 17:00' },
    { day: 'Sobota', value: 'wizyty po ustaleniu' },
    { day: 'Niedziela', value: 'nieczynne' },
  ],
} as const;

export const TREATMENTS: readonly Treatment[] = [
  {
    slug: 'fizjoterapia',
    index: '01',
    name: 'Fizjoterapia',
    summary: 'Terapia manualna i praca z ciałem prowadzona indywidualnie, od diagnozy po plan ćwiczeń.',
    description:
      'Spotkanie zaczynamy od rozmowy i badania — sprawdzamy, skąd bierze się ból i jak pracuje Twoje ciało na co dzień. ' +
      'Dalej terapia manualna, praca z powięzią i tkankami głębokimi, a na koniec zestaw ćwiczeń, które zabierasz ze sobą do domu.',
    highlights: ['Badanie funkcjonalne', 'Terapia manualna', 'Plan ćwiczeń na dom'],
    duration: '60 min',
    price: 'od 200 zł',
    image: 'assets/img/gabinet-1.webp',
  },
  {
    slug: 'zoga-face',
    index: '02',
    name: 'ZOGA Face',
    summary: 'Praca z powięzią twarzy, szyi i żuchwy — naturalne rozluźnienie napięć, które widać i czuć.',
    description:
      'ZOGA Face to terapia powięziowa twarzy wyrastająca z metody ZOGA Movement. Rozluźniamy napięcia żuchwy, karku i mięśni mimicznych, ' +
      'poprawiamy krążenie i drenaż. Efekt to wypoczęty rysunek twarzy, lżejsza żuchwa i spokojniejszy oddech.',
    highlights: ['Napięcia żuchwy i bruksizm', 'Drenaż i mikrokrążenie', 'Rozluźnienie karku'],
    duration: '60 min',
    price: 'od 220 zł',
    image: 'assets/img/gabinet-2.webp',
  },
  {
    slug: 'ert-marii-margo',
    index: '03',
    name: 'ERT Marii Margo',
    summary: 'Autorska metoda pracy z twarzą — głęboka, precyzyjna terapia tkanek i naturalny lifting.',
    description:
      'ERT to autorska metoda Marii Margo łącząca pracę wewnątrz jamy ustnej z terapią mięśni twarzy, szyi i dekoltu. ' +
      'Terapia prowadzona jest sekwencyjnie — najlepsze efekty daje seria zabiegów rozłożona w czasie.',
    highlights: ['Praca wewnątrzustna', 'Owal twarzy i dekolt', 'Zalecana seria zabiegów'],
    duration: '75 min',
    price: 'od 260 zł',
    image: 'assets/img/detale.webp',
  },
  {
    slug: 'masaz',
    index: '04',
    name: 'Masaż',
    summary: 'Relaksacyjny, leczniczy lub tkanek głębokich — dobierany do tego, czego potrzebujesz danego dnia.',
    description:
      'Masaż w OMA to nie gotowy protokół. Pracujemy na olejach naturalnych, w tempie dopasowanym do Twojego układu nerwowego — ' +
      'od głębokiej pracy na napiętych plecach po wyciszający masaż całego ciała.',
    highlights: ['Relaksacyjny', 'Leczniczy', 'Tkanek głębokich'],
    duration: '60 / 90 min',
    price: 'od 180 zł',
    image: 'assets/img/olejki.webp',
  },
];

export const VALUES: readonly Value[] = [
  {
    title: 'Jedna osoba, cała uwaga',
    text: 'Nie prowadzę kilku wizyt naraz. Ten czas jest wyłącznie Twój — bez pośpiechu i patrzenia na zegar.',
  },
  {
    title: 'Ciało jako całość',
    text: 'Napięcie żuchwy potrafi mieć źródło w stopie. Dlatego patrzę szerzej niż na miejsce, które boli.',
  },
  {
    title: 'Kameralne miejsce',
    text: 'Gabinet w krakowskiej kamienicy — parkiet, ciepłe światło, cisza. Miejsce, w którym łatwo odpuścić.',
  },
  {
    title: 'Efekt, który zostaje',
    text: 'Terapia to połowa drogi. Druga to ćwiczenia i nawyki, które dostajesz ode mnie na wynos.',
  },
];

export const PRICE_GROUPS: readonly PriceGroup[] = [
  {
    name: 'Fizjoterapia',
    items: [
      { name: 'Konsultacja fizjoterapeutyczna z terapią', duration: '60 min', price: '200 zł' },
      { name: 'Wizyta terapeutyczna', duration: '50 min', price: '180 zł' },
      { name: 'Terapia tkanek głębokich', duration: '60 min', price: '220 zł' },
    ],
  },
  {
    name: 'Twarz',
    items: [
      { name: 'ZOGA Face — terapia powięziowa twarzy', duration: '60 min', price: '220 zł' },
      { name: 'ERT Marii Margo — zabieg pojedynczy', duration: '75 min', price: '260 zł' },
      { name: 'ERT Marii Margo — pakiet 4 zabiegów', duration: '4 × 75 min', price: '960 zł' },
    ],
  },
  {
    name: 'Masaż',
    items: [
      { name: 'Masaż relaksacyjny', duration: '60 min', price: '180 zł' },
      { name: 'Masaż relaksacyjny', duration: '90 min', price: '250 zł' },
      { name: 'Masaż leczniczy', duration: '60 min', price: '200 zł' },
    ],
  },
];

export const TESTIMONIALS: readonly Testimonial[] = [
  {
    quote:
      'Przyszłam z bólem karku, który ciągnął się miesiącami. Wyszłam z konkretnym planem i po raz pierwszy od dawna spałam całą noc.',
    author: 'Kasia',
    context: 'fizjoterapia',
  },
  {
    quote:
      'ZOGA Face to była dla mnie zagadka, a okazało się, że najbardziej odpuściła mi żuchwa. Twarz wygląda po prostu na wypoczętą.',
    author: 'Magda',
    context: 'ZOGA Face',
  },
  {
    quote:
      'Gabinet jest kameralny i cichy, a Natalia tłumaczy każdy ruch. Czuć, że to miejsce zrobione z uważnością.',
    author: 'Ola',
    context: 'ERT Marii Margo',
  },
];

export const GALLERY: readonly GalleryPhoto[] = [
  { src: 'assets/img/gabinet-1.webp', alt: 'Gabinet OMA — leżanka do terapii przy oknie', span: 'tall' },
  { src: 'assets/img/gabinet-3.webp', alt: 'Kącik z lustrem i kosmetykami w gabinecie OMA', span: 'square' },
  { src: 'assets/img/olejki.webp', alt: 'Olejki eteryczne i olejki do ciała używane podczas masażu', span: 'square' },
  { src: 'assets/img/gabinet-2.webp', alt: 'Strefa umywalki z bukietem kwiatów w gabinecie OMA', span: 'tall' },
  { src: 'assets/img/detale.webp', alt: 'Detale gabinetu — kwiaty, palo santo i karta z ćwiczeniem', span: 'wide' },
];
