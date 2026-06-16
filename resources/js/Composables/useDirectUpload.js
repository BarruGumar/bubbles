import axios from 'axios';

export function useDirectUpload() {
    async function upload(file, context, contextId = null, signal = null) {
        const isGif = file.type === 'image/gif';

        // Get signed upload credentials from our server
        const { data: sig } = await axios.get(route('upload.signature'), {
            params: {
                context,
                context_id: contextId,
                is_gif: isGif ? 1 : 0,
            },
            signal,
        });

        // Build FormData with all signed params
        const fd = new FormData();
        fd.append('file', file);
        fd.append('api_key', sig.api_key);
        fd.append('timestamp', String(sig.timestamp));
        fd.append('signature', sig.signature);
        fd.append('folder', sig.folder);

        if (sig.public_id)      fd.append('public_id', sig.public_id);
        if (sig.overwrite)      fd.append('overwrite', sig.overwrite);
        if (sig.transformation) fd.append('transformation', sig.transformation);

        // Upload directly to Cloudinary (bypasses Laravel)
        const res = await fetch(
            `https://api.cloudinary.com/v1_1/${sig.cloud_name}/image/upload`,
            { method: 'POST', body: fd, signal },
        );

        if (!res.ok) {
            const err = await res.json().catch(() => ({}));
            throw new Error(err?.error?.message ?? `Cloudinary upload falhou (${res.status})`);
        }

        const data = await res.json();
        return { url: data.secure_url, public_id: data.public_id };
    }

    return { upload };
}
