import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RevealDirective } from '../../core/reveal.directive';
import { CONTACT, TREATMENTS } from '../../core/site.data';

@Component({
  selector: 'oma-contact',
  imports: [ReactiveFormsModule, RevealDirective],
  templateUrl: './contact.html',
  styleUrl: './contact.scss',
})
export class Contact {
  protected readonly contact = CONTACT;
  protected readonly treatments = TREATMENTS;
  protected readonly sent = signal(false);

  private readonly formBuilder = inject(FormBuilder);

  protected readonly form = this.formBuilder.nonNullable.group({
    name: ['', [Validators.required, Validators.minLength(2)]],
    phone: ['', [Validators.required, Validators.pattern(/^[0-9 +()-]{9,18}$/)]],
    treatment: [this.treatments[0].name, Validators.required],
    message: [''],
  });

  protected submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const { name, phone, treatment, message } = this.form.getRawValue();
    const subject = `Rezerwacja wizyty — ${treatment}`;
    const body = [
      `Imię: ${name}`,
      `Telefon: ${phone}`,
      `Zabieg: ${treatment}`,
      '',
      message || 'Proszę o kontakt w sprawie dostępnych terminów.',
    ].join('\n');

    window.location.href =
      `mailto:${this.contact.email}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;

    this.sent.set(true);
    this.form.reset({ treatment: this.treatments[0].name });
  }

  protected invalid(field: 'name' | 'phone'): boolean {
    const control = this.form.controls[field];
    return control.invalid && control.touched;
  }
}
