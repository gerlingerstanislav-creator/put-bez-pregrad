# Подключение разработчика

## Необходимое ПО

- Node.js 22 или совместимая версия LTS и npm;
- PHP 8.2 или новее для PHP API;
- Composer для установки PHPMailer;
- Git.

## Первые шаги

1. `git clone` текущего репозитория; работать в feature-ветке.
2. `npm install` — установить JS-зависимости.
3. `npm run dev` — запустить Vite dev-сервер для изменения интерфейса.
4. `npm run check` — проверить JS и unit-тесты.
5. `npm run build` и `node scripts/check-dist.mjs` — проверить готовую сборку.
6. `composer install --working-dir=mail-php` — установить серверные зависимости.
7. Для проверки PHP: `find public/api server/admin-api -name '*.php' -print0 | xargs -0 -n1 php -l` (для PowerShell: `Get-ChildItem public/api,server/admin-api -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }`).

Браузерный dev-сервер Vite сам не реализует PHP API. Для интеграционных тестов используй собранный каталог `dist/` и локальный PHP-сервер, при необходимости установив Composer-зависимости в `dist/api/vendor`. **Не используй рабочие SMTP-реквизиты на локальной машине без необходимости.**

## Где искать нужное изменение

- Тексты первого экрана → `src/components/landing/HeroSection.vue`.
- Блоки лендинга → `src/components/landing/`.
- Стили → `src/styles/landing.css`.
- Контакты → `src/composables/useSiteContacts.js`, `public/api/public.php`.
- Форма и антиспам → `PricingRequestSection.vue`, `useTransportRequest.js`, `public/api/{submit,spam}.php`.
- SMTP → `public/api/mailer.php` и административный PHP.
- Админка → `src/components/admin/`, `src/Admin.vue`, `server/admin-api/`.
- Сборка → `vite.config.js`, `scripts/finalize-build.mjs`.

## Правила Pull Request

Объясни цель, перечисли изменённые модули, тесты и риск регрессии. Не включай значения GitHub Secrets, пароль администратора, содержимое `.pbp-private`, фактический скрытый URL и персональные данные заявителей в код, скриншоты или CI-логи.

Не объединяй изменения в `main` и не выполняй workflow `Deploy` без согласования владельца.
