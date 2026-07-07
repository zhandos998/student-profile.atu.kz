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
        if (!textOriginals.has(node)) {
            textOriginals.set(node, node.nodeValue);
        }

        const original = textOriginals.get(node);
        const translated = translateText(original, locale);

        if (node.nodeValue !== translated) {
            node.nodeValue = translated;
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

            if (!originals.has(attribute)) {
                originals.set(attribute, element.getAttribute(attribute));
            }

            const original = originals.get(attribute);
            const translated = translateText(original, locale);

            if (element.getAttribute(attribute) !== translated) {
                element.setAttribute(attribute, translated);
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
