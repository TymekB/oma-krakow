import { Component } from '@angular/core';
import { RevealDirective } from '../../core/reveal.directive';
import { TREATMENTS } from '../../core/site.data';

@Component({
  selector: 'oma-hero',
  imports: [RevealDirective],
  templateUrl: './hero.html',
  styleUrl: './hero.scss',
})
export class Hero {
  protected readonly treatments = TREATMENTS;
}
