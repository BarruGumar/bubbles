import { ref } from 'vue'

export function useBubbles() {
  const bubbles = ref([])

  function setBubbles(data) {
    bubbles.value = data
  }

  function addBubble(bubble) {
    bubbles.value.push(bubble)
  }

  return {
    bubbles,
    setBubbles,
    addBubble
  }
}