import { Link, usePoll } from '@inertiajs/react';

export default function ServiceIntegrationAlerts({ alerts }) {
    usePoll(60000, { only: ['serviceAlerts'] });

    if (!alerts.count) return null;

    return (
        <aside role="status" className="border-b border-[#dbe5f6] bg-[#edf3ff] px-4 py-3 text-sm text-[#274f93] sm:px-6 lg:px-8">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <p className="font-semibold"><span>Face ID: заявки ожидают отправки</span> — {alerts.count}</p>
                <Link href={alerts.url} className="font-semibold underline underline-offset-2 hover:text-[#355da8]">Открыть заявки</Link>
            </div>
            <details className="mt-2">
                <summary className="w-fit cursor-pointer font-medium">Последние заявки</summary>
                <ul className="mt-2 space-y-2">
                    {alerts.items.map((item) => (
                        <li key={item.id} className="break-words">
                            <Link href={item.url} className="font-semibold underline underline-offset-2">№{item.id} · {item.student_name}</Link>
                            <p className={item.error ? 'mt-0.5 text-red-800' : 'mt-0.5'}>{item.error || <span>Ожидает проверки и отправки специалистом.</span>}</p>
                        </li>
                    ))}
                </ul>
            </details>
        </aside>
    );
}
