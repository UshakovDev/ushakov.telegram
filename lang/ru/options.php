<?php
$MESS["USH_TG_TAB_SETTINGS"] = "Настройки";
$MESS["USH_TG_TAB_TITLE_SETTINGS"] = "Общие настройки и шаблоны сообщений";
$MESS["USH_TG_TAB_RIGHTS"] = "Права доступа";
$MESS["USH_TG_TAB_TITLE_RIGHTS"] = "Настройка прав";

$MESS["USH_TG_SECTION_MAIN"] = "<b>Основные параметры</b>";
$MESS["USH_TG_OPT_BOT_TOKEN"] = "Токен бота Telegram";
$MESS["USH_TG_OPT_BOT_USERNAME"] = "Имя бота (без @)";
$MESS["USH_TG_OPT_WEBHOOK_PUBLIC_HOST"] = "Публичный хост/домен для вебхука (например, <туннель>.trycloudflare.com)";
$MESS["USH_TG_OPT_CHAT_IDS"] = "Дополнительные получатели (чат‑ID/каналы, через запятую)";
 

$MESS["USH_TG_SECTION_EVENTS"] = "<b>Какие события отправлять сотрудникам</b>";
$MESS["USH_TG_OPT_SEND_ORDER_NEW"] = "Новый заказ";
$MESS["USH_TG_OPT_SEND_ORDER_STATUS"] = "Смена статуса заказа";
$MESS["USH_TG_OPT_SEND_ORDER_PAY"] = "Оплата заказа";
$MESS["USH_TG_OPT_SEND_ORDER_CANCELED"] = "Отмена заказа";
$MESS["USH_TG_OPT_SEND_USER_REGISTERED"] = "Регистрация пользователя";
$MESS["USH_TG_OPT_SEND_FORM_NEW"] = "";
$MESS["USH_TG_OPT_SEND_SHIPMENT_STATUS"] = "Статус отгрузки";
$MESS["USH_TG_OPT_SEND_ORDER_UNCANCELED"] = "Снятие отмены заказа";
$MESS["USH_TG_TPL_ORDER_CANCELED"] = "Шаблон: отмена заказа";
$MESS["USH_TG_TPL_ORDER_UNCANCELED"] = "Шаблон: снятие отмены";
$MESS["USH_TG_TPL_ORDER_CANCELED_DEF"] = "❌ Заказ #ORDER_ID# отменён\nПричина: #REASON#\nСумма: #PRICE#\nСсылка: #ADMIN_URL#";
$MESS["USH_TG_TPL_ORDER_UNCANCELED_DEF"] = "♻️ Отмена заказа #ORDER_ID# снята\nСумма: #PRICE#\nСсылка: #ADMIN_URL#";
$MESS["USH_TG_NOTE_WEBHOOK_SET"] = "Вебхук установлен: #WEBHOOK#";
$MESS["USH_TG_NOTE_WEBHOOK_DELETED"] = "Вебхук удалён";
$MESS["USH_TG_NOTE_ERROR"] = "Ошибка: #ERROR#";
$MESS["USH_TG_WEBHOOK_INFO_URL"] = "URL";
$MESS["USH_TG_WEBHOOK_INFO_PENDING"] = "Pending";
$MESS["USH_TG_WEBHOOK_INFO_LAST_ERROR"] = "Последняя ошибка";
$MESS["USH_TG_BIND_HELP_TITLE"] = "Привязать Telegram для текущего пользователя: отправьте боту команду";
$MESS["USH_TG_COPY"] = "Скопировать";
$MESS["USH_TG_BIND_COPY_HINT"] = "(кликните по команде, чтобы скопировать)";
$MESS["USH_TG_BIND_PERSONAL_HINT"] = "Либо перейдите в личный кабинет: #URL# и нажмите кнопку «Привязать Telegram».";
$MESS["USH_TG_NOTE_TEMPLATES_APPLIED"] = "Шаблоны обновлены (HTML-версия).";
$MESS["USH_TG_NOTE_TEMPLATES_RESET"] = "Шаблоны сброшены к значениям по умолчанию.";

$MESS["USH_TG_SECTION_TEMPLATES"] = "<b>Шаблоны сообщений</b>";
$MESS["USH_TG_TPL_ORDER_NEW"] = "Шаблон: новый заказ";
$MESS["USH_TG_TPL_ORDER_STATUS"] = "Шаблон: смена статуса заказа";
$MESS["USH_TG_TPL_ORDER_PAY"] = "Шаблон: оплата заказа";
$MESS["USH_TG_TPL_USER_REGISTERED"] = "Шаблон: регистрация пользователя";
$MESS["USH_TG_TPL_FORM_NEW"] = "";
$MESS["USH_TG_TPL_SHIPMENT_STATUS"] = "Шаблон: статус отгрузки";


$MESS["USH_TG_TPL_ORDER_NEW_DEF"] = "✳️ Новый заказ #ORDER_ID#\nФИО: #FIO#\nСумма: #PRICE#\nДоставка: #DELIVERY#\nОплата: #PAYMENT#\nСсылка: #ADMIN_URL#\nКорзина:\n#BASKET#";
$MESS["USH_TG_TPL_ORDER_STATUS_DEF"] = "↻ Статус заказа #ORDER_ID# изменён на: #STATUS#\nСумма: #PRICE#\nСсылка: #ADMIN_URL#";
$MESS["USH_TG_TPL_ORDER_PAY_DEF"] = "✅ Заказ #ORDER_ID# оплачен\nСумма: #PRICE#\nМетод оплаты: #PAYMENT#\nСсылка: #ADMIN_URL#";

$MESS["USH_TG_TPL_USER_REGISTERED_DEF"] = "✍️ Новая регистрация\nID: #USER_ID#\nЛогин: #LOGIN#\nEmail: #EMAIL#";
$MESS["USH_TG_TPL_FORM_NEW_DEF"] = "";
$MESS["USH_TG_TPL_SHIPMENT_STATUS_DEF"] = "⛟ Отгрузка по заказу #ORDER_ID#: #SHIPMENT_STATUS#\nДоставка: #DELIVERY_NAME#\nТрек: #TRACKING#\nСсылка: #ADMIN_URL#";

$MESS["USH_TG_HINT_BLOCK_TITLE"] = "Подсказки: наведите курсор на значок возле параметров";
$MESS["USH_TG_HINT_BOT_TOKEN"] = "Токен бота из BotFather (формат 1234567:AA...), используется для отправки сообщений и установки вебхука. Не делитесь токеном. Если сменили токен в BotFather: сначала нажмите \"Удалить вебхук\", затем \"Установить вебхук\".";
$MESS["USH_TG_HINT_BOT_USERNAME"] = "Имя бота без @. Нужен для формирования ссылок t.me/<username> и дипссылок /start. Если поменяли username в BotFather - обновите поле здесь, иначе ссылки могут вести не на вашего бота.";
$MESS["USH_TG_HINT_PUBLIC_HOST"] = "Публичный https-домен, по которому Telegram сможет обратиться к /bitrix/tools/ushakov.telegram/webhook.php. Обычно поле оставляют пустым: модуль возьмёт текущий домен сайта автоматически. Заполняйте только для локальной разработки через туннели (ngrok, trycloudflare и т.п.). Указывайте только домен без путей. После смены домена переустановите вебхук.";
$MESS["USH_TG_HINT_CHAT_IDS"] = "Необязательное поле. Можно указать несколько получателей через запятую: 12345, -100123..., @channelname. Если оставить пустым - админ-уведомления будут отправляться всем привязанным сотрудникам. Чтобы получать уведомления в общем чате отдела: создайте группу/канал для сотрудников, добавьте туда бота (администратором) и укажите @channelname или отрицательный ID группы.";
$MESS["USH_TG_HINT_STAFF_GROUPS"] = "ID групп Битрикс через запятую (Админка → Пользователи → Группы → колонка ID). Участники этих групп считаются сотрудниками и получают админ-уведомления. Если оставить пустым - сотрудники не определяются; можно временно использовать поле \"Дополнительные получатели\" для явных chat_id. Роли периодически актуализирует агент reconcileRoles.";
$MESS["USH_TG_WEBHOOK_STATUS"] = "Статус вебхука";
$MESS["USH_TG_WEBHOOK_STATUS_NOT_CONFIGURED"] = "Заполните токен бота и нажмите «Установить вебхук».";
$MESS["USH_TG_BTN_SET_WEBHOOK"] = "Установить вебхук";
$MESS["USH_TG_BTN_DELETE_WEBHOOK"] = "Удалить вебхук";
$MESS["USH_TG_BTN_INFO_WEBHOOK"] = "Проверить статус";

// Пояснение для сотрудников про двойные уведомления при тестах
$MESS["USH_TG_HELP_STAFF_DOUBLE_TITLE"] = "Тестирование уведомлений сотрудниками";
$MESS["USH_TG_HELP_STAFF_DOUBLE_TEXT"] = "Если сотрудник оформляет заказ сам и у него включены уведомления и для сотрудников, и для покупателей, он получит два сообщения в Telegram:<br>"
    ."- как сотрудник: с административной ссылкой на карточку заказа (#ADMIN_URL#);<br>"
    ."- как покупатель: с пользовательской ссылкой в личный кабинет (#URL#).<br><br>"
    ."Это ожидаемое поведение: один и тот же пользователь выступает сразу в двух ролях. Чтобы протестировать только клиентские сообщения, временно отключите чекбоксы в разделе «Какие события отправлять сотрудникам» или создайте тестовую учётку, не входящую в группы сотрудников.";

$MESS["USH_TG_BTN_DEFAULTS"] = "По умолчанию";
$MESS["USH_TG_BTN_DEFAULTS_HINT"] = "Сбросить шаблоны сообщений к значениям по умолчанию";
$MESS["USH_TG_BTN_APPLY_HTML_TEMPLATES"] = "";
$MESS["USH_TG_BTN_APPLY_HTML_TEMPLATES_HINT"] = "";

$MESS["USH_TG_BOT_LINK"] = "Ссылка на бота";
$MESS["USH_TG_DEEPLINK"] = "Дипссылка для /start (для текущего пользователя)";
$MESS["USH_TG_DEEPLINK_MANUAL"] = "Или отправьте вручную в Telegram команду";

// Видимая инструкция по созданию бота и получению токена
$MESS["USH_TG_HELP_BOT_TITLE"] = "Как создать бота и получить токен";
$MESS["USH_TG_HELP_BOT_TEXT"] = "Для работы модуля нужен токен Telegram‑бота. Получите его так:<br>"
	."1) Откройте в Telegram официального бота <a href=\"https://t.me/BotFather\" target=\"_blank\">@BotFather</a>.<br>"
	."2) Отправьте команду <code>/newbot</code> и следуйте инструкциям.<br>"
	."3) Придумайте название (отображается у бота) и уникальный логин.<br>"
	."4) Логин должен оканчиваться на <code>_bot</code> (например, <code>usertest_bot</code>).<br>"
	."5) После успешного создания @BotFather пришлёт токен - скопируйте его и вставьте в поле \"Токен бота Telegram\" ниже.<br>"
	."6) Скопируйте логин (username) бота без знака @ и вставьте в поле \"Имя бота (без @)\". Это значение используется для ссылок t.me/<username>.";

// Инструкция по работе со статусом вебхука
$MESS["USH_TG_HELP_WEBHOOK_TITLE"] = "Как работать со статусом вебхука";
$MESS["USH_TG_HELP_WEBHOOK_TEXT"] = "Вебхук - это адрес, на который Telegram присылает события для бота.<br>"
	."URL - текущий адрес вебхука в Telegram. Должен вести на <code>https://&lt;домен&gt;/bitrix/tools/ushakov.telegram/webhook.php</code>.<br>"
	."Pending - число необработанных обновлений у Telegram (обычно 0).<br>"
	."Кнопка \"Установить вебхук\" - регистрирует вебхук. Требуется публичный HTTPS‑домен, доступный из интернета или туннель.<br>"
	."Кнопка \"Удалить вебхук\" - отключает доставку обновлений боту.<br>"
	."Кнопка \"Проверить статус\" - запрашивает <code>getWebhookInfo</code> и показывает URL, Pending и возможную последнюю ошибку.<br>"
	."Когда использовать: после смены токена или домена - сначала \"Удалить вебхук\", затем \"Установить вебхук\". Для локальной разработки укажите публичный туннель в поле \"Публичный хост/домен для вебхука\" и нажмите \"Установить вебхук\".";

$MESS["USH_TG_OPT_STAFF_GROUPS"] = "ID групп сотрудников (через запятую)";
$MESS["USH_TG_SECTION_CUSTOMERS"] = "<b>Уведомления для покупателей</b>";
$MESS["USH_TG_OPT_CUSTOMER_ENABLED"] = "Включить уведомления покупателям";

$MESS["USH_TG_OPT_CUSTOMER_SHOW_BIND"] = "Показывать кнопку «Привязать Telegram» на страницах заказов";

$MESS["USH_TG_OPT_CUSTOMER_ORDER_NEW"] = "Покупателям: новый заказ";
$MESS["USH_TG_OPT_CUSTOMER_ORDER_STATUS"] = "Покупателям: смена статуса";
$MESS["USH_TG_OPT_CUSTOMER_ORDER_PAY"] = "Покупателям: оплата заказа";
$MESS["USH_TG_OPT_CUSTOMER_ORDER_CANCEL"] = "Покупателям: отмена заказа";
$MESS["USH_TG_OPT_CUSTOMER_ORDER_UNCANCEL"] = "Покупателям: снятие отмены";
$MESS["USH_TG_OPT_CUSTOMER_SHIPMENT_STATUS"] = "Покупателям: статус отгрузки";

// Подсказка для шаблонов сообщений
$MESS["USH_TG_HINT_TEMPLATES"] = "Шаблоны ниже общие для сотрудников и покупателей: текст сообщений одинаковый. Ссылка на заказ подставляется автоматически в зависимости от получателя: для сотрудников - на административную карточку заказа, для покупателя - в личный кабинет (/personal/orders/). Отправляются только те события, которые включены выше в соответствующих секциях.";