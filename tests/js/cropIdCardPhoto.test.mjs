import assert from 'node:assert/strict';
import { test } from 'node:test';
import { cropIdCardPhoto } from '../../resources/js/Components/cropIdCardPhoto.js';

test('ID card crop exports the selected area as a 3:4 JPEG file', async () => {
    const calls = [];
    const originalImage = globalThis.Image;
    const originalDocument = globalThis.document;
    const originalFile = globalThis.File;

    class FakeImage {
        async decode() {}
    }

    class FakeFile {
        constructor(parts, name, options) {
            this.parts = parts;
            this.name = name;
            this.type = options.type;
        }
    }

    const canvas = {
        width: 0,
        height: 0,
        getContext: () => ({ drawImage: (...args) => calls.push(args) }),
        toBlob: (callback) => callback(new Blob(['jpeg'], { type: 'image/jpeg' })),
    };

    globalThis.Image = FakeImage;
    globalThis.File = FakeFile;
    globalThis.document = { createElement: () => canvas };

    try {
        const area = { x: 10, y: 20, width: 300, height: 400 };
        const file = await cropIdCardPhoto('blob:photo', area);

        assert.equal(canvas.width, 900);
        assert.equal(canvas.height, 1200);
        assert.equal(file.name, 'id-card-photo-3x4.jpg');
        assert.equal(file.type, 'image/jpeg');
        assert.deepEqual(calls[0].slice(1), [10, 20, 300, 400, 0, 0, 900, 1200]);
    } finally {
        globalThis.Image = originalImage;
        globalThis.File = originalFile;
        globalThis.document = originalDocument;
    }
});
