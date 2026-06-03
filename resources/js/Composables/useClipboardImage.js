/**
 * Detects an image in a paste event and forwards it to the caller.
 * Text-only pastes are not interrupted.
 *
 * When the clipboard HTML contains a .gif src, fetches the original animated
 * GIF via a server-side proxy (bypasses CORS). Falls back to the static PNG
 * the browser puts in the clipboard if the proxy fetch fails.
 *
 * @param {Object} opts
 * @param {(file: File) => void} opts.onImage  - called when a valid image is found
 * @param {number}  [opts.maxKB=4096]          - max allowed file size in KiB
 * @param {(msg: string) => void} [opts.onError] - called when image is too large or invalid
 */
export function useClipboardImage({ onImage, maxKB = 4096, onError } = {}) {
    function handlePaste(e) {
        const items = [...(e.clipboardData?.items ?? [])];
        const imgItem = items.find(i => i.kind === 'file' && i.type.startsWith('image/'));
        if (!imgItem) return; // no image in clipboard — let text paste proceed

        e.preventDefault();

        // Must call getAsFile() synchronously while the paste event is still active.
        // DataTransferItem clipboard data is cleared once the event handler returns,
        // so calling getAsFile() inside an async callback returns null.
        const fallbackFile = imgItem.getAsFile();

        const htmlItem = items.find(i => i.kind === 'string' && i.type === 'text/html');
        if (htmlItem) {
            htmlItem.getAsString(async (html) => {
                // Extract .gif URL from the img src in the HTML clipboard fragment
                const match = html.match(/src=["']([^"']+\.gif(?:[?#][^"']*)?)["']/i);
                if (match) {
                    try {
                        // Proxy through our server to bypass CORS restrictions
                        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
                        const resp = await fetch('/conversations/fetch-gif', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'image/gif',
                            },
                            body: JSON.stringify({ url: match[1] }),
                        });
                        if (resp.ok) {
                            const blob = await resp.blob();
                            const file = new File([blob], 'animation.gif', { type: 'image/gif' });
                            if (file.size > maxKB * 1024) {
                                onError?.(`GIF demasiado grande. Máximo ${Math.round(maxKB / 1024)} MB.`);
                            } else {
                                onImage?.(file);
                            }
                            return;
                        }
                    } catch {
                        // Network error — fall through to static PNG fallback
                    }
                }
                applyFallback(fallbackFile);
            });
        } else {
            applyFallback(fallbackFile);
        }
    }

    function applyFallback(file) {
        if (!file) return;
        if (file.size > maxKB * 1024) {
            const label = maxKB >= 1024 ? `${Math.round(maxKB / 1024)} MB` : `${maxKB} KB`;
            onError?.(`Imagem demasiado grande. Máximo ${label}.`);
            return;
        }
        onImage?.(file);
    }

    return { handlePaste };
}
