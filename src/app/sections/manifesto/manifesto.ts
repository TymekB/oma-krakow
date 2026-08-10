import { Component } from '@angular/core';
import { EditableDirective } from '../../core/editable.directive';
import { RevealDirective } from '../../core/reveal.directive';

@Component({
  selector: 'oma-manifesto',
  imports: [EditableDirective, RevealDirective],
  templateUrl: './manifesto.html',
  styleUrl: './manifesto.scss',
})
export class Manifesto {}
