# REST API AutoStart

Базовый URL локального сервера `http://127.0.0.1:8000/api`. Клиент передаёт `Accept: application/json`. Для тела JSON используйте `Content-Type: application/json`, для защищённых запросов добавьте `Authorization: Bearer <token>`.

## Доступ и основные действия

В таблицах пути указаны относительно базового URL. Роли в столбце «Доступ» применяются и к чтению, и к действиям, указанным в строке.

| Метод и путь | Контроллер и действие | Доступ | Успех и тело запроса |
| --- | --- | --- | --- |
| GET `/health` | SchoolController.health | Все | 200, состояние MySQL. При недоступности 503 |
| GET `/school` | SchoolController.show | Все | 200, категории, контакты, валюта, часовой пояс, имена инструкторов |
| POST `/register` | AuthController.register | Все | 201, `name,email,password,password_confirmation`, необязательный `phone` |
| POST `/login` | AuthController.login | Все | 200, `email,password` |
| POST `/logout` | AuthController.logout | Все роли | 204, отзывает текущий токен |
| GET `/me` | AuthController.me | Все роли | 200, профиль |
| PATCH `/me` | AuthController.update | Все роли | 200, `name,phone` |
| POST `/study-groups/{id}/enroll` | EnrollmentController.enroll | Курсант | 201, заявка от текущего пользователя |
| GET `/enrollments` | EnrollmentController.index | Курсант, администратор | 200, курсант получает только свои заявки |
| POST `/enrollments` | EnrollmentController.store | Администратор | 201, `user_id,study_group_id` |
| PATCH `/enrollments/{id}` | EnrollmentController.update | Администратор | 200, `status` равен `approved` или `rejected` |
| GET `/lessons/summary` | LessonController.summary | Курсант, инструктор | 200, минуты, часы и число занятий |
| POST `/lessons/{id}/book` | LessonController.book | Курсант | 200, бронирование свободного занятия |
| POST `/lessons/{id}/cancel` | LessonController.cancel | Все роли по политике | 200, отмена своей записи или занятия |
| POST `/lessons/{id}/complete` | LessonController.complete | Инструктор-владелец, администратор | 200, завершение вождения |
| GET `/tickets` | ExamController.tickets | Курсант | 200, билеты и проходной процент |
| GET `/tickets/{number}?category=B` | ExamController.ticket | Курсант | 200, вопросы и `version` |
| POST `/exams/submit` | ExamController.submit | Курсант | 201, `category,ticket_number,version,answers` |
| GET `/exams/history` | ExamController.history | Курсант, администратор | 200, список попыток |
| GET `/exams/history/{id}` | ExamController.show | Курсант-владелец, администратор | 200, попытка и разбор ответов |
| GET `/payments/balance` | PaymentController.balance | Курсант | 200, `paid,tuition,due,pending,available` |
| POST `/payments/demo` | PaymentController.checkout | Курсант | 201, `amount,payment_method,reference`. Способ `card` или `cash`, идентификатор запроса UUID v4 |
| POST `/payments/{id}/confirm` | PaymentController.confirm | Администратор | 200, подтверждение заявки на наличные |
| POST `/payments/{id}/cancel` | PaymentController.cancel | Курсант-владелец, администратор | 200, отмена заявки на наличные |
| GET `/reports/summary` | ReportController.summary | Администратор | 200, сводка по школе |
| GET `/reports/instructors` | ReportController.instructors | Администратор | 200, часы и число завершённых занятий. Период через `from,to` |

## CRUD

Стандартные действия `index, show, store, update, destroy` зарегистрированы через `Route::apiResource`. Для изменения поддерживаются `PATCH` и `PUT`. В клиенте используется `PATCH` с изменяемыми полями.

| Ресурс | GET списка и записи | POST / PATCH / DELETE | Поля |
| --- | --- | --- | --- |
| `/users` и `/users/{id}` | Администратор | Администратор | `name,email,role,phone,password,password_confirmation`. При изменении пароль можно пропустить |
| `/study-groups` и `/study-groups/{id}` | Администратор. Курсант видит открытые и свои подтверждённые группы | Администратор | `name,category,capacity,status,start_date,tuition` |
| `/cars` и `/cars/{id}` | Администратор. Инструктор видит активный транспорт | Администратор | `brand,model,plate_number,category,is_active` |
| `/lessons` и `/lessons/{id}` | Все роли в рамках политики | POST и PATCH для администратора и инструктора-владельца. DELETE для администратора | `type,start_at,end_at`. Для теории `study_group_id`. Для практики `car_id,instructor_id`. У инструктора его ID подставляется автоматически |
| `/questions` и `/questions/{id}` | Администратор | Администратор | `category,ticket_number,text,options,correct_option`. Индекс ответа начинается с 0 |
| `/payments` и `/payments/{id}` | Администратор. Курсант видит собственные записи | Администратор | `user_id,amount,payment_date,payment_method,status,comment` |

GET возвращает 200. POST создания возвращает 201, PATCH возвращает 200, DELETE возвращает 204. Для ошибок используются 401, 403, 404, 409 и 422. При высокой частоте входа и регистрации сервер возвращает 429.

## Списки и данные

В списках с пагинацией доступны `page`, `per_page` от 1 до 100, `sort` и `order=asc|desc`. Сортировать можно по полям с заголовками сортировки в административных таблицах и по `id`. В истории экзаменов допустимы `category,ticket_number,correct_answers,created_at`, в заявках `status,created_at`.

| Список | Фильтры |
| --- | --- |
| `users` | `search,role,email` |
| `study-groups` | `search,category,status` |
| `cars` | `search,category,is_active` |
| `lessons` | `search,type,status,instructor_id,study_group_id,from,to,upcoming`. Для курсанта `mode=mine\|available` |
| `enrollments` | `search,category,status,study_group_id` |
| `questions` | `search,category,ticket_number` |
| `exams/history` | `search,category,ticket_number,passed` |
| `payments` | `search,user_id,payment_method,status,from,to`. Ответ включает `received_total` для фильтра |

Календарные даты передаются как `YYYY-MM-DD`, начало и окончание занятия как `YYYY-MM-DDTHH:mm` во времени школы или ISO 8601 с явным смещением. Логические фильтры в строке запроса принимают `1` и `0`. Платежи имеют способы `cash`, `transfer`, `card` и статусы `received`, `pending`, `void`. Статус `pending` используется для ожидающих заявок на наличные. Такие заявки уменьшают доступную к оплате сумму `available`. Остаток `due` уменьшается после перехода платежа в `received`. При неизвестной стоимости обучения `due` и `available` равны `null`.

Запись о демо-операции имеет `is_demo: true`. Повтор `POST /payments/demo` с тем же идентификатором, способом и суммой возвращает ранее созданную запись с кодом 200. Демо-операции с картой сразу получают статус `received`. Заявки на наличные переходят в `received` после подтверждения или в `void` после отмены.

Список ресурсов возвращает `data`, `links`, `meta`. Ошибка валидации содержит `message` и `errors` по полям. Бизнес-конфликт содержит `message` с причиной. Ответ входа и регистрации содержит `data`, `token` и `token_type`.

При сдаче билета передайте `version` из GET и массив объектов вида `{"question_id": 12, "option": 0}`. Индексы вариантов начинаются с нуля. Если администратор изменил билет после загрузки, сервер просит открыть билет заново. Ключи ответов появляются после отправки попытки или при открытии её разбора.

Импортируйте [коллекцию Postman](AutoStart.postman_collection.json) и начните с входа нужной роли. Логин сохраняет токен и идентификатор пользователя в переменные коллекции. Запросы с записью используют локальную базу из текущего `base_url`.

Порядок запуска коллекции, назначение переменных и проверка зачисления с бронированием описаны в [руководстве по Postman](postman-guide.md).
