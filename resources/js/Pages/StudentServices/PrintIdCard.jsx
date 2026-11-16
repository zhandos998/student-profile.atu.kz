import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import LibraryBarcode from '@/Components/LibraryBarcode';
import './print-id-card.css';

const displayDate = (date) => date ? date.slice(0, 7).split('-').reverse().join('/') : '';

export default function PrintIdCard({ card }) {
    const [printLanguage, setPrintLanguage] = useState(card.education_language === 'kk' ? 'kk' : 'ru');
    const kazakh = printLanguage === 'kk';
    const labels = kazakh ? {
        title: 'ID-картаны басып шығару',
        backToRequest: 'Өтінімге оралу',
        pages: 'Алдыңғы және артқы жағы · PDF-тің 2 беті',
        print: 'Басып шығару / PDF',
        front: 'Алдыңғы жағы',
        back: 'Артқы жағы',
        studentPhoto: 'Студенттің фотосуреті',
    } : {
        title: 'Печать ID-карты',
        backToRequest: 'Назад к заявке',
        pages: 'Лицевая и оборотная стороны · 2 страницы PDF',
        print: 'Печать / PDF',
        front: 'Лицевая сторона',
        back: 'Оборотная сторона',
        studentPhoto: 'Фото студента',
    };
    const nameParts = (card.full_name || '').trim().split(/\s+/u);
    const nameLines = [nameParts[0], nameParts[1], nameParts.slice(2).join(' ')].filter(Boolean);
    const longestName = Math.max(...nameLines.map((line) => line.length));
    const validity = [displayDate(card.study_start_date), displayDate(card.study_end_date)].filter(Boolean).join('-');
    const printCard = async () => {
        await document.fonts.ready;
        await Promise.all(Array.from(document.images, (image) => image.decode().catch(() => {})));
        window.print();
    };

    return (
        <div className="id-print-page">
            <Head title={card.iin || labels.title} />
            <div className="id-print-toolbar">
                <Link href={route('student-services.show', card.id)} className="id-print-back">{labels.backToRequest}</Link>
                <div className="id-print-actions">
                    <div className="id-print-language" role="group" aria-label="Язык ID-карты">
                        <button type="button" aria-pressed={printLanguage === 'ru'} onClick={() => setPrintLanguage('ru')}>Русский</button>
                        <button type="button" aria-pressed={printLanguage === 'kk'} onClick={() => setPrintLanguage('kk')}>Қазақша</button>
                    </div>
                    <span>{labels.pages}</span>
                    <button type="button" onClick={printCard} className="id-print-button">{labels.print}</button>
                </div>
            </div>
            <main className="id-card-sheet" lang={printLanguage}>
                <section className="id-card-panel" aria-label={`${labels.front} ID-карты`}>
                    <h2 className="id-card-label">{labels.front}</h2>
                    <div className="id-card-art">
                        <img className="id-card-background" src="/images/id-card/ruFront.png" alt="" />
                        <div className={`id-card-university ${kazakh ? 'id-card-university-kz' : 'id-card-university-ru'}`}>
                            {kazakh ? 'АЛМАТЫ ТЕХНОЛОГИЯЛЫҚ УНИВЕРСИТЕТІ' : 'АЛМАТИНСКИЙ ТЕХНОЛОГИЧЕСКИЙ УНИВЕРСИТЕТ'}
                        </div>
                        <div className="id-card-type">Студент</div>
                        <img className="id-card-photo" src={route('student-services.id-card.photo', card.id)} alt={labels.studentPhoto} />
                        <div className="id-card-name" style={{ fontSize: longestName > 22 ? '3.4cqw' : '4.9cqw' }}>
                            {nameLines.map((line, index) => <div key={index}>{line}</div>)}
                        </div>
                        <div className="id-card-faculty">{kazakh ? card.faculty_kk : card.faculty_ru}</div>
                        <div className="id-card-specialty">{kazakh ? card.specialty_kk : card.specialty_ru}</div>
                        <div className="id-card-study-form">{kazakh ? 'Оқыту нысаны: Күндізгі бөлім' : 'Форма обучения: Очная'}</div>
                        <div className="id-card-validity">{kazakh ? 'Жарамдылық мерзімі' : 'Срок действия'}: {validity}</div>
                        <div className="id-card-rector">Ректор: Кулажанов Т.К.</div>
                    </div>
                </section>
                <section className="id-card-panel" aria-label={`${labels.back} ID-карты`}>
                    <h2 className="id-card-label">{labels.back}</h2>
                    <div className="id-card-art">
                        <img className="id-card-background" src={kazakh ? '/images/id-card/kzBack.png' : '/images/id-card/ruBack.png'} alt="" />
                        {card.library_rdr_id && <LibraryBarcode value={card.library_rdr_id} className="id-card-barcode" />}
                    </div>
                </section>
            </main>
        </div>
    );
}
