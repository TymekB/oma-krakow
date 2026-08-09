import { Component } from '@angular/core';
import { CONTACT, SHOP_URL, TREATMENTS } from '../../core/site.data';

@Component({
  selector: 'oma-footer',
  templateUrl: './footer.html',
  styleUrl: './footer.scss',
})
export class Footer {
  protected readonly contact = CONTACT;
  protected readonly treatments = TREATMENTS;
  protected readonly shopUrl = SHOP_URL;
  protected readonly year = new Date().getFullYear();
}
