import { ref } from 'vue'
import axios from 'axios'

export function useConnections() {
    const connections = ref([])
    const friendConnections = ref([])

    async function load() {
        try {
            const res = await axios.get('/api/connections')
            connections.value = res.data.map((c) => ({ from: c.from_bubble_id, to: c.to_bubble_id }))
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

    // TODO: Implementar aqui a recomendação automática de comunidades por interesses,
    // baseada nas comunidades onde o utilizador é membro (análise de gostos em comum).

    return { connections, friendConnections, load, loadFriendConnections }
}
