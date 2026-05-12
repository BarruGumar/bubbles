import { reactive } from 'vue'
import { router } from '@inertiajs/vue3'

export function useLikes(localPosts, likeRouteFn, authUser) {
  const localLikes = reactive({})

  function likeCount(post) { return localLikes[post.id]?.count   ?? post.likes_count }
  function isLiked(post)   { return localLikes[post.id]?.isLiked ?? post.is_liked    }

  function toggleLike(post) {
    if (!authUser.value) return
    const prev     = { count: likeCount(post), isLiked: isLiked(post) }
    const willLike = !prev.isLiked
    localLikes[post.id] = { count: prev.count + (willLike ? 1 : -1), isLiked: willLike }
    router.post(likeRouteFn(post.id), {}, {
      preserveScroll: true,
      onSuccess: () => {
        const idx = localPosts.value.findIndex(p => p.id === post.id)
        if (idx !== -1) {
          localPosts.value[idx].is_liked    = willLike
          localPosts.value[idx].likes_count = prev.count + (willLike ? 1 : -1)
        }
        delete localLikes[post.id]
      },
      onError: () => { localLikes[post.id] = prev },
    })
  }

  return { localLikes, likeCount, isLiked, toggleLike }
}
