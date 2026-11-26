import { Link, usePoll } from '@inertiajs/react';

export default function ServiceIntegrationAlerts({ alerts }) {
    usePoll(60000, { only: ['serviceAlerts'] });

    if (!alerts.count) return null;

    return (
        <aside role="status" className="border-b border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900 sm:px-6 lg:px-8">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="font-semibold"><span>Face ID: не отправлено заявок</span> — {alerts.count}</p>
                <Link href={alerts.url} className="font-semibold underline underline-offset-2 hover:text-red-700">Открыть заявки</Link>
            </div>
            <details className="mt-2">
                <summary className="w-fit cursor-pointer font-medium">Последние ошибки</summary>
                <ul className="mt-2 space-y-2">
                    {alerts.items.map((item) => (
                        <li key={item.id} className="break-words">
                            <Link href={item.url} className="font-semibold underline underline-offset-2">№{item.id} · {item.student_name}</Link>
                            <p className="mt-0.5">{item.error || <span>Отправка в Hikvision не подтверждена.</span>}</p>
                        </li>
                    ))}
                </ul>
            </details>
        </aside>
    );
}
