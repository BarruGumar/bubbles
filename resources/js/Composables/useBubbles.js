import { ref } from 'vue'
import axios from 'axios'

const COLORS = ['#009ac7', '#4ebcff', '#2ea87e', '#e07b4a', '#9b6bdf', '#c74a6b']
let _localId = -1

const STORAGE_KEY  = (userId) => `bubbles_pos_${userId}`
const SESSION_KEY  = (userId) => `bubbles_session_${userId}`

function loadStoredPositions(userId) {
    try {
        const stored = localStorage.getItem(STORAGE_KEY(userId))
        return stored ? JSON.parse(stored) : {}
    } catch {
        return {}
    }
}

function loadSessionCache(userId) {
    if (!userId) return null
    try {
        const raw = sessionStorage.getItem(SESSION_KEY(userId))
        return raw ? JSON.parse(raw) : null
    } catch {
        return null
    }
}

function saveSessionCache(userId, rawArray) {
    if (!userId) return
    try {
        sessionStorage.setItem(SESSION_KEY(userId), JSON.stringify(rawArray))
    } catch {}
}

// Tamanho dinâmico baseado no número de membros; reduzido em ecrãs pequenos
function sizeFromMembers(n) {
    const base = Math.min(70 + Math.sqrt(n ?? 0) * 9, 220)
    return Math.round(window.innerWidth < 640 ? base * 0.72 : base)
}

function makeLocal(raw) {
    return {
        ...raw,
        vx: raw.vx ?? 0,
        vy: raw.vy ?? 0,
        selected: false,
        phase: Math.random() * Math.PI * 2,
        spawnScale: raw.spawnScale ?? 1,
        activity: raw.activity ?? 0.35 + Math.random() * 0.4,
        avatars: raw.avatars ?? [],
        persisted: raw.persisted ?? false,
    }
}

function rawToLocal(b, i, total, storedPos) {
    const size   = sizeFromMembers(b.members)
    const angle  = (i / total) * Math.PI * 2 + (Math.random() - 0.5) * 0.6
    const dist   = 20 + Math.random() * 50
    const cx     = window.innerWidth / 2
    const cy     = window.innerHeight / 2
    const stored = storedPos[b.id]
    const sx = stored ? Math.max(60, Math.min(stored.x, window.innerWidth  - size - 60)) : null
    const sy = stored ? Math.max(60, Math.min(stored.y, window.innerHeight - size - 60)) : null
    return makeLocal({
        id:       b.id,
        x:        sx ?? cx - size / 2 + Math.cos(angle) * dist,
        y:        sy ?? cy - size / 2 + Math.sin(angle) * dist,
        label:    b.label,
        color:    b.color ?? '#009ac7',
        size,
        members:  b.members  ?? 0,
        isMember: b.is_member ?? false,
        image:    b.community_image ?? null,
        avatars:  b.avatars  ?? [],
        persisted: true,
        vx: stored ? 0 : Math.cos(angle) * 1.1,
        vy: stored ? 0 : Math.sin(angle) * 1.1,
    })
}

export function useBubbles() {
    const bubbles      = ref([])
    const hoveredId    = ref(null)
    const connectSource = ref(null)

    function savePositions(userId) {
        if (!userId) return
        try {
            const positions = {}
            bubbles.value.forEach((b) => {
                if (b.id > 0) positions[b.id] = { x: Math.round(b.x), y: Math.round(b.y) }
            })
            localStorage.setItem(STORAGE_KEY(userId), JSON.stringify(positions))
        } catch {}
    }

    // Merge fresh server data into the existing bubbles array without
    // resetting positions or velocities for bubbles already on screen.
    function mergeFromServer(freshRaw, userId) {
        saveSessionCache(userId, freshRaw)
        const storedPos = userId ? loadStoredPositions(userId) : {}
        const existing  = new Map(bubbles.value.map((b) => [b.id, b]))
        const freshIds  = new Set(freshRaw.map((b) => b.id))

        const merged = freshRaw.map((b, i) => {
            const cur  = existing.get(b.id)
            const size = sizeFromMembers(b.members)
            if (cur) {
                // Keep live position/velocity — only refresh metadata
                return {
                    ...cur,
                    label:    b.label,
                    color:    b.color ?? cur.color,
                    size,
                    members:  b.members  ?? 0,
                    isMember: b.is_member ?? false,
                    image:    b.community_image ?? null,
                    avatars:  b.avatars  ?? [],
                }
            }
            // Brand-new bubble that appeared since the cache was written
            return rawToLocal(b, i, freshRaw.length, storedPos)
        })

        // Keep local (unsaved) bubbles; drop bubbles removed server-side
        const locals = bubbles.value.filter((b) => b.id < 0)
        bubbles.value = [...merged.filter((b) => freshIds.has(b.id)), ...locals]
    }

    async function load(userId) {
        const storedPos = userId ? loadStoredPositions(userId) : {}

        // If we have a session cache render it immediately — no loading spinner.
        // A background fetch will silently merge fresh data once it arrives.
        const cached = loadSessionCache(userId)
        if (cached && cached.length > 0) {
            bubbles.value = cached.map((b, i) => rawToLocal(b, i, cached.length, storedPos))
            // Background refresh — updates member counts, avatars, new/removed bubbles
            axios.get('/api/bubbles')
                .then(({ data }) => {
                    if (Array.isArray(data) && data.length > 0) mergeFromServer(data, userId)
                })
                .catch(() => {})
            return
        }

        // Cold start (first visit or cache cleared) — await server response
        try {
            const { data } = await axios.get('/api/bubbles')
            if (Array.isArray(data) && data.length > 0) {
                saveSessionCache(userId, data)
                bubbles.value = data.map((b, i) => rawToLocal(b, i, data.length, storedPos))
            } else {
                bubbles.value = []
            }
        } catch (err) {
            console.error('[Bubbles] Erro ao carregar bolhas:', err?.response?.data ?? err)
            bubbles.value = []
        }
    }

    async function add(label, color = null, template = {}) {
        const lbl = (label.trim() || 'novo').replace(/^#*/, '#')
        color = color ?? COLORS[Math.floor(Math.random() * COLORS.length)]
        const x = window.innerWidth / 2 - 42
        const y = window.innerHeight / 2 - 42
        const lid = _localId--

        bubbles.value.push(
            makeLocal({
                id: lid,
                x,
                y,
                label: lbl,
                color,
                size: sizeFromMembers(1),
                members: 1,
                activity: 0.65,
                vx: (Math.random() - 0.5) * 2,
                vy: (Math.random() - 0.5) * 2,
                spawnScale: 0.1,
            }),
        )

        try {
            const payload = {
                label: lbl,
                color,
                x,
                y,
                size: sizeFromMembers(1),
                community_title:       template.title        || lbl,
                community_description: template.description  || null,
                community_tagline:     template.tagline      || null,
                community_cover_color: template.coverColor   || color,
                community_guidelines:  (template.guidelines  || []).filter(Boolean),
            }
            const { data } = await axios.post('/api/bubbles', payload)
            const idx = bubbles.value.findIndex((b) => b.id === lid)
            if (idx !== -1) {
                bubbles.value[idx].id = data.id
                bubbles.value[idx].persisted = true
            }
            return bubbles.value.find((b) => b.id === data.id) ?? null
        } catch (err) {
            console.error('[Bubbles] Erro ao criar bolha:', err?.response?.data ?? err)
            const idx = bubbles.value.findIndex((b) => b.id === lid)
            if (idx !== -1) bubbles.value.splice(idx, 1)
            return null
        }
    }

    function toggleSelect(id) {
        bubbles.value.forEach((b) => {
            b.selected = b.id === id ? !b.selected : false
        })
    }

    return { bubbles, hoveredId, connectSource, load, add, toggleSelect, savePositions }
}
