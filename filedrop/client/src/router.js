import { createRouter, createWebHistory } from 'vue-router';
import Home from './views/Home.vue';
import Session from './views/Session.vue';

const routes = [
  { path: '/', component: Home },
  { path: '/s/:sessionId', component: Session },
];

export default createRouter({
  history: createWebHistory(),
  routes,
});
