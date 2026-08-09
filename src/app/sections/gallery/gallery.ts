import { Component } from '@angular/core';
import { RevealDirective } from '../../core/reveal.directive';
import { GALLERY } from '../../core/site.data';

@Component({
  selector: 'oma-gallery',
  imports: [RevealDirective],
  templateUrl: './gallery.html',
  styleUrl: './gallery.scss',
})
export class Gallery {
  protected readonly photos = GALLERY;
}
