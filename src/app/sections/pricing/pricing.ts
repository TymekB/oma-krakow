import { Component } from '@angular/core';
import { RevealDirective } from '../../core/reveal.directive';
import { PRICE_GROUPS } from '../../core/site.data';

@Component({
  selector: 'oma-pricing',
  imports: [RevealDirective],
  templateUrl: './pricing.html',
  styleUrl: './pricing.scss',
})
export class Pricing {
  protected readonly groups = PRICE_GROUPS;
}
