import { Routes } from '@angular/router';

export const routes: Routes = [
  {
    path: '',
    title: 'OMA — Fizjoterapia, ZOGA Face, ERT Marii Margo, Masaż | Kraków',
    loadComponent: () => import('./pages/home/home').then((m) => m.Home),
  },
  { path: '**', redirectTo: '' },
];
