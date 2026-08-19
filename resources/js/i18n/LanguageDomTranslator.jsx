import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { translateText } from './translations';

const translatedAttributes = ['placeholder', 'title', 'aria-label', 'alt'];
const skippedTags = new Set([
    'SCRIPT',
    'STYLE',
    'NOSCRIPT',
    'TEXTAREA',
    'CODE',
    'PRE',
]);

const textOriginals = new WeakMap();
const attributeOriginals = new WeakMap();

function canTranslateTextNode(node) {
    const parent = node.parentElement;

    return parent && !skippedTags.has(parent.tagName);
}

function applyTextTranslations(root, locale) {
    const walker = document.createTreeWalker(
        root,
        NodeFilter.SHOW_TEXT,
        {
            acceptNode: (node) =>
                canTranslateTextNode(node)
                    ? NodeFilter.FILTER_ACCEPT
                    : NodeFilter.FILTER_REJECT,
        },
    );

    let node = walker.nextNode();

    while (node) {
        const current = node.nodeValue;
        const cached = textOriginals.get(node);

        if (
            !cached ||
            (current !== cached.original && current !== cached.translated)
        ) {
            const original = current;
            textOriginals.set(node, {
                original,
                translated: translateText(original, 'kk'),
            });
        }

        const { original, translated } = textOriginals.get(node);
        const nextValue = locale === 'kk' ? translated : original;

        if (node.nodeValue !== nextValue) {
            node.nodeValue = nextValue;
        }

        node = walker.nextNode();
    }
}

function originalAttributesFor(element) {
    if (!attributeOriginals.has(element)) {
        attributeOriginals.set(element, new Map());
    }

    return attributeOriginals.get(element);
}

function applyAttributeTranslations(root, locale) {
    const selector = translatedAttributes
        .map((attribute) => `[${attribute}]`)
        .join(',');

    if (!selector) {
        return;
    }

    root.querySelectorAll(selector).forEach((element) => {
        if (skippedTags.has(element.tagName)) {
            return;
        }

        const originals = originalAttributesFor(element);

        translatedAttributes.forEach((attribute) => {
            if (!element.hasAttribute(attribute)) {
                return;
            }

            const current = element.getAttribute(attribute);
            const cached = originals.get(attribute);

            if (
                !cached ||
                (current !== cached.original && current !== cached.translated)
            ) {
                const original = current;
                originals.set(attribute, {
                    original,
                    translated: translateText(original, 'kk'),
                });
            }

            const { original, translated } = originals.get(attribute);
            const nextValue = locale === 'kk' ? translated : original;

            if (element.getAttribute(attribute) !== nextValue) {
                element.setAttribute(attribute, nextValue);
            }
        });
    });
}

function applyDocumentTranslations(locale) {
    if (typeof document === 'undefined' || !document.body) {
        return;
    }

    document.documentElement.lang = locale;
    applyTextTranslations(document.body, locale);
    applyAttributeTranslations(document.body, locale);
}

export default function LanguageDomTranslator() {
    const { locale = 'ru' } = usePage().props;

    useEffect(() => {
        let frame = null;

        const schedule = () => {
            if (frame) {
                cancelAnimationFrame(frame);
            }

            frame = requestAnimationFrame(() => {
                applyDocumentTranslations(locale);
            });
        };

        schedule();

        const observer = new MutationObserver(schedule);

        observer.observe(document.body, {
            childList: true,
            subtree: true,
            characterData: true,
            attributes: true,
            attributeFilter: translatedAttributes,
        });

        return () => {
            if (frame) {
                cancelAnimationFrame(frame);
            }

            observer.disconnect();
        };
    }, [locale]);

    return null;
}
