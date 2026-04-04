import { useSidebar } from '@/hooks/useSidebar';
import { useAppearance } from '@/hooks/useAppearance';
import { type PageProps } from '@/types';
import { Link, router, usePage } from '@inertiajs/react';
import {
    BarChart3,
    ChevronLeft,
    ChevronRight,
    LayoutDashboard,
    LogOut,
    Moon,
    Settings,
    Sun,
    Swords,
    Users2,
} from 'lucide-react';
import { type ReactNode } from 'react';

interface AuthenticatedLayoutProps {
    children: ReactNode;
    header?: ReactNode;
}

/**
 * Root shell for all authenticated pages.
 *
 * Layout: collapsible sidebar (240px / 68px) + full-viewport content area.
 * Primary breakpoint: 768px–1024px (tablet-first).
 * Respects dark / light mode via CSS variables.
 */
export default function AuthenticatedLayout({ children, header }: AuthenticatedLayoutProps) {
    const { isCollapsed, toggle } = useSidebar();
    const { theme, toggleTheme } = useAppearance();

    function handleLogout() {
        router.post(route('logout'));
    }

    return (
        <div className="flex h-screen w-screen overflow-hidden bg-background text-foreground">
            {/* ── Sidebar ──────────────────────────────────────────────────── */}
            <aside
                className="relative flex flex-col shrink-0 overflow-hidden border-r border-border bg-card transition-all duration-300 ease-in-out"
                style={{ width: isCollapsed ? '68px' : '240px' }}
            >
                {/* Logo + toggle */}
                <div className="flex h-14 items-center justify-between px-3 border-b border-border shrink-0">
                    <Link
                        href={route('dashboard')}
                        className="flex items-center gap-2.5 overflow-hidden"
                    >
                        {/* Basketball SVG logo */}
                        <span className="flex h-8 w-8 shrink-0 items-center justify-center">
                            <BasketballIcon />
                        </span>
                        <span
                            className="whitespace-nowrap font-display font-bold tracking-tight text-foreground transition-all duration-300 text-base"
                            style={{
                                opacity: isCollapsed ? 0 : 1,
                                width: isCollapsed ? 0 : 'auto',
                                overflow: 'hidden',
                            }}
                        >
                            HoopSense<span className="text-accent">+</span>
                        </span>
                    </Link>

                    {/* Collapse toggle */}
                    <button
                        onClick={toggle}
                        aria-label={isCollapsed ? 'Expand sidebar' : 'Collapse sidebar'}
                        className="flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    >
                        {isCollapsed ? <ChevronRight size={15} /> : <ChevronLeft size={15} />}
                    </button>
                </div>

                {/* Primary nav */}
                <nav className="flex flex-1 flex-col gap-0.5 overflow-y-auto px-2 py-3">
                    <NavItem
                        href={route('dashboard')}
                        icon={<LayoutDashboard size={20} />}
                        label="Dashboard"
                        isCollapsed={isCollapsed}
                    />
                    <NavItem
                        href={route('teams.index')}
                        icon={<Users2 size={20} />}
                        label="Teams & Players"
                        isCollapsed={isCollapsed}
                    />
                    <NavItem
                        href={route('comparison.index')}
                        icon={<Swords size={20} />}
                        label="Team Comparison"
                        isCollapsed={isCollapsed}
                    />
                    <NavItem
                        href={route('comparison.index')}
                        icon={<BarChart3 size={20} />}
                        label="Player Matchup"
                        isCollapsed={isCollapsed}
                    />
                </nav>

                {/* Bottom: settings + theme toggle + logout */}
                <div className="flex flex-col gap-0.5 border-t border-border px-2 py-3 shrink-0">
                    <NavItem
                        href="#"
                        icon={<Settings size={20} />}
                        label="Settings"
                        isCollapsed={isCollapsed}
                    />

                    {/* Theme toggle */}
                    <button
                        onClick={toggleTheme}
                        aria-label={`Switch to ${theme === 'dark' ? 'light' : 'dark'} mode`}
                        className="flex h-9 w-full items-center gap-3 rounded-md px-2.5 text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    >
                        <span className="flex h-[20px] w-[20px] shrink-0 items-center justify-center">
                            {theme === 'dark' ? <Sun size={18} /> : <Moon size={18} />}
                        </span>
                        <span
                            className="whitespace-nowrap transition-all duration-300 overflow-hidden font-ui font-semibold"
                            style={{
                                opacity: isCollapsed ? 0 : 1,
                                width: isCollapsed ? 0 : 'auto',
                            }}
                        >
                            {theme === 'dark' ? 'Light Mode' : 'Dark Mode'}
                        </span>
                    </button>

                    {/* Logout */}
                    <button
                        onClick={handleLogout}
                        className="flex h-9 w-full items-center gap-3 rounded-md px-2.5 text-sm text-muted-foreground transition-colors hover:bg-destructive/10 hover:text-destructive"
                    >
                        <span className="flex h-[20px] w-[20px] shrink-0 items-center justify-center">
                            <LogOut size={18} />
                        </span>
                        <span
                            className="whitespace-nowrap transition-all duration-300 overflow-hidden font-ui font-semibold"
                            style={{
                                opacity: isCollapsed ? 0 : 1,
                                width: isCollapsed ? 0 : 'auto',
                            }}
                        >
                            Logout
                        </span>
                    </button>
                </div>
            </aside>

            {/* ── Content area ─────────────────────────────────────────────── */}
            <div className="flex flex-1 flex-col overflow-hidden">
                {/* Page header band (optional) */}
                {header && (
                    <div className="shrink-0 border-b border-border bg-card/80 px-5 py-3 backdrop-blur">
                        {header}
                    </div>
                )}

                {/* Scrollable page content */}
                <main className="flex-1 overflow-y-auto px-5 py-6">
                    {children}
                </main>
            </div>
        </div>
    );
}

// ── Basketball SVG Icon ───────────────────────────────────────────────────────

function BasketballIcon() {
    return (
        <svg viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" className="h-8 w-8">
            <circle cx="16" cy="16" r="15" fill="#F9A01B" />
            <circle cx="16" cy="16" r="15" stroke="#98002E" strokeWidth="0.75" />
            {/* Vertical seam */}
            <path d="M16 1 C16 8 16 24 16 31" stroke="#98002E" strokeWidth="1.2" strokeLinecap="round" />
            {/* Horizontal seam */}
            <path d="M1 16 C8 16 24 16 31 16" stroke="#98002E" strokeWidth="1.2" strokeLinecap="round" />
            {/* Top arc */}
            <path d="M5.5 7 C10 11 22 11 26.5 7" stroke="#98002E" strokeWidth="1.2" strokeLinecap="round" fill="none" />
            {/* Bottom arc */}
            <path d="M5.5 25 C10 21 22 21 26.5 25" stroke="#98002E" strokeWidth="1.2" strokeLinecap="round" fill="none" />
        </svg>
    );
}

// ── NavItem ──────────────────────────────────────────────────────────────────

interface NavItemProps {
    href: string;
    icon: ReactNode;
    label: string;
    isCollapsed: boolean;
}

function NavItem({ href, icon, label, isCollapsed }: NavItemProps) {
    const { url } = usePage();

    const hrefPath = href.split('?')[0];
    const isActive = url === hrefPath || (hrefPath !== '/' && url.startsWith(hrefPath));

    return (
        <Link
            href={href}
            title={isCollapsed ? label : undefined}
            className={[
                'flex h-9 w-full items-center gap-3 rounded-md px-2.5 text-sm transition-all duration-150',
                isActive
                    ? 'border-l-2 border-accent bg-primary/15 text-foreground shadow-[0_0_8px_rgba(249,160,27,0.12)]'
                    : 'border-l-2 border-transparent text-muted-foreground hover:bg-muted hover:text-foreground',
            ].join(' ')}
        >
            <span className={[
                'flex h-[20px] w-[20px] shrink-0 items-center justify-center transition-colors',
                isActive ? 'text-accent' : '',
            ].join(' ')}>
                {icon}
            </span>
            <span
                className="whitespace-nowrap overflow-hidden transition-all duration-300 font-ui font-semibold tracking-wide"
                style={{
                    opacity: isCollapsed ? 0 : 1,
                    width: isCollapsed ? 0 : 'auto',
                }}
            >
                {label}
            </span>
        </Link>
    );
}
