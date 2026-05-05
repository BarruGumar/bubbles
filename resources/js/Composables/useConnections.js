import { ref } from 'vue'
import axios from 'axios'

export function useConnections() {
  const connections = ref([{ from: 1, to: 2 }, { from: 1, to: 4 }])

  function has(a, b) {
    return connections.value.some(c =>
      (c.from === a && c.to === b) || (c.from === b && c.to === a)
    )
  }

  async function connect(fromId, toId) {
    if (fromId === toId || has(fromId, toId)) return
    connections.value.push({ from: fromId, to: toId })
    try {
      await axios.post('/api/connections', { bubble_id_1: fromId, bubble_id_2: toId })
    } catch {
      console.warn('[Connections] Não foi possível persistir a conexão.')
    }
  }

  return { connections, connect }
}
