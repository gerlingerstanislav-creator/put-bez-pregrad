<script setup>
import { applyPhoneInput, phoneFocus, phoneBlur, normalizeEmail } from '../../utils/contactInput.js';

defineProps({
  settings: { type: Object, required: true },
  busy: Boolean,
});
defineEmits(['save', 'logout']);
</script>

<template>
<section class="panel">
          <div class="heading"><h2>Контакты</h2><button type="button" class="ghost" :disabled="busy" @click="$emit('logout')">Выйти</button></div>
          <p>Телефоны, почта и MAX меняются сразу во всех блоках публичного сайта.</p>
          <form class="form" @submit.prevent="$emit('save')">
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
</template>
