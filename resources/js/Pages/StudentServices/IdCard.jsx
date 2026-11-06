import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Modal from '@/Components/Modal';
import { Head, Link, useForm } from '@inertiajs/react';
import { QRCodeSVG } from 'qrcode.react';
import { useState } from 'react';

const kaspiPaymentUrl = 'https://kaspi.kz/pay/ATU?amount=2100';

const educationLevelForSpecialty = (specialty) => {
    const code = String(specialty ?? '').match(/^\s*\d?([mмdд])(?=\d)/iu)?.[1];

    if (!code) return 'bachelor';

    return /^[mм]$/iu.test(code) ? 'master' : 'doctorate';
};

const fields = [
    { name: 'full_name', label: 'ФИО' },
    { name: 'iin', label: 'ИИН', maxLength: 12, inputMode: 'numeric' },
    { name: 'phone', label: 'Номер телефона', type: 'tel' },
    { name: 'study_start_date', label: 'Начало обучения', type: 'month' },
    { name: 'study_end_date', label: 'Конец обучения', type: 'month' },
    { name: 'course', label: 'Курс', type: 'number', min: 1, max: 8 },
    { name: 'faculty', label: 'Факультет' },
    { name: 'specialty', label: 'Специальность' },
];

const paymentSteps = [
    'В приложении Kaspi.kz откройте «Платежи».',
    'В поиске напишите «АТУ».',
    'Выберите «АТУ» из результатов поиска.',
    'Заполните свои данные.',
    'Выберите назначение платежа «Студенческий билет».',
    'Оплатите 2 100 тенге и прикрепите квитанцию ниже.',
];

export default function IdCard({ initial, hasOpenRequest }) {
    const form = useForm({ ...initial, service_type: 'id_card', photo: null, receipt: null });
    const [showPaymentQr, setShowPaymentQr] = useState(false);

    const submit = (event) => {
        event.preventDefault();

        if (form.data.photo?.size > 16 * 1024 * 1024) {
            form.setError('photo', 'Фото должно быть не больше 16 МБ.');
            return;
        }
        if (form.data.receipt?.size > 32 * 1024 * 1024) {
            form.setError('receipt', 'Квитанция должна быть не больше 32 МБ.');
            return;
        }

        form.post(route('student-services.store'), { forceFormData: true });
    };

    const inputClass = 'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#355da8] focus:ring-[#355da8]';

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">Заявка на ID-карту</h2>}>
            <Head title="Заявка на ID-карту" />
            <div className="bg-[#f4f7fc] px-4 py-8 sm:px-6 lg:px-8">
                <div className="w-full space-y-6">
                    <Link href={route('student-services.index')} className="text-sm font-medium text-[#355da8] hover:underline">Назад к услугам</Link>
                    {hasOpenRequest ? (
                        <section className="rounded-lg bg-white p-6 shadow-sm ring-1 ring-gray-200/80">
                            <p className="font-medium text-gray-900">Заявка на ID-карту уже подана.</p>
                            <p className="mt-2 text-sm text-gray-600">Её статус можно посмотреть в разделе «Услуги».</p>
                        </section>
                    ) : (
                        <form onSubmit={submit} className="space-y-6">
                            <section className="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200/80">
                                <h3 className="border-b border-[#dbe5f6] bg-[#edf3ff] px-5 py-4 text-base font-semibold text-[#274f93]">Данные студента</h3>
                                <div className="grid gap-5 p-5 sm:grid-cols-2 sm:p-6 lg:grid-cols-3">
                                    {fields.map((field) => (
                                        <label key={field.name} className="block text-sm font-medium text-gray-800">
                                            {field.label}
                                            <input
                                                type={field.type || 'text'}
                                                value={form.data[field.name] ?? ''}
                                                onChange={(event) => {
                                                    if (field.name === 'specialty') {
                                                        const specialty = event.target.value;
                                                        form.setData({
                                                            ...form.data,
                                                            specialty,
                                                            education_level: educationLevelForSpecialty(specialty),
                                                        });
                                                    } else {
                                                        form.setData(field.name, event.target.value);
                                                    }
                                                }}
                                                required={field.required !== false}
                                                maxLength={field.maxLength}
                                                inputMode={field.inputMode}
                                                min={field.min}
                                                max={field.max}
                                                className={inputClass}
                                            />
                                            {form.errors[field.name] && <span className="mt-1 block text-xs text-red-700">{form.errors[field.name]}</span>}
                                        </label>
                                    ))}
                                    <label className="block text-sm font-medium text-gray-800">
                                        Уровень образования
                                        <select value={form.data.education_level || 'bachelor'} onChange={(event) => form.setData('education_level', event.target.value)} required className={inputClass}>
                                            <option value="bachelor">Бакалавриат</option>
                                            <option value="master">Магистратура</option>
                                            <option value="doctorate">Докторантура</option>
                                        </select>
                                        {form.errors.education_level && <span className="mt-1 block text-xs text-red-700">{form.errors.education_level}</span>}
                                    </label>
                                    <label className="block text-sm font-medium text-gray-800">
                                        Язык обучения
                                        <select value={form.data.education_language} onChange={(event) => form.setData('education_language', event.target.value)} required className={inputClass}>
                                            <option value="">Выберите язык</option>
                                            <option value="kk">Казахский</option>
                                            <option value="ru">Русский</option>
                                            <option value="en">Английский</option>
                                        </select>
                                        {form.errors.education_language && <span className="mt-1 block text-xs text-red-700">{form.errors.education_language}</span>}
                                    </label>
                                </div>
                            </section>
                            <section className="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200/80">
                                <h3 className="border-b border-[#dbe5f6] bg-[#edf3ff] px-5 py-4 text-base font-semibold text-[#274f93]">Фото и оплата</h3>
                                <div className="space-y-5 p-5 sm:p-6">
                                    <label className="block text-sm font-medium text-gray-800">
                                        Фото студента для ID-карты (3×4)
                                        <input type="file" accept="image/jpeg,image/png" required onChange={(event) => { form.setData('photo', event.target.files[0] || null); form.clearErrors('photo'); }} className="mt-2 block w-full text-sm text-gray-700" />
                                        <span className="mt-1 block text-xs text-gray-500">JPG или PNG, до 16 МБ.</span>
                                        {form.errors.photo && <span className="mt-1 block text-xs text-red-700">{form.errors.photo}</span>}
                                    </label>
                                    <div className="border-t border-gray-100 pt-5">
                                        <h4 className="text-sm font-semibold text-gray-900">Оплата через Kaspi.kz</h4>
                                        <ol className="mt-3 list-decimal space-y-2 pl-5 text-sm text-gray-700">
                                            {paymentSteps.map((step) => <li key={step}>{step}</li>)}
                                        </ol>
                                        <p className="mt-3 text-sm text-gray-600">Прикрепите квитанцию из Kaspi.kz в PDF или скриншот оплаты.</p>
                                    </div>
                                    <div className="space-y-3">
                                        <button type="button" onClick={() => setShowPaymentQr(true)} className="rounded-md bg-[#e84234] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-[#cf352a] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#e84234]">
                                            Оплата Kaspi
                                        </button>
                                        <label className="block text-sm font-medium text-gray-800">
                                            Квитанция об оплате
                                            <input type="file" accept="application/pdf,image/jpeg,image/png" required onChange={(event) => { form.setData('receipt', event.target.files[0] || null); form.clearErrors('receipt'); }} className="mt-2 block w-full text-sm text-gray-700" />
                                            <span className="mt-1 block text-xs text-gray-500">PDF, JPG или PNG, до 32 МБ.</span>
                                            {form.errors.receipt && <span className="mt-1 block text-xs text-red-700">{form.errors.receipt}</span>}
                                        </label>
                                    </div>
                                </div>
                            </section>
                            <Modal show={showPaymentQr} onClose={() => setShowPaymentQr(false)} maxWidth="sm">
                                <div className="border-b border-[#dbe5f6] bg-[#edf3ff] px-5 py-4">
                                    <h3 className="text-base font-semibold text-[#274f93]">Оплата Kaspi</h3>
                                </div>
                                <div className="flex flex-col items-center gap-4 px-5 py-6 text-center">
                                    <QRCodeSVG value={kaspiPaymentUrl} size={208} level="M" marginSize={2} title="Ссылка на оплату Kaspi" />
                                    <p className="text-sm text-gray-600">Сканируйте QR-код или откройте ссылку на телефоне. После оплаты прикрепите квитанцию.</p>
                                    <a href={kaspiPaymentUrl} target="_blank" rel="noopener noreferrer" className="rounded-md bg-[#e84234] px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-[#cf352a] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#e84234]">
                                        Открыть Kaspi.kz
                                    </a>
                                    <button type="button" onClick={() => setShowPaymentQr(false)} className="text-sm font-medium text-gray-600 hover:text-gray-900">Закрыть</button>
                                </div>
                            </Modal>
                            {form.errors.service_type && <p role="alert" className="text-sm text-red-700">{form.errors.service_type}</p>}
                            <div className="flex justify-end">
                                <button type="submit" disabled={form.processing} className="rounded-md bg-[#355da8] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[#2f5192] disabled:opacity-50">
                                    Отправить заявку
                                </button>
                            </div>
                        </form>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
