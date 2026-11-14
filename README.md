# lekalo-php

Языковой адаптер [Lekalo](https://github.com/ichinya/lekalo) для PHP: понимание PHP-кода, типов и символов, привязка к исходникам, общие интеграции Mago и Testo.

- Протокол к ядру — существующий lekalo.target process/JSON protocol (сейчас реализован в adapters/php-laravel монорепы).
- Framework-пакеты (lekalo-php-laravel, lekalo-php-symfony) подключаются через versioned provider API — issue [#1](https://github.com/ichinya/lekalo-php/issues/1).
- Общий план отделения — [ichinya/lekalo#241](https://github.com/ichinya/lekalo/issues/241).

Статус: код адаптера переносится из adapters/php-laravel монорепы; на переходном этапе совместимый артефакт собирается в единый adapter.php из закреплённых выпусков пакетов.
