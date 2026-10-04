import InputError from '@/Components/InputError';
import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowRight,
    Eye,
    EyeOff,
    Lock,
    Mail,
    ShieldCheck,
    UserCircle2,
} from 'lucide-react';
import { useState } from 'react';

export default function Login({ status, canResetPassword }) {
    const [showPassword, setShowPassword] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <>
            <Head title="HoopSense+ Sign In" />

            <div className="relative flex min-h-screen w-full flex-col overflow-hidden bg-[#020611] text-white">
                <div className="absolute inset-0">
                    <img
                        src="/images/login-assets/login-bg.png"
                        alt=""
                        className="absolute inset-0 h-full w-full object-cover object-top"
                        style={{ filter: 'brightness(0.22) saturate(0.7)' }}
                    />
                </div>

                <div className="relative z-10 flex min-h-screen w-full flex-1 flex-col px-4 py-6 sm:px-6 lg:px-8">
                    <div className="flex flex-1 items-center justify-center">
                        <div className="w-full max-w-5xl">
                            <div className="mx-auto flex w-full max-w-[500px] flex-col items-center text-center">
                                <div className="mb-7 flex items-center gap-4">
                                    <img
                                        src="/images/dashboard-assets/basketball-logo.png"
                                        alt="HoopSense+ Logo"
                                        className="h-16 w-16 drop-shadow-[0_0_12px_rgba(255,133,50,0.5)]"
                                    />

                                    <div className="text-left">
                                        <h1 className="text-4xl font-black tracking-tight text-white sm:text-5xl">
                                            HoopSense<span className="text-[#ff8c42]">+</span>
                                        </h1>
                                        <p className="mt-1 max-w-md text-[11px] font-medium uppercase tracking-[0.22em] text-slate-300/90">
                                            Basketball Intelligence Platform
                                        </p>
                                    </div>
                                </div>

                                <p className="max-w-2xl text-balance text-sm leading-6 text-slate-200/85 sm:text-base">
                                    An Intelligent Basketball Game Decision Support System Using
                                    <span className="font-semibold text-[#ff9d57]"> Plus-Minus Analytics </span>
                                    for Winning Probability Optimization
                                </p>

                                <div className="relative mt-10 w-full overflow-hidden rounded-[24px] border border-[#ff8d45]/35 bg-[linear-gradient(180deg,rgba(10,18,38,0.82),rgba(3,8,22,0.92))] px-6 py-7 shadow-[0_0_0_1px_rgba(255,140,66,0.1),0_0_28px_rgba(255,119,37,0.14),0_30px_80px_rgba(1,5,18,0.65)] backdrop-blur-xl sm:px-8 sm:py-8">
                                    <div className="pointer-events-none absolute inset-0 rounded-[24px] ring-1 ring-inset ring-white/6" />
                                    <div className="pointer-events-none absolute -left-16 top-10 h-24 w-24 rounded-full bg-[#ff8c42]/12 blur-3xl" />
                                    <div className="pointer-events-none absolute -right-12 top-16 h-28 w-28 rounded-full bg-[#ff8c42]/12 blur-3xl" />

                                    <div className="mb-6 flex w-full items-center justify-center gap-4 border-b border-white/10 pb-5">
                                        <div className="flex h-14 w-14 items-center justify-center rounded-full border border-white/10 bg-white/5 shadow-inner shadow-white/5">
                                            <UserCircle2 className="h-8 w-8 text-slate-100" />
                                        </div>

                                        <div className="text-center">
                                            <h2 className="text-3xl text-left font-bold tracking-tight text-white">
                                                Sign In
                                            </h2>
                                            <p className="mt-1 text-sm text-slate-300/80">
                                                Access your HoopSense+ account
                                            </p>
                                        </div>
                                    </div>

                                    {status && (
                                        <div className="mb-5 rounded-2xl border border-emerald-400/25 bg-emerald-500/10 px-4 py-3 text-sm font-medium text-emerald-200">
                                            {status}
                                        </div>
                                    )}

                                    <form onSubmit={submit} className="space-y-5">
                                        <div className="space-y-2">
                                            <label
                                                htmlFor="email"
                                                className="block text-left text-sm font-semibold text-slate-100"
                                            >
                                                User ID
                                            </label>

                                            <div className="group flex h-14 items-center rounded-2xl border border-white/10 bg-[#0d1428]/90 px-4 shadow-[inset_0_1px_0_rgba(255,255,255,0.03)] transition focus-within:border-[#ff8c42]/60 focus-within:shadow-[0_0_0_1px_rgba(255,140,66,0.2),0_0_24px_rgba(255,140,66,0.08)]">
                                                <Mail className="h-5 w-5 text-slate-400 transition group-focus-within:text-[#ff9d57]" />
                                                <input
                                                    id="email"
                                                    type="email"
                                                    name="email"
                                                    value={data.email}
                                                    autoComplete="username"
                                                    onChange={(e) => setData('email', e.target.value)}
                                                    placeholder="Enter your email or user ID"
                                                    className="h-full w-full border-0 bg-transparent pl-3 text-sm text-white placeholder:text-slate-500 focus:ring-0"
                                                    autoFocus
                                                />
                                            </div>

                                            <InputError message={errors.email} className="mt-2 text-[#ffb48f]" />
                                        </div>

                                        <div className="space-y-2">
                                            <label
                                                htmlFor="password"
                                                className="block text-left text-sm font-semibold text-slate-100"
                                            >
                                                Password
                                            </label>

                                            <div className="group flex h-14 items-center rounded-2xl border border-white/10 bg-[#0d1428]/90 px-4 shadow-[inset_0_1px_0_rgba(255,255,255,0.03)] transition focus-within:border-[#ff8c42]/60 focus-within:shadow-[0_0_0_1px_rgba(255,140,66,0.2),0_0_24px_rgba(255,140,66,0.08)]">
                                                <Lock className="h-5 w-5 text-slate-400 transition group-focus-within:text-[#ff9d57]" />
                                                <input
                                                    id="password"
                                                    type={showPassword ? 'text' : 'password'}
                                                    name="password"
                                                    value={data.password}
                                                    autoComplete="current-password"
                                                    onChange={(e) => setData('password', e.target.value)}
                                                    placeholder="Enter your password"
                                                    className="h-full w-full border-0 bg-transparent px-3 text-sm text-white placeholder:text-slate-500 focus:ring-0"
                                                />
                                                <button
                                                    type="button"
                                                    onClick={() => setShowPassword((value) => !value)}
                                                    className="inline-flex h-8 w-8 items-center justify-center rounded-full text-slate-400 transition hover:bg-white/5 hover:text-[#ff9d57]"
                                                    aria-label={showPassword ? 'Hide password' : 'Show password'}
                                                >
                                                    {showPassword ? (
                                                        <EyeOff className="h-4.5 w-4.5" />
                                                    ) : (
                                                        <Eye className="h-4.5 w-4.5" />
                                                    )}
                                                </button>
                                            </div>

                                            <InputError
                                                message={errors.password}
                                                className="mt-2 text-[#ffb48f]"
                                            />
                                        </div>

                                        <div className="flex items-center justify-between gap-3">
                                            <label className="inline-flex items-center gap-2 text-sm text-slate-300/85">
                                                <input
                                                    type="checkbox"
                                                    name="remember"
                                                    checked={data.remember}
                                                    onChange={(e) =>
                                                        setData('remember', e.target.checked)
                                                    }
                                                    className="h-4 w-4 rounded border-white/20 bg-[#0d1428] text-[#ff8c42] focus:ring-[#ff8c42]/50"
                                                />
                                                Remember me
                                            </label>

                                            {canResetPassword && (
                                                <Link
                                                    href={route('password.request')}
                                                    className="text-sm font-semibold text-[#77a9ff] transition hover:text-[#9bc0ff]"
                                                >
                                                    Forgot Password?
                                                </Link>
                                            )}
                                        </div>

                                        <button
                                            type="submit"
                                            disabled={processing}
                                            className="group inline-flex h-14 w-full items-center justify-center gap-2 rounded-2xl border border-[#ff9b56]/70 bg-[linear-gradient(135deg,#ff6a00,#ff8c42)] text-lg font-bold text-white shadow-[0_0_0_1px_rgba(255,147,77,0.3),0_12px_30px_rgba(255,111,21,0.28),inset_0_1px_0_rgba(255,255,255,0.25)] transition duration-300 hover:-translate-y-0.5 hover:shadow-[0_0_0_1px_rgba(255,147,77,0.38),0_18px_38px_rgba(255,111,21,0.34),0_0_22px_rgba(255,140,66,0.28)] disabled:cursor-not-allowed disabled:opacity-70"
                                        >
                                            <span>{processing ? 'Signing In...' : 'Sign In'}</span>
                                            <ArrowRight className="h-5 w-5 transition group-hover:translate-x-0.5" />
                                        </button>
                                    </form>

                                    <div className="mt-6">
                                        <div className="flex items-center gap-4">
                                            <div className="h-px flex-1 bg-white/10" />
                                            <span className="text-sm font-semibold text-slate-400">or</span>
                                            <div className="h-px flex-1 bg-white/10" />
                                        </div>

                                        <p className="mt-5 text-center text-sm text-slate-300/80">
                                            Don&apos;t have an account?{' '}
                                            <Link
                                                href={route('register')}
                                                className="font-semibold text-[#77a9ff] transition hover:text-[#9bc0ff]"
                                            >
                                                Create a coach account
                                            </Link>
                                        </p>
                                    </div>

                                    {/* <div className="mt-6 flex items-center justify-center gap-2 rounded-2xl border border-white/8 bg-white/5 px-4 py-3 text-xs uppercase tracking-[0.24em] text-slate-400">
                                        <ShieldCheck className="h-4 w-4 text-[#ff9d57]" />
                                        Secure analytics access
                                    </div> */}
                                </div>
                            </div>
                        </div>
                    </div>

                    <footer className="relative z-10 flex justify-center pb-2 pt-6 text-center text-sm text-slate-400/80">
                        <p>&copy; 2026 HoopSense+. All rights reserved.</p>
                    </footer>
                </div>
            </div>
        </>
    );
}
