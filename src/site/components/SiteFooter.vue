<template>
  <footer class="border-t border-[var(--border-color)] bg-[var(--bg-secondary)]">
    <div class="max-w-5xl mx-auto px-4 py-12">
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-8 mb-10">

        <!-- Brand column -->
        <div>
          <div class="flex items-center gap-2 mb-3">
            <img :src="`${base}brand.svg`" alt="Seven CMS" class="w-7 h-7 rounded-[4px]" />
            <span class="font-bold text-base"><span class="text-[var(--primary)]">Seven</span> <span class="text-[var(--text-secondary)]">CMS</span></span>
          </div>
          <p class="text-sm text-[var(--text-tertiary)] leading-relaxed">
            {{ t('footer.tagline') }}
          </p>
          <div v-if="socials.length" class="flex items-center gap-3 mt-4">
            <a v-for="s in socials" :key="s.id"
               :href="s.url" target="_blank" rel="noopener noreferrer"
               :title="s.label || s.platform"
               class="text-[var(--text-tertiary)] hover:text-[var(--primary)] transition-colors">
              <component :is="iconComponent(s.platform)" class="w-5 h-5" />
            </a>
          </div>
        </div>

        <!-- Navigation column -->
        <div>
          <h3 class="text-xs font-semibold uppercase tracking-wider text-[var(--text-tertiary)] mb-3">{{ t('footer.navigation') }}</h3>
          <ul class="space-y-2 text-sm">
            <li><RouterLink :to="`/${lang}/`"     class="text-[var(--text-secondary)] hover:text-[var(--primary)] transition-colors">{{ t('nav.home') }}</RouterLink></li>
            <li><RouterLink :to="`/${lang}/blog`" class="text-[var(--text-secondary)] hover:text-[var(--primary)] transition-colors">{{ t('nav.blog') }}</RouterLink></li>
            <li><RouterLink :to="`/${lang}/about`" class="text-[var(--text-secondary)] hover:text-[var(--primary)] transition-colors">{{ t('nav.about') }}</RouterLink></li>
            <li v-if="!isLogin">
              <RouterLink :to="`/${lang}/auth`" class="text-[var(--text-secondary)] hover:text-[var(--primary)] transition-colors">{{ t('nav.signin') }}</RouterLink>
            </li>
          </ul>
        </div>

        <!-- Social / Stack column -->
        <div v-if="socials.length">
          <h3 class="text-xs font-semibold uppercase tracking-wider text-[var(--text-tertiary)] mb-3">{{ t('footer.followUs') }}</h3>
          <ul class="space-y-2 text-sm">
            <li v-for="s in socials" :key="s.id">
              <a :href="s.url" target="_blank" rel="noopener noreferrer"
                 class="flex items-center gap-2 text-[var(--text-secondary)] hover:text-[var(--primary)] transition-colors">
                <component :is="iconComponent(s.platform)" class="w-4 h-4 flex-shrink-0" />
                <span>{{ s.label || capitalize(s.platform) }}</span>
              </a>
            </li>
          </ul>
        </div>
        <div v-else>
          <h3 class="text-xs font-semibold uppercase tracking-wider text-[var(--text-tertiary)] mb-3">{{ t('footer.stack') }}</h3>
          <ul class="space-y-1.5 text-sm">
            <li>
              <a href="https://www.php.net/releases/8.4" target="_blank" rel="noopener noreferrer"
                 class="flex items-center gap-2 text-[var(--text-tertiary)] hover:text-[var(--primary)] transition-colors">
                <svg class="w-4 h-4 flex-shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm0 2c5.514 0 10 4.486 10 10s-4.486 10-10 10S2 17.514 2 12 6.486 2 12 2zm-1.5 4H7v12h2.5v-4H11c2.485 0 4.5-2.015 4.5-4.5S13.485 6 11 6h-.5zm0 2.5h.5c1.105 0 2 .895 2 2s-.895 2-2 2h-.5v-4z"/></svg>
                PHP 8.4 MVC
              </a>
            </li>
            <li>
              <a href="https://vuejs.org" target="_blank" rel="noopener noreferrer"
                 class="flex items-center gap-2 text-[var(--text-tertiary)] hover:text-[var(--primary)] transition-colors">
                <svg class="w-4 h-4 flex-shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M24 1.61h-9.94L12 5.16 9.94 1.61H0l12 20.78L24 1.61zM12 14.08L5.16 3.61H9.59L12 7.77l2.41-4.16h4.43L12 14.08z"/></svg>
                Vue 3 + Vite
              </a>
            </li>
            <li>
              <a href="https://tailwindcss.com" target="_blank" rel="noopener noreferrer"
                 class="flex items-center gap-2 text-[var(--text-tertiary)] hover:text-[var(--primary)] transition-colors">
                <svg class="w-4 h-4 flex-shrink-0" viewBox="0 0 24 24" fill="currentColor"><path d="M12 6C9.6 6 8.1 7.2 7.5 9.6c.9-1.2 1.95-1.65 3.15-1.35.685.171 1.174.668 1.715 1.219C13.305 10.48 14.355 11.55 16.5 11.55c2.4 0 3.9-1.2 4.5-3.6-.9 1.2-1.95 1.65-3.15 1.35-.685-.171-1.174-.668-1.715-1.219C15.195 7.07 14.145 6 12 6zm-4.5 6C5.1 12 3.6 13.2 3 15.6c.9-1.2 1.95-1.65 3.15-1.35.685.171 1.174.668 1.715 1.219C8.805 16.48 9.855 17.55 12 17.55c2.4 0 3.9-1.2 4.5-3.6-.9 1.2-1.95 1.65-3.15 1.35-.685-.171-1.174-.668-1.715-1.219C10.695 13.07 9.645 12 7.5 12z"/></svg>
                Tailwind CSS
              </a>
            </li>
            <li>
              <a href="https://redbeanphp.com" target="_blank" rel="noopener noreferrer"
                 class="flex items-center gap-2 text-[var(--text-tertiary)] hover:text-[var(--primary)] transition-colors">
                <svg class="w-4 h-4 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="12" rx="10" ry="6"/><path d="M2 12c0 3.314 4.477 6 10 6s10-2.686 10-6"/><path d="M2 12V7c0-3.314 4.477-6 10-6s10 2.686 10 6v5"/></svg>
                RedBeanPHP ORM
              </a>
            </li>
          </ul>
        </div>

      </div>

      <!-- Bottom bar -->
      <div class="border-t border-[var(--border-color)] pt-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-[var(--text-tertiary)]">
        <span>&copy; {{ year }} Seven CMS. {{ t('footer.allRights') }}</span>
        <div class="flex items-center gap-4">
          <RouterLink :to="`/${lang}/about`" class="hover:text-[var(--primary)] transition-colors">{{ t('nav.about') }}</RouterLink>
          <a :href="`/${lang}/blog`"  class="hover:text-[var(--primary)] transition-colors">{{ t('nav.blog') }}</a>
        </div>
      </div>
    </div>
  </footer>
</template>

<script setup>
import { ref, onMounted, h } from 'vue'
import { RouterLink } from 'vue-router'

const base = import.meta.env.BASE_URL

const props = defineProps({
  lang:    { type: String, default: 'en' },
  isLogin: { type: Boolean, default: false },
})

const i18n    = window.__DATA__?.i18n || {}
const socials = ref([])
const year    = new Date().getFullYear()

function t(key) {
  return i18n[key] || i18n[key.split('.').pop()] || key.split('.').pop()
}

onMounted(async () => {
  try {
    const res = await fetch('/api/social/links')
    if (res.ok) socials.value = await res.json()
  } catch { /* silently ignore */ }
})

const capitalize = (s) => s ? s.charAt(0).toUpperCase() + s.slice(1) : s

const ICONS = {
  github:    'M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22',
  twitter:   'M23 3a10.9 10.9 0 0 1-3.14 1.53 4.48 4.48 0 0 0-7.86 3v1A10.66 10.66 0 0 1 3 4s-4 9 5 13a11.64 11.64 0 0 1-7 2c9 5 20 0 20-11.5a4.5 4.5 0 0 0-.08-.83A7.72 7.72 0 0 0 23 3z',
  x:         'M17.751 3h3.067l-6.588 7.533L22 21h-6.064l-4.738-6.2L5.54 21H2.47l7.049-8.057L2 3h6.214l4.273 5.616L17.751 3zm-1.076 16.172h1.7L7.404 4.74H5.58l11.095 14.432z',
  instagram: null,
  facebook:  'M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z',
  youtube:   'M22.54 6.42a2.78 2.78 0 0 0-1.95-1.96C18.88 4 12 4 12 4s-6.88 0-8.59.46A2.78 2.78 0 0 0 1.46 6.42 29 29 0 0 0 1 12a29 29 0 0 0 .46 5.58 2.78 2.78 0 0 0 1.95 1.96C5.12 20 12 20 12 20s6.88 0 8.59-.46a2.78 2.78 0 0 0 1.95-1.96A29 29 0 0 0 23 12a29 29 0 0 0-.46-5.58z|M10 15l5-3-5-3v6z',
  linkedin:  'M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z|M2 9h4v12H2z|circle:4:4:2',
  telegram:  'm22 2-7 20-4-9-9-4 20-7z|M22 2 11 13',
  tiktok:    'M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5',
  discord:   'M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057 19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028c.462-.63.874-1.295 1.226-1.994a.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03z',
}

function iconComponent(platform) {
  const d = ICONS[platform.toLowerCase()] || 'M12 2a10 10 0 1 0 0 20A10 10 0 0 0 12 2z'
  return {
    render() {
      const svgProps = {
        xmlns: 'http://www.w3.org/2000/svg', viewBox: '0 0 24 24',
        fill: 'none', stroke: 'currentColor',
        'stroke-width': '1.5', 'stroke-linecap': 'round', 'stroke-linejoin': 'round',
        class: 'w-full h-full',
      }
      if (platform.toLowerCase() === 'instagram') {
        return h('svg', svgProps, [
          h('rect', { x: 2, y: 2, width: 20, height: 20, rx: 5, ry: 5 }),
          h('path', { d: 'M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z' }),
          h('line', { x1: 17.5, y1: 6.5, x2: 17.51, y2: 6.5 }),
        ])
      }
      const paths = d.split('|').map(seg => {
        if (seg.startsWith('circle:')) {
          const [, cx, cy, r] = seg.split(':')
          return h('circle', { cx, cy, r })
        }
        return h('path', { d: seg })
      })
      return h('svg', svgProps, paths)
    }
  }
}
</script>
