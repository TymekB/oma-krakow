import { Directive, ElementRef, OnDestroy, OnInit, effect, inject, input } from '@angular/core';

import { LandingContentService } from './landing-content.service';

@Directive({
  selector: '[omaEditable]',
})
export class EditableDirective implements OnInit, OnDestroy {
  readonly key = input.required<string>({ alias: 'omaEditable' });

  private readonly host = inject(ElementRef<HTMLElement>);
  private readonly content = inject(LandingContentService);

  private pencil?: HTMLButtonElement;
  private original = '';
  private editing = false;

  constructor() {
    effect(() => {
      const stored = this.content.values()[this.key()];

      if (stored !== undefined && !this.editing && stored !== this.element.textContent?.trim()) {
        this.element.textContent = stored;
      }

      this.togglePencil(this.content.canEdit());
    });
  }

  ngOnInit(): void {
    this.original = this.element.textContent?.trim() ?? '';
    void this.content.load();
  }

  ngOnDestroy(): void {
    this.pencil?.remove();
  }

  private get element(): HTMLElement {
    return this.host.nativeElement as HTMLElement;
  }

  private togglePencil(visible: boolean): void {
    if (!visible) {
      this.pencil?.remove();
      this.pencil = undefined;
      this.element.classList.remove('editable');

      return;
    }

    if (this.pencil) {
      return;
    }

    this.element.classList.add('editable');

    this.pencil = document.createElement('button');
    this.pencil.type = 'button';
    this.pencil.className = 'editable__pencil';
    this.pencil.title = 'Edytuj tekst';
    this.pencil.setAttribute('aria-label', 'Edytuj tekst');
    this.pencil.innerHTML =
      '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>';
    this.pencil.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();
      this.startEditing();
    });

    this.element.appendChild(this.pencil);
  }

  private startEditing(): void {
    if (this.editing) {
      return;
    }

    this.editing = true;
    this.pencil?.remove();
    this.element.contentEditable = 'true';
    this.element.classList.add('editable--active');
    this.element.focus();

    const finish = async (save: boolean) => {
      this.element.contentEditable = 'false';
      this.element.classList.remove('editable--active');
      this.element.removeEventListener('blur', onBlur);
      this.element.removeEventListener('keydown', onKeydown);

      const text = this.element.textContent?.trim() ?? '';

      if (save && text !== '') {
        const saved = await this.content.save(this.key(), text);

        if (!saved) {
          this.element.textContent = this.content.value(this.key()) ?? this.original;
        }
      } else {
        this.element.textContent = this.content.value(this.key()) ?? this.original;
      }

      this.editing = false;
      this.togglePencil(this.content.canEdit());
    };

    const onBlur = () => void finish(true);
    const onKeydown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        void finish(false);
      }

      if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        this.element.blur();
      }
    };

    this.element.addEventListener('blur', onBlur);
    this.element.addEventListener('keydown', onKeydown);
  }
}
