import { ref } from 'vue'
import axios from 'axios'

const COLORS = ['#009ac7', '#4ebcff', '#2ea87e', '#e07b4a', '#9b6bdf', '#c74a6b']
let _localId = -1

// Tamanho dinâmico baseado no número de membros
// 0 membros → 60px | 1 → ~67 | 25 → ~95 | 100 → ~130 | 400+ → 180 (máximo)
function sizeFromMembers(n) {
    return Math.round(Math.min(70 + Math.sqrt(n ?? 0) * 9, 220))
}

function makeLocal(raw) {
    return {
        ...raw,
        vx: raw.vx ?? 0,
        vy: raw.vy ?? 0,
        selected: false,
        hover: false,
        phase: Math.random() * Math.PI * 2,
        spawnScale: raw.spawnScale ?? 1,
        activity: raw.activity ?? 0.35 + Math.random() * 0.4,
        avatars: raw.avatars ?? [],
        persisted: raw.persisted ?? false,
    }
}

export function useBubbles() {
    const bubbles = ref([])
    const hoveredId = ref(null)
    const connectSource = ref(null)
    const loading = ref(true)

    async function load() {
        loading.value = true
        try {
            const { data } = await axios.get('/api/bubbles')
            if (Array.isArray(data) && data.length > 0) {
                bubbles.value = data.map((b) =>
                    makeLocal({
                        id: b.id,
                        x: b.x ?? Math.random() * (window.innerWidth - 200) + 100,
                        y: b.y ?? Math.random() * (window.innerHeight - 200) + 100,
                        label: b.label,
                        color: b.color ?? '#009ac7',
                        size: sizeFromMembers(b.members),
                        members: b.members ?? 0,
                        isMember: b.is_member ?? false,
                        image: b.community_image ?? null,
                        avatars: b.avatars ?? [],
                        persisted: true,
                    }),
                )
            } else {
                bubbles.value = []
            }
        } catch (err) {
            console.error('[Bubbles] Erro ao carregar bolhas:', err?.response?.data ?? err)
            bubbles.value = []
        } finally {
            loading.value = false
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
                community_title: template.title || lbl,
                community_description: template.description || null,
                community_tagline: template.tagline || null,
                community_cover_color: template.coverColor || color,
                community_guidelines: (template.guidelines || []).filter(Boolean),
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

    return { bubbles, hoveredId, connectSource, loading, load, add, toggleSelect }
}
