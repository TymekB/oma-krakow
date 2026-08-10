import { Directive, ElementRef, OnInit, effect, inject, input } from '@angular/core';

import { LandingContentService } from './landing-content.service';

@Directive({
  selector: '[omaEditable]',
})
export class EditableDirective implements OnInit {
  readonly key = input.required<string>({ alias: 'omaEditable' });

  private readonly host = inject(ElementRef<HTMLElement>);
  private readonly content = inject(LandingContentService);

  private original = '';
  private armed = false;
  private lastApplied?: string;

  constructor() {
    effect(() => {
      const stored = this.content.value(this.key());

      const holdsCaret = this.element.contains(document.activeElement);

      if (stored !== undefined && stored !== this.lastApplied && !holdsCaret) {
        this.lastApplied = stored;
        this.element.textContent = stored;
      }

      this.applyEditMode(this.content.canEdit());
    });
  }

  ngOnInit(): void {
    this.original = this.element.textContent?.trim() ?? '';
    void this.content.load();
  }

  private get element(): HTMLElement {
    return this.host.nativeElement as HTMLElement;
  }

  private applyEditMode(enabled: boolean): void {
    this.element.classList.toggle('editable', enabled);
    this.element.contentEditable = enabled ? 'true' : 'inherit';

    if (!enabled || this.armed) {
      return;
    }

    this.armed = true;
    this.element.title = 'Kliknij, aby edytować';
    this.element.spellcheck = false;

    this.element.addEventListener('blur', () => this.commit());
    this.element.addEventListener('keydown', (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        this.element.textContent = this.currentValue();
        this.element.blur();
      }

      if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        this.element.blur();
      }
    });
  }

  private commit(): void {
    const text = this.element.textContent?.trim() ?? '';

    if (text === '' || text === this.currentValue()) {
      this.element.textContent = this.currentValue();

      return;
    }

    this.lastApplied = text;
    this.content.stage(this.key(), text);
  }

  private currentValue(): string {
    return this.content.value(this.key()) ?? this.original;
  }
}
