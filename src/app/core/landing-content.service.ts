import { Injectable, computed, signal } from '@angular/core';

@Injectable({ providedIn: 'root' })
export class LandingContentService {
  readonly values = signal<Record<string, string>>({});
  readonly pending = signal<Record<string, string>>({});
  readonly canEdit = signal(false);

  readonly dirtyCount = computed(() => Object.keys(this.pending()).length);

  private loading?: Promise<void>;
  private parentBridgeReady = false;

  load(): Promise<void> {
    this.loading ??= this.bootstrap();

    return this.loading;
  }

  value(key: string): string | undefined {
    return this.pending()[key] ?? this.values()[key];
  }

  stage(key: string, value: string): void {
    this.pending.update((current) => ({ ...current, [key]: value }));
    this.notifyParent();
  }

  discard(): void {
    this.pending.set({});
    this.notifyParent();
  }

  async saveAll(): Promise<boolean> {
    const values = this.pending();

    if (Object.keys(values).length === 0) {
      return true;
    }

    try {
      const response = await fetch('/admin/landing-content', {
        method: 'PUT',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ values }),
      });

      if (!response.ok) {
        return false;
      }

      this.values.update((current) => ({ ...current, ...values }));
      this.pending.set({});
      this.notifyParent();

      return true;
    } catch {
      return false;
    }
  }

  private async bootstrap(): Promise<void> {
    await this.fetchValues();

    if (this.editRequested()) {
      await this.fetchSession();
      this.listenToParent();
    }
  }

  private editRequested(): boolean {
    return typeof window !== 'undefined' && new URLSearchParams(window.location.search).get('edit') === '1';
  }

  private async fetchValues(): Promise<void> {
    try {
      const response = await fetch('/landing-content.json', { credentials: 'include' });

      if (!response.ok) {
        return;
      }

      const payload = (await response.json()) as { values?: Record<string, string> };
      this.values.set(payload.values ?? {});
    } catch {
      this.values.set({});
    }
  }

  private async fetchSession(): Promise<void> {
    try {
      const response = await fetch('/admin/landing-content/session', { credentials: 'include' });

      if (!response.ok || response.redirected) {
        return;
      }

      const payload = (await response.json()) as { canEdit?: boolean };
      this.canEdit.set(payload.canEdit === true);
    } catch {
      this.canEdit.set(false);
    }
  }

  private listenToParent(): void {
    if (this.parentBridgeReady || window.parent === window) {
      return;
    }

    this.parentBridgeReady = true;

    window.addEventListener('message', (event: MessageEvent) => {
      if (event.origin !== window.location.origin) {
        return;
      }

      const message = (event.data ?? {}) as { type?: string };

      if (message.type === 'oma:save') {
        void this.saveAll().then((ok) => window.parent.postMessage({ type: 'oma:saved', ok }, window.location.origin));
      }

      if (message.type === 'oma:discard') {
        window.location.reload();
      }
    });
  }

  private notifyParent(): void {
    if (window.parent === window) {
      return;
    }

    window.parent.postMessage({ type: 'oma:dirty', count: this.dirtyCount() }, window.location.origin);
  }
}
