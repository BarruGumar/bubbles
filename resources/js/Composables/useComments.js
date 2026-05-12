import { reactive, ref } from 'vue'
import { router } from '@inertiajs/vue3'

export function useComments(commentStoreFn) {
  const expandedComments = ref(new Set())
  const commentTexts     = reactive({})

  function toggleComments(postId) {
    const s = new Set(expandedComments.value)
    s.has(postId) ? s.delete(postId) : s.add(postId)
    expandedComments.value = s
  }

  function submitComment(post) {
    const text = (commentTexts[post.id] ?? '').trim()
    if (!text) return
    router.post(commentStoreFn(post.id), { content: text }, {
      preserveScroll: true,
      onSuccess: () => { commentTexts[post.id] = '' },
    })
  }

  function deleteComment(commentId) {
    router.delete(route('comments.destroy', commentId), { preserveScroll: true })
  }

  return { expandedComments, commentTexts, toggleComments, submitComment, deleteComment }
}
