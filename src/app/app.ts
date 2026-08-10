import { Component, effect, inject } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { LandingContentService } from './core/landing-content.service';
import { Footer } from './layout/footer/footer';
import { Navbar } from './layout/navbar/navbar';

@Component({
  selector: 'app-root',
  imports: [RouterOutlet, Navbar, Footer],
  templateUrl: './app.html',
  styleUrl: './app.scss',
})
export class App {
  private readonly content = inject(LandingContentService);

  private guardsInstalled = false;

  constructor() {
    void this.content.load();

    effect(() => {
      if (this.content.canEdit()) {
        this.freezeNavigation();
      }
    });
  }

  private freezeNavigation(): void {
    if (this.guardsInstalled) {
      return;
    }

    this.guardsInstalled = true;
    document.body.classList.add('edit-mode');

    document.addEventListener(
      'click',
      (event) => {
        const target = event.target as HTMLElement | null;

        if (target?.closest('a[href]')) {
          event.preventDefault();
        }
      },
      true,
    );

    document.addEventListener('submit', (event) => event.preventDefault(), true);
  }
}
