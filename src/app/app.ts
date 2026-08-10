import { Component, inject } from '@angular/core';
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

  constructor() {
    void this.content.load();
  }
}
