import { useState, FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';

export default function BotMessagesUnlock() {
    const { data, setData, post, processing, errors } = useForm({ password: '' });
    const [showPass, setShowPass] = useState(false);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/bot/messages/unlock');
    };

    return (
        <>
            <Head title="Acceso — Mensajes del Bot" />
            <div className="flex min-h-screen items-center justify-center bg-gray-50 dark:bg-neutral-950 px-4">
                <div className="w-full max-w-sm">

                    {/* Card */}
                    <div className="rounded-2xl border border-sidebar-border/70 bg-white shadow-sm dark:bg-neutral-900 dark:border-neutral-700 overflow-hidden">

                        {/* Header */}
                        <div className="px-6 pt-6 pb-4 border-b border-gray-100 dark:border-neutral-800">
                            <div className="flex items-center gap-3 mb-1">
                                <div className="size-9 rounded-xl bg-[#075e54]/10 flex items-center justify-center">
                                    <svg className="size-5 text-[#075e54]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                </div>
                                <div>
                                    <h1 className="text-base font-semibold text-gray-900 dark:text-neutral-100">Mensajes del Bot</h1>
                                    <p className="text-xs text-gray-500 dark:text-neutral-400">Acceso protegido</p>
                                </div>
                            </div>
                        </div>

                        {/* Form */}
                        <form onSubmit={submit} className="px-6 py-5 space-y-4">
                            <div>
                                <label className="block text-xs font-medium text-gray-700 dark:text-neutral-300 mb-1.5">
                                    Contraseña
                                </label>
                                <div className="relative">
                                    <input
                                        type={showPass ? 'text' : 'password'}
                                        value={data.password}
                                        onChange={e => setData('password', e.target.value)}
                                        autoFocus
                                        autoComplete="current-password"
                                        className={`w-full rounded-lg border px-3 py-2 pr-10 text-sm outline-none transition-colors
                                            dark:bg-neutral-800 dark:text-neutral-100
                                            ${errors.password
                                                ? 'border-red-400 focus:ring-2 focus:ring-red-300'
                                                : 'border-gray-300 dark:border-neutral-600 focus:ring-2 focus:ring-[#25d366]/40 focus:border-[#25d366]'
                                            }`}
                                        placeholder="••••••••"
                                    />
                                    <button
                                        type="button"
                                        onClick={() => setShowPass(v => !v)}
                                        className="absolute inset-y-0 right-2.5 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-neutral-300"
                                        tabIndex={-1}
                                    >
                                        {showPass ? (
                                            <svg className="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                            </svg>
                                        ) : (
                                            <svg className="size-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        )}
                                    </button>
                                </div>
                                {errors.password && (
                                    <p className="mt-1.5 text-xs text-red-500">{errors.password}</p>
                                )}
                            </div>

                            <button
                                type="submit"
                                disabled={processing || !data.password}
                                className="w-full rounded-lg bg-[#075e54] py-2 text-sm font-semibold text-white hover:bg-[#0a7060] transition-colors disabled:opacity-50"
                            >
                                {processing ? 'Verificando…' : 'Ingresar'}
                            </button>
                        </form>

                        {/* Footer note */}
                        <div className="px-6 pb-5">
                            <p className="text-[11px] text-gray-400 dark:text-neutral-500 text-center leading-relaxed">
                                La contraseña es válida por 1 hora.
                                <br />
                                Pasado ese tiempo se pedirá nuevamente.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
