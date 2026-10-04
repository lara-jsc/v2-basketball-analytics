import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowRight, MailCheck } from 'lucide-react';
import { useState } from 'react';

export default function VerifyEmail({ status }) {
    const email = usePage().props.auth?.user?.email;
    const [throttled, setThrottled] = useState(false);
    const { post, processing } = useForm({});

    const submit = (e) => {
        e.preventDefault();
        setThrottled(false);

        // The resend route is rate limited (throttle:6,1). A 429 is not an
        // Inertia response, so catch it here instead of showing the error modal.
        const stopListening = router.on('invalid', (event) => {
            if (event.detail.response.status === 429) {
                event.preventDefault();
                setThrottled(true);
            }
        });

        post(route('verification.send'), {
            onFinish: () => stopListening(),
        });
    };

    const linkSent = status === 'verification-link-sent' && !throttled;

    return (
        <>
            <Head title="Verify Email" />

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
                                        <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-full border border-white/10 bg-white/5 shadow-inner shadow-white/5">
                                            <MailCheck className="h-8 w-8 text-slate-100" />
                                        </div>

                                        <div className="text-left">
                                            <h2 className="text-3xl font-bold tracking-tight text-white">
                                                Verify Your Email
                                            </h2>
                                            <p className="mt-1 text-sm text-slate-300/80">
                                                One more step before you start
                                            </p>
                                        </div>
                                    </div>

                                    <p className="mb-5 text-left text-sm leading-relaxed text-slate-300/75">
                                        We sent a verification link to{' '}
                                        {email ? (
                                            <span className="break-all font-semibold text-white">{email}</span>
                                        ) : (
                                            'your email address'
                                        )}
                                        . Click it to activate your account. Can&apos;t find it? Check your spam folder, or send a new link below.
                                    </p>

                                    {linkSent && (
                                        <div
                                            role="status"
                                            className="mb-5 rounded-2xl border border-emerald-400/25 bg-emerald-500/10 px-4 py-3 text-left text-sm font-medium text-emerald-200"
                                        >
                                            A new verification link has been sent to {email ?? 'your email address'}.
                                        </div>
                                    )}

                                    {throttled && (
                                        <p role="alert" className="mb-5 text-left text-sm font-medium text-[#ffb48f]">
                                            Too many requests. Wait a minute, then try again.
                                        </p>
                                    )}

                                    <form onSubmit={submit}>
                                        <button
                                            type="submit"
                                            disabled={processing}
                                            className="group inline-flex h-14 w-full items-center justify-center gap-2 rounded-2xl border border-[#ff9b56]/70 bg-[linear-gradient(135deg,#ff6a00,#ff8c42)] text-lg font-bold text-white shadow-[0_0_0_1px_rgba(255,147,77,0.3),0_12px_30px_rgba(255,111,21,0.28),inset_0_1px_0_rgba(255,255,255,0.25)] transition duration-300 hover:-translate-y-0.5 hover:shadow-[0_0_0_1px_rgba(255,147,77,0.38),0_18px_38px_rgba(255,111,21,0.34),0_0_22px_rgba(255,140,66,0.28)] disabled:cursor-not-allowed disabled:opacity-70"
                                        >
                                            <span>{processing ? 'Sending...' : 'Resend Verification Email'}</span>
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
                                            Wrong account?{' '}
                                            <Link
                                                href={route('logout')}
                                                method="post"
                                                as="button"
                                                className="font-semibold text-[#77a9ff] transition hover:text-[#9bc0ff]"
                                            >
                                                Log out
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
