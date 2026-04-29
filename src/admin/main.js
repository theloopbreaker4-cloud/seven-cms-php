import { createApp } from 'vue'
import App from './App.vue'
import CalendarWidget from '../shared/components/CalendarWidget.vue'
import { initConfirmLinks } from './dialog.js'
import { initUI } from './ui.js'
import { initForms } from './forms.js'
import { initSelects } from './select.js'
import './main.scss'

const el = document.getElementById('app')
if (el) {
  const data = window.__DATA__ ?? {}
  createApp(App, { data }).mount(el)
}

initConfirmLinks()
initUI()
initForms()
initSelects()

// Mount CalendarWidget into any [data-calendar] elements
document.querySelectorAll('[data-calendar]').forEach(target => {
  const events = JSON.parse(target.dataset.events || '[]')
  createApp(CalendarWidget, { events }).mount(target)
})
