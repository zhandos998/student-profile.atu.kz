import Modal from '@/Components/Modal';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

const inputClass = 'w-full rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-[#355da8] focus:outline-none focus:ring-1 focus:ring-[#355da8]';
const primaryClass = 'rounded bg-[#355da8] px-4 py-2 text-sm font-semibold text-white hover:bg-[#284e91] disabled:opacity-50';
const secondaryClass = 'rounded border border-[#cbd7ec] bg-white px-4 py-2 text-sm font-medium text-[#244983] hover:bg-[#edf3ff]';

export default function Index({ faculties, specialties, filters }) {
    const [tab, setTab] = useState('specialties');
    const [query, setQuery] = useState(filters);
    const [editing, setEditing] = useState(null);
    const [confirming, setConfirming] = useState(null);
    const form = useForm({ faculty_id: '', code: '', name_ru: '', name_kk: '', name_en: '' });

    const openNew = () => {
        form.setData({ faculty_id: '', code: '', name_ru: '', name_kk: '', name_en: '' });
        form.clearErrors();
        setEditing({ kind: 'new' });
    };

    const openSpecialty = (item) => {
        form.setData({ faculty_id: item.faculty_id || '', code: item.code || '', name_ru: item.name_ru || '', name_kk: item.name_kk || '', name_en: item.name_en || '' });
        form.clearErrors();
        setEditing({ kind: 'specialty', id: item.id });
    };

    const openFaculty = (item) => {
        form.setData({ faculty_id: '', code: '', name_ru: item.name_ru || '', name_kk: item.name_kk || '', name_en: item.name_en || '' });
        form.clearErrors();
        setEditing({ kind: 'faculty', id: item.id });
    };

    const save = (event) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: () => setEditing(null) };
        if (editing.kind === 'new') form.post(route('academic-directory.specialties.store'), options);
        if (editing.kind === 'specialty') form.patch(route('academic-directory.specialties.update', editing.id), options);
        if (editing.kind === 'faculty') form.patch(route('academic-directory.faculties.update', editing.id), options);
    };

    const toggle = () => {
        router.patch(route('academic-directory.specialties.visibility', confirming.id), {}, {
            preserveScroll: true,
            onSuccess: () => setConfirming(null),
        });
    };

    return <AuthenticatedLayout wide header={<div className="flex flex-wrap items-center justify-between gap-4"><div><h1 className="text-xl font-semibold text-gray-900">Справочник</h1><p className="mt-1 text-sm text-gray-500">Факультеты и специальности</p></div>{tab === 'specialties' && <button type="button" onClick={openNew} className={primaryClass}>Добавить специальность</button>}</div>}>
        <Head title="Справочник" />
        <div className="w-full space-y-5 px-4 py-6 sm:px-6 lg:px-8">
            <div className="flex gap-1 border-b border-[#dbe5f6]" role="tablist">
                {[['specialties', 'Специальности'], ['faculties', 'Факультеты']].map(([key, label]) => <button key={key} type="button" role="tab" aria-selected={tab === key} onClick={() => setTab(key)} className={`border-b-2 px-4 py-3 text-sm font-medium ${tab === key ? 'border-[#355da8] text-[#244983]' : 'border-transparent text-gray-500 hover:text-gray-800'}`}>{label}</button>)}
            </div>

            {tab === 'specialties' ? <>
                <form onSubmit={(event) => { event.preventDefault(); router.get(route('academic-directory.index'), query, { preserveScroll: true }); }} className="grid gap-3 rounded border border-[#dbe5f6] bg-white p-4 sm:grid-cols-[minmax(220px,1fr)_minmax(200px,280px)_minmax(150px,200px)_auto] sm:items-end">
                    <label className="text-sm font-medium text-gray-800">Поиск<input className={`${inputClass} mt-1`} value={query.search} onChange={(event) => setQuery({ ...query, search: event.target.value })} placeholder="Код или название" /></label>
                    <label className="text-sm font-medium text-gray-800">Факультет<select className={`${inputClass} mt-1`} value={query.faculty_id} onChange={(event) => setQuery({ ...query, faculty_id: event.target.value })}><option value="">Все факультеты</option>{faculties.map((faculty) => <option key={faculty.id} value={faculty.id}>{faculty.name_ru}</option>)}</select></label>
                    <label className="text-sm font-medium text-gray-800">Показать<select className={`${inputClass} mt-1`} value={query.status} onChange={(event) => setQuery({ ...query, status: event.target.value })}><option value="active">Активные</option><option value="hidden">Скрытые</option><option value="all">Все</option></select></label>
                    <button className={primaryClass} type="submit">Найти</button>
                </form>
                <div className="overflow-x-auto rounded border border-[#dbe5f6] bg-white">
                    <table className="w-full min-w-[850px] text-left text-sm"><thead className="border-b border-[#dbe5f6] bg-[#edf3ff] text-[#244983]"><tr><th className="px-4 py-3 font-semibold">Код</th><th className="px-4 py-3 font-semibold">Русский</th><th className="px-4 py-3 font-semibold">Қазақша</th><th className="px-4 py-3 font-semibold">Факультет</th><th className="px-4 py-3 font-semibold">Статус</th><th className="px-4 py-3 text-right font-semibold">Действия</th></tr></thead><tbody className="divide-y divide-gray-100">
                        {specialties.data.map((item) => <tr key={item.id}><td className="px-4 py-3 font-medium text-gray-900">{item.code || '—'}</td><td className="px-4 py-3 text-gray-900">{item.name_ru}</td><td className="px-4 py-3 text-gray-600">{item.name_kk || '—'}</td><td className="px-4 py-3 text-gray-600">{item.faculty?.name_ru || '—'}</td><td className="px-4 py-3"><span className={item.is_active ? 'text-emerald-700' : 'text-gray-500'}>{item.is_active ? 'Активна' : 'Скрыта'}</span></td><td className="whitespace-nowrap px-4 py-3 text-right"><button type="button" onClick={() => openSpecialty(item)} className="mr-2 text-[#355da8] hover:underline">Изменить</button><button type="button" onClick={() => setConfirming(item)} className={item.is_active ? 'text-red-700 hover:underline' : 'text-[#355da8] hover:underline'}>{item.is_active ? 'Скрыть' : 'Показать'}</button></td></tr>)}
                        {specialties.data.length === 0 && <tr><td colSpan="6" className="px-4 py-10 text-center text-gray-500">Специальности не найдены.</td></tr>}
                    </tbody></table>
                </div>
                {specialties.links.length > 3 && <nav className="flex flex-wrap gap-2" aria-label="Страницы">{specialties.links.map((link, index) => {
                    const label = index === 0 ? '‹' : index === specialties.links.length - 1 ? '›' : link.label;
                    return link.url ? <Link key={index} href={link.url} preserveScroll className={`rounded border px-3 py-2 text-sm ${link.active ? 'border-[#355da8] bg-[#355da8] text-white' : 'border-[#cbd7ec] bg-white text-[#244983]'}`}>{label}</Link> : <span key={index} className="rounded border border-gray-200 px-3 py-2 text-sm text-gray-400">{label}</span>;
                })}</nav>}
            </> : <div className="overflow-x-auto rounded border border-[#dbe5f6] bg-white"><table className="w-full min-w-[750px] text-left text-sm"><thead className="border-b border-[#dbe5f6] bg-[#edf3ff] text-[#244983]"><tr><th className="px-4 py-3">Русский</th><th className="px-4 py-3">Қазақша</th><th className="px-4 py-3">English</th><th className="px-4 py-3 text-right">Действие</th></tr></thead><tbody className="divide-y divide-gray-100">{faculties.map((item) => <tr key={item.id}><td className="px-4 py-3">{item.name_ru}</td><td className="px-4 py-3">{item.name_kk || '—'}</td><td className="px-4 py-3">{item.name_en || '—'}</td><td className="px-4 py-3 text-right"><button type="button" onClick={() => openFaculty(item)} className="text-[#355da8] hover:underline">Изменить перевод</button></td></tr>)}</tbody></table></div>}
        </div>

        <Modal show={Boolean(editing)} onClose={() => setEditing(null)} maxWidth="lg"><form onSubmit={save} className="p-6"><h2 className="text-lg font-semibold text-gray-900">{editing?.kind === 'faculty' ? 'Перевод факультета' : editing?.kind === 'new' ? 'Новая специальность' : 'Специальность'}</h2><div className="mt-5 space-y-4">
            {editing?.kind !== 'faculty' && <><label className="block text-sm font-medium">Факультет<select className={`${inputClass} mt-1`} value={form.data.faculty_id} onChange={(event) => form.setData('faculty_id', event.target.value)}><option value="">Не привязан</option>{faculties.map((item) => <option key={item.id} value={item.id}>{item.name_ru}</option>)}</select>{form.errors.faculty_id && <span className="text-red-600">{form.errors.faculty_id}</span>}</label><label className="block text-sm font-medium">Код<input className={`${inputClass} mt-1`} value={form.data.code} onChange={(event) => form.setData('code', event.target.value)} disabled={editing?.kind !== 'new'} />{form.errors.code && <span className="text-red-600">{form.errors.code}</span>}</label></>}
            <label className="block text-sm font-medium">Название на русском<input className={`${inputClass} mt-1`} value={form.data.name_ru} onChange={(event) => form.setData('name_ru', event.target.value)} disabled={editing?.kind === 'faculty'} />{form.errors.name_ru && <span className="text-red-600">{form.errors.name_ru}</span>}</label>
            <label className="block text-sm font-medium">Название на казахском<input className={`${inputClass} mt-1`} value={form.data.name_kk} onChange={(event) => form.setData('name_kk', event.target.value)} />{form.errors.name_kk && <span className="text-red-600">{form.errors.name_kk}</span>}</label>
            <label className="block text-sm font-medium">Название на английском<input className={`${inputClass} mt-1`} value={form.data.name_en} onChange={(event) => form.setData('name_en', event.target.value)} />{form.errors.name_en && <span className="text-red-600">{form.errors.name_en}</span>}</label>
        </div><div className="mt-6 flex justify-end gap-2"><button type="button" onClick={() => setEditing(null)} className={secondaryClass}>Отмена</button><button type="submit" disabled={form.processing} className={primaryClass}>Сохранить</button></div></form></Modal>
        <Modal show={Boolean(confirming)} onClose={() => setConfirming(null)} maxWidth="sm"><div className="p-6"><h2 className="text-lg font-semibold text-gray-900">{confirming?.is_active ? 'Скрыть специальность?' : 'Показать специальность?'}</h2><p className="mt-3 text-sm text-gray-600">{confirming?.name_ru}</p>{confirming?.is_active && <p className="mt-2 text-sm text-gray-600">Она исчезнет из списков выбора. Уже сохранённые данные останутся без изменений.</p>}<div className="mt-6 flex justify-end gap-2"><button type="button" onClick={() => setConfirming(null)} className={secondaryClass}>Отмена</button><button type="button" onClick={toggle} className={primaryClass}>{confirming?.is_active ? 'Скрыть' : 'Показать'}</button></div></div></Modal>
    </AuthenticatedLayout>;
}
