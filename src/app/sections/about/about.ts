import { Component } from '@angular/core';
import { RevealDirective } from '../../core/reveal.directive';
import { CONTACT, VALUES } from '../../core/site.data';

@Component({
  selector: 'oma-about',
  imports: [RevealDirective],
  templateUrl: './about.html',
  styleUrl: './about.scss',
})
export class About {
  protected readonly contact = CONTACT;
  protected readonly values = VALUES;
}
