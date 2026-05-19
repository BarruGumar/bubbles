import { ref } from 'vue';
import axios from 'axios';

// Module-level cache shared across all instances
const CACHE_TTL = 30_000;
const _cache = new Map();

function getCached(key) {
    const entry = _cache.get(key);
    if (!entry) return null;
    if (Date.now() - entry.at > CACHE_TTL) { _cache.delete(key); return null; }
    return entry.data;
}

export function useSearch() {
    const results = ref(null);
    const loading = ref(false);
    const error   = ref(false);

    let _ctrl  = null;
    let _timer = null;

    async function _fetch(q) {
        const cached = getCached(q);
        if (cached) { results.value = cached; loading.value = false; return; }

        if (_ctrl) _ctrl.abort();
        _ctrl = new AbortController();

        try {
            const res = await axios.get(route('search.api'), {
                params: { q },
                signal: _ctrl.signal,
            });
            _cache.set(q, { data: res.data, at: Date.now() });
            results.value = res.data;
            error.value   = false;
        } catch (e) {
            if (axios.isCancel(e)) return;
            error.value = true;
        } finally {
            loading.value = false;
        }
    }

    function search(q) {
        clearTimeout(_timer);
        const trimmed = (q ?? '').trim();
        if (trimmed.length < 2) {
            results.value = null;
            loading.value = false;
            error.value   = false;
            return;
        }
        loading.value = true;
        error.value   = false;
        _timer = setTimeout(() => _fetch(trimmed), 300);
    }

    function clear() {
        clearTimeout(_timer);
        if (_ctrl) { _ctrl.abort(); _ctrl = null; }
        results.value = null;
        loading.value = false;
        error.value   = false;
    }

    return { results, loading, error, search, clear };
}
