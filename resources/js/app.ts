import { createApp } from 'vue';
import App from './App.vue';
import '../css/portal.css';
import './admin-management.css';
createApp(App).mount('#app');
// Load the optional web font after the app mounts; network delays never block the workspace.
const judibasFont=document.createElement('link');judibasFont.rel='stylesheet';judibasFont.href='https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap';document.head.appendChild(judibasFont);
