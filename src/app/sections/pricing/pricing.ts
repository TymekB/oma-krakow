import { Component } from '@angular/core';
import { EditableDirective } from '../../core/editable.directive';
import { RevealDirective } from '../../core/reveal.directive';
import { PRICE_GROUPS } from '../../core/site.data';

@Component({
  selector: 'oma-pricing',
  imports: [EditableDirective, RevealDirective],
  templateUrl: './pricing.html',
  styleUrl: './pricing.scss',
})
export class Pricing {
  protected readonly groups = PRICE_GROUPS;
}
