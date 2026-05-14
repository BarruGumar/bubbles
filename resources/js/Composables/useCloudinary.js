/**
 * Transform a Cloudinary URL to deliver at a specific size.
 * - Returns the original URL unchanged for local (/storage) or blob: URLs.
 * - Replaces any existing transformation segment with the new one so we never
 *   double-apply transforms.
 *
 * @param {string|null} url  - Original image URL
 * @param {number}      w    - Desired width  (0 = omit)
 * @param {number}      h    - Desired height (0 = omit)
 * @param {string}      crop - Cloudinary crop mode (default 'fill')
 * @param {string}      g    - Gravity (e.g. 'face'); omitted if falsy
 */
export function clImg(url, w = 0, h = 0, crop = 'fill', g = '') {
  if (!url || !url.includes('res.cloudinary.com')) return url

  const marker = '/upload/'
  const idx    = url.indexOf(marker)
  if (idx === -1) return url

  const base = url.slice(0, idx + marker.length)
  const rest = url.slice(idx + marker.length)

  // Detect GIFs by extension — f_auto can deliver static WebP, stripping animation
  const isGif = /\.gif($|\?)/i.test(url)

  const parts = []
  if (w) parts.push(`w_${w}`)
  if (h) parts.push(`h_${h}`)
  parts.push(`c_${crop}`)
  if (g) parts.push(`g_${g}`)
  parts.push(isGif ? 'f_gif' : 'f_auto', 'q_auto')
  const transform = parts.join(',')

  // Detect and skip existing transform segment (starts with letter(s) + underscore,
  // is not a version tag like v1234567)
  const firstSlash = rest.indexOf('/')
  const firstSeg   = firstSlash === -1 ? rest : rest.slice(0, firstSlash)
  const publicPath = firstSlash === -1 ? ''   : rest.slice(firstSlash + 1)

  const isTransform = /^[a-z]{1,2}_/.test(firstSeg) && !/^v\d+$/.test(firstSeg)
  const afterTransform = isTransform ? publicPath : rest

  return `${base}${transform}/${afterTransform}`
}
