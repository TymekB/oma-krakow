import { Component } from '@angular/core';
import { RevealDirective } from '../../core/reveal.directive';

@Component({
  selector: 'oma-manifesto',
  imports: [RevealDirective],
  templateUrl: './manifesto.html',
  styleUrl: './manifesto.scss',
})
export class Manifesto {}
