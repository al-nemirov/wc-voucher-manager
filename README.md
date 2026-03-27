# WC Voucher Manager

![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue?logo=wordpress)
![WooCommerce](https://img.shields.io/badge/WooCommerce-5.0%2B-96588a?logo=woo)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?logo=php&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green)

[English](#english) | [Русский](#русский)

---

## English

WooCommerce plugin that replaces the standard coupon system with a beautiful Voucher Manager. Bulk creation, usage tracking, adaptive admin UI, and delightful frontend notifications.

### Features

- **Full rename**: "Coupon" → "Voucher" across the entire WooCommerce interface (admin + frontend, EN/RU)
- **Custom admin page**: Voucher table with status badges, usage bars, expiry warnings, batch filtering
- **Bulk generation**: Create up to 1000 vouchers at once via AJAX with progress bar
- **Usage tracking**: Date, customer email, order ID, full history per voucher
- **Frontend toast**: Animated notification with confetti when a voucher is applied in the cart
- **Thank-you banner**: Beautiful message on the order confirmation page showing savings
- **CSV export**: Export all vouchers or a specific batch
- **Statistics dashboard**: Cards with totals, active/used/expired counts, recent batches
- **Responsive design**: 4 breakpoints (1200 / 960 / 782 / 480px)
- **HPOS compatible**: Supports WooCommerce High-Performance Order Storage
- **Single menu entry**: Replaces default WooCommerce coupon menu with one "Vouchers" page

### Requirements

| Dependency   | Version |
|--------------|---------|
| WordPress    | 5.0+    |
| WooCommerce  | 5.0+    |
| PHP          | 7.4+    |

### Installation

#### Manual upload

1. Download the latest release as a `.zip` archive.
2. In WordPress admin go to **Plugins > Add New > Upload Plugin**.
3. Select the `.zip` file and click **Install Now**.
4. Activate the plugin.

#### Via FTP / file manager

1. Copy the `wc-voucher-manager` folder into `wp-content/plugins/`.
2. In WordPress admin go to **Plugins** and activate **WC Voucher Manager**.

#### Via Git (for development)

```bash
cd wp-content/plugins/
git clone https://github.com/al-nemirov/wc-voucher-manager.git
```

Activate the plugin from the WordPress admin panel.

### Usage

1. Navigate to **WooCommerce > Vouchers** in the admin sidebar.
2. **All vouchers** tab — view, search, filter by status, sort, bulk actions (trash / deactivate / activate).
3. **Bulk creation** tab — set quantity, prefix, discount type/amount, expiry, usage limit, then click **Create vouchers**.
4. **Statistics** tab — overview cards and recent batch history.
5. On the frontend, when a customer applies a voucher code in the cart, they see an animated toast with confetti and a friendly message.
6. On the order confirmation page, a banner shows the savings from the applied voucher.

### Plugin Structure

```
wc-voucher-manager/
├── wc-voucher-manager.php            # Main plugin file
├── includes/
│   ├── class-voucher-renamer.php     # Coupon → Voucher text replacement
│   ├── class-voucher-admin.php       # Admin page, WP_List_Table, tabs
│   ├── class-voucher-generator.php   # Bulk creation via AJAX + CSV export
│   ├── class-voucher-tracker.php     # Usage tracking, order metabox, column
│   └── class-voucher-frontend.php    # Toast notification, thank-you banner
├── assets/
│   ├── css/
│   │   ├── admin.css                 # Admin styles (responsive)
│   │   └── frontend.css              # Frontend styles (toast, banner, cart)
│   └── js/
│       ├── admin.js                  # Admin scripts (generator, toasts, copy)
│       └── frontend.js               # Frontend scripts (toast, confetti)
└── templates/
```

### AJAX Actions

| Action                        | Method | Description                |
|-------------------------------|--------|----------------------------|
| `wc_voucher_generate_batch`   | POST   | Generate a batch of vouchers |
| `wc_voucher_export_csv`       | GET    | Export vouchers as CSV     |

All actions require a valid `wc_voucher_nonce` and the `manage_woocommerce` capability.

### Contributing

Contributions are welcome! To get started:

1. Fork the repository.
2. Create a feature branch: `git checkout -b feature/my-feature`.
3. Commit your changes with clear messages.
4. Push to your fork and open a Pull Request.

---

## Русский

Плагин WooCommerce, который заменяет стандартную систему купонов на красивый Менеджер ваучеров. Массовое создание, отслеживание использования, адаптивный интерфейс админки и приятные уведомления на фронтенде.

### Возможности

- **Полное переименование**: «Купон» → «Ваучер» по всему интерфейсу WooCommerce (админка + фронтенд, EN/RU)
- **Кастомная админ-страница**: таблица ваучеров со статус-бейджами, прогресс-баром использований, предупреждениями об истечении, фильтром по партиям
- **Массовое создание**: до 1000 ваучеров за раз через AJAX с прогресс-баром
- **Отслеживание использования**: дата, email покупателя, ID заказа, полная история по каждому ваучеру
- **Toast-уведомление**: анимированное уведомление с конфетти при применении ваучера в корзине
- **Баннер «Спасибо»**: красивое сообщение на странице подтверждения заказа с суммой экономии
- **Экспорт в CSV**: все ваучеры или конкретная партия
- **Дашборд статистики**: карточки с итогами, активные/использованные/истёкшие, последние партии
- **Адаптивная вёрстка**: 4 брейкпоинта (1200 / 960 / 782 / 480px)
- **HPOS совместимость**: поддержка High-Performance Order Storage WooCommerce
- **Один пункт меню**: заменяет стандартный пункт купонов WooCommerce на единственную страницу «Ваучеры»

### Требования

| Зависимость  | Версия  |
|--------------|---------|
| WordPress    | 5.0+    |
| WooCommerce  | 5.0+    |
| PHP          | 7.4+    |

### Установка

#### Ручная загрузка

1. Скачайте последний релиз в виде `.zip` архива.
2. В админке WordPress перейдите в **Плагины > Добавить новый > Загрузить плагин**.
3. Выберите `.zip` файл и нажмите **Установить**.
4. Активируйте плагин.

#### Через FTP / файловый менеджер

1. Скопируйте папку `wc-voucher-manager` в `wp-content/plugins/`.
2. В админке WordPress перейдите в **Плагины** и активируйте **WC Voucher Manager**.

#### Через Git (для разработки)

```bash
cd wp-content/plugins/
git clone https://github.com/al-nemirov/wc-voucher-manager.git
```

Активируйте плагин через панель администратора WordPress.

### Использование

1. Перейдите в **WooCommerce > Ваучеры** в боковом меню админки.
2. Вкладка **Все ваучеры** — просмотр, поиск, фильтрация по статусу, сортировка, массовые действия (в корзину / деактивировать / активировать).
3. Вкладка **Массовое создание** — укажите количество, префикс, тип/сумму скидки, дату истечения, лимит использований, затем нажмите **Создать ваучеры**.
4. Вкладка **Статистика** — обзорные карточки и история последних партий.
5. На фронтенде, когда покупатель применяет код ваучера в корзине, появляется анимированный toast с конфетти и приятным сообщением.
6. На странице подтверждения заказа отображается баннер с суммой экономии по ваучеру.

### Структура плагина

```
wc-voucher-manager/
├── wc-voucher-manager.php            # Главный файл плагина
├── includes/
│   ├── class-voucher-renamer.php     # Замена Купон → Ваучер
│   ├── class-voucher-admin.php       # Админ-страница, WP_List_Table, вкладки
│   ├── class-voucher-generator.php   # Массовое создание через AJAX + CSV экспорт
│   ├── class-voucher-tracker.php     # Трекинг использования, метабокс заказа, колонка
│   └── class-voucher-frontend.php    # Toast-уведомление, баннер «Спасибо»
├── assets/
│   ├── css/
│   │   ├── admin.css                 # Стили админки (адаптивные)
│   │   └── frontend.css              # Стили фронтенда (toast, баннер, корзина)
│   └── js/
│       ├── admin.js                  # Скрипты админки (генератор, тосты, копирование)
│       └── frontend.js               # Скрипты фронтенда (toast, конфетти)
└── templates/
```

### AJAX-действия

| Действие                      | Метод | Описание                      |
|-------------------------------|-------|-------------------------------|
| `wc_voucher_generate_batch`   | POST  | Генерация партии ваучеров     |
| `wc_voucher_export_csv`       | GET   | Экспорт ваучеров в CSV        |

Все действия требуют валидный nonce `wc_voucher_nonce` и право `manage_woocommerce`.

### Участие в разработке

Мы рады вашему участию! Для начала:

1. Сделайте форк репозитория.
2. Создайте ветку: `git checkout -b feature/my-feature`.
3. Закоммитьте изменения с понятным описанием.
4. Отправьте в свой форк и откройте Pull Request.

---

## Author / Автор

Alexander Nemirov

## License / Лицензия

[MIT](LICENSE)
