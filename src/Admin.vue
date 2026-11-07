<script setup>
import { reactive, ref, onMounted } from 'vue';
import {formatRuPhone, isCompleteRuPhone, normalizeEmail, applyPhoneInput, phoneFocus, phoneBlur} from './utils/contactInput.js';

const loaded = ref(false);
const authorized = ref(false);
const busy = ref(false);
const csrf = ref('');
const password = ref('');
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
  const data = await request('/api/settings.php');
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
    const s = await request('/api/admin.php?action=session');
    authorized.value = s.authenticated;
    csrf.value = s.csrf;
    if (authorized.value) await readSettings();
  } catch (e) { showError(e); }
  finally { loaded.value = true; }
});
async function login() {
  busy.value = true; error.value = '';
  try {
    const data = await request('/api/admin.php?action=login', { password: password.value });
    csrf.value = data.csrf;
    password.value = '';
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
    await request('/api/admin.php?action=settings', { settings: { ...settings } });
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
    const result = await request('/api/admin.php?action=smtp', {
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
    const data = await request('/api/admin.php?action=test-email', {});
    showOk(data.message || 'Тестовое письмо принято SMTP-сервером. Проверьте почту');
  } catch(e) { showError(e); }
  finally { busy.value = false; }
}
async function changePassword() {
  if (changing.next !== changing.confirmation) { showError(new Error('Новые пароли не совпадают')); return; }
  if (changing.next.length < 14) { showError(new Error('Новый пароль должен содержать не менее 14 символов')); return; }
  busy.value = true;
  try {
    const data = await request('/api/admin.php?action=password', {
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
    await request('/api/admin.php?action=logout', {});
    authorized.value = false;
    csrf.value = '';
    const data = await request('/api/admin.php?action=session');
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
      <p class="warning" role="note">Пока работает HTTP, пароль и сессия не защищены от перехвата в сети. Включите HTTPS при первой возможности.</p>
      <p v-if="notice" class="notice" role="status">{{ notice }}</p>
      <p v-if="error" class="error" role="alert">{{ error }}</p>
      <div v-if="!loaded">Проверяем сессию…</div>
      <section v-else-if="!authorized" class="panel">
        <h2>Вход</h2>
        <form @submit.prevent="login" class="form">
          <label for="pwd">Пароль администратора</label>
          <input id="pwd" v-model="password" type="password" autocomplete="current-password" required />
          <button :disabled="busy || !password" type="submit">{{ busy ? 'Проверяем…' : 'Войти' }}</button>
        </form>
      </section>
      <template v-else>
        <section class="panel">
          <div class="heading"><h2>Контакты</h2><button type="button" class="ghost" :disabled="busy" @click="logout">Выйти</button></div>
          <p>Телефоны, почта и MAX меняются сразу во всех блоках публичного сайта.</p>
          <form class="form" @submit.prevent="save">
            <label for="phone1">Основной телефон</label>
            <input id="phone1" v-model="settings.primary_phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="+7 (999) 123-45-67" required maxlength="21" @input="applyPhoneInput($event, value => settings.primary_phone = value)" @focus="phoneFocus($event, value => settings.primary_phone = value)" @blur="phoneBlur($event, value => settings.primary_phone = value)"/>
            <label for="phone2">Дополнительный телефон</label>
            <input id="phone2" v-model="settings.secondary_phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="+7 (999) 123-45-67" required maxlength="21" @input="applyPhoneInput($event, value => settings.secondary_phone = value)" @focus="phoneFocus($event, value => settings.secondary_phone = value)" @blur="phoneBlur($event, value => settings.secondary_phone = value)"/>
            <label for="email1">Почта на сайте</label>
            <input id="email1" v-model.trim="settings.contact_email" type="email" inputmode="email" autocomplete="email" autocapitalize="off" :spellcheck="false" required maxlength="250" @blur="settings.contact_email = normalizeEmail(settings.contact_email)"/>
            <label for="email2">Почта для получения заявок</label>
            <input id="email2" v-model.trim="settings.request_email" type="email" inputmode="email" autocomplete="email" autocapitalize="off" :spellcheck="false" required maxlength="250" @blur="settings.request_email = normalizeEmail(settings.request_email)"/>
            <label for="max">Ссылка на профиль MAX</label>
            <input id="max" v-model.trim="settings.max_url" type="url" autocomplete="off" required maxlength="600" placeholder="https://max.ru/..."/>
            <button :disabled="busy" type="submit">{{ busy ? 'Сохраняем…' : 'Сохранить контакты' }}</button>
          </form>
        </section>
        <section class="panel">
          <h2>Отправка заявок по SMTP</h2>
          <p>Почта для заявок указывается выше. Здесь настраивается ящик, <strong>от имени которого</strong> сайт отправляет письма. Почтовый ящик должен поддерживать SMTP. Пароль не показывается после сохранения.</p>
          <p :class="smtp.configured ? 'notice' : 'warning'" role="status">
            {{ smtp.configured ? 'SMTP настроен. Проверьте доставку тестовым письмом.' : 'SMTP пока не настроен. Без него автоматическая отправка не работает.' }}
          </p>
          <form class="form" @submit.prevent="saveSmtp">
            <label for="smtp-host">Почтовый сервис</label>
            <select id="smtp-host" v-model="smtp.host">
              <option value="smtp.timeweb.ru">Timeweb — smtp.timeweb.ru</option>
              <option value="smtp.yandex.ru">Яндекс — smtp.yandex.ru</option>
            </select>
            <label for="smtp-user">Почта отправителя (SMTP-логин)</label>
            <input id="smtp-user" v-model.trim="smtp.user" type="email" inputmode="email" autocomplete="username" autocapitalize="off" :spellcheck="false" required maxlength="250" @blur="smtp.user = normalizeEmail(smtp.user)" placeholder="noreply@example.ru"/>
            <label for="smtp-password">Пароль почтового ящика / пароль приложения</label>
            <input id="smtp-password" v-model="smtp.password" type="password" autocomplete="new-password" :required="!smtp.configured" :placeholder="smtp.configured ? 'Оставьте пустым, чтобы не менять' : 'Введите пароль SMTP'" maxlength="512"/>
            <p class="hint">Для Яндекса обычно нужен отдельный пароль приложения. Соединение с SMTP шифруется (STARTTLS, порт 587), но сама админка пока работает по HTTP.</p>
            <button :disabled="busy" type="submit">{{ busy ? 'Сохраняем…' : 'Сохранить SMTP' }}</button>
            <button type="button" class="ghost" :disabled="busy || !smtp.configured" @click="testSmtp">Отправить тестовое письмо на {{ settings.request_email }}</button>
          </form>
        </section>
        <section class="panel">
          <h2>Смена пароля</h2>
          <form class="form" @submit.prevent="changePassword">
            <label for="old">Текущий пароль</label>
            <input id="old" v-model="changing.old" type="password" autocomplete="current-password" required/>
            <label for="new">Новый пароль (минимум 14 символов)</label>
            <input id="new" v-model="changing.next" type="password" autocomplete="new-password" required minlength="14"/>
            <label for="repeat">Повторите новый пароль</label>
            <input id="repeat" v-model="changing.confirmation" type="password" autocomplete="new-password" required minlength="14"/>
            <button :disabled="busy" type="submit">{{ busy ? 'Сохраняем…' : 'Изменить пароль' }}</button>
          </form>
        </section>
      </template>
    </div>
  </main>
</template>

<style scoped>
.admin{min-height:100vh;background:#f2f6f9;color:#152135;font-family:Inter,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
.admin *{box-sizing:border-box}
.admin-top{display:flex;justify-content:space-between;gap:20px;padding:18px max(20px,calc((100vw - 900px)/2));background:#fff;border-bottom:1px solid #dae5ec;font-weight:800}
.back{color:#087e92;text-decoration:none}
.admin-content{max-width:900px;padding:28px 20px 70px;margin:auto}
h1{font-size:clamp(28px,4vw,40px);line-height:1.2;margin:0 0 5px}
.intro{color:#526275;margin:0 0 22px}
.panel{background:white;padding:clamp(22px,4vw,36px);border-radius:16px;border:1px solid #dce5eb;margin-top:22px}
.panel h2{font-size:23px;margin:0 0 15px}
.panel p{color:#526275}
.panel p.warning{color:#62420f}.panel p.notice{color:#13653d}.panel .hint{font-size:14px;line-height:1.5}
.form{display:grid;gap:10px}
.form label{margin-top:10px;font-weight:750}
.form input,.form select{width:100%;padding:12px 13px;border:1px solid #9cabbb;border-radius:8px;font-size:17px;min-height:49px;color:#152135;background:#fff}
.form input:focus-visible,.form select:focus-visible,.admin button:focus-visible{outline:3px solid #087e92;outline-offset:2px}
.admin button{border:0;border-radius:9px;min-height:48px;padding:10px 18px;cursor:pointer;background:#df1036;color:#fff;font-weight:800;font-size:16px}
.form button{margin-top:18px}
.admin button:disabled{opacity:.55;cursor:wait}
.admin button.ghost{background:#eaf2f6;color:#152135}
.heading{display:flex;align-items:center;justify-content:space-between;gap:15px}
.warning,.error,.notice{padding:14px 16px;border-radius:9px;line-height:1.5}
.warning{background:#fff4de;color:#62420f}
.error{background:#ffedf0;color:#a30a28}
.notice{background:#e8f7ef;color:#13653d}
@media(max-width:600px){.admin-top{padding:15px 20px}.heading{align-items:start}}
</style>
