import { describe, it, expect, vi, afterEach } from 'vitest'

vi.mock('axios', () => ({
    default: { patch: vi.fn().mockResolvedValue({}) },
}))

import { applyTheme } from '../Composables/useTheme.js'

describe('applyTheme', () => {
    afterEach(() => {
        document.documentElement.classList.remove('dark')
    })

    it('adds dark class when theme is dark', () => {
        applyTheme('dark')
        expect(document.documentElement.classList.contains('dark')).toBe(true)
    })

    it('removes dark class when theme is light', () => {
        document.documentElement.classList.add('dark')
        applyTheme('light')
        expect(document.documentElement.classList.contains('dark')).toBe(false)
    })

    it('persists theme choice to localStorage', () => {
        applyTheme('dark')
        expect(localStorage.getItem('bubbles_theme')).toBe('dark')

        applyTheme('light')
        expect(localStorage.getItem('bubbles_theme')).toBe('light')
    })
})
