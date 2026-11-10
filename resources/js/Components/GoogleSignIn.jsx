import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

let sdkPromise;

function loadGoogle() {
    if (window.google?.accounts?.id) return Promise.resolve(window.google.accounts.id);

    if (!sdkPromise) {
        sdkPromise = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            const timeout = window.setTimeout(() => fail(), 15000);
            const fail = () => {
                window.clearTimeout(timeout);
                script.remove();
                sdkPromise = null;
                reject(new Error('Google SDK unavailable'));
            };

            script.src = 'https://accounts.google.com/gsi/client';
            script.async = true;
            script.onload = () => {
                window.clearTimeout(timeout);
                if (window.google?.accounts?.id) resolve(window.google.accounts.id);
                else fail();
            };
            script.onerror = fail;
            document.head.appendChild(script);
        });
    }

    return sdkPromise;
}

export default function GoogleSignIn() {
    const target = useRef(null);
    const submitting = useRef(false);
    const [enabled, setEnabled] = useState(false);
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);
    const [retry, setRetry] = useState(0);

    useEffect(() => {
        let active = true;
        let refreshTimer;

        const initialize = async () => {
            const response = await fetch(route('login.google.challenge'), {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) throw new Error('Google challenge unavailable');

            const challenge = await response.json();
            if (!active || !challenge.enabled) return;

            const google = await loadGoogle();
            if (!active || !target.current) return;

            google.initialize({
                client_id: challenge.client_id,
                nonce: challenge.nonce,
                auto_select: false,
                context: 'signin',
                callback: ({ credential }) => {
                    if (!active || !credential || submitting.current) return;

                    submitting.current = true;
                    setBusy(true);
                    setError('');
                    router.post(route('login.google'), {
                        credential,
                        nonce: challenge.nonce,
                    }, {
                        preserveScroll: true,
                        onError: (errors) => {
                            if (!active) return;
                            setError(errors.google || 'Не удалось войти через Google. Попробуйте ещё раз.');
                            setRetry((value) => value + 1);
                        },
                        onFinish: () => {
                            submitting.current = false;
                            if (active) setBusy(false);
                        },
                    });
                },
            });

            setEnabled(true);
            await new Promise((resolve) => window.requestAnimationFrame(resolve));
            if (!active || !target.current) return;

            target.current.replaceChildren();
            google.renderButton(target.current, {
                type: 'standard',
                theme: 'outline',
                size: 'large',
                shape: 'rectangular',
                text: 'signin_with',
                locale: document.documentElement.lang === 'kk' ? 'kk' : 'ru',
                width: Math.min(360, target.current.clientWidth || 320),
            });

            refreshTimer = window.setTimeout(() => setRetry((value) => value + 1), 240000);
        };

        initialize().catch(() => {
            if (active) setError('Вход через Google сейчас недоступен.');
        });

        return () => {
            active = false;
            window.clearTimeout(refreshTimer);
        };
    }, [retry]);

    return (
        <div className={enabled || error ? 'mt-5 space-y-3' : 'hidden'}>
            {enabled && <div className="flex items-center gap-3 text-xs text-gray-400">
                <span className="h-px flex-1 bg-gray-200" />
                или
                <span className="h-px flex-1 bg-gray-200" />
            </div>}
            <div ref={target} className={`${enabled ? 'flex min-h-10 justify-center' : 'hidden'} ${busy ? 'pointer-events-none opacity-60' : ''}`} />
            {error && <p role="alert" className="text-center text-sm text-red-700">{error}</p>}
        </div>
    );
}
