/**
 * Detects an image in a paste event and forwards it to the caller.
 * Text-only pastes are not interrupted.
 *
 * @param {Object} opts
 * @param {(file: File) => void} opts.onImage  - called when a valid image is found
 * @param {number}  [opts.maxKB=4096]          - max allowed file size in KiB
 * @param {(msg: string) => void} [opts.onError] - called when image is too large or invalid
 */
export function useClipboardImage({ onImage, maxKB = 4096, onError } = {}) {
    function handlePaste(e) {
        const items = e.clipboardData?.items;
        if (!items) return;

        for (const item of items) {
            if (item.kind !== 'file' || !item.type.startsWith('image/')) continue;

            const file = item.getAsFile();
            if (!file) continue;

            if (file.size > maxKB * 1024) {
                const label = maxKB >= 1024 ? `${Math.round(maxKB / 1024)} MB` : `${maxKB} KB`;
                onError?.(`Imagem demasiado grande. Máximo ${label}.`);
                e.preventDefault();
                return;
            }

            onImage?.(file);
            e.preventDefault();
            return;
        }
        // No image in clipboard → let default text paste proceed normally
    }

    return { handlePaste };
}
