import { useAppearance } from '@/hooks/useAppearance';
import { type PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Moon, Sun } from 'lucide-react';
import { type ReactNode } from 'react';

interface AuthenticatedLayoutProps {
  children: ReactNode;
  /** Pass header content (page title, action buttons) from the page component */
  header?: ReactNode;
}

/**
 * Root shell for all authenticated pages.
 *
 * Layout: topbar (brand + nav + theme toggle + user) + scrollable main area.
 * Primary breakpoint: 768px–1024px (tablet-first).
 */
export default function AuthenticatedLayout({ children, header }: AuthenticatedLayoutProps) {
  const { auth } = usePage<PageProps>().props;
  const { theme, toggleTheme } = useAppearance();

  return (
    <div className="min-h-screen bg-background text-foreground transition-colors">
      {/* ── Top navigation bar ─────────────────────────────────────────────── */}
      <header className="sticky top-0 z-30 border-b border-border bg-card/95 backdrop-blur">
        <div className="mx-auto flex h-14 max-w-screen-xl items-center gap-4 px-4 md:px-6">
          {/* Brand */}
          <Link href={route('dashboard')} className="flex shrink-0 items-center gap-2.5">
            <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary text-xs font-bold text-primary-foreground shadow">
              HS+
            </span>
            <span className="hidden text-base font-semibold tracking-tight text-foreground sm:block">
              HoopSense<span className="text-primary">+</span>
            </span>
          </Link>

          {/* Primary nav — in-scope pages only */}
          <nav className="flex flex-1 items-center gap-1 overflow-x-auto">
            <NavLink href={route('teams.index')} label="Teams" />
            <NavLink href={route('comparison.index')} label="Compare Teams" />
          </nav>

          {/* Right-side controls */}
          <div className="flex shrink-0 items-center gap-2">
            {/* Theme toggle */}
            <button
              onClick={toggleTheme}
              aria-label={`Switch to ${theme === 'dark' ? 'light' : 'dark'} mode`}
              className="flex h-8 w-8 items-center justify-center rounded-lg border border-border text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
            >
              {theme === 'dark' ? <Sun size={15} /> : <Moon size={15} />}
            </button>

            {/* User + logout */}
            <div className="flex items-center gap-2">
              <span className="hidden text-sm text-muted-foreground md:block">
                {auth.user.name}
              </span>
              <Link
                method="post"
                href={route('logout')}
                as="button"
                className="rounded-lg border border-border px-3 py-1.5 text-xs font-medium text-foreground transition-colors hover:bg-muted"
              >
                Sign out
              </Link>
            </div>
          </div>
        </div>

        {/* Optional page-level header (title + actions passed from page) */}
        {header && (
          <div className="border-t border-border bg-background/50 px-4 py-3 md:px-6">
            {header}
          </div>
        )}
      </header>

      {/* ── Page content ───────────────────────────────────────────────────── */}
      <main className="mx-auto max-w-screen-xl px-4 py-6 md:px-6">
        {children}
      </main>
    </div>
  );
}

// ── Internal helpers ────────────────────────────────────────────────────────

interface NavLinkProps {
  href: string;
  label: string;
}

function NavLink({ href, label }: NavLinkProps) {
  return (
    <Link
      href={href}
      className="whitespace-nowrap rounded-md px-3 py-1.5 text-sm font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
    >
      {label}
    </Link>
  );
}
