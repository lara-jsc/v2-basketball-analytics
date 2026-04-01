import { useAppearance } from '@/hooks/useAppearance';
import { Link, usePage } from '@inertiajs/react';
import { Moon, SunMedium } from 'lucide-react';

export default function AuthenticatedLayout({ children }) {
    const user = usePage().props.auth.user;
    const { theme, toggleTheme } = useAppearance();

    return (
        <div className="min-h-screen bg-slate-100 text-slate-900 transition-colors dark:bg-slate-950 dark:text-slate-100">
            <div className="mx-auto min-h-screen max-w-[1500px] px-4 py-5 sm:px-6 lg:px-8">
                <div className="rounded-[2rem] border border-slate-200/80 bg-white/80 p-4 shadow-[0_24px_80px_rgba(15,23,42,0.08)] backdrop-blur dark:border-slate-800 dark:bg-slate-950/85 dark:shadow-[0_24px_80px_rgba(2,6,23,0.45)] sm:p-5">
                    <div className="mb-4 flex items-center justify-between rounded-[1.5rem] border border-slate-200 bg-white px-5 py-4 dark:border-slate-800 dark:bg-slate-900">
                        <Link
                            href={route('dashboard')}
                            className="flex items-center gap-3"
                        >
                            <span className="flex h-11 w-11 items-center justify-center rounded-2xl bg-[#1c326b] text-sm font-semibold text-white shadow-sm">
                                BA
                            </span>
                            <div>
                                <p className="text-[11px] uppercase tracking-[0.3em] text-slate-400 dark:text-slate-500">
                                    Basketball Analytics
                                </p>
                                <h1 className="text-lg font-semibold text-slate-900 dark:text-white">
                                    Thesis Dashboard
                                </h1>
                            </div>
                        </Link>

                        <div className="flex items-center gap-3">
                            <button
                                type="button"
                                onClick={toggleTheme}
                                className="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 text-slate-600 transition hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                                aria-label="Toggle theme"
                            >
                                {theme === 'dark' ? (
                                    <SunMedium className="h-5 w-5" />
                                ) : (
                                    <Moon className="h-5 w-5" />
                                )}
                            </button>

                            <div className="hidden text-right sm:block">
                                <p className="text-sm font-medium text-slate-900 dark:text-white">
                                    {user.name}
                                </p>
                                <p className="text-[11px] uppercase tracking-[0.24em] text-slate-400 dark:text-slate-500">
                                    Authorized User
                                </p>
                            </div>

                            <Link
                                method="post"
                                href={route('logout')}
                                as="button"
                                className="rounded-2xl bg-slate-900 px-4 py-2.5 text-sm font-medium text-white transition hover:bg-slate-800 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                            >
                                Sign out
                            </Link>
                        </div>
                    </div>

                    <main>{children}</main>
                </div>
            </div>
        </div>
    );
}
