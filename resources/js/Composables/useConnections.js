import { ref } from 'vue'
import axios from 'axios'

export function useConnections() {
  const connections       = ref([])
  const friendConnections = ref([])

  function has(a, b) {
    return connections.value.some(c =>
      (c.from === a && c.to === b) || (c.from === b && c.to === a)
    )
  }

  async function load() {
    try {
      const res = await axios.get('/api/connections')
      connections.value = res.data.map(c => ({ from: c.from_bubble_id, to: c.to_bubble_id }))
    } catch {
      console.warn('[Connections] Não foi possível carregar conexões.')
    }
  }

  async function loadFriendConnections() {
    try {
      const res = await axios.get('/api/friend-connections')
      friendConnections.value = res.data
    } catch {
      console.warn('[FriendConnections] Não foi possível carregar.')
    }
  }

  async function connect(fromId, toId) {
    if (fromId === toId || has(fromId, toId)) return
    connections.value.push({ from: fromId, to: toId })
    try {
      await axios.post('/api/connections', { from_bubble_id: fromId, to_bubble_id: toId })
    } catch {
      console.warn('[Connections] Não foi possível persistir a conexão.')
    }
  }

  return { connections, friendConnections, load, loadFriendConnections, connect }
}
