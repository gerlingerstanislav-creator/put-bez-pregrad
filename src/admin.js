import { createApp } from 'vue';
import './styles.css';
import { redirectToHttps } from './utils/redirectToHttps.js';
import Admin from './Admin.vue';
if (!redirectToHttps()) createApp(Admin).mount('#admin');
