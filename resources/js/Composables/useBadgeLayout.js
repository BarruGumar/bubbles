// Visual radius of the badge circle + clearance margin
export const BADGE_COLLISION_R = 20

/**
 * Compute where the midpoint badge should render so it doesn't overlap any
 * community bubble (other than the two it connects).
 *
 * Uses iterative 2D push — moves in whatever direction avoids the overlap,
 * not just perpendicularly to the connection line.
 */
export function resolveBadgePos(midX, midY, fromId, toId, bubbles, badgeR = BADGE_COLLISION_R) {
    let x = midX
    let y = midY

    for (let iter = 0; iter < 10; iter++) {
        let pushed = false
        for (const b of bubbles) {
            if (b.id === fromId || b.id === toId) continue
            const bCx = b.x + b.size * 0.5
            const bCy = b.y + b.size * 0.5
            const minD = b.size * 0.5 + badgeR
            const dx = x - bCx
            const dy = y - bCy
            const d = Math.hypot(dx, dy) || 0.01
            if (d < minD) {
                const push = (minD - d) / d
                x += dx * push
                y += dy * push
                pushed = true
            }
        }
        if (!pushed) break
    }

    return { x, y }
}
