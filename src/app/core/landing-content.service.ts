import { Injectable, signal } from '@angular/core';

@Injectable({ providedIn: 'root' })
export class LandingContentService {
  readonly values = signal<Record<string, string>>({});
  readonly canEdit = signal(false);
  readonly ready = signal(false);

  private loading?: Promise<void>;

  load(): Promise<void> {
    this.loading ??= this.fetchAll();

    return this.loading;
  }

  value(key: string): string | undefined {
    return this.values()[key];
  }

  async save(key: string, value: string): Promise<boolean> {
    const response = await fetch(`/admin/landing-content/${key}`, {
      method: 'PUT',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ value }),
    });

    if (!response.ok) {
      return false;
    }

    this.values.update((current) => ({ ...current, [key]: value }));

    return true;
  }

  private async fetchAll(): Promise<void> {
    await Promise.all([this.fetchValues(), this.fetchSession()]);
    this.ready.set(true);
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
      const response = await fetch('/admin/landing-content/session', {
        credentials: 'include',
        redirect: 'follow',
      });

      if (!response.ok || response.redirected) {
        return;
      }

      const payload = (await response.json()) as { canEdit?: boolean };
      this.canEdit.set(payload.canEdit === true);
    } catch {
      this.canEdit.set(false);
    }
  }
}
