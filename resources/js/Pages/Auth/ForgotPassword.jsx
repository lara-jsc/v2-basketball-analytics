import InputError from '@/Components/InputError';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowRight, Mail, KeyRound } from 'lucide-react';

export default function ForgotPassword({ status }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('password.email'));
    };

    return (
        <>
            <Head title="Forgot Password" />

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
                                    <div className="flex h-16 w-16 items-center justify-center rounded-full border border-[#ff9d57]/50 bg-[radial-gradient(circle_at_30%_30%,#f6b67d_0%,#d56d27_28%,#8c3d14_65%,#120a0e_100%)] shadow-[0_0_30px_rgba(255,133,50,0.35)]">
                                        <div className="relative h-10 w-10 rounded-full border-[2.5px] border-[#1d0f10]">
                                            <div className="absolute left-1/2 top-0 h-full w-[2px] -translate-x-1/2 bg-[#1d0f10]" />
                                            <div className="absolute left-0 top-1/2 h-[2px] w-full -translate-y-1/2 bg-[#1d0f10]" />
                                            <div className="absolute inset-[-2px] rounded-full border-[2px] border-transparent border-l-[#1d0f10] border-r-[#1d0f10]" />
                                        </div>
                                    </div>

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
                                            <KeyRound className="h-8 w-8 text-slate-100" />
                                        </div>

                                        <div className="text-center">
                                            <h2 className="text-3xl text-left font-bold tracking-tight text-white">
                                                Forgot Password
                                            </h2>
                                            <p className="mt-1 text-sm text-slate-300/80">
                                                We'll send you a reset link
                                            </p>
                                        </div>
                                    </div>

                                    <p className="mb-5 text-left text-sm leading-relaxed text-slate-300/75">
                                        No problem. Enter your email address and we'll send you a password reset link so you can choose a new one.
                                    </p>

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
                                                Email Address
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
                                                    placeholder="Enter your email address"
                                                    className="h-full w-full border-0 bg-transparent pl-3 text-sm text-white placeholder:text-slate-500 focus:ring-0"
                                                    autoFocus
                                                />
                                            </div>

                                            <InputError message={errors.email} className="mt-2 text-[#ffb48f]" />
                                        </div>

                                        <button
                                            type="submit"
                                            disabled={processing}
                                            className="group inline-flex h-14 w-full items-center justify-center gap-2 rounded-2xl border border-[#ff9b56]/70 bg-[linear-gradient(135deg,#ff6a00,#ff8c42)] text-lg font-bold text-white shadow-[0_0_0_1px_rgba(255,147,77,0.3),0_12px_30px_rgba(255,111,21,0.28),inset_0_1px_0_rgba(255,255,255,0.25)] transition duration-300 hover:-translate-y-0.5 hover:shadow-[0_0_0_1px_rgba(255,147,77,0.38),0_18px_38px_rgba(255,111,21,0.34),0_0_22px_rgba(255,140,66,0.28)] disabled:cursor-not-allowed disabled:opacity-70"
                                        >
                                            <span>{processing ? 'Sending...' : 'Email Password Reset Link'}</span>
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
                                            Remember your password?{' '}
                                            <Link
                                                href={route('login')}
                                                className="font-semibold text-[#77a9ff] transition hover:text-[#9bc0ff]"
                                            >
                                                Back to Sign In
                                            </Link>
                                        </p>
                                    </div>
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
