import { Component } from '@angular/core';
import { EditableDirective } from '../../core/editable.directive';
import { RevealDirective } from '../../core/reveal.directive';
import { GALLERY } from '../../core/site.data';

@Component({
  selector: 'oma-gallery',
  imports: [EditableDirective, RevealDirective],
  templateUrl: './gallery.html',
  styleUrl: './gallery.scss',
})
export class Gallery {
  protected readonly photos = GALLERY;
}
