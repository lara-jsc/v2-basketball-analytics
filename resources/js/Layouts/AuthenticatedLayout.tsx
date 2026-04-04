import { useSidebar } from '@/hooks/useSidebar';
import { useAppearance } from '@/hooks/useAppearance';
import { type PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    ChevronLeft,
    ChevronRight,
    LayoutDashboard,
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

    return (
        <div className="flex h-screen w-screen overflow-hidden bg-background text-foreground">
            {/* ── Sidebar ──────────────────────────────────────────────────── */}
            <aside
                className="relative flex flex-col shrink-0 overflow-hidden border-r border-border bg-card transition-all duration-300 ease-in-out"
                style={{ width: isCollapsed ? '68px' : '240px' }}
            >
                {/* Logo + toggle */}
                <div className="flex h-14 items-center justify-between px-3 border-b border-border shrink-0">
                    {/* Logo — hidden when collapsed */}
                    <Link
                        href={route('dashboard')}
                        className="flex items-center gap-2.5 overflow-hidden"
                    >
                        <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary text-[11px] font-bold text-primary-foreground shadow">
                            HS+
                        </span>
                        <span
                            className="whitespace-nowrap font-semibold tracking-tight text-foreground transition-all duration-300"
                            style={{
                                opacity: isCollapsed ? 0 : 1,
                                width: isCollapsed ? 0 : 'auto',
                                overflow: 'hidden',
                            }}
                        >
                            HoopSense<span className="text-primary">+</span>
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
                        icon={<LayoutDashboard size={18} />}
                        label="Dashboard"
                        isCollapsed={isCollapsed}
                    />
                    <NavItem
                        href={route('teams.index')}
                        icon={<Users2 size={18} />}
                        label="Teams & Players"
                        isCollapsed={isCollapsed}
                    />
                    <NavItem
                        href={route('comparison.index')}
                        icon={<Swords size={18} />}
                        label="Team Comparison"
                        isCollapsed={isCollapsed}
                    />
                    <NavItem
                        href={route('comparison.index')}
                        icon={<BarChart3 size={18} />}
                        label="Player Matchup"
                        isCollapsed={isCollapsed}
                    />
                </nav>

                {/* Bottom: settings + theme toggle */}
                <div className="flex flex-col gap-0.5 border-t border-border px-2 py-3 shrink-0">
                    <NavItem
                        href="#"
                        icon={<Settings size={18} />}
                        label="Settings"
                        isCollapsed={isCollapsed}
                    />

                    {/* Theme toggle */}
                    <button
                        onClick={toggleTheme}
                        aria-label={`Switch to ${theme === 'dark' ? 'light' : 'dark'} mode`}
                        className="flex h-9 w-full items-center gap-3 rounded-md px-2.5 text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    >
                        <span className="flex h-[18px] w-[18px] shrink-0 items-center justify-center">
                            {theme === 'dark' ? <Sun size={16} /> : <Moon size={16} />}
                        </span>
                        <span
                            className="whitespace-nowrap transition-all duration-300 overflow-hidden font-medium"
                            style={{
                                opacity: isCollapsed ? 0 : 1,
                                width: isCollapsed ? 0 : 'auto',
                                fontFamily: "'Rajdhani', sans-serif",
                            }}
                        >
                            {theme === 'dark' ? 'Light Mode' : 'Dark Mode'}
                        </span>
                    </button>
                </div>
            </aside>

            {/* ── Content area ─────────────────────────────────────────────── */}
            <div className="flex flex-1 flex-col overflow-hidden">
                {/* Page header band (optional — passed from each page) */}
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

// ── NavItem ──────────────────────────────────────────────────────────────────

interface NavItemProps {
    href: string;
    icon: ReactNode;
    label: string;
    isCollapsed: boolean;
}

function NavItem({ href, icon, label, isCollapsed }: NavItemProps) {
    const { url } = usePage();

    // Active if current URL starts with the href path (handles nested routes)
    const hrefPath = href.split('?')[0];
    const isActive = url === hrefPath || (hrefPath !== '/' && url.startsWith(hrefPath));

    return (
        <Link
            href={href}
            title={isCollapsed ? label : undefined}
            className={[
                'flex h-9 w-full items-center gap-3 rounded-md px-2.5 text-sm transition-colors',
                isActive
                    ? 'border-l-2 border-primary bg-primary/10 text-foreground'
                    : 'border-l-2 border-transparent text-muted-foreground hover:bg-muted hover:text-foreground',
            ].join(' ')}
        >
            <span className="flex h-[18px] w-[18px] shrink-0 items-center justify-center">
                {icon}
            </span>
            <span
                className="whitespace-nowrap overflow-hidden transition-all duration-300"
                style={{
                    opacity: isCollapsed ? 0 : 1,
                    width: isCollapsed ? 0 : 'auto',
                    fontFamily: "'Rajdhani', sans-serif",
                    fontWeight: 600,
                    letterSpacing: '0.02em',
                }}
            >
                {label}
            </span>
        </Link>
    );
}
