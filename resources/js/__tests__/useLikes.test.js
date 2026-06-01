import { describe, it, expect, vi, beforeEach } from 'vitest'
import { ref } from 'vue'

vi.mock('@inertiajs/vue3', () => ({
    router: { post: vi.fn() },
}))

import { router } from '@inertiajs/vue3'
import { useLikes } from '../Composables/useLikes.js'

const makePost = (overrides = {}) => ({
    id: 1,
    likes_count: 5,
    is_liked: false,
    ...overrides,
})

describe('useLikes', () => {
    beforeEach(() => vi.clearAllMocks())

    it('likeCount falls back to post.likes_count when no local state', () => {
        const { likeCount } = useLikes(ref([]), () => '/', ref(null))
        expect(likeCount(makePost({ likes_count: 10 }))).toBe(10)
    })

    it('isLiked falls back to post.is_liked when no local state', () => {
        const { isLiked } = useLikes(ref([]), () => '/', ref(null))
        expect(isLiked(makePost({ is_liked: true }))).toBe(true)
        expect(isLiked(makePost({ is_liked: false }))).toBe(false)
    })

    it('toggleLike does nothing when authUser is null', () => {
        const { likeCount, isLiked, toggleLike } = useLikes(ref([]), () => '/', ref(null))
        const post = makePost()
        toggleLike(post)
        expect(router.post).not.toHaveBeenCalled()
        expect(isLiked(post)).toBe(false)
        expect(likeCount(post)).toBe(5)
    })

    it('toggleLike optimistically likes a post', () => {
        const { likeCount, isLiked, toggleLike } = useLikes(ref([]), () => '/', ref({ id: 1 }))
        const post = makePost({ likes_count: 5, is_liked: false })
        toggleLike(post)
        expect(isLiked(post)).toBe(true)
        expect(likeCount(post)).toBe(6)
    })

    it('toggleLike optimistically unlikes a post', () => {
        const { likeCount, isLiked, toggleLike } = useLikes(ref([]), () => '/', ref({ id: 1 }))
        const post = makePost({ likes_count: 5, is_liked: true })
        toggleLike(post)
        expect(isLiked(post)).toBe(false)
        expect(likeCount(post)).toBe(4)
    })
})
