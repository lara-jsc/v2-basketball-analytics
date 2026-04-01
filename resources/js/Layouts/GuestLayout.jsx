import { useAppearance } from '@/hooks/useAppearance';
import { Link } from '@inertiajs/react';
import { Moon, SunMedium } from 'lucide-react';

export default function GuestLayout({ children }) {
    const { theme, toggleTheme } = useAppearance();

    return (
        <div className="relative flex min-h-screen items-center justify-center overflow-hidden px-6 py-10">
            <div className="absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(30,64,175,0.18),_transparent_28%),linear-gradient(180deg,rgba(239,246,255,0.96),rgba(255,255,255,1))] dark:bg-[radial-gradient(circle_at_top,_rgba(59,130,246,0.18),_transparent_24%),linear-gradient(180deg,rgba(15,23,42,0.98),rgba(2,6,23,1))]" />

            <div className="relative w-full max-w-5xl">
                <div className="mb-4 flex justify-end">
                    <button
                        type="button"
                        onClick={toggleTheme}
                        className="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-white/50 bg-white/80 text-slate-700 shadow-sm backdrop-blur transition hover:bg-white dark:border-slate-700 dark:bg-slate-900/90 dark:text-slate-100 dark:hover:bg-slate-800"
                        aria-label="Toggle theme"
                    >
                        {theme === 'dark' ? (
                            <SunMedium className="h-5 w-5" />
                        ) : (
                            <Moon className="h-5 w-5" />
                        )}
                    </button>
                </div>

                <div className="overflow-hidden rounded-[2rem] border border-white/40 bg-white/95 shadow-2xl shadow-slate-950/10 backdrop-blur dark:border-slate-800 dark:bg-slate-950/90 dark:shadow-slate-950/40">
                    <div className="grid min-h-[620px] lg:grid-cols-[1.05fr_0.95fr]">
                        <div className="hidden flex-col justify-between bg-[#1c326b] p-10 text-white dark:bg-slate-900 lg:flex">
                            <div className="space-y-6">
                                <Link href={route('login')} className="inline-flex w-fit items-center gap-3">
                                    <span className="flex h-12 w-12 items-center justify-center rounded-2xl border border-white/15 bg-white/10 text-lg font-semibold">
                                        BA
                                    </span>
                                    <div>
                                        <p className="text-xs uppercase tracking-[0.35em] text-blue-100/80">
                                            Thesis System
                                        </p>
                                        <p className="mt-1 text-lg font-semibold">
                                            Basketball Analytics
                                        </p>
                                    </div>
                                </Link>

                                <div className="max-w-md space-y-4">
                                    <p className="text-sm uppercase tracking-[0.32em] text-blue-100/90">
                                        Minimal. Focused. Academic.
                                    </p>
                                    <h1 className="text-4xl font-semibold leading-tight">
                                        A cleaner login screen with attention kept at the center.
                                    </h1>
                                    <p className="text-sm leading-7 text-blue-50/85">
                                        Light mode and dark mode are both available, while the overall layout stays institutional and restrained.
                                    </p>
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4 text-sm text-blue-50/85">
                                <div className="rounded-2xl border border-white/10 bg-white/10 p-4">
                                    <p className="text-2xl font-semibold text-white">CSV</p>
                                    <p className="mt-2">Import historical team data</p>
                                </div>
                                <div className="rounded-2xl border border-white/10 bg-white/10 p-4">
                                    <p className="text-2xl font-semibold text-white">LIVE</p>
                                    <p className="mt-2">Prepare for real-time analytics later</p>
                                </div>
                            </div>
                        </div>

                        <div className="flex items-center justify-center p-6 sm:p-10">
                            <div className="w-full max-w-md">{children}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
