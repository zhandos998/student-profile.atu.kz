import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import LibraryBarcode from '@/Components/LibraryBarcode';
import { Head, Link, useForm } from '@inertiajs/react';

const labels = {
    full_name: 'ФИО',
    iin: 'ИИН',
    phone: 'Номер телефона',
    study_start_date: 'Начало обучения',
    study_end_date: 'Конец обучения',
    course: 'Курс',
    education_language: 'Язык обучения',
    education_level: 'Уровень образования',
    faculty: 'Факультет',
    specialty: 'Специальность',
};

const faceIdLabels = {
    iin: 'ИИН',
    last_name: 'Фамилия',
    first_name: 'Имя',
    middle_name: 'Отчество',
    faculty: 'Факультет',
    course: 'Курс',
    education_level: 'Уровень образования',
};

const legacyFaceIdLabels = {
    iin: 'ИИН',
    full_name: 'ФИО',
    faculty: 'Факультет',
    course: 'Курс',
    education_level: 'Уровень образования',
};

const statuses = { pending: 'Ожидает', in_progress: 'В работе', completed: 'Выполнено', rejected: 'Отказано' };
const languages = { kk: 'Казахский', ru: 'Русский', en: 'Английский' };
const educationLevels = { bachelor: 'Бакалавриат', master: 'Магистратура', doctorate: 'Докторантура' };
const displayValue = (request, key) => {
    const value = request[key];
    if (!value) return 'Не указано';
    if (key === 'education_language') return languages[value] || value;
    if (key === 'education_level') return educationLevels[value] || value;
    if (['study_start_date', 'study_end_date'].includes(key)) return value.slice(0, 7).split('-').reverse().join('.');
    return value;
};

export default function Show({ serviceRequest, canManage, canPrint, canRequestLibraryBarcode, libraryDates }) {
    const isIdCard = serviceRequest.service_type === 'id_card';
    const visibleFaceIdLabels = serviceRequest.last_name || serviceRequest.first_name ? faceIdLabels : legacyFaceIdLabels;
    const title = isIdCard ? 'Заявка на ID-карту' : 'Заявка на Face ID';
    const barcodeForm = useForm({ regdate: libraryDates.regdate, reregdate: libraryDates.reregdate });

    const requestBarcode = (event) => {
        event.preventDefault();
        barcodeForm.post(route('student-services.library-barcode.store', serviceRequest.id), { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold text-gray-800">{title}</h2>}>
            <Head title={title} />
            <div className="bg-[#f4f7fc] px-4 py-8 sm:px-6 lg:px-8">
                <div className="w-full space-y-6">
                    <Link href={route('student-services.index')} className="text-sm font-medium text-[#355da8] hover:underline">Назад к услугам</Link>
                    <section className="overflow-hidden rounded-lg bg-white shadow-sm ring-1 ring-gray-200/80">
                        <h3 className="border-b border-[#dbe5f6] bg-[#edf3ff] px-5 py-4 text-base font-semibold text-[#274f93]">{title}</h3>
                        <div className="space-y-5 p-5 sm:p-6">
                            <div className="flex flex-wrap gap-x-8 gap-y-2 text-sm">
                                <p><span className="text-gray-500">Статус: </span><span className="font-semibold text-gray-900">{statuses[serviceRequest.status]}</span></p>
                                {canManage && <p><span className="text-gray-500">Студент: </span><span className="font-semibold text-gray-900">{serviceRequest.student_name}</span></p>}
                            </div>
                            {isIdCard && (
                                <>
                                    <dl className="grid gap-x-6 gap-y-4 border-t border-gray-100 pt-5 sm:grid-cols-2 lg:grid-cols-3">
                                        {Object.entries(labels).map(([key, label]) => (
                                            <div key={key} className="min-w-0">
                                                <dt className="text-xs text-gray-500">{label}</dt>
                                                <dd className="mt-1 break-words text-sm font-medium text-gray-900">
                                                    {displayValue(serviceRequest, key)}
                                                </dd>
                                            </div>
                                        ))}
                                    </dl>
                                    {serviceRequest.library_rdr_id && (
                                        <div className="border-t border-gray-100 pt-5">
                                            <p className="mb-3 text-sm font-semibold text-gray-900">Штрихкод библиотеки</p>
                                            <LibraryBarcode value={serviceRequest.library_rdr_id} className="h-auto max-w-full" />
                                        </div>
                                    )}
                                    {canRequestLibraryBarcode && (
                                        <form onSubmit={requestBarcode} className="space-y-4 border-t border-gray-100 pt-5">
                                            <h4 className="text-sm font-semibold text-gray-900">Получить штрихкод библиотеки</h4>
                                            <div className="grid gap-4 sm:grid-cols-2">
                                                <label className="text-sm font-medium text-gray-800">
                                                    Дата регистрации
                                                    <input type="date" required value={barcodeForm.data.regdate} onChange={(event) => barcodeForm.setData('regdate', event.target.value)} className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#355da8] focus:ring-[#355da8]" />
                                                    {barcodeForm.errors.regdate && <span className="mt-1 block text-xs text-red-700">{barcodeForm.errors.regdate}</span>}
                                                </label>
                                                <label className="text-sm font-medium text-gray-800">
                                                    Дата перерегистрации
                                                    <input type="date" required value={barcodeForm.data.reregdate} onChange={(event) => barcodeForm.setData('reregdate', event.target.value)} className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-[#355da8] focus:ring-[#355da8]" />
                                                    {barcodeForm.errors.reregdate && <span className="mt-1 block text-xs text-red-700">{barcodeForm.errors.reregdate}</span>}
                                                </label>
                                            </div>
                                            {barcodeForm.errors.library_barcode && <p role="alert" className="text-sm text-red-700">{barcodeForm.errors.library_barcode}</p>}
                                            <button type="submit" disabled={barcodeForm.processing} className="rounded-md bg-[#355da8] px-4 py-2 text-sm font-semibold text-white hover:bg-[#2f5192] disabled:opacity-50">Получить штрихкод</button>
                                        </form>
                                    )}
                                    <div className="flex flex-wrap gap-3 border-t border-gray-100 pt-5">
                                        {canPrint && <a href={route('student-services.id-card.print', serviceRequest.id)} target="_blank" rel="noopener noreferrer" className="rounded-md bg-[#355da8] px-3 py-2 text-sm font-semibold text-white hover:bg-[#2f5192]">Печать ID-карты</a>}
                                        {serviceRequest.has_photo && <a href={route('student-services.download', [serviceRequest.id, 'photo'])} className="rounded-md border border-[#c9d8f0] px-3 py-2 text-sm font-semibold text-[#274f93] hover:bg-[#edf3ff]">Скачать фото 3×4</a>}
                                        {serviceRequest.has_receipt && <a href={route('student-services.download', [serviceRequest.id, 'receipt'])} className="rounded-md border border-[#c9d8f0] px-3 py-2 text-sm font-semibold text-[#274f93] hover:bg-[#edf3ff]">Скачать квитанцию</a>}
                                    </div>
                                </>
                            )}
                            {!isIdCard && (
                                <>
                                    <dl className="grid gap-x-6 gap-y-4 border-t border-gray-100 pt-5 sm:grid-cols-2 lg:grid-cols-3">
                                        {Object.entries(visibleFaceIdLabels).map(([key, label]) => (
                                            <div key={key} className="min-w-0">
                                                <dt className="text-xs text-gray-500">{label}</dt>
                                                <dd className="mt-1 break-words text-sm font-medium text-gray-900">{displayValue(serviceRequest, key)}</dd>
                                            </div>
                                        ))}
                                    </dl>
                                    {serviceRequest.hikvision_synced_at && (
                                        <p className="border-t border-gray-100 pt-4 text-sm font-medium text-emerald-800">
                                            Face ID передан в Hikvision.
                                        </p>
                                    )}
                                    {serviceRequest.has_photo && (
                                        <div className="border-t border-gray-100 pt-5">
                                            <a href={route('student-services.download', [serviceRequest.id, 'photo'])} className="inline-flex rounded-md border border-[#c9d8f0] px-3 py-2 text-sm font-semibold text-[#274f93] hover:bg-[#edf3ff]">Скачать фото</a>
                                        </div>
                                    )}
                                </>
                            )}
                            {serviceRequest.student_comment && <p className="whitespace-pre-wrap text-sm text-gray-700">{serviceRequest.student_comment}</p>}
                            {serviceRequest.staff_comment && <p className="whitespace-pre-wrap border-l-2 border-[#c9d8f0] pl-3 text-sm text-gray-700">{serviceRequest.staff_comment}</p>}
                        </div>
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
