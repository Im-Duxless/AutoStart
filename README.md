# AutoStart

AutoStart ведёт набор в группы автошколы, расписание теории и вождения, тренажёр ПДД и учёт платежей. Vue-клиент и Laravel REST API запускаются на разных портах из одного репозитория.

## Что установить

- PHP 8.3 или новее с расширениями `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `curl`, `dom` и `xml`. Для запуска тестов без MySQL нужен `pdo_sqlite`.
- Composer 2, MySQL 8.0 или 8.4 и Node.js 20.19+ либо 22.12+.
- OSPanel подходит для запуска PHP и MySQL в Windows. VS Code и Postman удобны для работы с кодом и проверки API.

Проверьте версии командами `php -v`, `composer --version` и `node -v`. Если PHP есть только в OSPanel, откройте его терминал или добавьте каталог нужного PHP в `Path` текущего PowerShell. В стандартной установке Composer доступен как PHAR.

```powershell
$env:Path = 'C:\OSPanel\modules\PHP-8.3;' + $env:Path
php C:\OSPanel\data\PHP-8.3\default\composer\composer.phar --version
```

Если команда `composer` не найдена, используйте тот же путь к PHAR для команды `install` ниже.

## Сервер и база данных

1. Запустите модуль MySQL в OSPanel. Адрес, порт и пользователя возьмите из настроек запущенного профиля MySQL.
2. В консоли MySQL или в менеджере БД создайте отдельную базу приложения.

```sql
CREATE DATABASE autostart CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

3. В корне проекта установите зависимости и создайте локальную конфигурацию.

```powershell
composer install
if (-not (Test-Path -LiteralPath '.env')) {
    Copy-Item .env.example .env
    php artisan key:generate
}
```

Если файл `.env` уже настроен, используйте его. Проверьте `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` и `DB_PASSWORD`. В чистом окружении порт по умолчанию в примере равен `3306`. Для проверки на текущем компьютере использован отдельный экземпляр MySQL на `127.0.0.1:3308` с данными в `work/mysql-data`.

Чтобы снова запустить этот экземпляр с уже заполненной базой, выполните команду в корне проекта при свободном порте 3308.

```powershell
& 'C:\OSPanel\modules\MySQL-8.4\bin\mysqld.exe' --no-defaults --basedir=C:/OSPanel/modules/MySQL-8.4 `
    "--datadir=$($PWD.Path)\work\mysql-data" --bind-address=127.0.0.1 --port=3308 --mysqlx=OFF --console
```

4. Заполните новую базу и запустите Laravel.

```powershell
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8000
```

Проверка подключения к базе доступна по [адресу /api/health](http://127.0.0.1:8000/api/health). Если Symfony сообщает об ошибке доступа к временному каталогу OSPanel, укажите PHP каталог проекта.

```powershell
New-Item -ItemType Directory -Force work | Out-Null
php -d "sys_temp_dir=$($PWD.Path)\work" artisan serve --host=127.0.0.1 --port=8000
```

Для полного пересоздания **выделенной локальной базы** выполните `php artisan migrate:fresh --seed`. Команда удаляет все таблицы в выбранной базе.

## Клиент Vue

Откройте второй терминал в корне проекта.

```powershell
Set-Location client
npm ci
npm run dev
```

Откройте [AutoStart](http://localhost:5173). Для сборки используйте `npm run build`. Готовые файлы появятся в `client/dist`. Статический сервер должен отдавать `index.html` для маршрутов Vue Router.

По умолчанию браузер обращается к `http://127.0.0.1:8000/api`. Если меняете адрес API, скопируйте `client/.env.example` в `client/.env.local` и задайте `VITE_API_URL`. В Laravel укажите точный адрес клиента в `CLIENT_URL` и перезапустите оба приложения.

## Локальные аккаунты

| Роль | Электронная почта | Пароль |
| --- | --- | --- |
| Администратор | `admin@autostart.local` | `AutoStart!2026` |
| Инструктор | `instructor@autostart.local` | `AutoStart!2026` |
| Курсант | `student@autostart.local` | `AutoStart!2026` |

Сидеры добавляют локальные данные в окружениях `local` и `testing`. Для нового наполнения используются даты относительно дня запуска. У готового курсанта подтверждена группа B. У инструктора есть проведённое вождение, предстоящие слоты и прошедшее занятие, которое можно отметить проведённым.

Поля `SCHOOL_PHONE`, `SCHOOL_EMAIL`, `SCHOOL_ADDRESS` и `SCHOOL_HOURS` заполняются фактическими контактами школы. При пустых полях на странице доступна подача заявки через кабинет. Валюта и часовой пояс задаются в `SCHOOL_CURRENCY` и `APP_TIMEZONE`. Занятия вводятся и показываются по местному времени школы. Фотография на главной сгенерирована для оформления сайта. Билеты содержат авторские тренировочные вопросы.

## Проверка сценария

1. Зарегистрируйте нового курсанта, откройте «Обучение» и подайте заявку в открытую группу B.
2. В кабинете администратора откройте «Заявки» и зачислите курсанта. Во вкладке «Занятия» можно добавить теорию для этой группы.
3. Войдите курсантом и проверьте группу в расписании. На странице «Вождение» выберите время и подтвердите запись.
4. После окончания занятия инструктор открывает его из раздела «Мои занятия» и отмечает проведённым. Для быстрой ручной проверки можно завершить уже прошедшее занятие у локального инструктора.
5. Откройте «Экзамены ПДД», решите билет и проверьте сохранённый разбор. После внесения платежа администратором проверьте историю и остаток в кабинете курсанта.

```powershell
php artisan route:list --path=api
php artisan test
```

По умолчанию PHPUnit запускает тесты на временной SQLite в памяти. Для той же проверки на MySQL используйте отдельную пустую схему. Пример в PowerShell ниже использует остальные реквизиты MySQL из `.env`. PHPUnit пересоздаёт таблицы в указанной тестовой схеме.

```sql
CREATE DATABASE autostart_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```powershell
$env:DB_CONNECTION = 'mysql'
$env:DB_DATABASE = 'autostart_test'
php artisan test
Remove-Item Env:DB_CONNECTION, Env:DB_DATABASE
```

## Где искать код

| Каталог | Назначение |
| --- | --- |
| `routes/api.php` | Маршруты и допуски по ролям |
| `app/Http/Controllers/Api` | CRUD и вызовы сервисов |
| `app/Http/Requests`, `app/Http/Resources` | Проверка входных данных и JSON-ответы |
| `app/Models`, `app/Enums` | Семь моделей, связи и перечисления |
| `app/Policies`, `app/Http/Middleware` | Проверки роли и владельца |
| `app/Services` | Зачисление, бронирование и проверка билета |
| `database` | Миграции, фабрики и начальные данные |
| `client/src/views`, `client/src/components` | Страницы и общие элементы Vue |
| `tests/Feature` | Проверки сценариев через HTTP-интерфейс Laravel |

- [Архитектура и диаграмма вариантов использования](docs/architecture.md)
- [Сущности и ER-диаграмма](docs/database.md)
- [Маршруты и параметры API](docs/api.md)
- [Правила записи на вождение](docs/booking.md)
- [Коллекция Postman](docs/AutoStart.postman_collection.json)
