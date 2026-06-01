import { vi } from 'vitest'

// Stub Ziggy's global route() helper
global.route = vi.fn((name, id) => `/${name}/${id ?? ''}`)
