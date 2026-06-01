import { describe, it, expect, vi, beforeEach } from 'vitest'

vi.mock('@inertiajs/vue3', () => ({
    router: { post: vi.fn(), delete: vi.fn() },
}))

import { router } from '@inertiajs/vue3'
import { useComments } from '../Composables/useComments.js'

describe('useComments', () => {
    beforeEach(() => vi.clearAllMocks())

    it('toggleComments adds postId to expanded set', () => {
        const { expandedComments, toggleComments } = useComments(() => '/')
        toggleComments(42)
        expect(expandedComments.value.has(42)).toBe(true)
    })

    it('toggleComments removes postId when called twice', () => {
        const { expandedComments, toggleComments } = useComments(() => '/')
        toggleComments(42)
        toggleComments(42)
        expect(expandedComments.value.has(42)).toBe(false)
    })

    it('submitComment does not call router when text is empty', () => {
        const { submitComment } = useComments(() => '/')
        submitComment({ id: 1 })
        expect(router.post).not.toHaveBeenCalled()
    })

    it('submitComment does not call router when text is only whitespace', () => {
        const { submitComment, commentTexts } = useComments(() => '/')
        commentTexts[1] = '   '
        submitComment({ id: 1 })
        expect(router.post).not.toHaveBeenCalled()
    })

    it('submitComment calls router.post with trimmed text', () => {
        const { submitComment, commentTexts } = useComments(() => '/posts/1/comments')
        commentTexts[1] = '  Hello world  '
        submitComment({ id: 1 })
        expect(router.post).toHaveBeenCalledWith(
            '/posts/1/comments',
            { content: 'Hello world' },
            expect.any(Object),
        )
    })
})
