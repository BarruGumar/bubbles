import { ref } from 'vue'

export function useConnections() {
  const connections = ref([])

  function addConnection(c) {
    connections.value.push(c)
  }

  return {
    connections,
    addConnection
  }
}