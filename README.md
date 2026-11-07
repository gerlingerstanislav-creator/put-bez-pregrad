# Путь без преград

Самостоятельный публичный одностраничный сайт перевозки людей на инвалидных колясках в Ульяновске и области. Исходная страница перенесена из [project-0](https://github.com/gerlingerstanislav-creator/project-0), зависимость от Laravel/Inertia удалена.

**Стек:** Vue 3, Vite, Tailwind CSS 4. В production только статические файлы: HTML, CSS, JavaScript, изображения.

## Локальная работа

```bash
npm install
npm run dev
npm run check
npm run build
node scripts/check-dist.mjs
```

## Автоматическая публикация

`.github/workflows/deploy.yml` запускается при каждом push в `main` и вручную через GitHub Actions. Workflow проверяет сборку и загружает содержимое `dist/` на Timeweb через **FTPS с проверкой сертификата, порт 21**.

В разделе GitHub **Settings → Secrets and variables → Actions** должны быть четыре секрета:

| Secret | Что задавать |
|---|---|
| `TIMEWEB_FTP_HOST` | FTP-хост Timeweb (без схемы и пути) |
| `TIMEWEB_FTP_USER` | FTP-логин |
| `TIMEWEB_FTP_PASSWORD` | FTP-пароль |
| `TIMEWEB_FTP_DIR` | `/`, если FTP ограничен директорией public_html; иначе `/public_html/` или фактический путь от FTP-корня |

Путь деплоя нормализуется для корректной завершающей косой черты. Полная очистка удалённой директории отключена; скрипт синхронизации управляет только ранее загруженными им файлами.

Проверка до привязки домена: http://bpreg.tw1.ru. После публикации необходимо привязать домен `путь-без-преград73.рф` в Timeweb, настроить DNS, выпустить SSL и включить HTTPS.

Заявки из формы **не отправляются автоматически на сервер**: открывается почтовое приложение посетителя с подготовленным письмом на bezpregrad73@yandex.ru. Изображения автопарка демонстрационные, а не фотографии реальной машины.

Исходники: `src/App.vue` (лендинг), `src/components` (иконки), `public/images` (иллюстрации), `src/styles.css` (глобальные стили).
