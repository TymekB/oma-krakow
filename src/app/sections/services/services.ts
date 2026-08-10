import { Component } from '@angular/core';
import { EditableDirective } from '../../core/editable.directive';
import { RevealDirective } from '../../core/reveal.directive';
import { TREATMENTS } from '../../core/site.data';

@Component({
  selector: 'oma-services',
  imports: [EditableDirective, RevealDirective],
  templateUrl: './services.html',
  styleUrl: './services.scss',
})
export class Services {
  protected readonly treatments = TREATMENTS;
}
