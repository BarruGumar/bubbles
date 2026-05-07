import { ref } from 'vue'
import axios from 'axios'

const COLORS = ['#009ac7', '#4ebcff', '#2ea87e', '#e07b4a', '#9b6bdf', '#c74a6b']
let _localId  = -1

function makeLocal(raw) {
  return {
    ...raw,
    vx:        raw.vx        ?? 0,
    vy:        raw.vy        ?? 0,
    selected:  false,
    hover:     false,
    phase:     Math.random() * Math.PI * 2,
    spawnScale:raw.spawnScale ?? 1,
    activity:  raw.activity  ?? 0.35 + Math.random() * 0.4,
    avatars:   raw.avatars   ?? [],
    persisted: raw.persisted ?? false,
  }
}

const DEMO = [
  {
    id: 1, x: 320, y: 180, label: '#futebol', color: '#009ac7', size: 100, members: 312, activity: 0.42,
    avatars: [
      { id: 'av1', angle: 30,  name: 'João',  msg: 'Que golo incrível!' },
      { id: 'av2', angle: 140, name: 'Rita',  msg: 'Vamos Portugal!'    },
      { id: 'av3', angle: 240, name: 'Pedro', msg: 'Que jogo...'        },
    ],
    posts: [
      { id: 'p1', author: 'João',  text: 'Que golo incrível! 🔥', time: '2m' },
      { id: 'p2', author: 'Rita',  text: 'Vamos Portugal! 🇵🇹',    time: '5m' },
      { id: 'p3', author: 'Pedro', text: 'Que jogo épico...',      time: '8m' },
    ],
  },
  {
    id: 2, x: 600, y: 130, label: '#ps5', color: '#4ebcff', size: 80, members: 88, activity: 0.42,
    avatars: [
      { id: 'av4', angle: 60,  name: 'Tomas', msg: 'Alguém online?'  },
      { id: 'av5', angle: 200, name: 'Ana',   msg: 'Novo jogo saiu!' },
    ],
    posts: [
      { id: 'p4', author: 'Tomas', text: 'Alguém para jogar esta noite? 🎮', time: '1m'  },
      { id: 'p5', author: 'Ana',   text: 'Novo jogo saiu! Já comprei 😍',    time: '14m' },
    ],
  },
  {
    id: 3, x: 750, y: 300, label: '#xbox', color: '#2ea87e', size: 80, members: 54, activity: 0.42,
    avatars: [
      { id: 'av6', angle: 100, name: 'Luís', msg: 'Game pass novo?' },
    ],
    posts: [
      { id: 'p6', author: 'Luís', text: 'Game Pass tem jogo novo 👀', time: '30m' },
    ],
  },
  {
    id: 4, x: 160, y: 360, label: '#música', color: '#e07b4a', size: 90, members: 141, activity: 0.42,
    avatars: [
      { id: 'av7', angle: 320, name: 'Mia', msg: 'Nova playlist!'  },
      { id: 'av8', angle: 80,  name: 'Rui', msg: 'Concerto amanhã' },
    ],
    posts: [
      { id: 'p7', author: 'Mia', text: 'Nova playlist de verão 🌊🎵', time: '3m'  },
      { id: 'p8', author: 'Rui', text: 'Concerto amanhã às 21h!',    time: '20m' },
      { id: 'p9', author: 'Mia', text: 'Quem vem? 🙋',               time: '22m' },
    ],
  },
]

export function useBubbles() {
  const bubbles       = ref([])
  const hoveredId     = ref(null)
  const connectSource = ref(null)
  const loading       = ref(true)

  async function load() {
    loading.value = true
    try {
      const { data } = await axios.get('/api/bubbles')
      if (Array.isArray(data) && data.length > 0) {
        bubbles.value = data.map(b => makeLocal({
          id:        b.id,
          x:         b.x       ?? Math.random() * (window.innerWidth  - 200) + 100,
          y:         b.y       ?? Math.random() * (window.innerHeight - 200) + 100,
          label:     b.label,
          color:     b.color   ?? '#009ac7',
          size:      b.size    ?? 85,
          members:   b.members ?? 0,
          isMember:  b.is_member ?? false,
          avatars:   b.avatars ?? [],
          persisted: true,
        }))
      } else {
        bubbles.value = []
      }
    } catch {
      bubbles.value = []
    } finally {
      loading.value = false
    }
  }

  async function add(label, color = null, template = {}) {
    const lbl = (label.trim() || 'novo').replace(/^#*/, '#')
    color     = color ?? COLORS[Math.floor(Math.random() * COLORS.length)]
    const x   = window.innerWidth  / 2 - 42
    const y   = window.innerHeight / 2 - 42
    const lid = _localId--

    bubbles.value.push(makeLocal({
      id: lid, x, y, label: lbl, color, size: 85, members: 0, activity: 0.65,
      vx: (Math.random() - 0.5) * 2,
      vy: (Math.random() - 0.5) * 2,
      spawnScale: 0.1,
    }))

    try {
      const payload = {
        label:                  lbl,
        color,
        x,
        y,
        size:                   85,
        user_id:                template.userId      ?? null,
        community_title:        template.title       || lbl,
        community_description:  template.description || null,
        community_tagline:      template.tagline     || null,
        community_cover_color:  template.coverColor  || color,
        community_guidelines:   (template.guidelines || []).filter(Boolean),
      }
      const { data } = await axios.post('/api/bubbles', payload)
      const idx = bubbles.value.findIndex(b => b.id === lid)
      if (idx !== -1) {
        bubbles.value[idx].id        = data.id
        bubbles.value[idx].persisted = true
      }
      return bubbles.value.find(b => b.id === data.id) ?? null
    } catch (err) {
      console.error('[Bubbles] Erro ao criar bolha:', err?.response?.data ?? err)
      const idx = bubbles.value.findIndex(b => b.id === lid)
      if (idx !== -1) bubbles.value.splice(idx, 1)
      return null
    }
  }

  function toggleSelect(id) {
    bubbles.value.forEach(b => { b.selected = b.id === id ? !b.selected : false })
  }

  return { bubbles, hoveredId, connectSource, loading, load, add, toggleSelect }
}
