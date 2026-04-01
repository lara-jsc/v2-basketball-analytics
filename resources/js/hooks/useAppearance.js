import { useEffect, useState } from 'react';

const STORAGE_KEY = 'basketball-analytics-theme';

function resolveInitialTheme() {
    if (typeof window === 'undefined') {
        return 'light';
    }

    const storedTheme = window.localStorage.getItem(STORAGE_KEY);

    if (storedTheme === 'light' || storedTheme === 'dark') {
        return storedTheme;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches
        ? 'dark'
        : 'light';
}

export function useAppearance() {
    const [theme, setTheme] = useState(resolveInitialTheme);

    useEffect(() => {
        const root = document.documentElement;

        root.classList.toggle('dark', theme === 'dark');
        root.dataset.theme = theme;
        window.localStorage.setItem(STORAGE_KEY, theme);
    }, [theme]);

    const toggleTheme = () => {
        setTheme((currentTheme) =>
            currentTheme === 'dark' ? 'light' : 'dark',
        );
    };

    return { theme, toggleTheme };
}
