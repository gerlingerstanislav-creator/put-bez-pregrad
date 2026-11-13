import { createApp } from 'vue';
import './styles.css';
import { redirectToHttps } from './utils/redirectToHttps.js';
import App from './App.vue';
if (!redirectToHttps()) createApp(App).mount('#app');
