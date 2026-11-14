<script setup>
import { ref } from 'vue';

defineProps({ busy: Boolean });
const emit = defineEmits(['login']);
const password = ref('');

function emitLogin() {
  if (!password.value) return;
  emit('login', password.value);
  password.value = '';
}
</script>

<template>
<section class="panel">
        <h2>Вход</h2>
        <form @submit.prevent="emitLogin" class="form">
          <label for="pwd">Пароль администратора</label>
          <input id="pwd" v-model="password" type="password" autocomplete="current-password" required />
          <button :disabled="busy || !password" type="submit">{{ busy ? 'Проверяем…' : 'Войти' }}</button>
        </form>
      </section>
</template>
