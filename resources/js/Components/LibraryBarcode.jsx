import JsBarcode from 'jsbarcode';
import { useEffect, useRef } from 'react';
import './library-barcode.css';

export default function LibraryBarcode({ value, className = '' }) {
    const barcode = useRef(null);

    useEffect(() => {
        if (!barcode.current || !value) return;

        JsBarcode(barcode.current, value, {
            format: 'CODE128A',
            width: 4,
            height: 130,
            margin: 0,
            marginLeft: 40,
            marginRight: 40,
            font: 'PhilosopherKaz',
            fontOptions: 'bold',
            fontSize: 45,
            textMargin: 0,
        });

        barcode.current.querySelectorAll('text').forEach((label) => {
            label.setAttribute('y', Number(label.getAttribute('y')) - 12);
        });
    }, [value]);

    if (!value) return null;

    return <svg ref={barcode} className={className} role="img" aria-label={`Штрихкод библиотеки ${value}`} />;
}
