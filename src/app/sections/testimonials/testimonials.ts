import { Component } from '@angular/core';
import { RevealDirective } from '../../core/reveal.directive';
import { TESTIMONIALS } from '../../core/site.data';

@Component({
  selector: 'oma-testimonials',
  imports: [RevealDirective],
  templateUrl: './testimonials.html',
  styleUrl: './testimonials.scss',
})
export class Testimonials {
  protected readonly testimonials = TESTIMONIALS;
}
