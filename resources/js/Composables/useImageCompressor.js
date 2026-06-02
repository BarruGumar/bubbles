/**
 * Compress an image File/Blob in-browser via Canvas API before upload.
 * - Downscales to maxWidth/maxHeight if needed (never upscales)
 * - Converts to JPEG to remove PNG overhead
 * - Fills transparent areas with white (safe for screenshots)
 * - GIF and SVG are returned unchanged (can't recompress without side effects)
 * - If canvas.toBlob fails for any reason, returns the original file unchanged
 *
 * Typical result: 4 MB PNG screenshot → 250–500 KB JPEG (80–95 % reduction)
 */
const COMPRESS_THRESHOLD = 1.5 * 1024 * 1024; // 1.5 MB

export async function compressImage(file, { maxWidth = 1200, maxHeight = 1200, quality = 0.92 } = {}) {
    if (
        !file.type.startsWith('image/') ||
        file.type === 'image/gif' ||
        file.type === 'image/svg+xml' ||
        file.size <= COMPRESS_THRESHOLD
    ) {
        return file;
    }

    return new Promise((resolve) => {
        const objectUrl = URL.createObjectURL(file);
        const img = new Image();

        img.onload = () => {
            URL.revokeObjectURL(objectUrl);

            let { naturalWidth: w, naturalHeight: h } = img;

            if (w > maxWidth || h > maxHeight) {
                const ratio = Math.min(maxWidth / w, maxHeight / h);
                w = Math.round(w * ratio);
                h = Math.round(h * ratio);
            }

            const canvas = document.createElement('canvas');
            canvas.width = w;
            canvas.height = h;

            const ctx = canvas.getContext('2d');
            // White background so transparent PNGs don't become black in JPEG
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, w, h);
            ctx.drawImage(img, 0, 0, w, h);

            // Prefer WebP (~25 % smaller than JPEG at equal quality, supported everywhere modern)
            canvas.toBlob(
                (webpBlob) => {
                    if (webpBlob && webpBlob.type === 'image/webp') {
                        const name = file.name.replace(/\.[^.]+$/, '') + '.webp';
                        resolve(new File([webpBlob], name, { type: 'image/webp', lastModified: Date.now() }));
                        return;
                    }
                    // Browser doesn't support WebP output → fall back to JPEG
                    canvas.toBlob(
                        (jpgBlob) => {
                            if (!jpgBlob) { resolve(file); return; }
                            const name = file.name.replace(/\.[^.]+$/, '') + '.jpg';
                            resolve(new File([jpgBlob], name, { type: 'image/jpeg', lastModified: Date.now() }));
                        },
                        'image/jpeg',
                        quality,
                    );
                },
                'image/webp',
                quality,
            );
        };

        img.onerror = () => {
            URL.revokeObjectURL(objectUrl);
            resolve(file); // fallback: send original
        };

        img.src = objectUrl;
    });
}
