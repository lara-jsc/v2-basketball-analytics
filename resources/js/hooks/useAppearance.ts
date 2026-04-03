import { useEffect, useState } from 'react';

export type Theme = 'light' | 'dark';

const STORAGE_KEY = 'hoopsense-theme';

export function resolveInitialTheme(): Theme {
  if (typeof window === 'undefined') {
    return 'light';
  }

  const stored = window.localStorage.getItem(STORAGE_KEY);

  if (stored === 'light' || stored === 'dark') {
    return stored;
  }

  // Default to system preference
  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

export function applyTheme(theme: Theme): void {
  if (typeof document === 'undefined') {
    return;
  }

  document.documentElement.classList.toggle('dark', theme === 'dark');
  document.documentElement.dataset['theme'] = theme;

  if (typeof window !== 'undefined') {
    window.localStorage.setItem(STORAGE_KEY, theme);
  }
}

export interface UseAppearanceReturn {
  theme: Theme;
  toggleTheme: () => void;
}

export function useAppearance(): UseAppearanceReturn {
  const [theme, setTheme] = useState<Theme>(resolveInitialTheme);

  useEffect(() => {
    applyTheme(theme);
  }, [theme]);

  const toggleTheme = (): void => {
    setTheme((current) => (current === 'dark' ? 'light' : 'dark'));
  };

  return { theme, toggleTheme };
}
