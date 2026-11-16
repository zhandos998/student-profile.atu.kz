const PHOTO_WIDTH = 900;
const PHOTO_HEIGHT = 1200;

export async function cropIdCardPhoto(imageUrl, area) {
    const image = new Image();
    image.src = imageUrl;
    await image.decode();

    const canvas = document.createElement('canvas');
    canvas.width = PHOTO_WIDTH;
    canvas.height = PHOTO_HEIGHT;

    const context = canvas.getContext('2d');
    if (!context) {
        throw new Error('Canvas is unavailable.');
    }

    context.imageSmoothingEnabled = true;
    context.imageSmoothingQuality = 'high';
    context.drawImage(image, area.x, area.y, area.width, area.height, 0, 0, PHOTO_WIDTH, PHOTO_HEIGHT);

    const blob = await new Promise((resolve, reject) => {
        canvas.toBlob((result) => result ? resolve(result) : reject(new Error('Could not crop image.')), 'image/jpeg', 0.92);
    });

    return new File([blob], 'id-card-photo-3x4.jpg', { type: 'image/jpeg' });
}
