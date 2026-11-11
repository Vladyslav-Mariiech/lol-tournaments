import { createRouter, createWebHistory } from 'vue-router'
import HomeView from '../views/HomeView.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'home',
      component: HomeView,
    },
    {
      path: '/admin/tournaments/:id/registrations',
      name: 'admin-registrations',
      component: () => import('../views/AdminRegistrationsView.vue'),
    },
  ],
})

export default router
