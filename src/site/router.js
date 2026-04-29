import { createRouter, createWebHistory } from 'vue-router'

import HomePage    from './pages/HomePage.vue'
import BlogList    from './pages/BlogList.vue'
import BlogPost    from './pages/BlogPost.vue'
import PageShow    from './pages/PageShow.vue'
import AuthLogin   from './pages/AuthLogin.vue'
import AuthRegister from './pages/AuthRegister.vue'
import AuthForgot  from './pages/AuthForgot.vue'
import AuthRecover from './pages/AuthRecover.vue'
import AboutPage   from './pages/AboutPage.vue'
import NotFound    from './pages/NotFound.vue'

const lang = window.__LANG__ || 'en'

export default createRouter({
  history: createWebHistory(),
  routes: [
    { path: `/:lang/`,                    component: HomePage },
    { path: `/:lang/blog`,                component: BlogList },
    { path: `/:lang/blog/:slug`,          component: BlogPost },
    { path: `/:lang/page/:slug`,          component: PageShow },
    { path: `/:lang/auth`,                component: AuthLogin },
    { path: `/:lang/auth/register`,       component: AuthRegister },
    { path: `/:lang/auth/forgot`,         component: AuthForgot },
    { path: `/:lang/auth/recover/:token`, component: AuthRecover },
    { path: `/:lang/about`,              component: AboutPage },
    { path: '/:pathMatch(.*)*',          component: NotFound },
  ],
  scrollBehavior: () => ({ top: 0 }),
})
