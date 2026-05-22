import { ref } from 'vue'
import axios from 'axios'

const COLORS = ['#009ac7', '#4ebcff', '#2ea87e', '#e07b4a', '#9b6bdf', '#c74a6b']
let _localId = -1

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
                const cx = window.innerWidth / 2
                const cy = window.innerHeight / 2
                bubbles.value = data.map((b, i) => {
                    const size = sizeFromMembers(b.members)
                    const angle = (i / data.length) * Math.PI * 2 + (Math.random() - 0.5) * 0.6
                    const dist = 20 + Math.random() * 50
                    return makeLocal({
                        id: b.id,
                        x: cx - size / 2 + Math.cos(angle) * dist,
                        y: cy - size / 2 + Math.sin(angle) * dist,
                        label: b.label,
                        color: b.color ?? '#009ac7',
                        size,
                        members: b.members ?? 0,
                        isMember: b.is_member ?? false,
                        image: b.community_image ?? null,
                        avatars: b.avatars ?? [],
                        persisted: true,
                        vx: Math.cos(angle) * 1.1,
                        vy: Math.sin(angle) * 1.1,
                    })
                })
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
