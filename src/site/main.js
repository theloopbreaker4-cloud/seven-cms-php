import { createApp } from 'vue'
import App    from './App.vue'
import router from './router.js'
import './main.scss'

const el = document.getElementById('app')
if (el) {
  createApp(App).use(router).mount(el)
}
