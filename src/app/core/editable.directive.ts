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
  private editing = false;
  private armed = false;

  constructor() {
    effect(() => {
      const stored = this.content.value(this.key());

      if (!this.editing && stored !== undefined && stored !== this.element.textContent?.trim()) {
        this.element.textContent = stored;
      }

      this.toggleEditMode(this.content.canEdit());
    });
  }

  ngOnInit(): void {
    this.original = this.element.textContent?.trim() ?? '';
    void this.content.load();
  }

  private get element(): HTMLElement {
    return this.host.nativeElement as HTMLElement;
  }

  private toggleEditMode(enabled: boolean): void {
    this.element.classList.toggle('editable', enabled);

    if (!enabled || this.armed) {
      return;
    }

    this.armed = true;
    this.element.title = 'Kliknij, aby edytować';
    this.element.addEventListener('click', (event) => {
      if (!this.content.canEdit()) {
        return;
      }

      event.preventDefault();
      event.stopPropagation();
      this.startEditing();
    });
  }

  private startEditing(): void {
    if (this.editing) {
      return;
    }

    this.editing = true;
    this.element.contentEditable = 'true';
    this.element.classList.add('editable--active');
    this.element.focus();
    this.selectAll();

    const finish = (save: boolean) => {
      this.element.contentEditable = 'false';
      this.element.classList.remove('editable--active');
      this.element.removeEventListener('blur', onBlur);
      this.element.removeEventListener('keydown', onKeydown);

      const text = this.element.textContent?.trim() ?? '';

      if (save && text !== '' && text !== this.currentValue()) {
        this.content.stage(this.key(), text);
      } else {
        this.element.textContent = this.currentValue();
      }

      this.editing = false;
    };

    const onBlur = () => finish(true);
    const onKeydown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        finish(false);
      }

      if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        this.element.blur();
      }
    };

    this.element.addEventListener('blur', onBlur);
    this.element.addEventListener('keydown', onKeydown);
  }

  private selectAll(): void {
    const selection = window.getSelection();

    if (!selection) {
      return;
    }

    const range = document.createRange();
    range.selectNodeContents(this.element);
    selection.removeAllRanges();
    selection.addRange(range);
  }

  private currentValue(): string {
    return this.content.value(this.key()) ?? this.original;
  }
}
