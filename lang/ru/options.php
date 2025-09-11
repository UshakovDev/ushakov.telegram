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
$MESS["USH_TG_OPT_SEND_FORM_NEW"] = "Новая заявка (через почтовые события)";

$MESS["USH_TG_SECTION_TEMPLATES"] = "<b>Шаблоны сообщений</b>";
$MESS["USH_TG_TPL_ORDER_NEW"] = "Шаблон: новый заказ";
$MESS["USH_TG_TPL_ORDER_STATUS"] = "Шаблон: смена статуса заказа";
$MESS["USH_TG_TPL_ORDER_PAY"] = "Шаблон: оплата заказа";
$MESS["USH_TG_TPL_USER_REGISTERED"] = "Шаблон: регистрация пользователя";
$MESS["USH_TG_TPL_FORM_NEW"] = "Шаблон: новая заявка";

$MESS["USH_TG_TPL_ORDER_NEW_DEF"] = "🛒 Новый заказ #ORDER_ID#\nФИО: #FIO#\nСумма: #PRICE#\nДоставка: #DELIVERY#\nОплата: #PAYMENT#\nСсылка: #ADMIN_URL#\nКорзина:\n#BASKET#";
$MESS["USH_TG_TPL_ORDER_STATUS_DEF"] = "🔁 Статус заказа #ORDER_ID# изменён на: #STATUS#\nСумма: #PRICE#\nСсылка: #ADMIN_URL#";
$MESS["USH_TG_TPL_ORDER_PAY_DEF"] = "✅ Заказ #ORDER_ID# оплачен. Сумма: #PRICE#\nМетод оплаты: #PAYMENT#\nСсылка: #ADMIN_URL#";
$MESS["USH_TG_TPL_USER_REGISTERED_DEF"] = "👤 Новая регистрация\nID: #USER_ID#\nЛогин: #LOGIN#\nEmail: #EMAIL#";
$MESS["USH_TG_TPL_FORM_NEW_DEF"] = "✉️ Новая заявка (#EVENT_NAME#)\nТема: #SUBJECT#\nПоля:\n#FIELDS#";

$MESS["USH_TG_HINT_BLOCK_TITLE"] = "Подсказки: наведите курсор на значок возле параметров";
$MESS["USH_TG_HINT_CHAT_IDS"] = "Можно указывать несколько chat_id через запятую, поддерживаются отрицательные ID супергрупп и @channelname. Поле используется как дополнение к сотрудникам: если оставить пустым, админ‑уведомления будут отправляться всем привязанным сотрудникам.";
$MESS["USH_TG_WEBHOOK_STATUS"] = "Статус вебхука";
$MESS["USH_TG_WEBHOOK_STATUS_NOT_CONFIGURED"] = "Заполните токен бота и нажмите «Установить вебхук».";
$MESS["USH_TG_BTN_SET_WEBHOOK"] = "Установить вебхук";
$MESS["USH_TG_BTN_DELETE_WEBHOOK"] = "Удалить вебхук";
$MESS["USH_TG_BTN_INFO_WEBHOOK"] = "Проверить статус";
$MESS["USH_TG_DEEPLINK"] = "Дипссылка для /start (для текущего пользователя)";
$MESS["USH_TG_DEEPLINK_MANUAL"] = "Или отправьте вручную в Telegram команду";

$MESS["USH_TG_OPT_STAFF_GROUPS"] = "ID групп сотрудников (через запятую)";
$MESS["USH_TG_SECTION_CUSTOMERS"] = "<b>Уведомления для покупателей</b>";
$MESS["USH_TG_OPT_CUSTOMER_ENABLED"] = "Включить уведомления покупателям";
$MESS["USH_TG_OPT_CUSTOMER_ORDER_NEW"] = "Покупателям: новый заказ";
$MESS["USH_TG_OPT_CUSTOMER_ORDER_STATUS"] = "Покупателям: смена статуса";
$MESS["USH_TG_OPT_CUSTOMER_ORDER_PAY"] = "Покупателям: оплата заказа";
$MESS["USH_TG_OPT_CUSTOMER_ORDER_CANCEL"] = "Покупателям: отмена заказа";
$MESS["USH_TG_OPT_CUSTOMER_ORDER_UNCANCEL"] = "Покупателям: снятие отмены";