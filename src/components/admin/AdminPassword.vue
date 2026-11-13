<script setup>
defineProps({
  changing: { type: Object, required: true },
  busy: Boolean,
});
defineEmits(['change-password']);
</script>

<template>
<section class="panel">
          <h2>Смена пароля</h2>
          <form class="form" @submit.prevent="$emit('change-password')">
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
