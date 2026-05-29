import { ref } from 'vue';

// Module-level singleton — persists across all component instances on the same page
const onlineUsers = ref(new Set());

export function useOnlineUsers() {
    return { onlineUsers };
}
