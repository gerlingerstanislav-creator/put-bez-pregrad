<script setup>
import { normalizeEmail } from '../../utils/contactInput.js';

defineProps({
  settings: { type: Object, required: true },
  smtp: { type: Object, required: true },
  busy: Boolean,
});
defineEmits(['save-smtp', 'test-smtp']);
</script>

<template>
<section class="panel">
          <h2>Отправка заявок по SMTP</h2>
          <p>Почта для заявок указывается выше. Здесь настраивается ящик, <strong>от имени которого</strong> сайт отправляет письма. Почтовый ящик должен поддерживать SMTP. Кириллические домены (например, .рф) поддерживаются и преобразуются в Punycode автоматически. Пароль не показывается после сохранения.</p>
          <p :class="smtp.configured ? 'notice' : 'warning'" role="status">
            {{ smtp.configured ? 'SMTP настроен. Проверьте доставку тестовым письмом.' : 'SMTP пока не настроен. Без него автоматическая отправка не работает.' }}
          </p>
          <form class="form" @submit.prevent="$emit('save-smtp')">
            <label for="smtp-host">Почтовый сервис</label>
            <select id="smtp-host" v-model="smtp.host">
              <option value="smtp.timeweb.ru">Timeweb — smtp.timeweb.ru</option>
              <option value="smtp.yandex.ru">Яндекс — smtp.yandex.ru</option>
            </select>
            <label for="smtp-user">Почта отправителя (SMTP-логин)</label>
            <input id="smtp-user" v-model.trim="smtp.user" type="text" inputmode="email" autocomplete="username" autocapitalize="off" :spellcheck="false" required maxlength="250" @blur="smtp.user = normalizeEmail(smtp.user)" placeholder="noreply@пример.рф"/>
            <label for="smtp-password">Пароль почтового ящика / пароль приложения</label>
            <input id="smtp-password" v-model="smtp.password" type="password" autocomplete="new-password" :required="!smtp.configured" :placeholder="smtp.configured ? 'Оставьте пустым, чтобы не менять' : 'Введите пароль SMTP'" maxlength="512"/>
            <p class="hint">Для Яндекса обычно нужен отдельный пароль приложения. SMTP защищён: Timeweb — SSL/TLS, порт 465; Яндекс — STARTTLS, порт 587. Вход и управление настройками должны выполняться только по HTTPS.</p>
            <button :disabled="busy" type="submit">{{ busy ? 'Сохраняем…' : 'Сохранить SMTP' }}</button>
            <button type="button" class="ghost" :disabled="busy || !smtp.configured" @click="$emit('test-smtp')">Отправить тестовое письмо на {{ settings.request_email }}</button>
          </form>
        </section>
</template>
