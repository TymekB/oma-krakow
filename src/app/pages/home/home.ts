import { Component } from '@angular/core';
import { About } from '../../sections/about/about';
import { Contact } from '../../sections/contact/contact';
import { Gallery } from '../../sections/gallery/gallery';
import { Hero } from '../../sections/hero/hero';
import { Manifesto } from '../../sections/manifesto/manifesto';
import { Pricing } from '../../sections/pricing/pricing';
import { Services } from '../../sections/services/services';
import { Testimonials } from '../../sections/testimonials/testimonials';

@Component({
  selector: 'oma-home',
  imports: [Hero, About, Services, Manifesto, Gallery, Pricing, Testimonials, Contact],
  template: `
    <oma-hero />
    <oma-about />
    <oma-services />
    <oma-manifesto />
    <oma-gallery />
    <oma-pricing />
    <oma-testimonials />
    <oma-contact />
  `,
})
export class Home {}
