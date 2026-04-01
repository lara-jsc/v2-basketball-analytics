import { useEffect, useState } from 'react';

const STORAGE_KEY = 'basketball-analytics-theme';

export function resolveInitialTheme() {
    if (typeof window === 'undefined') {
        return 'light';
    }

    const storedTheme = window.localStorage.getItem(STORAGE_KEY);

    if (storedTheme === 'light' || storedTheme === 'dark') {
        return storedTheme;
    }

    return 'light';
}

export function applyTheme(theme) {
    if (typeof document === 'undefined') {
        return;
    }

    const root = document.documentElement;

    root.classList.toggle('dark', theme === 'dark');
    root.dataset.theme = theme;

    if (typeof window !== 'undefined') {
        window.localStorage.setItem(STORAGE_KEY, theme);
    }
}

export function useAppearance() {
    const [theme, setTheme] = useState(resolveInitialTheme);

    useEffect(() => {
        applyTheme(theme);
    }, [theme]);

    const toggleTheme = () => {
        setTheme((currentTheme) =>
            currentTheme === 'dark' ? 'light' : 'dark',
        );
    };

    return { theme, toggleTheme };
}
