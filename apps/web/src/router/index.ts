import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router';

const routes: RouteRecordRaw[] = [
  {
    path: '/',
    name: 'landing',
    component: () => import('../pages/LandingPage.vue'),
  },
  {
    path: '/tournaments/:id(\\d+)',
    name: 'tournament',
    component: () => import('../pages/LeaguePage.vue'),
    props: (route) => ({ tournamentId: Number(route.params.id) }),
  },
  {
    path: '/:pathMatch(.*)*',
    redirect: { name: 'landing' },
  },
];

export const router = createRouter({
  history: createWebHistory(),
  routes,
});
