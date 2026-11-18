# lekalo-php

Языковой адаптер [Lekalo](https://github.com/ichinya/lekalo) для PHP: понимание PHP-кода, типов и символов, привязка к исходникам, общие интеграции Mago и Testo.

- Протокол к ядру — существующий lekalo.target process/JSON protocol.
- Framework-пакеты (lekalo-php-laravel, lekalo-php-symfony) подключаются через versioned provider API — issue [#1](https://github.com/ichinya/lekalo-php/issues/1).
- Общий план отделения — [ichinya/lekalo#241](https://github.com/ichinya/lekalo/issues/241).

## Подключение к проекту

В каталоге приложения выполните:

```sh
lekalo init --runtime gh/ichinya/lekalo-php --rules gh/ichinya/lekalo-php-laravel --component-path routes
lekalo runtime lock
lekalo validate --strict
```

Публичный манифест `lekalo.package.json` поставляет `runtime/check.php` по протоколу `lekalo/runtime/v1`. Для запуска нужен PHP CLI 8.x с tokenizer. Версия пакета 0.2.0 независима от версии интерпретатора. Runtime читает статические регистрации в файлах из настройки `php.static-router/v1` выбранного пакета правил. Пакеты с одинаковыми требованиями могут использовать одну настройку. При конфликте настроек или неподдерживаемой конструкции runtime возвращает `partial`.

## Состав PHP-адаптера

| Путь | Содержимое |
| --- | --- |
| `src/provider.php` | Контракт провайдеров `dev.lekalo.provider@0.1.0` (`lekalo/provider/v0.1.0`): закрытый реестр, детерминированные переговоры версий, стабильные коды отказов `provider-missing` / `provider-duplicate` / `provider-version-conflict`. |
| `src/kernel.php`, `src/{ai-lint,analyzer,strict-profile,scenario-map,type-*,operation-*,route-policy,route-map}.php` | Языковое ядро: протокол, декодер, канонический вывод, типы, операции, generic-HTTP route map, статический lint. Ядро не называет ни один фреймворк. |
| `mago-toolchain.lock.json` | Пин тулчейна Mago (языковая custody). |
| `tests/protocol.php` | Сюита протокола: 143 проверки, включая переговоры провайдеров с синтетическим фикстурным провайдером (критерий приёмки lekalo-php#1) и child-лег пустого реестра. |
| `tests/analyzer.php` | Сюита analyzer-шва: 61 проверка. |
| `main.php` | Source-режим без провайдера: `describe`/`scan`/типы/операции работают; framework-семейства отказывают типизированно (`provider-missing`). |
| `lekalo.php-build.json` | Манифест сборки runtime: порядок PHP-модулей, ресурсы и версия provider API. |
| `build.php`, `build-lib.php` | Сборщик самостоятельного артефакта из runtime и выбранных пакетов правил. |
| `tests/composition.php` | Runtime без Laravel, сторонние rules, детерминизм, границы входов и конфликты providers. |

## Независимая сборка runtime и правил

```sh
# PHP без framework-пакета: target=php, identity=lekalo-target-php
php -n build.php --output /tmp/php-adapter.php

# Совместимая Laravel-композиция; путь может указывать на любой checkout
php -n build.php --rules ../lekalo-php-laravel \
  --id lekalo-target-php-laravel --target php-laravel \
  --output /tmp/laravel-adapter.php

# Проверка сохранённых байтов без перезаписи
php -n build.php --output /tmp/php-adapter.php --check
php -n tests/composition.php
```

`--runtime DIR` выбирает другой runtime-пакет; `--rules DIR` можно повторить
до восьми раз. Имена репозиториев не участвуют в выборе. Каждый каталог
содержит `lekalo.php-build.json` со схемой `lekalo/php-package/v1`, ролью
`runtime` или `rules`, `id`, `version`, `provider_api`, списком `modules`
и объектом `resources` (имя PHP-константы → относительный файл).
Модули — PHP-фрагменты с `<?php` и LF; они используют общие символы процесса
и не должны требовать соседние файлы во время исполнения готового артефакта.

Сборщик читает исходники как данные, закрепляет их SHA-256 в отчёте и
сортирует пакеты правил по ID. Чужой код при сборке не исполняется.
Несовместимый provider API, повторяющиеся ID, ссылки и выход за каталог
отказывают до записи результата. Runtime регистрирует hooks правил через
`lekalo_provider_register`; пересекающиеся одиночные capability families
отказывают с `provider-capability-conflict`. Scenario runners объединяются
отдельным API; одинаковые runner ID и разные defaults отказывают с
`provider-runner-conflict`.

Для старых сборочных пакетов сборщик также принимает имя `lekalo.package.json`. Версия legacy-композиции сохраняется в `lekalo.php-build.json`.

## Запуск

Пакет работает со всеми актуальными версиями PHP (8.0–8.6; 8.6 — RC
до GA). Хост-интерпретатор не нужен: матрица версий гоняется через
официальные docker-образы.

```sh
php -n tests/protocol.php   # 143 проверки протокола и провайдерного шва
php -n tests/analyzer.php   # 61 проверка analyzer/strict-profile
scripts/version-matrix.sh   # обе сюиты на php 8.0…8.6 через docker
```

Совместимость с 8.0: без `readonly`-свойств и `never` (8.1+) в
раннтайме; `array_is_list` (8.1+) подключается guarded-полифиллом в
kernel — на 8.1+ идёт нативная реализация. Сгенерированный код
type-генератора целится в 8.2+ (`final readonly`) независимо от
раннтайма адаптера.

Сюита `lekalo-php-laravel` запускается из его репозитория и резолвит этот пакет через `../lekalo-php/src` (или переменную `LEKALO_PHP_SRC`).

Статус: исходники и сборка принадлежат этому репозиторию. Монорепа вызывает
закреплённый сборщик над vendored-пакетами для совместимого Laravel-артефакта;
список модулей и ресурсов определяется манифестами пакетов.

Install the expression generator with `cargo install --locked --path crates/expressions`. Select `services.expressions/php.command: [lekalo-expressions-php]` in the project configuration, lock it, then run `lekalo expressions render FILE --target php`.
