import { useSidebar } from '@/hooks/useSidebar';
import { useAppearance } from '@/hooks/useAppearance';
import { Link, router, usePage } from '@inertiajs/react';
import {
    BarChart3,
    Radio,
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

export default function AuthenticatedLayout({ children, header }: AuthenticatedLayoutProps) {
    const { isCollapsed, toggle } = useSidebar();
    const { theme, toggleTheme } = useAppearance();
    const isDark = theme === 'dark';

    function handleLogout() {
        router.post(route('logout'));
    }

    return (
        <div className="flex h-screen w-screen overflow-hidden bg-background text-foreground">

            {/* ── SIDEBAR ─────────────────────────────────────────────────── */}
            <aside
                className="relative flex flex-col shrink-0 overflow-hidden transition-all duration-300 ease-in-out"
                style={{
                    width: isCollapsed ? '72px' : '240px',
                    backgroundImage: `url('/images/dashboard-assets/dark-mode-bg.png')`,
                    backgroundSize: 'cover',
                    backgroundPosition: 'center',
                    borderRight: '1px solid hsl(var(--border))',
                }}
            >
                {/* Overlay — dark in dark mode, light in light mode */}
                <div className="absolute inset-0 pointer-events-none"
                     style={{ background: isDark ? 'rgba(5,8,16,0.82)' : 'rgba(240,244,255,0.92)' }} />

                {/* Orange top accent line */}
                <div className="absolute top-0 inset-x-0 h-[2px] z-10"
                     style={{ background: 'linear-gradient(90deg, #FF8C00, #FFD700, #FF8C00)' }} />

                {/* ── Logo row ── */}
                <div className="relative z-10 flex h-16 items-center justify-between px-3 shrink-0"
                     style={{ borderBottom: '1px solid hsl(var(--border))' }}>
                    <Link href={route('dashboard')} className="flex items-center gap-2.5 overflow-hidden min-w-0">
                        <img
                            src="/images/dashboard-assets/basketball-logo.png"
                            alt="HoopSense+"
                            className="h-9 w-9 shrink-0 object-contain drop-shadow-[0_0_8px_rgba(255,140,0,0.7)]"
                        />
                        <span
                            className="whitespace-nowrap font-bold tracking-tight text-sm transition-all duration-300 overflow-hidden"
                            style={{
                                fontFamily: 'Orbitron, sans-serif',
                                opacity: isCollapsed ? 0 : 1,
                                width: isCollapsed ? 0 : 'auto',
                                color: 'hsl(var(--foreground))',
                                textShadow: isDark ? '0 0 12px rgba(255,140,0,0.4)' : 'none',
                            }}
                        >
                            Hoop<span style={{ color: '#FF8C00' }}>Sense+</span>
                        </span>
                    </Link>

                    <button
                        onClick={toggle}
                        className="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg transition-all hover:text-[#FF8C00]"
                        style={{
                            border: '1px solid hsl(var(--border))',
                            background: 'hsl(var(--muted) / 0.4)',
                            color: 'hsl(var(--muted-foreground))',
                        }}
                        aria-label={isCollapsed ? 'Expand sidebar' : 'Collapse sidebar'}
                    >
                        {isCollapsed ? <ChevronRight size={13} /> : <ChevronLeft size={13} />}
                    </button>
                </div>

                {/* ── Nav items ── */}
                <nav className="relative z-10 flex flex-1 flex-col gap-1 overflow-y-auto px-2 py-4">
                    <NavItem href={route('dashboard')}        icon={<LayoutDashboard size={20} />} label="Dashboard"      isCollapsed={isCollapsed} />
                    <NavItem href={route('teams.index')}      icon={<Users2 size={20} />}          label="Teams & Players" isCollapsed={isCollapsed} />
                    <NavItem href={route('comparison.index')} icon={<Swords size={20} />}          label="Team Comparison" isCollapsed={isCollapsed} />
                    <NavItem href={route('comparison.index')} icon={<BarChart3 size={20} />}        label="Player Matchup"  isCollapsed={isCollapsed} />
                    <NavItem href={route('live-games.index')}  icon={<Radio size={20} />}            label="Live Games"      isCollapsed={isCollapsed} />
                </nav>

                {/* ── Bottom ── */}
                <div className="relative z-10 flex flex-col gap-1 px-2 py-3 shrink-0"
                     style={{ borderTop: '1px solid hsl(var(--border))' }}>
                    <NavItem href="#" icon={<Settings size={20} />} label="Settings" isCollapsed={isCollapsed} />

                    <button
                        onClick={toggleTheme}
                        className="flex h-11 w-full items-center gap-3 rounded-xl px-3 text-sm transition-all hover:bg-muted/40"
                        style={{ color: 'hsl(var(--muted-foreground))' }}
                    >
                        <span className="flex h-[20px] w-[20px] shrink-0 items-center justify-center">
                            {theme === 'dark' ? <Sun size={18} /> : <Moon size={18} />}
                        </span>
                        <span className="whitespace-nowrap overflow-hidden transition-all duration-300 font-semibold text-sm"
                              style={{ opacity: isCollapsed ? 0 : 1, width: isCollapsed ? 0 : 'auto' }}>
                            {theme === 'dark' ? 'Light Mode' : 'Dark Mode'}
                        </span>
                    </button>

                    <button
                        onClick={handleLogout}
                        className="flex h-11 w-full items-center gap-3 rounded-xl px-3 text-sm transition-all hover:bg-red-500/10 hover:text-red-400"
                        style={{ color: 'hsl(var(--muted-foreground))' }}
                    >
                        <span className="flex h-[20px] w-[20px] shrink-0 items-center justify-center">
                            <LogOut size={18} />
                        </span>
                        <span className="whitespace-nowrap overflow-hidden transition-all duration-300 font-semibold text-sm"
                              style={{ opacity: isCollapsed ? 0 : 1, width: isCollapsed ? 0 : 'auto' }}>
                            Logout
                        </span>
                    </button>
                </div>
            </aside>

            {/* ── CONTENT AREA ─────────────────────────────────────────────── */}
            <div className="flex flex-1 flex-col overflow-hidden relative">
                {/* Arena background */}
                <div className="absolute inset-0 z-0">
                    <img
                        src="/images/dashboard-assets/dashboard-bg.png"
                        alt=""
                        className="w-full h-full object-cover object-top"
                        style={{ filter: isDark ? 'brightness(0.38) saturate(0.8)' : 'brightness(0.75) saturate(0.5)' }}
                    />
                    <div className="absolute inset-0"
                         style={{
                             background: isDark
                                 ? 'linear-gradient(180deg, rgba(5,8,16,0.30) 0%, rgba(5,8,16,0.55) 50%, rgba(5,8,16,0.80) 100%)'
                                 : 'linear-gradient(180deg, rgba(220,230,248,0.45) 0%, rgba(220,230,248,0.60) 50%, rgba(220,230,248,0.80) 100%)',
                         }} />
                </div>

                {/* Optional header */}
                {header && (
                    <div className="relative z-10 shrink-0 px-5 py-3 backdrop-blur-sm"
                         style={{ borderBottom: '1px solid hsl(var(--border))', background: 'hsl(var(--background) / 0.7)' }}>
                        {header}
                    </div>
                )}

                {/* Page content */}
                <main className="relative z-10 flex-1 overflow-y-auto px-5 py-6">
                    {children}
                </main>
            </div>
        </div>
    );
}

// ── NavItem ───────────────────────────────────────────────────────────────────

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
            className="relative flex h-11 w-full items-center gap-3 rounded-xl px-3 transition-all duration-150 overflow-hidden"
            style={isActive ? {
                background: 'linear-gradient(90deg, rgba(255,140,0,0.18) 0%, rgba(255,140,0,0.04) 100%)',
                borderLeft: '3px solid #FF8C00',
                boxShadow: '0 0 20px rgba(255,140,0,0.08)',
            } : {
                borderLeft: '3px solid transparent',
            }}
        >
            {/* Active glow */}
            {isActive && (
                <div className="pointer-events-none absolute inset-0"
                     style={{ background: 'radial-gradient(ellipse at left center, rgba(255,140,0,0.12) 0%, transparent 70%)' }} />
            )}

            <span className="relative flex h-[20px] w-[20px] shrink-0 items-center justify-center transition-all"
                  style={isActive ? {
                      color: '#FF8C00',
                      filter: 'drop-shadow(0 0 6px rgba(255,140,0,0.8))',
                  } : { color: 'hsl(var(--muted-foreground))' }}>
                {icon}
            </span>

            <span
                className="relative whitespace-nowrap overflow-hidden transition-all duration-300 font-semibold tracking-wide text-sm"
                style={{
                    opacity: isCollapsed ? 0 : 1,
                    width: isCollapsed ? 0 : 'auto',
                    color: isActive ? 'hsl(var(--foreground))' : 'hsl(var(--muted-foreground))',
                }}
            >
                {label}
            </span>
        </Link>
    );
}
