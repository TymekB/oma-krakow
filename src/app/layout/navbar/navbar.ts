import { Component, HostListener, signal } from '@angular/core';
import { CONTACT, SHOP_URL } from '../../core/site.data';

interface NavLink {
  readonly href: string;
  readonly label: string;
  readonly external?: boolean;
}

@Component({
  selector: 'oma-navbar',
  templateUrl: './navbar.html',
  styleUrl: './navbar.scss',
})
export class Navbar {
  protected readonly contact = CONTACT;
  protected readonly scrolled = signal(false);
  protected readonly menuOpen = signal(false);

  protected readonly links: readonly NavLink[] = [
    { href: '#o-mnie', label: 'O mnie' },
    { href: SHOP_URL, label: 'Sklep', external: true },
    { href: '#gabinet', label: 'Gabinet' },
    { href: '#cennik', label: 'Cennik' },
    { href: '#opinie', label: 'Opinie' },
    { href: '#kontakt', label: 'Kontakt' },
  ];

  @HostListener('window:scroll')
  protected onScroll(): void {
    this.scrolled.set(window.scrollY > 24);
  }

  protected toggleMenu(): void {
    this.menuOpen.update((open) => !open);
  }

  protected closeMenu(): void {
    this.menuOpen.set(false);
  }
}
