<script setup>
import { reactive, ref, onMounted } from 'vue';
import { formatRuPhone, isCompleteRuPhone, normalizeEmail } from './utils/contactInput.js';
import AdminLogin from './components/admin/AdminLogin.vue';
import AdminContacts from './components/admin/AdminContacts.vue';
import AdminSmtp from './components/admin/AdminSmtp.vue';
import AdminPassword from './components/admin/AdminPassword.vue';

// Resolve the private API from the current hidden entry directory.
const adminApiPath = window.location.pathname.replace(/(?:\/index\.html)?\/?$/, '/') + 'api/';
const adminEndpoint = (action) => `${adminApiPath}admin.php?action=${encodeURIComponent(action)}`;
const adminSettingsEndpoint = `${adminApiPath}settings.php`;

const loaded = ref(false);
const authorized = ref(false);
const busy = ref(false);
const csrf = ref('');
const notice = ref('');
const error = ref('');
const changing = reactive({ old: '', next: '', confirmation: '' });
const settings = reactive({
  primary_phone: '',
  secondary_phone: '',
  contact_email: '',
  request_email: '',
  max_url: '',
});
const smtp = reactive({host: 'smtp.timeweb.ru', user: '', password: '', configured: false});

function showError(e) { error.value = e.message || 'Произошла ошибка'; notice.value = ''; }
function showOk(message) { notice.value = message; error.value = ''; }
async function request(url, data) {
  const options = { credentials: 'same-origin', cache: 'no-store' };
  if (data) {
    options.method = 'POST';
    options.headers = { 'Content-Type': 'application/json' };
    options.body = JSON.stringify({ ...data, csrf: csrf.value });
  }
  const response = await fetch(url, options);
  let payload;
  try { payload = await response.json(); }
  catch { throw new Error('Сервер вернул неожиданный ответ. Проверьте PHP на Timeweb'); }
  if (!response.ok) throw new Error(payload.error || 'Ошибка запроса');
  return payload;
}
async function readSettings() {
  const data = await request(adminSettingsEndpoint);
  Object.assign(settings, data.settings, {
    primary_phone: formatRuPhone(data.settings.primary_phone),
    secondary_phone: formatRuPhone(data.settings.secondary_phone),
    contact_email: normalizeEmail(data.settings.contact_email),
    request_email: normalizeEmail(data.settings.request_email),
  });
  Object.assign(smtp, data.smtp || {});
  smtp.password = '';
  csrf.value = data.csrf;
}
onMounted(async () => {
  try {
    const s = await request(adminEndpoint('session'));
    authorized.value = s.authenticated;
    csrf.value = s.csrf;
    if (authorized.value) await readSettings();
  } catch (e) { showError(e); }
  finally { loaded.value = true; }
});
async function login(password) {
  busy.value = true; error.value = '';
  try {
    const data = await request(adminEndpoint('login'), { password });
    csrf.value = data.csrf;
    authorized.value = true;
    await readSettings();
    showOk('Вход выполнен');
  } catch (e) { showError(e); }
  finally { busy.value = false; }
}
async function save() {
  settings.primary_phone = formatRuPhone(settings.primary_phone);
  settings.secondary_phone = formatRuPhone(settings.secondary_phone);
  settings.contact_email = normalizeEmail(settings.contact_email);
  settings.request_email = normalizeEmail(settings.request_email);
  if (!isCompleteRuPhone(settings.primary_phone) || !isCompleteRuPhone(settings.secondary_phone)) {
    showError(new Error('Введите оба номера полностью: +7 и 10 цифр'));
    return;
  }
  busy.value = true;
  try {
    await request(adminEndpoint('settings'), { settings: { ...settings } });
    showOk('Контакты сохранены и уже доступны на сайте');
  } catch (e) { showError(e); }
  finally { busy.value = false; }
}

async function saveSmtp() {
  if (!smtp.user || (!smtp.password && !smtp.configured)) {
    showError(new Error('Укажите почтовый ящик отправителя и его пароль'));
    return;
  }
  busy.value = true;
  try {
    const result = await request(adminEndpoint('smtp'), {
      host: smtp.host, user: normalizeEmail(smtp.user), password: smtp.password,
    });
    Object.assign(smtp, result.smtp);
    smtp.password = '';
    showOk('Настройки SMTP сохранены. Теперь проверьте тестовым письмом');
  } catch (e) { showError(e); }
  finally { busy.value = false; }
}
async function testSmtp() {
  busy.value = true;
  try {
    const data = await request(adminEndpoint('test-email'), {});
    showOk(data.message || 'Тестовое письмо принято SMTP-сервером. Проверьте почту');
  } catch(e) { showError(e); }
  finally { busy.value = false; }
}
async function changePassword() {
  if (changing.next !== changing.confirmation) { showError(new Error('Новые пароли не совпадают')); return; }
  if (changing.next.length < 14) { showError(new Error('Новый пароль должен содержать не менее 14 символов')); return; }
  busy.value = true;
  try {
    const data = await request(adminEndpoint('password'), {
      current_password: changing.old,
      new_password: changing.next,
    });
    csrf.value = data.csrf;
    changing.old = ''; changing.next = ''; changing.confirmation = '';
    showOk('Пароль изменён. Запомните новый пароль');
  } catch (e) { showError(e); }
  finally { busy.value = false; }
}
async function logout() {
  busy.value = true;
  try {
    await request(adminEndpoint('logout'), {});
    authorized.value = false;
    csrf.value = '';
    const data = await request(adminEndpoint('session'));
    csrf.value = data.csrf;
    showOk('Вы вышли');
  } catch (e) { showError(e); }
  finally { busy.value = false; }
}
</script>
<template>
  <main class="admin">
    <header class="admin-top">
      <a href="/" class="back">← На сайт</a>
      <span>Путь без преград</span>
    </header>
    <div class="admin-content">
      <h1>Администрирование</h1>
      <p class="intro">Настройка контактов и получения заявок</p>
      <p v-if="notice" class="notice" role="status">{{ notice }}</p>
      <p v-if="error" class="error" role="alert">{{ error }}</p>
      <div v-if="!loaded">Проверяем сессию…</div>
      <AdminLogin v-else-if="!authorized" :busy="busy" @login="login" />
      <template v-else>
        <AdminContacts :settings="settings" :busy="busy" @save="save" @logout="logout" />
        <AdminSmtp :settings="settings" :smtp="smtp" :busy="busy" @save-smtp="saveSmtp" @test-smtp="testSmtp" />
        <AdminPassword :changing="changing" :busy="busy" @change-password="changePassword" />
      </template>
    </div>
  </main>
</template>

<style src="./styles/admin.css"></style>
