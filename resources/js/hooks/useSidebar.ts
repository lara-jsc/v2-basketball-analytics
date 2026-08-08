import { useCallback, useEffect, useState } from 'react';

const STORAGE_KEY = 'hoopsense-sidebar-collapsed';

/**
 * Persists sidebar collapsed state to localStorage.
 * Defaults to expanded (false) on first visit.
 */
export function useSidebar() {
    const [isCollapsed, setIsCollapsed] = useState<boolean>(() => {
        if (typeof window === 'undefined') return false;
        return localStorage.getItem(STORAGE_KEY) === 'true';
    });

    useEffect(() => {
        localStorage.setItem(STORAGE_KEY, String(isCollapsed));
    }, [isCollapsed]);

    const toggle = useCallback(() => setIsCollapsed((v) => !v), []);
    const setCollapsed = useCallback((value: boolean) => setIsCollapsed(value), []);

    return { isCollapsed, toggle, setCollapsed };
}
