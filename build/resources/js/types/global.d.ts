import { AxiosStatic } from 'axios';

declare global {
  interface Window {
    axios: AxiosStatic;
    toast: typeof import('vue-sonner').toast;
  }
}

export {};