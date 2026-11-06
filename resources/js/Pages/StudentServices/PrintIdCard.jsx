import { Head, Link } from '@inertiajs/react';
import LanguageDomTranslator from '@/i18n/LanguageDomTranslator';
import LibraryBarcode from '@/Components/LibraryBarcode';
import './print-id-card.css';

const displayDate = (date) => date ? date.slice(0, 7).split('-').reverse().join('/') : '';

export default function PrintIdCard({ card }) {
    const kazakh = card.education_language === 'kk';
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
            <LanguageDomTranslator />
            <Head title="Печать ID-карты" />
            <div className="id-print-toolbar">
                <Link href={route('student-services.show', card.id)} className="id-print-back">Назад к заявке</Link>
                <div className="id-print-actions">
                    <span>Лицевая и оборотная стороны · 2 страницы PDF</span>
                    <button type="button" onClick={printCard} className="id-print-button">Печать / PDF</button>
                </div>
            </div>
            <main className="id-card-sheet">
                <section className="id-card-panel" aria-label="Лицевая сторона ID-карты">
                    <h2 className="id-card-label">Лицевая сторона</h2>
                    <div className="id-card-art">
                        <img className="id-card-background" src="/images/id-card/ruFront.png" alt="" />
                        <div className={`id-card-university ${kazakh ? 'id-card-university-kz' : 'id-card-university-ru'}`}>
                            {kazakh ? 'АЛМАТЫ ТЕХНОЛОГИЯЛЫҚ УНИВЕРСИТЕТІ' : 'АЛМАТИНСКИЙ ТЕХНОЛОГИЧЕСКИЙ УНИВЕРСИТЕТ'}
                        </div>
                        <div className="id-card-type">Студент</div>
                        <img className="id-card-photo" src={route('student-services.id-card.photo', card.id)} alt="Фото студента" />
                        <div className="id-card-name" style={{ fontSize: longestName > 22 ? '3.4cqw' : '4.9cqw' }}>
                            {nameLines.map((line, index) => <div key={index}>{line}</div>)}
                        </div>
                        <div className="id-card-faculty">{card.faculty}</div>
                        <div className="id-card-specialty">{card.specialty}</div>
                        <div className="id-card-study-form">{kazakh ? 'Оқыту нысаны: Күндізгі бөлім' : 'Очная'}</div>
                        <div className="id-card-validity">{kazakh ? 'Жарамдылық мерзімі' : 'Срок действия'}: {validity}</div>
                        <div className="id-card-rector">Ректор: Кулажанов Т.К.</div>
                    </div>
                </section>
                <section className="id-card-panel" aria-label="Оборотная сторона ID-карты">
                    <h2 className="id-card-label">Оборотная сторона</h2>
                    <div className="id-card-art">
                        <img className="id-card-background" src={kazakh ? '/images/id-card/kzBack.png' : '/images/id-card/ruBack.png'} alt="" />
                        {card.library_rdr_id && <LibraryBarcode value={card.library_rdr_id} className="id-card-barcode" />}
                    </div>
                </section>
            </main>
        </div>
    );
}
