const DAMPING = 0.87
const REPULSE = 2.8
const ATTRACT = 0.00085
const DRIFT = 0.04
const MAX_SPD = 1.2
const EXPANDED_SIZE = 300

// Cell size must be ≥ max interaction radius to guarantee no missed pairs.
const CELL_SIZE = 300

const BADGE_RADIUS = 26

function buildGrid(bubbles) {
    const grid = Object.create(null)
    for (const b of bubbles) {
        const col = ((b.x + b.size * 0.5) / CELL_SIZE) | 0
        const row = ((b.y + b.size * 0.5) / CELL_SIZE) | 0
        const key = `${col},${row}`
        ;(grid[key] ??= []).push(b)
    }
    return grid
}

export function usePhysics() {
    function step(bubbles, draggingId, badgeObstacles = []) {
        const cx = window.innerWidth / 2
        const cy = window.innerHeight / 2 - 40
        const grid = buildGrid(bubbles)

        for (let i = 0; i < bubbles.length; i++) {
            const b1 = bubbles[i]
            if (draggingId === b1.id || b1.selected) continue

            let fx = 0,
                fy = 0

            const col = ((b1.x + b1.size * 0.5) / CELL_SIZE) | 0
            const row = ((b1.y + b1.size * 0.5) / CELL_SIZE) | 0

            for (let dc = -1; dc <= 1; dc++) {
                for (let dr = -1; dr <= 1; dr++) {
                    const neighbors = grid[`${col + dc},${row + dr}`]
                    if (!neighbors) continue
                    for (const b2 of neighbors) {
                        if (b2 === b1) continue
                        const s2 = b2.selected ? EXPANDED_SIZE : b2.size
                        const dx = b1.x - b2.x
                        const dy = b1.y - b2.y
                        const d = Math.hypot(dx, dy) || 0.01
                        const minD = (b1.size + s2) * 0.5 + 10
                        if (d < minD) {
                            const penetration = minD - d
                            const s = penetration / minD
                            fx += (dx / d) * s * REPULSE
                            fy += (dy / d) * s * REPULSE
                            // Positional correction: push b1 out of b2 immediately each frame
                            const corr = penetration * 0.28
                            b1.x += (dx / d) * corr
                            b1.y += (dy / d) * corr
                        }
                    }
                }
            }

            // Repulse bubbles from friend-connection badge positions
            const bCx = b1.x + b1.size * 0.5
            const bCy = b1.y + b1.size * 0.5
            for (const badge of badgeObstacles) {
                const dx = bCx - badge.x
                const dy = bCy - badge.y
                const d = Math.hypot(dx, dy) || 0.01
                const minD = b1.size * 0.5 + BADGE_RADIUS
                if (d < minD) {
                    const penetration = minD - d
                    const s = penetration / minD
                    fx += (dx / d) * s * REPULSE
                    fy += (dy / d) * s * REPULSE
                    b1.x += (dx / d) * penetration * 0.35
                    b1.y += (dy / d) * penetration * 0.35
                }
            }

            fx += (cx - b1.x - b1.size * 0.5) * ATTRACT
            fy += (cy - b1.y - b1.size * 0.5) * ATTRACT

            b1.phase = (b1.phase || 0) + 0.008 + b1.activity * 0.006
            const ds = DRIFT * (0.7 + b1.activity * 0.8)
            fx += Math.sin(b1.phase + b1.id) * ds
            fy += Math.cos(b1.phase * 0.9 + b1.id * 0.6) * ds

            b1.vx = (b1.vx + fx) * DAMPING
            b1.vy = (b1.vy + fy) * DAMPING
            b1.vx = Math.max(-MAX_SPD, Math.min(MAX_SPD, b1.vx))
            b1.vy = Math.max(-MAX_SPD, Math.min(MAX_SPD, b1.vy))
            const pad = window.innerWidth < 640 ? 28 : 60
            b1.x = Math.max(pad, Math.min(b1.x + b1.vx, window.innerWidth - b1.size - pad))
            b1.y = Math.max(60, Math.min(b1.y + b1.vy, window.innerHeight - b1.size - pad))

            b1.activity = Math.max(0.08, Math.min(1, b1.activity + (Math.random() - 0.5) * 0.006))
            b1.spawnScale += (1 - b1.spawnScale) * 0.14
        }
    }

    return { step }
}
