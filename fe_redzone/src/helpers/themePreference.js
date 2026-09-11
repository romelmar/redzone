const key = 'redzone-theme'

export function readThemePreference() {
  try {
    const saved = localStorage.getItem(key)
    return saved === 'dark' ? 'dark' : 'light'
  } catch {
    return 'light'
  }
}

export function saveThemePreference(theme) {
  if (!['light', 'dark'].includes(theme)) return
  try { localStorage.setItem(key, theme) } catch { /* Storage may be disabled. */ }
}
