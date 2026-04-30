import { ref, watchEffect } from 'vue'

type ThemePreference = 'auto' | 'light' | 'dark'

const STORAGE_KEY = 'dt_theme'

function loadPref(): ThemePreference {
  const stored = localStorage.getItem(STORAGE_KEY)
  if (stored === 'light' || stored === 'dark' || stored === 'auto') return stored
  return 'auto'
}

const preference = ref<ThemePreference>(loadPref())

watchEffect(() => {
  const pref = preference.value
  localStorage.setItem(STORAGE_KEY, pref)
  const html = document.documentElement
  if (pref === 'auto') {
    html.removeAttribute('data-theme')
  } else {
    html.setAttribute('data-theme', pref)
  }
})

export function useTheme() {
  function setTheme(p: ThemePreference) {
    preference.value = p
  }

  return { preference, setTheme }
}
