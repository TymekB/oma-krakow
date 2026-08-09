import { Component } from '@angular/core';
import { RevealDirective } from '../../core/reveal.directive';
import { TREATMENTS } from '../../core/site.data';

@Component({
  selector: 'oma-services',
  imports: [RevealDirective],
  templateUrl: './services.html',
  styleUrl: './services.scss',
})
export class Services {
  protected readonly treatments = TREATMENTS;
}
